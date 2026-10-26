<?php

namespace Tests\Feature;

use Tests\TestCase;

class DashboardUiTest extends TestCase
{
    public function test_home_page_contains_dashboard_and_navigation(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Welcome to')
            ->assertSee('Random Video Chat')
            ->assertSee('Screen Sharing')
            ->assertSee('Browser & perangkat', false);
    }

    public function test_dashboard_alias_renders_the_same_navigation(): void
    {
        $this->get('/dashboard')->assertOk()->assertSee('Guest Workspace');
    }

    public function test_random_chat_page_is_available(): void
    {
        $this->get('/random')->assertOk()->assertSee('Mulai Bertemu');
    }
}
