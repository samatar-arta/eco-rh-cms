<?php

namespace App\Http\Controllers;

use App\Models\Paylist;
use App\Services\AuditService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Téléchargement authentifié et autorisé (GESTIONNAIRE_PAIE) d'un bulletin privé. */
class PaylistFichierController extends Controller
{
    public function __invoke(Paylist $paylist): StreamedResponse
    {
        Gate::authorize('view', $paylist);

        $disque = Storage::disk(config('rh.paylist.disk'));
        abort_unless($disque->exists($paylist->fichier_path), 404);

        app(AuditService::class)->enregistrer(auth()->user(), 'paylist.telechargement', "{$paylist->matricule} {$paylist->periode()}", $paylist);

        return $disque->response(
            $paylist->fichier_path,
            sprintf('bulletin-%s-%d-%02d.pdf', $paylist->matricule, $paylist->annee, $paylist->mois),
            ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Content-Type' => 'application/pdf'],
        );
    }
}
