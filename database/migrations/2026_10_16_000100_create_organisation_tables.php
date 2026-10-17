<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('societes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 20)->nullable()->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sites_affectation', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('departements', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('abrege', 30)->nullable();
            $table->foreignId('societe_id')->constrained('societes')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['societe_id', 'name']);
            // Cible des clés étrangères composites (cohérence société/département).
            $table->unique(['id', 'societe_id']);
        });

        Schema::create('site_departements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites_affectation')->cascadeOnDelete();
            $table->foreignId('societe_id')->constrained('societes')->restrictOnDelete();
            $table->unsignedBigInteger('departement_id');
            $table->timestamps();

            // Le département doit appartenir à la société indiquée.
            $table->foreign(['departement_id', 'societe_id'])
                ->references(['id', 'societe_id'])->on('departements')
                ->restrictOnDelete();

            $table->unique(['site_id', 'departement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_departements');
        Schema::dropIfExists('departements');
        Schema::dropIfExists('sites_affectation');
        Schema::dropIfExists('societes');
    }
};
