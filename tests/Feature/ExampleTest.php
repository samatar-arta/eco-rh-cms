<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_login_page_of_the_rh_panel_is_reachable(): void
    {
        $this->get('/rh/login')->assertOk();
    }
}
