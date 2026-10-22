<?php

namespace App\Filament\Resources\SiteDepartements\Pages;

use App\Filament\Resources\SiteDepartements\SiteDepartementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSiteDepartements extends ManageRecords
{
    protected static string $resource = SiteDepartementResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
