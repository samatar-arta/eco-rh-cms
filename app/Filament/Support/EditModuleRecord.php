<?php

namespace App\Filament\Support;

use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

abstract class EditModuleRecord extends EditRecord
{
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return static::getResource()::donneesAEdition($data);
    }

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), ...WorkflowActions::all(), DeleteAction::make()];
    }
}
