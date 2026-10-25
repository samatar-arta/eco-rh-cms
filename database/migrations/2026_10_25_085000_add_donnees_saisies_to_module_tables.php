<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Valeurs saisies à la main pour alimenter les variables d'un modèle (montants,
 * dates de demande, heures...). Voir App\Services\ChampsSaisieService.
 */
return new class extends Migration
{
    private const TABLES = ['documents', 'conges', 'sanctions'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'donnees_saisies')) {
                Schema::table($table, fn (Blueprint $t) => $t->json('donnees_saisies')->nullable());
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'donnees_saisies')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('donnees_saisies'));
            }
        }
    }
};
