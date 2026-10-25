<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** « Titre de congé » devient le modèle par défaut du congé maternité (plus « Décision de congé maternité »). */
return new class extends Migration
{
    public function up(): void
    {
        // Données d'exploitation uniquement : les tests partent des seeders.
        if (app()->environment('testing') || ! Schema::hasTable('model_conges')) {
            return;
        }

        $typeId = DB::table('types_conges')->where('code', 'CONGE_MATERNITE')->value('id');
        $titre = DB::table('model_conges')->where('type_conge_id', $typeId)->where('titre', 'Titre de congé')->where('is_active', true)->exists();

        if ($typeId === null || ! $titre) {
            return;
        }

        DB::transaction(function () use ($typeId) {
            DB::table('model_conges')->where('type_conge_id', $typeId)->update(['is_default' => false, 'updated_at' => now()]);
            DB::table('model_conges')->where('type_conge_id', $typeId)->where('titre', 'Titre de congé')->update(['is_default' => true]);
        });
    }

    public function down(): void {}
};
