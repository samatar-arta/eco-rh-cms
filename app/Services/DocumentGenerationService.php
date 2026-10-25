<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\WorkflowStatut;
use App\Exceptions\WorkflowException;
use App\Models\Workflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Finalisation d'un dossier approuvé : contenu figé, PDF, signature interne,
 * archivage. Idempotent : la référence est déjà attribuée (jamais d'appel à
 * ReferenceService ici), le contenu figé n'est pas re-rendu et un PDF déjà
 * archivé n'est pas régénéré. Peut être relancé sans risque après un échec.
 */
class DocumentGenerationService
{
    public function __construct(
        private TemplateRendererService $renderer,
        private PdfGenerationService $pdf,
        private WorkflowService $workflow,
        private ChampsSaisieService $saisies,
    ) {}

    /** @return string chemin du PDF archivé */
    public function finaliser(Model $record): string
    {
        $verrou = Cache::lock('finalisation:'.$record::rhModule()->morphAlias().':'.$record->getKey(), 120);

        return $verrou->block(10, fn () => $this->executer($record->refresh()));
    }

    private function executer(Model $record): string
    {
        /** @var Workflow|null $workflow */
        $workflow = $record->workflow()->first();

        if ($workflow?->statut !== WorkflowStatut::Approuve || $record->reference === null) {
            throw new WorkflowException("Le dossier n'est pas approuvé : génération impossible.");
        }

        $colonne = $record->colonneFichierFinal();

        if ($record->{$colonne} !== null && Storage::disk(config('rh.pdf.disk'))->exists($record->{$colonne})) {
            return $record->{$colonne};
        }

        // Un champ saisi à la main ne doit jamais sortir vide dans un document officiel.
        if ($record->contenu_genere === null) {
            $this->saisies->verifier($record);
        }

        $approbateur = $workflow->approbateur;

        // Contenu figé une seule fois : une reprise réutilise la copie existante.
        if ($record->contenu_genere === null) {
            $modele = $record->modele;
            $gabarit = $modele?->contenu ?? '<p>Dossier {{ employe.name }} ({{ employe.matricule }}).</p>';

            $record->forceFill([
                'contenu_genere' => $this->renderer->rendre($gabarit, $record),
                'model_version' => $modele?->version,
            ])->save();
        }

        $html = view('pdf.dossier', [
            'reference' => $record->reference,
            'contenu' => $this->apposerSignatureImage($record->contenu_genere),
            'signataire' => $approbateur?->name ?? 'Inconnu',
            'roleSignataire' => Role::RhManager->label(),
            'dateApprobation' => $workflow->date_approbation?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            'identifiant' => $record::rhModule()->value.'#'.$record->getKey(),
            'empreinte' => hash('sha256', $record->contenu_genere),
        ])->render();

        try {
            $binaire = $this->pdf->depuisHtml($html);
        } catch (Throwable $e) {
            $this->workflow->journaliser($workflow, null, 'echec_pdf', WorkflowStatut::Approuve, Str::limit($e->getMessage(), 500));
            throw $e;
        }

        $chemin = sprintf(
            '%s/%s/%s/%s.pdf',
            config('rh.pdf.directory'),
            $record::rhModule()->morphAlias(),
            now()->year,
            Str::slug(str_replace('/', '-', $record->reference), '-', null)
        );

        Storage::disk(config('rh.pdf.disk'))->put($chemin, $binaire);
        $record->forceFill([$colonne => $chemin])->save();

        $this->workflow->journaliser($workflow, null, 'generation_pdf', WorkflowStatut::Approuve, $chemin);
        $this->workflow->journaliser($workflow, $approbateur, 'signature', WorkflowStatut::Approuve, 'Signature interne apposée (SHA-256 '.hash('sha256', $record->contenu_genere).')', Role::RhManager);
        $this->workflow->journaliser($workflow, null, 'archivage', WorkflowStatut::Approuve, $chemin);

        return $chemin;
    }

    /**
     * Remplace le marqueur [[SIGNATURE_DARH]] par l'image de signature, intégrée en
     * base64 (aucun chargement de fichier par dompdf). Appliqué au rendu du PDF
     * seulement : le contenu figé et son empreinte restent inchangés. Sans fichier,
     * le marqueur est retiré.
     */
    private function apposerSignatureImage(string $contenu): string
    {
        $chemin = storage_path('app/signatures/darh.png');
        $image = is_file($chemin)
            ? '<img src="data:image/png;base64,'.base64_encode(file_get_contents($chemin)).'" style="height:120px; width:auto; margin-top:5px">'
            : '';

        return str_replace('[[SIGNATURE_DARH]]', $image, $contenu);
    }
}
