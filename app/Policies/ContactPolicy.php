<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasAnyRole(Role::Admin, Role::GestionnairePaie, ...Role::rh());
    }

    public function view(User $user, Contact $contact): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->hasAnyRole(Role::Admin, Role::RhManager, Role::GestionnairePaie);
    }

    public function update(User $user, Contact $contact): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $this->create($user);
    }
}
