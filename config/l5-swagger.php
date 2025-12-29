<?php

return [
    'default' => 'default',
    'documentations' => [
        'default' => [
            'api' => [
                'title' => 'BOMEQP API Documentation',
                'version' => '1.0.0',
                'description' => 'Comprehensive API documentation for BOMEQP Accreditation Management System',
                'termsOfService' => '',
                'contact' => [
                    'name' => 'API Support',
                    'email' => 'support@bomeqp.com',
                ],
                'license' => [
                    'name' => 'MIT',
                ],
            ],
            'routes' => [
                'api' => 'api/documentation',
            ],
            'paths' => [
                'use_absolute_path' => env('L5_SWAGGER_USE_ABSOLUTE_PATH', false),
                'base' => env('L5_SWAGGER_BASE_PATH', null),
                'docs' => storage_path('api-docs'),
                'docs_json' => 'api-docs.json',
                'docs_yaml' => 'api-docs.yaml',
                'annotations' => [
                    app_path('Http/Controllers'),
                ],
                'excludes' => [],
            ],
        ],
    ],
    'defaults' => [
        'routes' => [
            'docs' => 'api/doc',
            'oauth2_callback' => 'api/oauth2-callback',
            'middleware' => [
                'api' => [],
                'asset' => [],
                'docs' => [],
                'oauth2_callback' => [],
            ],
            'group_by' => 'tags',
            'group_by_default' => false,
            'subPath' => false,
            'hide' => false,
        ],
        'paths' => [
            'use_absolute_path' => env('L5_SWAGGER_USE_ABSOLUTE_PATH', false),
            'docs' => storage_path('api-docs'),
            'models' => storage_path('api-docs/models'),
            'views' => resource_path('views/vendor/l5-swagger'),
        ],
        'scanOptions' => [
            'exclude' => [],
            'pattern' => '*.php',
            'open_api_spec_version' => env('L5_SWAGGER_OPEN_API_SPEC_VERSION', '3.0.0'),
        ],
        'securityDefinitions' => [
            'securitySchemes' => [
                'sanctum' => [
                    'type' => 'http',
                    'description' => 'Enter token in format: Bearer {token}',
                    'name' => 'Authorization',
                    'in' => 'header',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT',
                ],
            ],
            'security' => [
                [
                    'sanctum' => [],
                ],
            ],
        ],
        'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', false),
        'generate_yaml_copy' => env('L5_SWAGGER_GENERATE_YAML_COPY', false),
        'proxy' => false,
        'additional_config_url' => null,
        'operations_sort' => env('L5_SWAGGER_OPERATIONS_SORT', null),
        'validator_url' => null,
        'ui' => [
            'display' => [
                'dark_mode' => env('L5_SWAGGER_UI_DARK_MODE', false),
                'doc_expansion' => env('L5_SWAGGER_UI_DOC_EXPANSION', 'none'),
                'filter' => env('L5_SWAGGER_UI_FILTERS', true),
            ],
            'authorization' => [
                'persist_authorization' => env('L5_SWAGGER_UI_PERSIST_AUTHORIZATION', false),
                'oauth2' => [
                    'client_id' => env('L5_SWAGGER_UI_OAUTH2_CLIENT_ID'),
                    'client_secret' => env('L5_SWAGGER_UI_OAUTH2_CLIENT_SECRET'),
                    'realm' => env('L5_SWAGGER_UI_OAUTH2_REALM'),
                    'app_name' => env('L5_SWAGGER_UI_OAUTH2_APP_NAME'),
                    'scope_separator' => env('L5_SWAGGER_UI_OAUTH2_SCOPE_SEPARATOR', ' '),
                    'additional_query_string_params' => env('L5_SWAGGER_UI_OAUTH2_ADDITIONAL_QUERY_STRING_PARAMS'),
                    'use_basic_authentication_with_access_code_grant' => env('L5_SWAGGER_UI_OAUTH2_USE_BASIC_AUTH', false),
                    'use_pkce_with_authorization_code_grant' => env('L5_SWAGGER_UI_OAUTH2_USE_PKCE', false),
                ],
            ],
        ],
        'constants' => [
            'L5_SWAGGER_CONST_HOST' => env('L5_SWAGGER_CONST_HOST', 'https://aeroenix.com/v1'),
        ],
    ],
];

