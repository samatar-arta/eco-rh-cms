<?php

namespace App\Jobs;

use App\Models\Workflow;
use App\Services\DocumentGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** PDF + signature + archivage d'un dossier approuvé, hors transaction, avec reprises. */
class FinaliserDossierJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function __construct(public int $workflowId) {}

    public function handle(DocumentGenerationService $service): void
    {
        $workflow = Workflow::findOrFail($this->workflowId);

        $service->finaliser($workflow->workflowable);
    }
}
