<?php

namespace App\Filament\Support;

/**
 * Ressources d'administration (référentiels, types, modèles, niveaux de sanction) :
 * visibles et accessibles aux ADMIN et RH_MANAGER actifs, quelle que soit la lecture ouverte par la policy.
 */
trait AdminOnlyResource
{
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->is_active
            && $user->estAdministrateur()
            && parent::canViewAny();
    }
}
