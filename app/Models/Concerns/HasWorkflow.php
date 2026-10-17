<?php

namespace App\Models\Concerns;

use App\Enums\RhModule;
use App\Models\Workflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/** @mixin Model */
trait HasWorkflow
{
    abstract public static function rhModule(): RhModule;

    public function workflow(): MorphOne
    {
        return $this->morphOne(Workflow::class, 'workflowable');
    }
}
