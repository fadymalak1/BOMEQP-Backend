<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Documentation Title
    |--------------------------------------------------------------------------
    */
    'title' => env('SCRIBE_TITLE', 'BOMEQP API Documentation'),

    /*
    |--------------------------------------------------------------------------
    | Documentation Description
    |--------------------------------------------------------------------------
    */
    'description' => env('SCRIBE_DESCRIPTION', 'Comprehensive API documentation for BOMEQP Accreditation Management System'),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL for your API. This is used to generate example URLs.
    |
    */
    'base_url' => env('SCRIBE_BASE_URL', 'https://aeroenix.com/v1'),

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Configure which routes to document.
    |
    */
    'routes' => [
        [
            'match' => [
                'domains' => ['*'],
                'prefixes' => ['api/*'],
            ],
            'include' => [],
            'exclude' => [
                'api/stripe/webhook',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Type
    |--------------------------------------------------------------------------
    |
    | The type of documentation to generate.
    | Options: 'static' (HTML) or 'laravel' (dynamic)
    |
    */
    'type' => 'static',

    /*
    |--------------------------------------------------------------------------
    | Static Type Output Path
    |--------------------------------------------------------------------------
    |
    | Where to output the generated HTML documentation.
    |
    */
    'static' => [
        'output_path' => 'public/docs',
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAPI
    |--------------------------------------------------------------------------
    |
    | Generate OpenAPI specification file.
    |
    */
    'openapi' => [
        'enabled' => true,
        'output_path' => 'public/docs/openapi.yaml',
    ],

    /*
    |--------------------------------------------------------------------------
    | Postman
    |--------------------------------------------------------------------------
    |
    | Generate Postman collection file.
    |
    */
    'postman' => [
        'enabled' => true,
        'output_path' => 'public/docs/postman.json',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Configure authentication for the documentation.
    |
    */
    'auth' => [
        'enabled' => false,
        'guard' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Intro Text
    |--------------------------------------------------------------------------
    |
    | Text to display at the top of the documentation.
    |
    */
    'intro_text' => <<<'INTRO'
Welcome to the BOMEQP API Documentation. This documentation provides comprehensive information about all available API endpoints.

## Authentication

Most endpoints require authentication using Laravel Sanctum. Include the bearer token in the Authorization header:

```
Authorization: Bearer {token}
```

After successful login/registration, you'll receive a token that should be included in all subsequent requests.
INTRO
    ,

    /*
    |--------------------------------------------------------------------------
    | Example Languages
    |--------------------------------------------------------------------------
    |
    | Languages to show code examples in.
    |
    */
    'example_languages' => [
        'bash',
        'javascript',
        'php',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logo
    |--------------------------------------------------------------------------
    |
    | Logo to display in the documentation.
    |
    */
    'logo' => false,

    /*
    |--------------------------------------------------------------------------
    | Theme
    |--------------------------------------------------------------------------
    |
    | Theme for the documentation.
    | Options: 'default', 'elements'
    |
    */
    'theme' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Try It Out
    |--------------------------------------------------------------------------
    |
    | Enable "Try It Out" feature in the documentation.
    |
    */
    'try_it_out' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Group
    |--------------------------------------------------------------------------
    |
    | Default group for endpoints without a @group annotation.
    |
    */
    'default_group' => 'General',

    /*
    |--------------------------------------------------------------------------
    | Sort
    |--------------------------------------------------------------------------
    |
    | How to sort endpoints within groups.
    | Options: 'alpha', 'http_method', 'custom'
    |
    */
    'sort' => 'alpha',

    /*
    |--------------------------------------------------------------------------
    | Strategies
    |--------------------------------------------------------------------------
    |
    | Strategies for extracting API information.
    |
    */
    'strategies' => [
        'metadata' => [
            \Knuckles\Scribe\Extracting\Strategies\Metadata\GetFromDocBlocks::class,
        ],
        'urlParameters' => [
            \Knuckles\Scribe\Extracting\Strategies\UrlParameters\GetFromUrlParamTag::class,
        ],
        'queryParameters' => [
            \Knuckles\Scribe\Extracting\Strategies\QueryParameters\GetFromQueryParamTag::class,
        ],
        'headers' => [
            \Knuckles\Scribe\Extracting\Strategies\Headers\GetFromHeaderTag::class,
        ],
        'bodyParameters' => [
            \Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromBodyParamTag::class,
        ],
        'responses' => [
            \Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseTag::class,
            \Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseAttrib::class,
            \Knuckles\Scribe\Extracting\Strategies\Responses\UseApiResource::class,
            \Knuckles\Scribe\Extracting\Strategies\Responses\UseTransformerTags::class,
            \Knuckles\Scribe\Extracting\Strategies\Responses\ResponseCalls::class,
        ],
        'responseFields' => [
            \Knuckles\Scribe\Extracting\Strategies\ResponseFields\GetFromResponseFieldTag::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fractal
    |--------------------------------------------------------------------------
    |
    | Fractal serializer configuration.
    |
    */
    'fractal' => [
        'serializer' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Transactions
    |--------------------------------------------------------------------------
    |
    | Whether to wrap response calls in database transactions.
    |
    */
    'database_connections_to_transact' => [config('database.default')],

    /*
    |--------------------------------------------------------------------------
    | Route Matcher
    |--------------------------------------------------------------------------
    |
    | Custom route matcher class.
    |
    */
    'routeMatcher' => \Knuckles\Scribe\Matching\RouteMatcher::class,

    /*
    |--------------------------------------------------------------------------
    | Continue Without Database
    |--------------------------------------------------------------------------
    |
    | Whether to continue generating documentation even if database is not available.
    |
    */
    'continue_without_database' => false,

    /*
    |--------------------------------------------------------------------------
    | Groups
    |--------------------------------------------------------------------------
    |
    | Custom groups configuration.
    |
    */
    'groups' => [
        'default' => 'General',
    ],

    /*
    |--------------------------------------------------------------------------
    | Examples
    |--------------------------------------------------------------------------
    |
    | Example configuration.
    |
    */
    'examples' => [
        'faker_seed' => null,
    ],
];

