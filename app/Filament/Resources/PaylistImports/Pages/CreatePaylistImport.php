<?php

namespace App\Filament\Resources\PaylistImports\Pages;

use App\Exceptions\PaylistException;
use App\Filament\Resources\PaylistImports\PaylistImportResource;
use App\Services\PaylistImportService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Téléverse puis analyse le fichier : rien n'est importé avant confirmation. */
class CreatePaylistImport extends CreateRecord
{
    protected static string $resource = PaylistImportResource::class;

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        $chemin = $data['fichier'];

        try {
            return app(PaylistImportService::class)->analyser(
                auth()->user(), $chemin, $data['nom_fichier'] ?? basename($chemin), (int) $data['mois'], (int) $data['annee'],
            );
        } catch (PaylistException $e) {
            Storage::disk(config('rh.paylist.disk'))->delete($chemin);
            Notification::make()->title('Analyse impossible')->body($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Fichier analysé : vérifiez l\'aperçu puis confirmez l\'import.';
    }
}
