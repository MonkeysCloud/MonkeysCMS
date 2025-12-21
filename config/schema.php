<?php

declare(strict_types=1);

/**
 * Configuration Schema for MonkeysCMS
 * 
 * Used by SchemaValidator to validate configuration at startup.
 */

return [
    'app' => [
        'type' => 'array',
        'required' => true,
        'children' => [
            'name' => ['type' => 'string', 'required' => true],
            'env' => ['type' => 'string', 'required' => true, 'enum' => ['local', 'development', 'staging', 'production']],
            'debug' => ['type' => 'bool', 'required' => true],
            'url' => ['type' => 'string', 'required' => true],
            'timezone' => ['type' => 'string', 'required' => false],
            'locale' => ['type' => 'string', 'required' => false],
        ],
    ],
    
    'cms' => [
        'type' => 'array',
        'required' => true,
        'children' => [
            'modules' => [
                'type' => 'array',
                'required' => true,
                'children' => [
                    'paths' => ['type' => 'array', 'required' => true],
                    'cache_enabled' => ['type' => 'bool', 'required' => false],
                ],
            ],
            'content' => [
                'type' => 'array',
                'required' => false,
                'children' => [
                    'default_status' => ['type' => 'string', 'required' => false],
                    'enable_revisions' => ['type' => 'bool', 'required' => false],
                    'enable_translations' => ['type' => 'bool', 'required' => false],
                    'media_path' => ['type' => 'string', 'required' => false],
                ],
            ],
            'admin' => [
                'type' => 'array',
                'required' => false,
                'children' => [
                    'path' => ['type' => 'string', 'required' => false],
                    'per_page' => ['type' => 'int', 'required' => false, 'min' => 1, 'max' => 100],
                    'max_per_page' => ['type' => 'int', 'required' => false, 'min' => 1, 'max' => 500],
                ],
            ],
        ],
    ],
    
    'themes' => [
        'type' => 'array',
        'required' => true,
        'children' => [
            'active' => ['type' => 'string', 'required' => true],
            'paths' => [
                'type' => 'array',
                'required' => true,
                'children' => [
                    'contrib' => ['type' => 'string', 'required' => true],
                    'custom' => ['type' => 'string', 'required' => true],
                ],
            ],
            'admin_theme' => ['type' => 'string', 'required' => false],
            'cache' => [
                'type' => 'array',
                'required' => false,
                'children' => [
                    'enabled' => ['type' => 'bool', 'required' => false],
                    'path' => ['type' => 'string', 'required' => false],
                ],
            ],
        ],
    ],
    
    'view' => [
        'type' => 'array',
        'required' => true,
        'children' => [
            'extension' => ['type' => 'string', 'required' => false],
            'paths' => ['type' => 'array', 'required' => true],
            'components' => [
                'type' => 'array',
                'required' => false,
                'children' => [
                    'paths' => ['type' => 'array', 'required' => false],
                    'namespace' => ['type' => 'string', 'required' => false],
                ],
            ],
            'cache' => [
                'type' => 'array',
                'required' => false,
                'children' => [
                    'enabled' => ['type' => 'bool', 'required' => false],
                    'path' => ['type' => 'string', 'required' => false],
                ],
            ],
        ],
    ],
    
    'database' => [
        'type' => 'array',
        'required' => true,
        'children' => [
            'default' => ['type' => 'string', 'required' => true],
            'connections' => ['type' => 'array', 'required' => true],
        ],
    ],
    
    'cache' => [
        'type' => 'array',
        'required' => false,
        'children' => [
            'default' => ['type' => 'string', 'required' => false],
            'stores' => ['type' => 'array', 'required' => false],
            'prefix' => ['type' => 'string', 'required' => false],
            'ttl' => ['type' => 'int', 'required' => false],
        ],
    ],
];
