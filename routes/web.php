<?php

use App\Http\Controllers\DossierFichierController;
use App\Http\Controllers\PaylistFichierController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/rh');

Route::get('/paylists/{paylist}/fichier', PaylistFichierController::class)
    ->whereNumber('paylist')
    ->middleware('auth')
    ->name('paylists.fichier');

Route::get('/fichiers/{module}/{id}/{fichier?}', DossierFichierController::class)
    ->whereNumber('id')
    ->whereIn('fichier', ['final', 'justificatif'])
    ->middleware('auth')
    ->name('dossiers.fichier');
