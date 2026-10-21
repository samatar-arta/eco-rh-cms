<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Employe;
use App\Models\SiteDepartement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Le build Vite n'est pas versionné : les tests n'en dépendent pas.
        $this->withoutVite();
    }

    private function userWithRole(Role $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_active' => true])->save();
        $user->assignRole($role);

        return $user->fresh();
    }

    private function employe(SiteDepartement $sd, array $attrs = []): Employe
    {
        return Employe::factory()->create($attrs + [
            'societe_id' => $sd->societe_id,
            'departement_id' => $sd->departement_id,
            'site_id' => $sd->site_id,
        ]);
    }

    public function test_guest_cannot_access_the_listing(): void
    {
        $this->get('/employes')->assertRedirect();
    }

    public function test_user_without_rh_role_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_active' => true])->save();

        $this->actingAs($user)->get('/employes')->assertForbidden();
    }

    public function test_admin_sees_employees_paginated_by_20_with_status_badges(): void
    {
        $sd = SiteDepartement::factory()->create();
        foreach (range(1, 25) as $i) {
            $this->employe($sd, ['name' => sprintf('Employe %02d', $i), 'statut_employe' => $i === 1 ? 'SORTI' : 'ACTIF']);
        }

        $page1 = $this->actingAs($this->userWithRole(Role::Admin))->get('/employes');
        $page1->assertOk()->assertSee('Employe 01')->assertSee('Employe 20')->assertDontSee('Employe 21');
        $page1->assertSee('bg-green-100', false)->assertSee('bg-slate-100', false);
        $page1->assertSee('page=2', false);

        $this->actingAs($this->userWithRole(Role::Admin))->get('/employes?page=2')
            ->assertOk()->assertSee('Employe 21')->assertDontSee('Employe 01');
    }

    public function test_search_matches_name_matricule_and_function_and_escapes_wildcards(): void
    {
        $sd = SiteDepartement::factory()->create();
        $this->employe($sd, ['name' => 'ALICE MARTIN', 'matricule' => 'M100', 'fonction' => 'CHAUFFEUR']);
        $this->employe($sd, ['name' => 'BOB DURAND', 'matricule' => 'M200', 'fonction' => 'GARDIEN']);
        $admin = $this->userWithRole(Role::Admin);

        $this->actingAs($admin)->get('/employes?q=alice')->assertSee('ALICE MARTIN')->assertDontSee('BOB DURAND');
        $this->actingAs($admin)->get('/employes?q=M200')->assertSee('BOB DURAND')->assertDontSee('ALICE MARTIN');
        $this->actingAs($admin)->get('/employes?q=gardien')->assertSee('BOB DURAND')->assertDontSee('ALICE MARTIN');
        $this->actingAs($admin)->get('/employes?q=%25')->assertDontSee('ALICE MARTIN')->assertDontSee('BOB DURAND');
    }

    public function test_status_filter_rejects_unknown_values(): void
    {
        $this->actingAs($this->userWithRole(Role::Admin))->get('/employes?statut=HACK')
            ->assertSessionHasErrors('statut');
    }

    public function test_rh_user_only_sees_employees_of_own_perimeter(): void
    {
        $mine = SiteDepartement::factory()->create();
        $other = SiteDepartement::factory()->create();
        $this->employe($mine, ['name' => 'DANS LE PERIMETRE']);
        $this->employe($other, ['name' => 'HORS PERIMETRE']);

        $rh = $this->userWithRole(Role::RhSenior);
        $rh->siteDepartements()->attach($mine->id);

        $this->actingAs($rh)->get('/employes')
            ->assertOk()->assertSee('DANS LE PERIMETRE')->assertDontSee('HORS PERIMETRE');
    }
}
