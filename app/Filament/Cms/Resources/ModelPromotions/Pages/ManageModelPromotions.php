<?php

namespace App\Filament\Cms\Resources\ModelPromotions\Pages;

use App\Filament\Cms\Resources\ModelPromotions\ModelPromotionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageModelPromotions extends ManageRecords
{
    protected static string $resource = ModelPromotionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->mutateDataUsing(function (array $data): array {
            $data['cree_par'] = auth()->id();

            return $data;
        })];
    }
}
