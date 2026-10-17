<?php

namespace App\Policies;

use App\Models\Evaluation;

class EvaluationPolicy extends ModuleRecordPolicy
{
    protected string $modelClass = Evaluation::class;
}
