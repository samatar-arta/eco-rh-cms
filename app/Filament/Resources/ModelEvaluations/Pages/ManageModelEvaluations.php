<?php

namespace App\Filament\Resources\ModelEvaluations\Pages;

use App\Filament\Resources\ModelEvaluations\ModelEvaluationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageModelEvaluations extends ManageRecords
{
    protected static string $resource = ModelEvaluationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->mutateDataUsing(function (array $data): array {
            $data['cree_par'] = auth()->id();

            return $data;
        })];
    }
}
