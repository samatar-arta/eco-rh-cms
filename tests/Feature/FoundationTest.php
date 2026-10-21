<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Contact;
use App\Models\Departement;
use App\Models\Employe;
use App\Models\SiteDepartement;
use App\Models\Societe;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(Role $role, array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $user->forceFill(['is_active' => $attrs['is_active'] ?? true])->save();
        $user->assignRole($role);

        return $user->fresh();
    }

    /** @return array{0: SiteDepartement, 1: Employe} */
    private function employeInPerimeter(): array
    {
        $sd = SiteDepartement::factory()->create();
        $employe = Employe::factory()->create([
            'societe_id' => $sd->societe_id,
            'departement_id' => $sd->departement_id,
            'site_id' => $sd->site_id,
        ]);

        return [$sd, $employe];
    }

    public function test_root_redirects_to_rh_and_register_is_gone(): void
    {
        $this->get('/')->assertRedirect('/rh');
        $this->get('/register')->assertNotFound();
    }

    public function test_panel_access_requires_active_user_with_a_role(): void
    {
        $rh = Filament::getPanel('rh');
        $cms = Filament::getPanel('cms');

        $this->assertFalse(User::factory()->create()->canAccessPanel($rh));
        $this->assertTrue($this->userWithRole(Role::RhJunior)->canAccessPanel($rh));
        $this->assertFalse($this->userWithRole(Role::RhJunior)->canAccessPanel($cms));
        $this->assertTrue($this->userWithRole(Role::Admin)->canAccessPanel($cms));
        $this->assertFalse($this->userWithRole(Role::Admin, ['is_active' => false])->canAccessPanel($rh));
    }

    public function test_role_is_not_mass_assignable(): void
    {
        $user = User::create(['name' => 'X', 'email' => 'x@example.com', 'password' => 'secret-pass-123', 'is_active' => false]);

        $this->assertTrue($user->fresh()->is_active);
        $this->assertSame([], $user->rolesEnum());
    }

    public function test_employee_visibility_is_limited_to_user_perimeter(): void
    {
        [$sd, $employe] = $this->employeInPerimeter();
        [, $autre] = $this->employeInPerimeter();

        $junior = $this->userWithRole(Role::RhJunior);
        $junior->siteDepartements()->attach($sd);

        $this->assertTrue($junior->can('view', $employe));
        $this->assertFalse($junior->can('view', $autre));
        $this->assertSame([$employe->id], Employe::visibleTo($junior)->pluck('id')->all());
    }

    public function test_user_without_perimeter_sees_nothing(): void
    {
        [, $employe] = $this->employeInPerimeter();

        $senior = $this->userWithRole(Role::RhSenior);

        $this->assertFalse($senior->can('view', $employe));
        $this->assertSame(0, Employe::visibleTo($senior)->count());
    }

    public function test_admin_has_unrestricted_access_to_all_employees_without_any_perimeter(): void
    {
        [, $employe] = $this->employeInPerimeter();
        [, $autre] = $this->employeInPerimeter();
        $admin = $this->userWithRole(Role::Admin);

        foreach (['viewAny', 'create'] as $ability) {
            $this->assertTrue($admin->can($ability, Employe::class));
        }
        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertTrue($admin->can($ability, $employe));
            $this->assertTrue($admin->can($ability, $autre));
        }
        $this->assertSame(2, Employe::visibleTo($admin)->count());

        $inactif = $this->userWithRole(Role::Admin, ['is_active' => false]);
        $this->assertFalse($inactif->can('view', $employe));
        $this->assertSame(0, Employe::visibleTo($inactif)->count());
    }

    public function test_only_rh_roles_can_create_or_update_employees(): void
    {
        [$sd, $employe] = $this->employeInPerimeter();

        $paie = $this->userWithRole(Role::GestionnairePaie);
        $paie->siteDepartements()->attach($sd);
        $officer = $this->userWithRole(Role::RhOfficer);
        $officer->siteDepartements()->attach($sd);

        $this->assertTrue($paie->can('view', $employe));
        $this->assertFalse($paie->can('create', Employe::class));
        $this->assertTrue($officer->can('update', $employe));
        $this->assertFalse($officer->can('delete', $employe));
    }

    public function test_user_cannot_change_own_assignments(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $other = $this->userWithRole(Role::RhJunior);
        $senior = $this->userWithRole(Role::RhSenior);

        $this->assertFalse($admin->can('manageAssignments', $admin));
        $this->assertTrue($admin->can('manageAssignments', $other));
        $this->assertFalse($senior->can('manageAssignments', $senior));
        $this->assertFalse($senior->can('manageAssignments', $other));
    }

    public function test_referentials_are_writable_by_admin_only(): void
    {
        $societe = Societe::factory()->create();

        $this->assertTrue($this->userWithRole(Role::Admin)->can('update', $societe));
        $this->assertTrue($this->userWithRole(Role::RhJunior)->can('view', $societe));
        $this->assertFalse($this->userWithRole(Role::RhJunior)->can('update', $societe));
    }

    public function test_site_departement_rejects_department_of_another_company(): void
    {
        $societeA = Societe::factory()->create();
        $societeB = Societe::factory()->create();
        $departementB = Departement::factory()->create(['societe_id' => $societeB->id]);

        $this->expectException(QueryException::class);

        SiteDepartement::factory()->create([
            'societe_id' => $societeA->id,
            'departement_id' => $departementB->id,
        ]);
    }

    public function test_employee_rejects_department_of_another_company(): void
    {
        $departementB = Departement::factory()->create();

        $this->expectException(QueryException::class);

        Employe::factory()->create([
            'societe_id' => Societe::factory()->create()->id,
            'departement_id' => $departementB->id,
        ]);
    }

    public function test_contact_does_not_create_a_user_account(): void
    {
        $before = User::count();

        Contact::factory()->create();

        $this->assertSame($before, User::count());
        $this->assertFalse(is_subclass_of(Contact::class, \Illuminate\Foundation\Auth\User::class));
    }

    public function test_employee_age_and_seniority_are_computed(): void
    {
        $employe = Employe::factory()->make([
            'date_naissance' => now()->subYears(30)->subDay(),
            'date_embauche' => now()->subYears(4)->subDay(),
            'date_sortie' => null,
        ]);

        $this->assertSame(30, $employe->age);
        $this->assertSame(4, (int) $employe->anciennete);
    }
}
