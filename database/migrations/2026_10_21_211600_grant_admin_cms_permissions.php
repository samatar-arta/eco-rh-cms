<?php

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Génère les permissions Shield du panneau cms (sans toucher aux policies
     * existantes) et les accorde au rôle ADMIN. À rejouer via
     * `php artisan app:sync-permissions` après l'ajout d'une ressource.
     */
    public function up(): void
    {
        Artisan::call('app:sync-permissions');
    }

    public function down(): void
    {
        SpatieRole::findByName(Role::Admin->value, 'web')->syncPermissions([]);
        Permission::query()->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
