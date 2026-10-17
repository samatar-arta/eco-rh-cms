<?php

namespace App\Filament\Cms\Resources\TypesSanctions\Pages;

use App\Filament\Cms\Resources\TypesSanctions\TypeSanctionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTypesSanctions extends ManageRecords
{
    protected static string $resource = TypeSanctionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
