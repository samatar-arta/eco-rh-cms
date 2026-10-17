<?php

namespace App\Services;

use App\Enums\WorkflowStatut;
use App\Exceptions\WorkflowException;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\EmployeAffectation;
use App\Models\Mutation;
use App\Models\Promotion;
use App\Models\User;
use App\Models\Workflow;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Règles métier et effets des promotions et mutations.
 *
 * - Avant soumission/approbation : champs exigés par type, changement réel,
 *   cohérence société/département.
 * - Après approbation : la situation de l'employé est modifiée (une seule fois,
 *   à la date d'effet) et l'historique des affectations est conservé, jamais écrasé.
 */
class EffetsApprobationService
{
    private const PROMOTION_REQUIS = [
        'PROMOTION_GRADE' => ['nouveau_grade'],
        'PROMOTION_FONCTION' => ['nouvelle_fonction'],
        'PROMOTION_SALARIALE' => ['nouveau_salaire'],
        'PROMOTION_GRADE_FONCTION' => ['nouveau_grade', 'nouvelle_fonction'],
    ];

    private const MUTATION_REQUIS = [
        'MUTATION_SITE' => ['nouveau_site_id'],
        'MUTATION_DEPARTEMENT' => ['nouveau_departement_id'],
        'MUTATION_FONCTION' => ['nouvelle_fonction'],
        'MUTATION_SOCIETE' => ['nouvelle_societe_id', 'nouveau_departement_id'],
        'MUTATION_SITE_DEPARTEMENT' => ['nouveau_site_id', 'nouveau_departement_id'],
    ];

    private const LIBELLES = [
        'nouveau_grade' => 'le nouveau grade', 'nouvelle_fonction' => 'la nouvelle fonction',
        'nouveau_salaire' => 'le nouveau salaire', 'nouveau_site_id' => 'le nouveau site',
        'nouveau_departement_id' => 'le nouveau département', 'nouvelle_societe_id' => 'la nouvelle société',
    ];

    public function concerne(Model $record): bool
    {
        return $record instanceof Promotion || $record instanceof Mutation;
    }

    /** @throws ValidationException */
    public function verifierSoumission(Model $record): void
    {
        if ($record instanceof Promotion) {
            $this->verifierPromotion($record);
        } elseif ($record instanceof Mutation) {
            $this->verifierMutation($record);
        }
    }

    /**
     * Applique les effets si la date d'effet est échue et qu'ils ne l'ont pas
     * déjà été (idempotent). Doit être appelé dans une transaction.
     *
     * @return bool vrai si les effets ont été appliqués par cet appel
     *
     * @throws WorkflowException en cas de conflit avec la situation actuelle de l'employé
     */
    public function appliquerSiEchu(Model $record, Workflow $workflow, ?User $acteur = null): bool
    {
        if (! $this->concerne($record) || $record->effet_applique_le !== null || $record->date_effet->gt(today())) {
            return false;
        }

        $employe = Employe::query()->where('matricule', $record->matricule)->lockForUpdate()->firstOrFail();

        $resume = $record instanceof Promotion
            ? $this->appliquerPromotion($record, $employe, $acteur)
            : $this->appliquerMutation($record, $employe, $acteur);

        $record->forceFill(['effet_applique_le' => now()])->saveQuietly();
        app(WorkflowService::class)->journaliser($workflow, $acteur, 'effets_appliques', WorkflowStatut::Approuve, $resume);

        return true;
    }

