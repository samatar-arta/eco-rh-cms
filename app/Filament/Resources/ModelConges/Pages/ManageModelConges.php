<?php

namespace App\Filament\Resources\ModelConges\Pages;

use App\Filament\Resources\ModelConges\ModelCongeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageModelConges extends ManageRecords
{
    protected static string $resource = ModelCongeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->mutateDataUsing(function (array $data): array {
            $data['cree_par'] = auth()->id();

            return $data;
        })];
    }
}
