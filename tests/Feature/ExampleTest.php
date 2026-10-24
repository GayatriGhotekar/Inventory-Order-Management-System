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
            ->assertSee('id="categories-view"', false)
            ->assertSee('id="suppliers-view"', false)
            ->assertSee('id="purchases-view"', false)
            ->assertSee('id="customers-view"', false)
            ->assertSee('id="warehouses-view"', false)
            ->assertSee('id="transfers-view"', false)
            ->assertSee('id="returns-view"', false)
            ->assertSee('id="audits-view"', false)
            ->assertSee('id="record-dialog"', false)
            ->assertSee('data-action="create-order"', false)
            ->assertSee('/css/inventory.css', false)
            ->assertSee('/js/inventory.js', false);

        $response->assertSee('inventory.css?v=', false)
            ->assertSee('inventory.js?v=', false);

        $this->assertFileExists(public_path('css/inventory.css'));
        $this->assertFileExists(public_path('js/inventory.js'));

        $script = file_get_contents(public_path('js/inventory.js'));
        $this->assertStringContainsString("'create-product'", $script);
        $this->assertStringContainsString("'adjust-stock'", $script);
        $this->assertStringContainsString("'advance-order'", $script);
        $this->assertStringContainsString("'receive-purchase'", $script);
        $this->assertStringContainsString('openShipmentDialog', $script);
        $this->assertStringContainsString("'receive-return'", $script);
        $this->assertStringContainsString('loadAudits', $script);
    }
}
