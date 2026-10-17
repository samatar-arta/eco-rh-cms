<?php

namespace App\Policies;

use App\Models\Conge;

class CongePolicy extends ModuleRecordPolicy
{
    protected string $modelClass = Conge::class;
}
