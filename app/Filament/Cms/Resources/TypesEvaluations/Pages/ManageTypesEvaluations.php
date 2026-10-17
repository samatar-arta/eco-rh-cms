<?php

namespace App\Filament\Cms\Resources\TypesEvaluations\Pages;

use App\Filament\Cms\Resources\TypesEvaluations\TypeEvaluationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTypesEvaluations extends ManageRecords
{
    protected static string $resource = TypeEvaluationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
