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

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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

    /** @return list<Role> */
    public function roles(): array
    {
        return $this->roleAssignments->pluck('role')->all();
    }

    public function hasRole(Role $role): bool
    {
        return in_array($role, $this->roles(), true);
    }

    public function hasAnyRole(Role ...$roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function assignRole(Role $role): void
    {
        $this->roleAssignments()->firstOrCreate(['role' => $role->value]);
        $this->unsetRelation('roleAssignments');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($panel->getId()) {
            'rh' => $this->roles() !== [],
            'cms' => $this->hasRole(Role::Admin),
            default => false,
        };
    }
}
