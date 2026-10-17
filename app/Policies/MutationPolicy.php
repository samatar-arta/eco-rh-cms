<?php

namespace App\Policies;

use App\Models\Mutation;

class MutationPolicy extends ModuleRecordPolicy
{
    protected string $modelClass = Mutation::class;
}
