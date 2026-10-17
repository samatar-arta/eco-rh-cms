<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types_evaluations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('model_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_evaluation_id')->constrained('types_evaluations')->restrictOnDelete();
            $table->string('titre');
            $table->longText('contenu');
            // [{"libelle": "...", "coefficient": 2, "note_max": 20}, ...]
            $table->json('criteres')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->string('matricule', 30);
            $table->foreignId('type_evaluation_id')->constrained('types_evaluations')->restrictOnDelete();
            $table->foreignId('model_evaluation_id')->nullable()->constrained('model_evaluations')->restrictOnDelete();
            $table->foreignId('evaluateur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_evaluation');
            $table->date('periode_debut')->nullable();
            $table->date('periode_fin')->nullable();
            // Notes saisies par critère : [{"libelle": "...", "note": 14}, ...]
            $table->json('resultats_criteres')->nullable();
            $table->decimal('note', 5, 2)->nullable();
            $table->text('appreciation')->nullable();
            $table->text('objectifs')->nullable();
            $table->string('reference', 60)->nullable()->unique();
            $table->string('fichier_path')->nullable();
            $table->timestamps();

            $table->foreign('matricule')->references('matricule')->on('employes')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->index(['matricule', 'date_evaluation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
        Schema::dropIfExists('model_evaluations');
        Schema::dropIfExists('types_evaluations');
    }
};
