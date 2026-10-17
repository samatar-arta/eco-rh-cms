<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('password');
        });

        // Un utilisateur peut cumuler plusieurs rôles (valeurs de App\Enums\Role).
        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30);
            $table->timestamps();

            $table->unique(['user_id', 'role']);
        });

        // Périmètres site-département accessibles à un utilisateur.
        Schema::create('site_departement_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_departement_id')->constrained('site_departements')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'site_departement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_departement_user');
        Schema::dropIfExists('role_user');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
