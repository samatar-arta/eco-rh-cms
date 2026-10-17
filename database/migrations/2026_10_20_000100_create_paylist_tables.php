<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paylist_imports', function (Blueprint $table) {
            $table->id();
            $table->string('nom_fichier');
            $table->string('chemin_fichier');
            $table->unsignedTinyInteger('mois');
            $table->unsignedSmallInteger('annee');
            $table->foreignId('importe_par')->constrained('users')->restrictOnDelete();
            $table->dateTime('date_import')->nullable();
            $table->string('statut_import', 30)->default('ANALYSE');
            $table->unsignedInteger('total_lignes')->default(0);
            $table->unsignedInteger('lignes_importees')->default(0);
            $table->unsignedInteger('lignes_rejetees')->default(0);
            $table->json('apercu')->nullable();
            $table->json('rapport_erreurs')->nullable();
            $table->timestamps();
        });

        Schema::create('paylists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paylist_import_id')->constrained('paylist_imports')->restrictOnDelete();
            $table->string('matricule', 30);
            $table->unsignedTinyInteger('mois');
            $table->unsignedSmallInteger('annee');
            $table->string('fichier_path');
            $table->char('empreinte_fichier', 64);
            $table->string('statut', 20)->default('IMPORTE');
            $table->foreignId('importe_par')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign('matricule')->references('matricule')->on('employes')
                ->cascadeOnUpdate()->restrictOnDelete();
            // Un seul bulletin par employé et par période.
            $table->unique(['matricule', 'annee', 'mois']);
            $table->index(['annee', 'mois']);
        });

        Schema::create('paylist_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paylist_id')->constrained('paylists')->restrictOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->restrictOnDelete();
            $table->foreignId('distribue_par')->constrained('users')->restrictOnDelete();
            $table->dateTime('date_distribution')->nullable();
            $table->string('canal', 20);
            $table->string('statut', 20)->default('EN_ATTENTE');
            $table->string('preuve_distribution')->nullable();
            $table->text('commentaire')->nullable();
            $table->unsignedSmallInteger('tentatives')->default(0);
            $table->timestamps();

            // Rejouer la même opération ne crée jamais de doublon.
            $table->unique(['paylist_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paylist_distributions');
        Schema::dropIfExists('paylists');
        Schema::dropIfExists('paylist_imports');
    }
};
