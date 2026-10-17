<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Accès renforcé : les bulletins de paie ne sont accessibles qu'au
 * GESTIONNAIRE_PAIE actif (ni ADMIN, ni les rôles RH). Aucune suppression.
 */
abstract class PaylistModulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasRole(Role::GestionnairePaie);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $model): bool
    {
        return false;
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
