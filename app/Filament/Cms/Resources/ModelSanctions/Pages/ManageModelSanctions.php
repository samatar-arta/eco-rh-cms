<?php

namespace App\Filament\Cms\Resources\ModelSanctions\Pages;

use App\Filament\Cms\Resources\ModelSanctions\ModelSanctionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageModelSanctions extends ManageRecords
{
    protected static string $resource = ModelSanctionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->mutateDataUsing(function (array $data): array {
            $data['cree_par'] = auth()->id();

            return $data;
        })];
    }
}
