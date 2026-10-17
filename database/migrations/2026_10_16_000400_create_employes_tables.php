<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employes', function (Blueprint $table) {
            $table->id();
            $table->string('matricule', 30)->unique();
            $table->string('name');
            $table->string('fonction')->nullable();
            $table->date('date_naissance')->nullable();
            $table->date('date_embauche')->nullable();
            $table->foreignId('contrat_id')->nullable()->constrained('contrats')->restrictOnDelete();
            $table->string('statut_employe', 20)->default('ACTIF');
            $table->date('date_sortie')->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('num_cnss', 50)->nullable();
            $table->string('num_cni', 50)->nullable();
            $table->string('niveau_etude')->nullable();
            $table->text('adresse')->nullable();
            $table->string('situation_familiale', 30)->nullable();
            $table->string('personne_contacter')->nullable();
            $table->string('lien_parental', 50)->nullable();
            $table->string('telephone_parental', 30)->nullable();
            $table->text('formation_certificat')->nullable();
            $table->string('permis_conduite', 50)->nullable();
            $table->string('categorie', 50)->nullable();
            $table->boolean('handicap')->default(false);
            $table->foreignId('societe_id')->constrained('societes')->restrictOnDelete();
            $table->unsignedBigInteger('departement_id');
            $table->foreignId('site_id')->nullable()->constrained('sites_affectation')->restrictOnDelete();
            $table->string('dossier_path')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['departement_id', 'societe_id'])
                ->references(['id', 'societe_id'])->on('departements')
                ->restrictOnDelete();

            $table->index(['site_id', 'departement_id']);
            $table->index('statut_employe');
        });

        // Historique des affectations : jamais écrasé, une ligne par période.
        Schema::create('employe_affectations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites_affectation')->restrictOnDelete();
            $table->foreignId('societe_id')->constrained('societes')->restrictOnDelete();
            $table->unsignedBigInteger('departement_id');
            $table->string('fonction')->nullable();
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->string('motif')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['departement_id', 'societe_id'])
                ->references(['id', 'societe_id'])->on('departements')
                ->restrictOnDelete();

            $table->index(['employe_id', 'date_debut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employe_affectations');
        Schema::dropIfExists('employes');
    }
};
