<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Désactive les anciens modèles remplacés : « Décision de sanction » (v1, remplacé
 * par « Décision sanctionnant un agent », seulement s'il est actif pour le même
 * type) et tout modèle de document qui contient encore [MONTANT_SALAIRE]. Rien
 * n'est supprimé : les dossiers qui les référencent restent valides.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Données d'exploitation uniquement : les tests partent des seeders.
        if (app()->environment('testing')) {
            return;
        }

        if (Schema::hasTable('model_sanctions')) {
            DB::table('model_sanctions')
                ->where('titre', 'Décision de sanction')
                ->where('version', '<', 2)
                ->whereIn('type_sanction_id', DB::table('model_sanctions')
                    ->where('titre', 'Décision sanctionnant un agent')->where('is_active', true)->select('type_sanction_id'))
                ->update(['is_active' => false, 'is_default' => false, 'updated_at' => now()]);
        }

        if (Schema::hasTable('model_documents')) {
            DB::table('model_documents')
                ->where('contenu', 'like', '%[MONTANT_SALAIRE]%')
                ->update(['is_active' => false, 'is_default' => false, 'updated_at' => now()]);
        }
    }

    public function down(): void {}
};
