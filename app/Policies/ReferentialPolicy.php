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
        return $user->is_active && $user->roles() !== [];
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->hasRole(Role::Admin);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->create($user);
    }
}
