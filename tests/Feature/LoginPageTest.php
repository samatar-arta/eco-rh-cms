<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_pages_use_the_port_background_and_centered_card(): void
    {
        foreach (['/rh/login', '/cms/login'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('images/bg-login.jpg', false)
                ->assertSee('.fi-simple-main', false)
                ->assertSee('fi-simple-main', false);
        }

        $this->assertFileExists(public_path('images/bg-login.jpg'));
    }

    public function test_panel_theme_is_loaded_on_both_panels_after_login(): void
    {
        foreach (['rh' => '/rh', 'cms' => '/cms'] as $url) {
            $admin = User::factory()->create();
            $admin->forceFill(['is_active' => true])->save();
            $admin->assignRole(Role::Admin);

            $this->actingAs($admin)->get($url)->assertOk()
                ->assertSee('rh-panel-style', false)
                ->assertSee('ECOSYSTEME RH DLS');
        }
    }
}
