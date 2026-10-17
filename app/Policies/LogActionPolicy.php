<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\LogAction;
use App\Models\User;

/** Journal d'audit : consultation réservée aux ADMIN actifs, jamais modifiable. */
class LogActionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasRole(Role::Admin);
    }

    public function view(User $user, LogAction $log): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, LogAction $log): bool
    {
        return false;
    }

    public function delete(User $user, LogAction $log): bool
    {
        return false;
    }
}
