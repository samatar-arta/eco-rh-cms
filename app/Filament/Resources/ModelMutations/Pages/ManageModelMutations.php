<?php

namespace App\Filament\Resources\ModelMutations\Pages;

use App\Filament\Resources\ModelMutations\ModelMutationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageModelMutations extends ManageRecords
{
    protected static string $resource = ModelMutationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->mutateDataUsing(function (array $data): array {
            $data['cree_par'] = auth()->id();

            return $data;
        })];
    }
}