    /**
     * Applique les effets des dossiers approuvés dont la date d'effet est
     * arrivée (tâche planifiée). Un conflit est tracé une seule fois et le
     * dossier est laissé en l'état pour traitement manuel.
     *
     * @return array{appliques:int,conflits:int}
     */
    public function appliquerEchus(): array
    {
        $resultat = ['appliques' => 0, 'conflits' => 0];

        foreach ([Promotion::class, Mutation::class] as $classe) {
            $classe::query()
                ->whereNull('effet_applique_le')
                ->whereDate('date_effet', '<=', today())
                ->whereHas('workflow', fn ($q) => $q->where('statut', WorkflowStatut::Approuve))
                ->with('workflow')
                ->orderBy('id')
                ->each(function (Model $record) use (&$resultat) {
                    try {
                        $applique = DB::transaction(fn () => $this->appliquerSiEchu($record, $record->workflow));
                        $resultat['appliques'] += (int) $applique;
                    } catch (WorkflowException $e) {
                        $resultat['conflits']++;

                        if (! $record->workflow->histories()->where('etape', 'effets_en_conflit')->exists()) {
                            app(WorkflowService::class)->journaliser($record->workflow, null, 'effets_en_conflit', WorkflowStatut::Approuve, $e->getMessage());
                        }
                    }
                });
        }

        return $resultat;
    }

    private function verifierPromotion(Promotion $promotion): void
    {
        $code = $promotion->type?->code;
        $requis = self::PROMOTION_REQUIS[$code] ?? null;

        if ($requis === null) {
            // Type ajouté par l'administration : au moins un changement.
            if (blank($promotion->nouvelle_fonction) && blank($promotion->nouveau_grade) && blank($promotion->nouveau_salaire)) {
                throw ValidationException::withMessages(['nouvelle_fonction' => 'Renseignez au moins une nouvelle fonction, un nouveau grade ou un nouveau salaire.']);
            }

            return;
        }

        $this->exiger($promotion, $requis);
    }

    private function verifierMutation(Mutation $mutation): void
    {
        $code = $mutation->type?->code;
        $requis = self::MUTATION_REQUIS[$code] ?? null;

        if ($requis !== null) {
            $this->exiger($mutation, $requis);
        }

        $societe = $mutation->nouvelle_societe_id ?? $mutation->ancienne_societe_id;

        if ($mutation->nouvelle_societe_id !== null && $mutation->nouvelle_societe_id !== $mutation->ancienne_societe_id && blank($mutation->nouveau_departement_id)) {
            throw ValidationException::withMessages(['nouveau_departement_id' => 'Un changement de société exige un département de la nouvelle société.']);
        }

        if ($mutation->nouveau_departement_id !== null && $societe !== null
            && ! Departement::query()->whereKey($mutation->nouveau_departement_id)->where('societe_id', $societe)->exists()) {
            throw ValidationException::withMessages(['nouveau_departement_id' => 'Le département choisi n\'appartient pas à la société de destination.']);
        }

        $change = ($mutation->nouveau_site_id !== null && $mutation->nouveau_site_id !== $mutation->ancien_site_id)
            || ($mutation->nouveau_departement_id !== null && $mutation->nouveau_departement_id !== $mutation->ancien_departement_id)
            || ($mutation->nouvelle_societe_id !== null && $mutation->nouvelle_societe_id !== $mutation->ancienne_societe_id)
            || (filled($mutation->nouvelle_fonction) && $mutation->nouvelle_fonction !== $mutation->ancienne_fonction);

        if (! $change) {
            throw ValidationException::withMessages(['nouveau_site_id' => 'La mutation ne modifie aucune affectation de l\'employé.']);
        }
    }

    /** @param list<string> $champs */
    private function exiger(Model $record, array $champs): void
    {
        foreach ($champs as $champ) {
            if (blank($record->{$champ})) {
                throw ValidationException::withMessages([$champ => 'Pour ce type, '.self::LIBELLES[$champ].' est obligatoire.']);
            }
        }
    }

