<?php

namespace App\Console\Commands;

use App\Enums\RhModule;
use App\Enums\WorkflowStatut;
use App\Services\DocumentGenerationService;
use Illuminate\Console\Command;
use Throwable;

class FinaliserDossiers extends Command
{
    protected $signature = 'dossiers:finaliser {module? : DOCUMENT, CONGE, SANCTION...} {id? : identifiant du dossier}';

    protected $description = 'Génère (ou reprend) le PDF des dossiers approuvés sans fichier final. Ne consomme jamais de nouvelle référence.';

    public function handle(DocumentGenerationService $service): int
    {
        $modules = $this->argument('module')
            ? [RhModule::from(strtoupper($this->argument('module')))]
            : RhModule::cases();
        $echecs = 0;
        $traites = 0;

        foreach ($modules as $module) {
            $classe = $module->recordClass();
            $colonne = (new $classe)->colonneFichierFinal();

            $requete = $classe::query()
                ->whereNotNull('reference')
                ->whereNull($colonne)
                ->whereHas('workflow', fn ($w) => $w->where('statut', WorkflowStatut::Approuve->value));

            if ($this->argument('id')) {
                $requete->whereKey($this->argument('id'));
            }

            foreach ($requete->get() as $record) {
                try {
                    $this->line("{$module->value} #{$record->getKey()} {$record->reference} → ".$service->finaliser($record));
                    $traites++;
                } catch (Throwable $e) {
                    $echecs++;
                    $this->error("{$module->value} #{$record->getKey()} : {$e->getMessage()}");
                }
            }
        }

        $this->info("{$traites} dossier(s) finalisé(s), {$echecs} échec(s).");

        return $echecs === 0 ? self::SUCCESS : self::FAILURE;
    }
}
