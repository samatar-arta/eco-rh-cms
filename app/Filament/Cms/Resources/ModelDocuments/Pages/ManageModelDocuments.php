<?php

namespace App\Filament\Cms\Resources\ModelDocuments\Pages;

use App\Filament\Cms\Resources\ModelDocuments\ModelDocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageModelDocuments extends ManageRecords
{
    protected static string $resource = ModelDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->mutateDataUsing(function (array $data): array {
            $data['cree_par'] = auth()->id();

            return $data;
        })];
    }
}