    private function appliquerPromotion(Promotion $promotion, Employe $employe, ?User $acteur): string
    {
        $changements = [];

        if (filled($promotion->nouveau_grade)) {
            $changements['grade'] = $promotion->nouveau_grade;
        }

        if (filled($promotion->nouvelle_fonction)) {
            $changements['fonction'] = $promotion->nouvelle_fonction;
        }

        if ($changements === []) {
            return 'Promotion approuvée : aucun changement de situation (salaire non porté sur la fiche employé).';
        }

        if (isset($changements['fonction']) && $changements['fonction'] !== $employe->fonction) {
            $this->changerAffectation($employe, ['fonction' => $changements['fonction']], $promotion->date_effet, 'Promotion '.($promotion->reference ?? ''), $acteur);
        }

        $employe->update($changements);

        return 'Promotion appliquée : '.collect($changements)->map(fn ($v, $k) => "$k = $v")->implode(', ').'.';
    }

    private function appliquerMutation(Mutation $mutation, Employe $employe, ?User $acteur): string
    {
        foreach (['site_id' => 'ancien_site_id', 'departement_id' => 'ancien_departement_id', 'societe_id' => 'ancienne_societe_id'] as $colonne => $ancien) {
            if ($mutation->{$ancien} !== null && $employe->{$colonne} !== $mutation->{$ancien}) {
                throw new WorkflowException("L'affectation actuelle de l'employé {$employe->matricule} a changé depuis la création de la mutation : application impossible, créez une nouvelle mutation.");
            }
        }

        $nouveau = [
            'site_id' => $mutation->nouveau_site_id ?? $employe->site_id,
            'societe_id' => $mutation->nouvelle_societe_id ?? $employe->societe_id,
            'departement_id' => $mutation->nouveau_departement_id ?? $employe->departement_id,
            'fonction' => filled($mutation->nouvelle_fonction) ? $mutation->nouvelle_fonction : $employe->fonction,
        ];

        if (! Departement::query()->whereKey($nouveau['departement_id'])->where('societe_id', $nouveau['societe_id'])->exists()) {
            throw new WorkflowException('Le département de destination n\'appartient pas à la société de destination.');
        }

        $this->changerAffectation($employe, $nouveau, $mutation->date_effet, 'Mutation '.($mutation->reference ?? ''), $acteur);
        $employe->update($nouveau);

        return 'Mutation appliquée : nouvelle affectation enregistrée, ancienne affectation clôturée.';
    }

    /**
     * Clôture l'affectation courante (en la créant d'abord si l'historique est
     * vide) et ouvre la nouvelle à la date d'effet. Rien n'est supprimé.
     *
     * @param  array<string,mixed>  $nouveau  site_id, societe_id, departement_id, fonction (partiel)
     */
    private function changerAffectation(Employe $employe, array $nouveau, CarbonInterface $dateEffet, string $motif, ?User $acteur): void
    {
        $courante = EmployeAffectation::query()->where('employe_id', $employe->getKey())->whereNull('date_fin')->latest('date_debut')->first();

        if ($courante === null) {
            $debut = ($employe->date_embauche ?? $dateEffet->copy()->subDay())->copy()->min($dateEffet->copy()->subDay());
            $courante = EmployeAffectation::create([
                'employe_id' => $employe->getKey(), 'site_id' => $employe->site_id, 'societe_id' => $employe->societe_id,
                'departement_id' => $employe->departement_id, 'fonction' => $employe->fonction,
                'date_debut' => $debut, 'motif' => 'Situation initiale', 'cree_par' => $acteur?->getKey(),
            ]);
        }

        $courante->update(['date_fin' => $dateEffet->copy()->subDay()->max($courante->date_debut)]);

        EmployeAffectation::create([
            'employe_id' => $employe->getKey(),
            'site_id' => $nouveau['site_id'] ?? $employe->site_id,
            'societe_id' => $nouveau['societe_id'] ?? $employe->societe_id,
            'departement_id' => $nouveau['departement_id'] ?? $employe->departement_id,
            'fonction' => $nouveau['fonction'] ?? $employe->fonction,
            'date_debut' => $dateEffet,
            'motif' => trim($motif),
            'cree_par' => $acteur?->getKey(),
        ]);
    }
}
