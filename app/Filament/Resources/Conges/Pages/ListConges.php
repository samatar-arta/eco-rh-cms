<?php

namespace App\Filament\Resources\Conges\Pages;

use App\Filament\Resources\Conges\CongeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConges extends ListRecords
{
    protected static string $resource = CongeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
