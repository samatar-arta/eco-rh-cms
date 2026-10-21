<?php

namespace Tests\Feature;

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
}
