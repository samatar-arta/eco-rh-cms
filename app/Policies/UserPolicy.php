<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->estAdministrateur() && $user->checkPermissionTo('ViewAny:User');
    }

    public function view(User $user, User $target): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->checkPermissionTo('Create:User');
    }

    public function update(User $user, User $target): bool
    {
        return $this->viewAny($user) && $user->checkPermissionTo('Update:User');
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
