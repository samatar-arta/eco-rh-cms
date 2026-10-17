<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // Journal d'audit en ajout seul : ni mots de passe, ni jetons, ni contenu de bulletin.
        Schema::create('logs_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->text('description')->nullable();
            $table->string('entite_type', 60)->nullable();
            $table->unsignedBigInteger('entite_id')->nullable();
            $table->string('adresse_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['entite_type', 'entite_id']);
            $table->index('action');
            $table->index('created_at');
        });

        // Dernière échéance pour laquelle une relance de retard a été envoyée (une relance par étape).
        Schema::table('workflows', function (Blueprint $table) {
            $table->dateTime('retard_notifie_le')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('workflows', fn (Blueprint $table) => $table->dropColumn('retard_notifie_le'));
        Schema::dropIfExists('logs_actions');
        Schema::dropIfExists('notifications');
    }
};
