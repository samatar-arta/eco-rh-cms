<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOnlyResourcesTest extends TestCase
{
    use RefreshDatabase;

    /** Écrans d'administration issus de l'ancien panneau CMS, désormais sous /rh. */
    private const ADMIN_SCREENS = [
        'contrats', 'departements', 'journal-audit', 'niveaux-sanction', 'site-departements',
        'sites-affectation', 'societes', 'users', 'workflow-histories',
        'model-conges', 'model-documents', 'model-evaluations', 'model-mutations', 'model-promotions', 'model-sanctions',
        'types-conges', 'types-documents', 'types-evaluations', 'types-mutations', 'types-promotions', 'types-sanctions',
    ];

    private function userWithRole(Role $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_active' => true])->save();
        $user->assignRole($role);

        return $user->fresh();
    }

    public function test_roles_other_than_admin_and_manager_get_403_on_every_admin_screen(): void
    {
        Filament::setCurrentPanel('rh');

        foreach (Role::cases() as $role) {
            if (in_array($role, [Role::Admin, Role::RhManager], true)) {
                continue;
            }
            $user = $this->userWithRole($role);

            foreach (self::ADMIN_SCREENS as $screen) {
                $this->actingAs($user)->get('/rh/'.$screen)->assertForbidden("{$role->value} sur /rh/{$screen}");
            }
        }
    }

    public function test_admin_and_manager_get_200_on_every_admin_screen(): void
    {
        Filament::setCurrentPanel('rh');

        foreach ([Role::Admin, Role::RhManager] as $role) {
            $user = $this->userWithRole($role);

            foreach (self::ADMIN_SCREENS as $screen) {
                $this->actingAs($user)->get('/rh/'.$screen)->assertOk("{$role->value} sur /rh/{$screen}");
            }
        }
    }

    public function test_admin_screens_are_hidden_from_non_admin_navigation(): void
    {
        Filament::setCurrentPanel('rh');
        $this->actingAs($this->userWithRole(Role::RhSenior))->get('/rh')
            ->assertOk()
            ->assertDontSee('/rh/types-documents', false)
            ->assertDontSee('/rh/users', false);
    }

    /** Test séparé : la navigation Filament est mise en cache par application. */
    public function test_admin_screens_are_listed_in_the_manager_navigation(): void
    {
        Filament::setCurrentPanel('rh');
        $this->actingAs($this->userWithRole(Role::RhManager))->get('/rh')
            ->assertOk()
            ->assertSee('/rh/types-documents', false)
            ->assertSee('/rh/users', false);
    }

    public function test_cms_panel_is_gone(): void
    {
        $this->get('/cms')->assertNotFound();
    }
}
