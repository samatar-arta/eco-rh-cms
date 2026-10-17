<?php

namespace App\Filament\Cms\Resources\Departements\Pages;

use App\Filament\Cms\Resources\Departements\DepartementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDepartements extends ManageRecords
{
    protected static string $resource = DepartementResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
