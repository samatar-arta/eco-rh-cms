<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** Crée un rôle Spatie par valeur de l'enum et reprend les affectations existantes de `role_user`. */
    public function up(): void
    {
        foreach (Role::cases() as $role) {
            SpatieRole::findOrCreate($role->value, 'web');
        }

        User::query()->each(fn (User $user) => $user->miroirRoles());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        SpatieRole::query()->whereIn('name', array_map(fn (Role $r) => $r->value, Role::cases()))->delete();
    }
};
