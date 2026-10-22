<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Models\WorkflowHistory;

/** Historique du workflow : consultation réservée aux ADMIN actifs, jamais modifiable. */
class WorkflowHistoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasRole(Role::Admin) && $user->checkPermissionTo('ViewAny:WorkflowHistory');
    }

    public function view(User $user, WorkflowHistory $history): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, WorkflowHistory $history): bool
    {
        return false;
    }

    public function delete(User $user, WorkflowHistory $history): bool
    {
        return false;
    }
}
