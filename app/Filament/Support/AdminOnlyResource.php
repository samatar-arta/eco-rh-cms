<?php

namespace App\Filament\Support;

use App\Enums\Role;

/**
 * Ressources d'administration (référentiels, types, modèles, niveaux de sanction) :
 * visibles et accessibles au seul ADMIN actif, quelle que soit la lecture ouverte par la policy.
 */
trait AdminOnlyResource
{
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->is_active
            && $user->hasRole(Role::Admin)
            && parent::canViewAny();
    }
}
