<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Création et modification des utilisateurs par un ADMIN. Les rôles et les
 * périmètres ne sont jamais modifiables par l'utilisateur concerné : un
 * administrateur ne peut pas élargir ses propres droits, ni se désactiver.
 */
class UserAdministrationService
{
    /**
     * @param  array{name: string, email: string, password: string, is_active?: bool, roles?: list<string>, perimetres?: list<int>}  $data
     */
    public function creer(User $acteur, array $data): User
    {
        $this->exiger($acteur->can('create', User::class));

        return DB::transaction(function () use ($acteur, $data) {
            $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            $user->is_active = $data['is_active'] ?? true;
            $user->save();

            $this->synchroniser($user, $data['roles'] ?? [], $data['perimetres'] ?? []);
            app(AuditService::class)->enregistrer($acteur, 'user.creation', 'Rôles : '.implode(', ', $data['roles'] ?? []), $user);

            return $user;
        });
    }

    /**
     * @param  array{name?: string, email?: string, password?: string|null, is_active?: bool, roles?: list<string>, perimetres?: list<int>}  $data
     */
    public function modifier(User $acteur, User $cible, array $data): User
    {
        $this->exiger($acteur->can('update', $cible));
        $soiMeme = $acteur->is($cible);

        return DB::transaction(function () use ($acteur, $cible, $data, $soiMeme) {
            $cible->fill(array_filter([
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
                'password' => $data['password'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''));

            if (array_key_exists('is_active', $data) && ! $soiMeme) {
                $cible->is_active = (bool) $data['is_active'];
            }
            $cible->save();

            if (! $soiMeme && $acteur->can('manageAssignments', $cible)
                && (array_key_exists('roles', $data) || array_key_exists('perimetres', $data))) {
                $this->synchroniser(
                    $cible,
                    $data['roles'] ?? array_map(fn (Role $r) => $r->value, $cible->rolesEnum()),
                    $data['perimetres'] ?? $cible->siteDepartements()->pluck('site_departements.id')->all(),
                );
                app(AuditService::class)->enregistrer($acteur, 'user.permissions', 'Rôles et périmètres modifiés', $cible);
            }

            app(AuditService::class)->enregistrer($acteur, 'user.modification', 'Champs : '.implode(', ', array_keys($data)), $cible);

            return $cible->refresh();
        });
    }

    /** @param list<string> $roles @param list<int> $perimetres */
    private function synchroniser(User $user, array $roles, array $perimetres): void
    {
        $roles = collect($roles)->map(fn ($r) => Role::from($r))->unique();

        $user->roleAssignments()->whereNotIn('role', $roles->map->value->all())->delete();
        foreach ($roles as $role) {
            $user->assignRole($role);
        }
        $user->unsetRelation('roleAssignments');
        $user->miroirRoles();

        $user->siteDepartements()->sync($perimetres);
    }

    private function exiger(bool $autorise): void
    {
        if (! $autorise) {
            throw new AuthorizationException('Action non autorisée sur cet utilisateur.');
        }
    }
}
