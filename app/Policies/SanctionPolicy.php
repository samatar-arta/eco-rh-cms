<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Sanction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Dossiers disciplinaires : accès renforcé. Manager, Senior et Officier
 * consultent dans leur périmètre ; un Junior ne voit que les sanctions qu'il
 * a lui-même créées.
 */
class SanctionPolicy extends ModuleRecordPolicy
{
    protected string $modelClass = Sanction::class;

    public function view(User $user, Model $record): bool
    {
        if (! parent::view($user, $record)) {
            return false;
        }

        return $user->estAdministrateur()
            || $user->hasAnyRole(Role::RhManager, Role::RhSenior, Role::RhOfficer)
            || $record->cree_par === $user->getKey();
    }
}
