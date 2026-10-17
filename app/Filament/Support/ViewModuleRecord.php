<?php

namespace App\Filament\Support;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

abstract class ViewModuleRecord extends ViewRecord
{
    protected function getHeaderActions(): array
    {
        return [EditAction::make(), ...WorkflowActions::all()];
    }
}
