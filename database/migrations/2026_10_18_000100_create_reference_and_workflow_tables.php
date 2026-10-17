<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un compteur par module et par année, commun à tous les types du module.
        Schema::create('reference_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('module', 30);
            $table->unsignedSmallInteger('annee');
            $table->unsignedInteger('dernier_numero')->default(0);
            $table->string('prefixe', 60);
            // Jetons : {numero} (zéros à gauche sur `largeur`), {prefixe}, {annee}.
            $table->string('format', 100)->default('{numero}/{prefixe}/{annee}');
            $table->unsignedTinyInteger('largeur')->default(4);
            $table->timestamps();

            $table->unique(['module', 'annee']);
        });

        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('workflowable_type', 30);
            $table->unsignedBigInteger('workflowable_id');
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approuve_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('statut', 30)->default('BROUILLON');
            $table->timestamp('date_soumission')->nullable();
            $table->timestamp('date_validation')->nullable();
            $table->timestamp('date_approbation')->nullable();
            $table->timestamp('date_limite_validation')->nullable();
            $table->timestamp('date_limite_approbation')->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamps();

            // Un seul workflow par enregistrement métier.
            $table->unique(['workflowable_type', 'workflowable_id']);
            $table->index('statut');
        });

        Schema::create('workflow_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role', 30)->nullable();
            $table->string('etape', 50);
            $table->string('statut', 30);
            $table->text('commentaire')->nullable();
            // Échéance ouverte par cette entrée (soumission, attente d'approbation).
            $table->timestamp('date_limite')->nullable();
            $table->dateTime('date_action');
            $table->timestamps();

            $table->index(['workflow_id', 'date_action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_histories');
        Schema::dropIfExists('workflows');
        Schema::dropIfExists('reference_sequences');
    }
};
