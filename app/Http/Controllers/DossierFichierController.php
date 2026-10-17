<?php

namespace App\Http\Controllers;

use App\Enums\RhModule;
use App\Services\AuditService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Téléchargement authentifié et autorisé des fichiers privés d'un dossier :
 * PDF final (`final`) ou justificatif joint à un congé (`justificatif`).
 */
class DossierFichierController extends Controller
{
    public function __invoke(string $module, int $id, string $fichier = 'final'): StreamedResponse
    {
        $module = RhModule::tryFrom(strtoupper($module)) ?? abort(404);
        $record = $module->recordClass()::findOrFail($id);

        Gate::authorize('view', $record);

        $colonne = match (true) {
            $fichier === 'final' => $record->colonneFichierFinal(),
            $fichier === 'justificatif' && $module === RhModule::Conge => 'fichier_path',
            default => abort(404),
        };

        $chemin = $record->{$colonne};
        $disque = $colonne === 'fichier_path' && $fichier === 'justificatif' ? 'local' : config('rh.pdf.disk');

        abort_if($chemin === null || ! Storage::disk($disque)->exists($chemin), 404);

        app(AuditService::class)->enregistrer(auth()->user(), 'dossier.telechargement', "{$module->value} #{$record->getKey()} ({$fichier})", $record);

        return Storage::disk($disque)->response($chemin, basename($chemin), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
