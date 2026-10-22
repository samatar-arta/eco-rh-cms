<?php

namespace App\Filament\Resources\Societes\Pages;

use App\Filament\Resources\Societes\SocieteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSocietes extends ManageRecords
{
    protected static string $resource = SocieteResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
