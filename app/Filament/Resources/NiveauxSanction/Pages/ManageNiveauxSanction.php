<?php

namespace App\Filament\Resources\NiveauxSanction\Pages;

use App\Filament\Resources\NiveauxSanction\NiveauSanctionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageNiveauxSanction extends ManageRecords
{
    protected static string $resource = NiveauSanctionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
