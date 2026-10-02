<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200)
            ->assertSee('Inventory &amp; Order Management', false)
            ->assertSee('id="login-form"', false)
            ->assertSee('/css/inventory.css', false)
            ->assertSee('/js/inventory.js', false);

        $this->assertFileExists(public_path('css/inventory.css'));
        $this->assertFileExists(public_path('js/inventory.js'));
    }
}
