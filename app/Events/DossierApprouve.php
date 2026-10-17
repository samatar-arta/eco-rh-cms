<?php

namespace App\Events;

use App\Models\Workflow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Émis après validation de la transaction d'approbation. Les opérations
 * secondaires (PDF, signature, archivage, e-mails) s'y raccrochent, hors
 * transaction SQL, avec reprise possible sans nouvelle référence.
 */
class DossierApprouve
{
    use Dispatchable;

    public function __construct(public Workflow $workflow) {}
}
