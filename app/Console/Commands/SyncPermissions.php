<?php

namespace App\Console\Commands;

use App\Enums\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

class SyncPermissions extends Command
{
    protected $signature = 'app:sync-permissions';

    protected $description = 'Génère les permissions Shield du panneau rh et les accorde à ADMIN et RH_MANAGER';

    public function handle(): int
    {
        // `permissions` seulement : les policies existantes ne sont jamais réécrites.
        Artisan::call('shield:generate', ['--all' => true, '--panel' => 'rh', '--option' => 'permissions']);

        // ADMIN et RH_MANAGER ont les mêmes droits d'administration.
        foreach ([Role::Admin, Role::RhManager] as $role) {
            SpatieRole::findOrCreate($role->value, 'web')->syncPermissions(Permission::all());
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info(Permission::count().' permissions accordées aux rôles ADMIN et RH_MANAGER.');

        return self::SUCCESS;
    }
}
