<?php

namespace App\Filament\Resources\Mutations\Pages;

use App\Filament\Resources\Mutations\MutationResource;
use App\Filament\Support\CreateModuleRecord;

class CreateMutation extends CreateModuleRecord
{
    protected static string $resource = MutationResource::class;
}
