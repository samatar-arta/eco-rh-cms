<?php

namespace App\Filament\Cms\Resources\TypesConges\Pages;

use App\Filament\Cms\Resources\TypesConges\TypeCongeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTypesConges extends ManageRecords
{
    protected static string $resource = TypeCongeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
