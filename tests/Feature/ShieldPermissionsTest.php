<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Societe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShieldPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(Role::Admin);

        return $admin;
    }

    public function test_admin_role_receives_generated_permissions_and_can_write_referentials(): void
    {
        $admin = $this->admin();

        $this->assertTrue($admin->can('create', Societe::class));
        $this->assertTrue($admin->can('viewAny', User::class));
    }

    public function test_admin_without_the_shield_permission_is_denied(): void
    {
        $admin = $this->admin();
        $role = \Spatie\Permission\Models\Role::findByName(Role::Admin->value, 'web');
        $role->revokePermissionTo('Create:Societe');
        $admin->forgetCachedPermissions();

        $this->assertFalse($admin->fresh()->can('create', Societe::class));
    }

    public function test_non_admin_never_gets_write_access_even_with_the_permission(): void
    {
        $junior = User::factory()->create(['is_active' => true]);
        $junior->assignRole(Role::RhJunior);
        $junior->givePermissionTo('Create:Societe');

        $this->assertFalse($junior->can('create', Societe::class));
    }
}
