<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OpenApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_docs_route_has_no_public_directory_conflict(): void
    {
        $this->assertDirectoryDoesNotExist(base_path('public/docs'));

        $this->get('/docs')
            ->assertOk()
            ->assertSee('Plant Assistant API Docs', false);
    }

    public function test_openapi_document_is_valid_and_references_existing_routes(): void
    {
        $specPath = base_path('public/openapi.json');
        $this->assertFileExists($specPath);

        $spec = json_decode((string) file_get_contents($specPath), true);
        $this->assertIsArray($spec);
        $this->assertSame('3.0.3', $spec['openapi'] ?? null);
        $this->assertIsArray($spec['paths'] ?? null);

        $routeMap = [];
        foreach (Route::getRoutes() as $route) {
            $uri = '/'.$route->uri();
            foreach ($route->methods() as $method) {
                $method = strtolower($method);
                if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    $routeMap[$method][$uri] = true;
                }
            }
        }

        foreach ($spec['paths'] as $path => $operations) {
            foreach ($operations as $method => $_operation) {
                $this->assertTrue(
                    isset($routeMap[strtolower($method)][$path]),
                    "OpenAPI path/method is not implemented: {$method} {$path}"
                );
            }
        }
    }

    public function test_all_api_routes_are_documented_in_openapi(): void
    {
        $spec = json_decode((string) file_get_contents(base_path('public/openapi.json')), true);
        $paths = $spec['paths'] ?? [];

        $documentedMap = [];
        foreach ($paths as $path => $operations) {
            foreach ($operations as $method => $_operation) {
                $documentedMap[strtolower($method).':'.$path] = true;
            }
        }

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }

            $path = '/'.$route->uri();
            foreach ($route->methods() as $method) {
                $method = strtolower($method);
                if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    continue;
                }

                $this->assertTrue(
                    isset($documentedMap[$method.':'.$path]),
                    "Route is missing in OpenAPI: {$method} {$path}"
                );
            }
        }
    }

    public function test_critical_endpoints_are_documented_in_openapi(): void
    {
        $spec = json_decode((string) file_get_contents(base_path('public/openapi.json')), true);
        $paths = $spec['paths'] ?? [];

        $expected = [
            ['get', '/api/feed'],
            ['get', '/api/feed/personal'],
            ['get', '/api/feed/trending'],
            ['get', '/api/dashboard/overview'],
            ['get', '/api/dashboard/activity'],
            ['get', '/api/dashboard/plant-health'],
            ['get', '/api/admin/reports'],
            ['put', '/api/admin/reports/{id}/review'],
            ['get', '/api/admin/metrics/traffic'],
        ];

        foreach ($expected as [$method, $path]) {
            $this->assertArrayHasKey($path, $paths, "Missing OpenAPI path: {$path}");
            $this->assertArrayHasKey($method, $paths[$path], "Missing OpenAPI operation: {$method} {$path}");
        }
    }

    public function test_openapi_query_constraints_match_runtime_validation_for_feed_and_dashboard(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/feed?per_page=500')->assertStatus(422);
        $this->getJson('/api/dashboard/activity?days=1000')->assertStatus(422);
    }

    public function test_openapi_has_detailed_override_for_feed_and_media_upload(): void
    {
        $spec = json_decode((string) file_get_contents(base_path('public/openapi.json')), true);

        $feedParameters = $spec['paths']['/api/feed']['get']['parameters'] ?? [];
        $sortByParam = collect($feedParameters)->firstWhere('name', 'sort_by');
        $this->assertSame(['created_at', 'likes'], $sortByParam['schema']['enum'] ?? null);

        $uploadSchema = $spec['paths']['/api/plants/{plantId}/images']['post']['requestBody']['content']['multipart/form-data']['schema'] ?? null;
        $this->assertSame('binary', $uploadSchema['properties']['image']['format'] ?? null);
    }
}
