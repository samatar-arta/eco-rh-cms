<?php

namespace App\Console\Commands;

use App\Models\Workflow;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class DetectWorkflowOverdue extends Command
{
    protected $signature = 'workflow:retards';

    protected $description = "Liste les dossiers dont l'échéance de validation ou d'approbation est dépassée";

    public function handle(NotificationService $notifications): int
    {
        $retards = Workflow::enRetard()->orderBy('id')->get();

        $this->table(
            ['Workflow', 'Type', 'Dossier', 'Statut', 'Échéance'],
            $retards->map(fn (Workflow $w) => [
                $w->id, $w->workflowable_type, $w->workflowable_id, $w->statut->value,
                ($w->date_limite_validation ?? $w->date_limite_approbation)?->toDateTimeString(),
            ])
        );

        $relances = $retards->filter(fn (Workflow $w) => $notifications->retard($w))->count();

        $this->info("{$retards->count()} dossier(s) en retard, {$relances} relance(s) envoyée(s).");

        return self::SUCCESS;
    }
}
