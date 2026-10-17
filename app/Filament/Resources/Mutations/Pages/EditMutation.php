<?php

namespace App\Filament\Resources\Mutations\Pages;

use App\Filament\Resources\Mutations\MutationResource;
use App\Filament\Support\EditModuleRecord;

class EditMutation extends EditModuleRecord
{
    protected static string $resource = MutationResource::class;
}
