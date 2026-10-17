<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types_sanctions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('requires_conseil_discipline')->default(false);
            $table->boolean('requires_workflow')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Niveau appliqué (avertissement, blâme, mise à pied...) : distinct du type.
        Schema::create('niveaux_sanction', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('model_sanctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_sanction_id')->constrained('types_sanctions')->restrictOnDelete();
            $table->string('titre');
            $table->longText('contenu');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sanctions', function (Blueprint $table) {
            $table->id();
            $table->string('matricule', 30);
            $table->foreignId('type_sanction_id')->constrained('types_sanctions')->restrictOnDelete();
            $table->foreignId('model_sanction_id')->nullable()->constrained('model_sanctions')->restrictOnDelete();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_sanction');
            $table->text('description')->nullable();
            $table->text('motif')->nullable();
            $table->foreignId('sanction_precedente_id')->nullable()->constrained('sanctions')->nullOnDelete();
            $table->foreignId('niveau_sanction_id')->nullable()->constrained('niveaux_sanction')->restrictOnDelete();
            $table->string('reference', 60)->nullable()->unique();
            $table->string('fichier_path')->nullable();
            $table->timestamps();

            $table->foreign('matricule')->references('matricule')->on('employes')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->index(['matricule', 'date_sanction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctions');
        Schema::dropIfExists('model_sanctions');
        Schema::dropIfExists('niveaux_sanction');
        Schema::dropIfExists('types_sanctions');
    }
};
