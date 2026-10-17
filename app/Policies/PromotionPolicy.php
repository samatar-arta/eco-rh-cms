<?php

namespace App\Policies;

use App\Models\Promotion;

class PromotionPolicy extends ModuleRecordPolicy
{
    protected string $modelClass = Promotion::class;
}
