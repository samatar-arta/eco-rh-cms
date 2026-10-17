<?php

namespace App\Filament\Cms\Resources\Contrats\Pages;

use App\Filament\Cms\Resources\Contrats\ContratResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageContrats extends ManageRecords
{
    protected static string $resource = ContratResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
