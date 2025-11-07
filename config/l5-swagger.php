<?php

return [
    'default' => 'default',

    'documentations' => [
        'default' => [
            'api' => [
                'title' => env('L5_SWAGGER_API_TITLE', 'Rexxgo API'),
            ],

            'routes' => [
                'api' => 'api/documentation',
            ],

            'paths' => [
                'docs' => storage_path('api-docs'),
                'docs_json' => 'openapi.json',
                'docs_yaml' => 'openapi.yaml',
                'format_to_use_for_docs' => env('L5_FORMAT', 'json'),
                'annotations' => [
                    base_path('app/Swagger/OpenApi.php'),
                    base_path('Modules/Auth/app/Http/Controllers/AuthController.php'),
                    base_path('Modules/Wallet/app/Http/Controllers/WalletController.php'),
                    base_path('Modules/Profile/app/Http/Controllers/ProfileController.php'),
                    base_path('Modules/Notification/app/Http/Controllers/NotificationController.php'),
                    base_path('Modules/Treasury/app/Http/Controllers/TreasuryController.php'),
                ],
                'base' => env('L5_SWAGGER_BASE_PATH', null),
                'excludes' => [],
            ],

            'generate_always' => false,
            'generate_yaml_copy' => false,
        ],
    ],
];


