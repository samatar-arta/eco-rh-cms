<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types_conges', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('color', 20)->nullable();
            $table->unsignedSmallInteger('default_days_per_year')->default(0);
            $table->boolean('requires_justificatif')->default(false);
            $table->boolean('requires_workflow')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('model_conges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_conge_id')->constrained('types_conges')->restrictOnDelete();
            $table->string('titre');
            $table->longText('contenu');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('conges', function (Blueprint $table) {
            $table->id();
            $table->string('matricule', 30);
            // Utilisateur interne ayant saisi la demande.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('type_conge_id')->constrained('types_conges')->restrictOnDelete();
            $table->foreignId('model_conge_id')->nullable()->constrained('model_conges')->restrictOnDelete();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->decimal('nbre_jours', 5, 1);
            $table->text('motif')->nullable();
            $table->string('fichier_path')->nullable();
            // Référence officielle ou, à défaut, numéro de demande interne.
            $table->string('reference', 60)->nullable()->unique();
            $table->decimal('solde_avant', 5, 1)->nullable();
            $table->date('date_decision')->nullable();
            $table->text('commentaire_decision')->nullable();
            $table->timestamps();

            $table->foreign('matricule')->references('matricule')->on('employes')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->index(['matricule', 'date_debut']);
        });

        // Droits acquis / consommés par employé, type de congé et année.
        Schema::create('conge_soldes', function (Blueprint $table) {
            $table->id();
            $table->string('matricule', 30);
            $table->foreignId('type_conge_id')->constrained('types_conges')->restrictOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->decimal('jours_acquis', 5, 1)->default(0);
            $table->decimal('jours_consommes', 5, 1)->default(0);
            $table->timestamps();

            $table->foreign('matricule')->references('matricule')->on('employes')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->unique(['matricule', 'type_conge_id', 'annee']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conge_soldes');
        Schema::dropIfExists('conges');
        Schema::dropIfExists('model_conges');
        Schema::dropIfExists('types_conges');
    }
};
