<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employes', function (Blueprint $table) {
            $table->string('grade', 50)->nullable()->after('fonction');
        });

        // Date à laquelle les effets d'une promotion/mutation approuvée ont été appliqués à l'employé.
        foreach (['promotions', 'mutations'] as $nom) {
            Schema::table($nom, function (Blueprint $table) {
                $table->dateTime('effet_applique_le')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['promotions', 'mutations'] as $nom) {
            Schema::table($nom, fn (Blueprint $table) => $table->dropColumn('effet_applique_le'));
        }
        Schema::table('employes', fn (Blueprint $table) => $table->dropColumn('grade'));
    }
};
