<?php

namespace App\Listeners;

use App\Events\DossierApprouve;
use App\Jobs\FinaliserDossierJob;

class FinaliserDossierApprouve
{
    public function handle(DossierApprouve $event): void
    {
        FinaliserDossierJob::dispatch($event->workflow->getKey());
    }
}
