<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    use HasRoles {
        hasRole as protected spatieHasRole;
        assignRole as protected spatieAssignRole;
    }

    /**
     * Ni `is_active` ni les rôles ne sont assignables en masse : seuls les
     * services/ressources d'administration les modifient explicitement.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    public function siteDepartements(): BelongsToMany
    {
        return $this->belongsToMany(SiteDepartement::class, 'site_departement_user')->withTimestamps();
    }

    /**
     * `role_user` reste la source de vérité des rôles métier ; les tables Spatie
     * (utilisées par Filament Shield pour les permissions) en sont le miroir.
     *
     * @return list<Role>
     */
    public function rolesEnum(): array
    {
        return $this->roleAssignments->pluck('role')->all();
    }

    public function hasRole($roles, ?string $guard = null): bool
    {
        if ($roles instanceof Role) {
            return in_array($roles, $this->rolesEnum(), true);
        }

        return $this->spatieHasRole($roles, $guard);
    }

    public function hasAnyRole(...$roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function assignRole(...$roles): static
    {
        foreach ($roles as $role) {
            if ($role instanceof Role) {
                $this->roleAssignments()->firstOrCreate(['role' => $role->value]);
            } else {
                $this->spatieAssignRole($role);
            }
        }
        $this->unsetRelation('roleAssignments');
        $this->miroirRoles();

        return $this;
    }

    /** Aligne les rôles Spatie correspondant à l'enum sur `role_user` (les autres rôles Shield sont conservés). */
    public function miroirRoles(): void
    {
        $actuels = $this->rolesEnum();
        foreach (Role::cases() as $role) {
            if (in_array($role, $actuels, true)) {
                $this->spatieAssignRole(SpatieRole::findOrCreate($role->value, 'web'));
            } else {
                $this->removeRole($role->value);
            }
        }
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($panel->getId()) {
            'rh' => $this->rolesEnum() !== [],
            'cms' => $this->hasRole(Role::Admin),
            default => false,
        };
    }
}
