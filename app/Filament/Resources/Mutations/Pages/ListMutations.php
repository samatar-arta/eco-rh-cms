<?php

namespace App\Filament\Resources\Mutations\Pages;

use App\Filament\Resources\Mutations\MutationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMutations extends ListRecords
{
    protected static string $resource = MutationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
