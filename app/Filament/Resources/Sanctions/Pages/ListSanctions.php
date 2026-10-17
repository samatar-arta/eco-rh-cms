<?php

namespace App\Filament\Resources\Sanctions\Pages;

use App\Filament\Resources\Sanctions\SanctionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSanctions extends ListRecords
{
    protected static string $resource = SanctionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
