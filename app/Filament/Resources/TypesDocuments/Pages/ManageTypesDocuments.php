<?php

namespace App\Filament\Resources\TypesDocuments\Pages;

use App\Filament\Resources\TypesDocuments\TypeDocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTypesDocuments extends ManageRecords
{
    protected static string $resource = TypeDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
