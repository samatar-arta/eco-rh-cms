<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowHistory;

/** Historique du workflow : consultation réservée aux ADMIN et RH_MANAGER actifs, jamais modifiable. */
class WorkflowHistoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->estAdministrateur() && $user->checkPermissionTo('ViewAny:WorkflowHistory');
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
