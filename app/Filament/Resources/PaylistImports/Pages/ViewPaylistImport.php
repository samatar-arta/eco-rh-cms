<?php

namespace App\Filament\Resources\PaylistImports\Pages;

use App\Exceptions\PaylistException;
use App\Filament\Resources\PaylistImports\PaylistImportResource;
use App\Services\PaylistImportService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPaylistImport extends ViewRecord
{
    protected static string $resource = PaylistImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirmer')
                ->label(fn () => $this->record->statut_import->value === 'ANALYSE' ? 'Confirmer l\'import' : 'Reprendre l\'import')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->statut_import->confirmable())
                ->action(function () {
                    try {
                        $import = app(PaylistImportService::class)->confirmer(auth()->user(), $this->record);
                    } catch (PaylistException $e) {
                        Notification::make()->title('Import refusé')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    $this->record = $import->refresh();
                    Notification::make()
                        ->title("Import terminé : {$import->lignes_importees} importé(s), {$import->lignes_rejetees} rejeté(s)")
                        ->{$import->lignes_rejetees > 0 ? 'warning' : 'success'}()->send();
                }),
        ];
    }
}
