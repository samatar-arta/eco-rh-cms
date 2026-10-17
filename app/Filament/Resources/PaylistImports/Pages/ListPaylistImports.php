<?php

namespace App\Filament\Resources\PaylistImports\Pages;

use App\Filament\Resources\PaylistImports\PaylistImportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPaylistImports extends ListRecords
{
    protected static string $resource = PaylistImportResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nouvel import')];
    }
}
