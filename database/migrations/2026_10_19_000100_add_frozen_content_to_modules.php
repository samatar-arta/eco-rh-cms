<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Modules dont le contenu final doit être figé au même titre que `documents`. */
    private array $tables = ['conges', 'sanctions', 'evaluations', 'promotions', 'mutations'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->longText('contenu_genere')->nullable();
                $t->unsignedInteger('model_version')->nullable();
            });
        }

        // `conges.fichier_path` contient le justificatif ; le PDF final a sa propre colonne.
        Schema::table('conges', function (Blueprint $t) {
            $t->string('decision_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('conges', fn (Blueprint $t) => $t->dropColumn('decision_path'));

        foreach ($this->tables as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['contenu_genere', 'model_version']));
        }
    }
};
