<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Référentiels (sociétés, sites, départements, site-départements, contrats) :
 * lecture pour tout utilisateur actif ayant un rôle, écriture ADMIN.
 */
abstract class ReferentialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->rolesEnum() !== [];
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->hasRole(Role::Admin) && $this->permis($user, 'Create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->is_active && $user->hasRole(Role::Admin) && $this->permis($user, 'Update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->is_active && $user->hasRole(Role::Admin) && $this->permis($user, 'Delete');
    }

    /** Permission Shield `Action:Modèle`, le modèle étant déduit du nom de la policy. */
    protected function permis(User $user, string $action): bool
    {
        return $user->checkPermissionTo($action.':'.str_replace('Policy', '', class_basename(static::class)));
    }
}
