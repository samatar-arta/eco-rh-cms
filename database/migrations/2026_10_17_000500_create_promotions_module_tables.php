<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types_promotions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('model_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_promotion_id')->constrained('types_promotions')->restrictOnDelete();
            $table->string('titre');
            $table->longText('contenu');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('matricule', 30);
            $table->foreignId('type_promotion_id')->constrained('types_promotions')->restrictOnDelete();
            $table->foreignId('model_promotion_id')->nullable()->constrained('model_promotions')->restrictOnDelete();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ancienne_fonction')->nullable();
            $table->string('nouvelle_fonction')->nullable();
            $table->string('ancien_grade', 50)->nullable();
            $table->string('nouveau_grade', 50)->nullable();
            $table->decimal('ancien_salaire', 12, 2)->nullable();
            $table->decimal('nouveau_salaire', 12, 2)->nullable();
            $table->date('date_effet');
            $table->text('motif')->nullable();
            $table->string('reference', 60)->nullable()->unique();
            $table->string('fichier_path')->nullable();
            $table->timestamps();

            $table->foreign('matricule')->references('matricule')->on('employes')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->index(['matricule', 'date_effet']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('model_promotions');
        Schema::dropIfExists('types_promotions');
    }
};
