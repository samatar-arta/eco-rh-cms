<?php

namespace App\Filament\Cms\Resources\TypesMutations\Pages;

use App\Filament\Cms\Resources\TypesMutations\TypeMutationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTypesMutations extends ManageRecords
{
    protected static string $resource = TypeMutationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
