<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Models\Workflow;

/**
 * Suivi des workflows : lecture seule. Les rôles RH et l'ADMIN consultent, chacun dans
 * sa portée (voir `Workflow::scopeVisibleTo`) ; le gestionnaire de paie n'a pas accès.
 * Les workflows ne s'écrivent que par `WorkflowService`, jamais à la main.
 */
class WorkflowPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active
            && $user->hasAnyRole(Role::Admin, Role::RhManager, Role::RhSenior, Role::RhOfficer, Role::RhJunior);
    }

    public function view(User $user, Workflow $workflow): bool
    {
        return $this->viewAny($user)
            && Workflow::query()->whereKey($workflow->getKey())->visibleTo($user)->exists();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Workflow $workflow): bool
    {
        return false;
    }

    public function delete(User $user, Workflow $workflow): bool
    {
        return false;
    }
}
