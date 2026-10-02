<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    public function test_swagger_ui_is_available(): void
    {
        $this->get('/api/documentation')
            ->assertOk()
            ->assertSee('SwaggerUIBundle', false)
            ->assertSee('/docs/openapi.json', false);
    }

    public function test_openapi_document_is_valid_json_and_contains_main_endpoints(): void
    {
        $contents = file_get_contents(public_path('docs/openapi.json'));
        $document = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('3.0.3', $document['openapi']);
        $this->assertSame('Inventory & Order Management API', $document['info']['title']);
        $this->assertArrayHasKey('bearerAuth', $document['components']['securitySchemes']);

        $requiredPaths = [
            '/api/register',
            '/api/login',
            '/api/categories',
            '/api/products',
            '/api/suppliers',
            '/api/customers',
            '/api/inventory',
            '/api/orders',
            '/api/orders/{id}/status',
            '/api/dashboard',
            '/api/reports/daily-sales',
            '/reports/sales-summary',
        ];

        foreach ($requiredPaths as $path) {
            $this->assertArrayHasKey($path, $document['paths']);
        }
    }
}
