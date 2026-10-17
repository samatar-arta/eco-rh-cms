<?php

namespace App\Filament\Cms\Resources\TypesPromotions\Pages;

use App\Filament\Cms\Resources\TypesPromotions\TypePromotionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTypesPromotions extends ManageRecords
{
    protected static string $resource = TypePromotionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
