<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('prenom')->nullable();
            $table->string('fonction')->nullable();
            $table->string('email')->nullable();
            $table->string('telephone', 30)->nullable();
            $table->foreignId('societe_id')->constrained('societes')->restrictOnDelete();
            $table->unsignedBigInteger('departement_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign(['departement_id', 'societe_id'])
                ->references(['id', 'societe_id'])->on('departements')
                ->restrictOnDelete();
        });

        Schema::create('contrats', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrats');
        Schema::dropIfExists('contacts');
    }
};
