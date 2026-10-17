<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types_documents', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('requires_model')->default(true);
            $table->boolean('requires_workflow')->default(true);
            // Délai minimal (en mois) entre deux productions officielles pour un même
            // employé et un même type ; null = aucune règle de renouvellement.
            $table->unsignedSmallInteger('delai_renouvellement_mois')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('model_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_document_id')->constrained('types_documents')->restrictOnDelete();
            $table->string('titre');
            $table->longText('contenu');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('matricule', 30);
            $table->foreignId('type_document_id')->constrained('types_documents')->restrictOnDelete();
            $table->foreignId('model_document_id')->nullable()->constrained('model_documents')->restrictOnDelete();
            $table->unsignedInteger('model_version')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference', 60)->nullable()->unique();
            $table->date('date_production')->nullable();
            $table->longText('contenu_genere')->nullable();
            $table->string('fichier_path')->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->foreign('matricule')->references('matricule')->on('employes')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->index(['matricule', 'type_document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('model_documents');
        Schema::dropIfExists('types_documents');
    }
};
