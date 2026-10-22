<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Employe;
use App\Models\User;

/**
 * Les rôles RH et GESTIONNAIRE_PAIE sont limités à leurs périmètres
 * site-département ; un ADMIN ou RH_MANAGER actif a un accès total (before()). Pour les
 * autres, pas de suppression : on passe par le statut (SORTI).
 */
class EmployePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->estAdministrateur() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->hasAnyRole(...Role::rh(), ...[Role::GestionnairePaie]);
    }

    public function view(User $user, Employe $employe): bool
    {
        return $this->viewAny($user) && $employe->isInScopeOf($user);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->hasAnyRole(...Role::rh());
    }

    public function update(User $user, Employe $employe): bool
    {
        return $this->create($user) && $employe->isInScopeOf($user);
    }

    public function delete(User $user, Employe $employe): bool
    {
        return false;
    }
}
