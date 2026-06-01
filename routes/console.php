<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('openapi:generate', function () {
    $spec = [
        'openapi' => '3.0.3',
        'info' => [
            'title' => 'Plant Assistant API',
            'description' => 'Generated API specification from current Laravel routes.',
            'version' => '1.3.0',
        ],
        'servers' => [[
            'url' => 'http://127.0.0.1:8000',
            'description' => 'Local server',
        ]],
        'components' => [
            'securitySchemes' => [
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'Token',
                ],
            ],
            'schemas' => [
                'ErrorResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'message' => ['type' => 'string'],
                        'status' => ['type' => 'integer'],
                    ],
                ],
            ],
        ],
        'security' => [['bearerAuth' => []]],
        'paths' => [],
    ];

    $overrides = [
        '/api/auth/register' => [
            'post' => [
                'tags' => ['Auth'],
                'summary' => 'Register user',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['name', 'email', 'password', 'password_confirmation'],
                                'properties' => [
                                    'name' => ['type' => 'string', 'maxLength' => 255],
                                    'email' => ['type' => 'string', 'format' => 'email'],
                                    'password' => ['type' => 'string', 'minLength' => 8],
                                    'password_confirmation' => ['type' => 'string', 'minLength' => 8],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '201' => ['description' => 'Registered'],
                    '422' => ['description' => 'Validation error'],
                ],
            ],
        ],
        '/api/auth/login' => [
            'post' => [
                'tags' => ['Auth'],
                'summary' => 'Login',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['email', 'password'],
                                'properties' => [
                                    'email' => ['type' => 'string', 'format' => 'email'],
                                    'password' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => ['description' => 'Authenticated'],
                    '422' => ['description' => 'Validation error'],
                ],
            ],
        ],
        '/api/feed' => [
            'get' => [
                'tags' => ['Feed'],
                'summary' => 'Public feed',
                'parameters' => [
                    ['in' => 'query', 'name' => 'search', 'schema' => ['type' => 'string', 'maxLength' => 255]],
                    ['in' => 'query', 'name' => 'sort_by', 'schema' => ['type' => 'string', 'enum' => ['created_at', 'likes']]],
                    ['in' => 'query', 'name' => 'sort_order', 'schema' => ['type' => 'string', 'enum' => ['asc', 'desc']]],
                    ['in' => 'query', 'name' => 'per_page', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]],
                    ['in' => 'query', 'name' => 'days', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 90]],
                ],
                'responses' => [
                    '200' => ['description' => 'Feed response'],
                    '422' => ['description' => 'Validation error'],
                ],
            ],
        ],
        '/api/dashboard/activity' => [
            'get' => [
                'tags' => ['Dashboard'],
                'summary' => 'Activity stats',
                'parameters' => [
                    ['in' => 'query', 'name' => 'days', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 365]],
                ],
                'responses' => [
                    '200' => ['description' => 'Activity response'],
                    '422' => ['description' => 'Validation error'],
                ],
            ],
        ],
        '/api/plants' => [
            'get' => [
                'tags' => ['Plants'],
                'summary' => 'Current user plants',
                'parameters' => [
                    ['in' => 'query', 'name' => 'room_id', 'schema' => ['type' => 'integer']],
                    ['in' => 'query', 'name' => 'is_public', 'schema' => ['type' => 'boolean']],
                    ['in' => 'query', 'name' => 'search', 'schema' => ['type' => 'string', 'maxLength' => 255]],
                    ['in' => 'query', 'name' => 'sort_by', 'schema' => ['type' => 'string', 'enum' => ['created_at', 'name', 'planted_at', 'height']]],
                    ['in' => 'query', 'name' => 'sort_order', 'schema' => ['type' => 'string', 'enum' => ['asc', 'desc']]],
                    ['in' => 'query', 'name' => 'per_page', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]],
                ],
            ],
        ],
        '/api/plants/{plantId}/images' => [
            'post' => [
                'tags' => ['Plant Images'],
                'summary' => 'Upload plant image',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['image'],
                                'properties' => [
                                    'image' => ['type' => 'string', 'format' => 'binary'],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '201' => ['description' => 'Image created'],
                    '422' => ['description' => 'Validation error'],
                ],
            ],
        ],
        '/api/plants/public/{plantId}/images' => [
            'get' => [
                'tags' => ['Plant Images'],
                'summary' => 'Public plant images',
                'parameters' => [
                    ['in' => 'path', 'name' => 'plantId', 'required' => true, 'schema' => ['type' => 'integer']],
                    ['in' => 'query', 'name' => 'per_page', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]],
                ],
                'responses' => [
                    '200' => ['description' => 'Image list'],
                    '404' => ['description' => 'Plant not found'],
                ],
            ],
        ],
        '/api/admin/reports/{id}/review' => [
            'put' => [
                'tags' => ['Admin Reports'],
                'summary' => 'Review report',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['status'],
                                'properties' => [
	                                    'status' => ['type' => 'string', 'enum' => ['accepted', 'rejected']],
	                                    'admin_comment' => ['type' => 'string', 'maxLength' => 1000],
	                                    'resolution_action' => ['type' => 'string', 'enum' => ['tip_delete_rank', 'block_user', 'tip_warn_rank', 'hide_plant', 'warn_user', 'delete_plant']],
	                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => ['description' => 'Reviewed'],
                    '429' => ['description' => 'Rate limited'],
                ],
            ],
        ],
        '/api/admin/metrics/traffic' => [
            'get' => [
                'tags' => ['Admin Metrics'],
                'summary' => 'Traffic metrics',
                'parameters' => [
                    ['in' => 'query', 'name' => 'minutes', 'schema' => ['type' => 'integer', 'minimum' => 5, 'maximum' => 720]],
                ],
                'responses' => [
                    '200' => ['description' => 'Metrics payload'],
                    '429' => ['description' => 'Rate limited'],
                ],
            ],
        ],
        '/api/users/{id}' => [
            'put' => [
                'tags' => ['Users'],
                'summary' => 'Admin update user',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['name', 'email', 'rank', 'role_name'],
                                'properties' => [
                                    'name' => ['type' => 'string', 'maxLength' => 255],
                                    'email' => ['type' => 'string', 'format' => 'email'],
                                    'rank' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100000],
                                    'role_name' => ['type' => 'string', 'enum' => ['user', 'admin']],
                                    'password' => ['type' => 'string', 'minLength' => 8, 'nullable' => true],
                                    'password_confirmation' => ['type' => 'string', 'minLength' => 8, 'nullable' => true],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => ['description' => 'User updated'],
                    '403' => ['description' => 'Forbidden'],
                    '422' => ['description' => 'Validation error'],
                ],
            ],
        ],
        '/api/users/{id}/block' => [
            'post' => [
                'tags' => ['Users'],
                'summary' => 'Block user',
                'requestBody' => [
                    'required' => false,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'reason' => ['type' => 'string', 'maxLength' => 1000, 'nullable' => true],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => ['description' => 'User blocked'],
                    '403' => ['description' => 'Forbidden'],
                ],
            ],
        ],
        '/api/users/{id}/unblock' => [
            'post' => [
                'tags' => ['Users'],
                'summary' => 'Unblock user',
                'responses' => [
                    '200' => ['description' => 'User unblocked'],
                    '403' => ['description' => 'Forbidden'],
                ],
            ],
        ],
        '/api/admin/plants/{plantId}/moderate' => [
            'post' => [
                'tags' => ['Admin Reports'],
                'summary' => 'Directly moderate plant',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['resolution_action'],
                                'properties' => [
                                    'resolution_action' => ['type' => 'string', 'enum' => ['hide_plant', 'warn_user', 'block_user', 'delete_plant']],
                                    'admin_comment' => ['type' => 'string', 'maxLength' => 1000, 'nullable' => true],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => ['description' => 'Plant moderated'],
                    '403' => ['description' => 'Forbidden'],
                    '422' => ['description' => 'Validation error'],
                ],
            ],
        ],
        '/api/admin/tips/{tipId}/moderate' => [
            'post' => [
                'tags' => ['Admin Reports'],
                'summary' => 'Directly moderate tip',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['resolution_action'],
                                'properties' => [
                                    'resolution_action' => ['type' => 'string', 'enum' => ['tip_delete_rank', 'tip_warn_rank', 'block_user']],
                                    'admin_comment' => ['type' => 'string', 'maxLength' => 1000, 'nullable' => true],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => ['description' => 'Tip moderated'],
                    '403' => ['description' => 'Forbidden'],
                    '422' => ['description' => 'Validation error'],
                ],
            ],
        ],
        '/api/plants/{plantId}/reports' => [
            'get' => [
                'tags' => ['Reports'],
                'summary' => 'Plant reports for owner or admin',
                'parameters' => [
                    ['in' => 'path', 'name' => 'plantId', 'required' => true, 'schema' => ['type' => 'integer']],
                    ['in' => 'query', 'name' => 'per_page', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]],
                ],
                'responses' => [
                    '200' => ['description' => 'Report list'],
                    '403' => ['description' => 'Forbidden'],
                ],
            ],
            'post' => [
                'tags' => ['Reports'],
                'summary' => 'Create plant report',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['reason'],
                                'properties' => [
                                    'reason' => ['type' => 'string', 'enum' => ['inappropriate_image', 'spam', 'abuse', 'misinformation', 'other']],
                                    'details' => ['type' => 'string', 'maxLength' => 1000, 'nullable' => true],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '201' => ['description' => 'Report created'],
                    '422' => ['description' => 'Validation error'],
                ],
            ],
        ],
        '/api/reports/received' => [
            'get' => [
                'tags' => ['Reports'],
                'summary' => 'Reports received for my plants and tips',
                'parameters' => [
                    ['in' => 'query', 'name' => 'per_page', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]],
                ],
                'responses' => [
                    '200' => ['description' => 'Report list'],
                    '401' => ['description' => 'Unauthorized'],
                ],
            ],
        ],
    ];

    $routes = collect(Route::getRoutes())->filter(fn ($route) => str_starts_with($route->uri(), 'api/'));

    foreach ($routes as $route) {
        $uri = '/'.$route->uri();
        $parts = explode('/', trim($route->uri(), '/'));
        $tag = isset($parts[1]) ? ucfirst(str_replace('-', ' ', $parts[1])) : 'General';

        $pathParams = [];
        preg_match_all('/\{([^}]+)\}/', $uri, $matches);
        foreach ($matches[1] as $param) {
            $pathParams[] = [
                'in' => 'path',
                'name' => $param,
                'required' => true,
                'schema' => ['type' => 'string'],
            ];
        }

        foreach ($route->methods() as $method) {
            $method = strtolower($method);
            if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                continue;
            }

            $operation = [
                'tags' => [$tag],
                'summary' => strtoupper($method).' '.$uri,
                'responses' => [
                    '200' => ['description' => 'Successful response'],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden'],
                    '422' => ['description' => 'Validation error'],
                ],
            ];

            if (! empty($pathParams)) {
                $operation['parameters'] = $pathParams;
            }

            if (in_array($method, ['post', 'put', 'patch'], true)) {
                $operation['requestBody'] = [
                    'required' => false,
                    'content' => [
                        'application/json' => [
                            'schema' => ['type' => 'object'],
                        ],
                    ],
                ];
            }

            if ($method === 'post') {
                $operation['responses']['201'] = ['description' => 'Created'];
            }

            if ($method === 'delete') {
                $operation['responses']['204'] = ['description' => 'Deleted'];
            }

            if (isset($overrides[$uri][$method])) {
                $operation = array_replace_recursive($operation, $overrides[$uri][$method]);
            }

            $spec['paths'][$uri][$method] = $operation;
        }
    }

    ksort($spec['paths']);
    file_put_contents(public_path('openapi.json'), json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $this->info('OpenAPI spec generated at: '.public_path('openapi.json'));
})->purpose('Generate OpenAPI document from current API routes');
