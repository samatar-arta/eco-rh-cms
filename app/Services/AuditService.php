<?php

namespace App\Services;

use App\Models\LogAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Journal d'audit. Ne jamais passer de mot de passe, de jeton ou de contenu
 * de bulletin dans `$description`.
 */
class AuditService
{
    public function enregistrer(?User $acteur, string $action, ?string $description = null, ?Model $entite = null): LogAction
    {
        return LogAction::create([
            'user_id' => $acteur?->getKey(),
            'action' => Str::limit($action, 80, ''),
            'description' => $description === null ? null : Str::limit($description, 1000),
            'entite_type' => $entite ? class_basename($entite) : null,
            'entite_id' => $entite?->getKey(),
            'adresse_ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
