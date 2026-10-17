<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasRole(Role::Admin);
    }

    public function view(User $user, User $target): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, User $target): bool
    {
        return $this->viewAny($user);
    }

    /** Un administrateur ne peut pas modifier ses propres rôles ni périmètres. */
    public function manageAssignments(User $user, User $target): bool
    {
        return $this->viewAny($user) && $user->isNot($target);
    }

    public function delete(User $user, User $target): bool
    {
        return $this->viewAny($user) && $user->isNot($target);
    }
}
