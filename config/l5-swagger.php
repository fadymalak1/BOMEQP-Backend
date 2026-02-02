<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default documentation route
    |--------------------------------------------------------------------------
    |
    | Laravel will register these routes - STARTING with $defaults['prefix']
    |
    */
    'defaults' => [
        'routes' => [
            /*
            |--------------------------------------------------------------------------
            | Route for accessing api documentation interface
            |--------------------------------------------------------------------------
            */
            'api' => 'api/documentation',
        ],

        /*
        |--------------------------------------------------------------------------
        | Swagger configuration
        |--------------------------------------------------------------------------
        |
        | Configuration for the Swagger OpenAPI spec generation
        |
        */
        'paths' => [
            /*
            |--------------------------------------------------------------------------
            | Absolute path to location where parsed swagger annotations are stored
            |--------------------------------------------------------------------------
            */
            'docs' => storage_path('api-docs'),

            /*
            |--------------------------------------------------------------------------
            | Absolute path to directory containing the swagger annotations are stored.
            |--------------------------------------------------------------------------
            */
            'annotations' => base_path('app'),

            /*
            |--------------------------------------------------------------------------
            | Absolute path to directory where to export views
            |--------------------------------------------------------------------------
            */
            'views' => resource_path('views/vendor/l5-swagger'),

            /*
            |--------------------------------------------------------------------------
            | Edit to set the api's base path
            |--------------------------------------------------------------------------
            */
            'base' => env('L5_SWAGGER_BASE_PATH', null),

            /*
            |--------------------------------------------------------------------------
            | Absolute path to directories that should be excluded from scanning
            | @deprecated Please use `scanOptions.exclude`
            |--------------------------------------------------------------------------
            */
            'excludes' => [],
        ],

        /*
        |--------------------------------------------------------------------------
        | API security definitions. Will be generated into documentation file.
        |--------------------------------------------------------------------------
        */
        'securityDefinitions' => [
            'securitySchemes' => [
                'sanctum' => [
                    'type' => 'apiKey',
                    'description' => 'Enter token in format (Bearer <token>)',
                    'name' => 'Authorization',
                    'in' => 'header',
                ],
            ],
            'security' => [
                [
                    'sanctum' => [],
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Set this to `true` in development mode so that docs would be regenerated on each request
        | Set this to `false` to disable swagger generation on production
        |--------------------------------------------------------------------------
        */
        'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', false),

        /*
        |--------------------------------------------------------------------------
        | Set this to `true` to generate a copy of documentation in yaml format
        |--------------------------------------------------------------------------
        */
        'generate_yaml_copy' => env('L5_SWAGGER_GENERATE_YAML_COPY', false),

        /*
        |--------------------------------------------------------------------------
        | Edit to set the swagger version number
        |--------------------------------------------------------------------------
        */
        'swagger_version' => env('L5_SWAGGER_SWAGGER_VERSION', '3.0'),

        /*
        |--------------------------------------------------------------------------
        | Edit to trust the proxy's ip address - needed for AWS Load Balancer
        | string[]
        |--------------------------------------------------------------------------
        */
        'proxy' => false,

        /*
        |--------------------------------------------------------------------------
        | Configs plugin allows to fetch external configs instead of passing them to SwaggerUIBundle.
        | See more at: https://github.com/swagger-api/swagger-ui#configs-plugin
        |--------------------------------------------------------------------------
        */
        'additional_config_url' => null,

        /*
        |--------------------------------------------------------------------------
        | Apply a sort to the operation list of each API. It can be 'alpha' (sort by paths alphanumerically),
        | 'method' (sort by HTTP method).
        | Default is the order returned by the server unchanged.
        |--------------------------------------------------------------------------
        */
        'operations_sort' => env('L5_SWAGGER_OPERATIONS_SORT', null),

        /*
        |--------------------------------------------------------------------------
        | Pass the validatorUrl parameter to SwaggerUi init. If set to null, no validation will be done.
        | The default is 'https://validator.swagger.io/validator'.
        |--------------------------------------------------------------------------
        */
        'validator_url' => null,

        /*
        |--------------------------------------------------------------------------
        | Swagger UI configuration parameters
        |--------------------------------------------------------------------------
        */
        'ui' => [
            'display' => [
                /*
                |--------------------------------------------------------------------------
                | Controls the default expansion setting for the operations and tags.
                | It can be 'list' (expands only the tags), 'full' (expands the tags and operations),
                | or 'none' (expands nothing).
                |--------------------------------------------------------------------------
                */
                'doc_expansion' => env('L5_SWAGGER_UI_DOC_EXPANSION', 'none'),

                /*
                |--------------------------------------------------------------------------
                | If set, enables filtering. The top bar will show an edit box that
                | you can use to filter the tagged operations that are shown. Can be
                | Boolean to enable or disable, or a string, in which case filtering
                | will be enabled using that string as the filter expression. Filtering
                | is case-sensitive matching the filter expression anywhere inside the tag.
                |--------------------------------------------------------------------------
                */
                'filter' => env('L5_SWAGGER_UI_FILTERS', true),
            ],

            'authorization' => [
                /*
                |--------------------------------------------------------------------------
                | If set to true, it persists authorization data, and it would not be lost on browser close/refresh
                |--------------------------------------------------------------------------
                */
                'persist_authorization' => env('L5_SWAGGER_UI_PERSIST_AUTHORIZATION', false),

                'oauth2' => [
                    /*
                    |--------------------------------------------------------------------------
                    | Default clientId. MUST be a string
                    |--------------------------------------------------------------------------
                    */
                    'client_id' => env('L5_SWAGGER_UI_OAUTH2_CLIENT_ID', 'your-client-id'),

                    /*
                    |--------------------------------------------------------------------------
                    | Default clientSecret. MUST be a string
                    |--------------------------------------------------------------------------
                    */
                    'client_secret' => env('L5_SWAGGER_UI_OAUTH2_CLIENT_SECRET', 'your-client-secret'),

                    /*
                    |--------------------------------------------------------------------------
                    | Realm query parameter (for oauth1) added to authorizationUrl and tokenUrl.
                    | MUST be a string
                    |--------------------------------------------------------------------------
                    */
                    'realm' => env('L5_SWAGGER_UI_OAUTH2_REALM', ''),

                    /*
                    |--------------------------------------------------------------------------
                    | Application name, displayed in authorization popup.
                    | MUST be a string
                    |--------------------------------------------------------------------------
                    */
                    'app_name' => env('L5_SWAGGER_UI_OAUTH2_APP_NAME', 'L5 Swagger UI'),

                    /*
                    |--------------------------------------------------------------------------
                    | Scope separator for passing scopes, encoded before calling, default value is a space
                    | (encoded value %20).
                    | MUST be a string
                    |--------------------------------------------------------------------------
                    */
                    'scope_separator' => env('L5_SWAGGER_UI_OAUTH2_SCOPE_SEPARATOR', ' '),

                    /*
                    |--------------------------------------------------------------------------
                    | Additional query parameters added to authorizationUrl and tokenUrl.
                    |--------------------------------------------------------------------------
                    */
                    'additional_query_string_params' => env('L5_SWAGGER_UI_OAUTH2_ADDITIONAL_QUERY_STRING_PARAMS', []),

                    /*
                    |--------------------------------------------------------------------------
                    | Only activated for the accessCode flow. During the authorization_code request to the tokenUrl,
                    | pass the Client Password using the HTTP Basic Authentication scheme
                    * (Authorization header with Basic base64encoded(client_id + client_secret))
                    |--------------------------------------------------------------------------
                    */
                    'use_basic_authentication_with_access_code_grant' => env('L5_SWAGGER_UI_OAUTH2_USE_BASIC_AUTH_WITH_ACCESS_CODE_GRANT', false),

                    /*
                    |--------------------------------------------------------------------------
                    | Only activated for the implicit flow. During the authorization_code request to the tokenUrl,
                    | pass the Client Password using the HTTP Basic Authentication scheme
                    | (Authorization header with Basic base64encoded(client_id + client_secret))
                    |--------------------------------------------------------------------------
                    */
                    'use_pkce_with_authorization_code_grant' => env('L5_SWAGGER_UI_OAUTH2_USE_PKCE_WITH_AUTHORIZATION_CODE_GRANT', false),
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Constants which can be used in annotations
        |--------------------------------------------------------------------------
        */
        'constants' => [
            'L5_SWAGGER_CONST_HOST' => env('L5_SWAGGER_CONST_HOST', 'https://app.bomeqp.com'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware applied to all routes registered by this package.
    | This middleware will be applied to ALL routes even if they have their own middleware defined.
    |
    | Note: Empty array means no middleware applied.
    |
    */
    'routes' => [
        'api' => [
            /*
            |--------------------------------------------------------------------------
            | Middleware for the documentation UI interface
            |--------------------------------------------------------------------------
            | Empty array means no middleware applied (public access)
            */
            'middleware' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Swagger UI Scan Options
    |--------------------------------------------------------------------------
    |
    | Configuration for what should be scanned and included in the documentation
    |
    */
    'scanOptions' => [
        /*
        |--------------------------------------------------------------------------
        | processors should be an array of processors either \OpenApi\Attributes\OpenApi
        | or a class that implements \OpenApi\Analysis
        |--------------------------------------------------------------------------
        */
        'processors' => [],
        /*
        |--------------------------------------------------------------------------
        | pattern is an optional pattern to match file names in scanned directories
        |--------------------------------------------------------------------------
        */
        'pattern' => null,
        /*
        |--------------------------------------------------------------------------
        | Absolute paths to directories that should be excluded from scanning
        | @note This option will override `paths.excludes`
        |--------------------------------------------------------------------------
        */
        'exclude' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Generate Swagger OpenAPI documentation file
    |--------------------------------------------------------------------------
    |
    | Set to true to generate documentation when running `php artisan l5-swagger:generate`
    | command
    |
    */
    'generate_documentation' => env('L5_SWAGGER_GENERATE_DOCUMENTATION', true),

];
