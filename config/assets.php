<?php

/**
 * MonkeysCMS Asset Configuration
 * 
 * Defines external JS/CSS libraries with their versions.
 * Run `php cms assets:update` to download/update all assets.
 */

return [
    // Target directory for downloaded assets
    'public_path' => 'public/js',
    
    // Libraries to manage
    'libraries' => [
        'htmx' => [
            'version' => '1.9.10',
            'url' => 'https://unpkg.com/htmx.org@{version}/dist/htmx.min.js',
            'filename' => 'htmx.min.js',
            'description' => 'High power tools for HTML',
            'homepage' => 'https://htmx.org',
        ],
        
        'sortable' => [
            'version' => '1.15.2',
            'url' => 'https://cdnjs.cloudflare.com/ajax/libs/Sortable/{version}/Sortable.min.js',
            'filename' => 'sortable.min.js',
            'description' => 'Reorderable drag-and-drop lists',
            'homepage' => 'https://sortablejs.github.io/Sortable/',
        ],
        
        'alpine' => [
            'version' => '3.14.3',
            'url' => 'https://cdn.jsdelivr.net/npm/alpinejs@{version}/dist/cdn.min.js',
            'filename' => 'alpine.min.js',
            'description' => 'Lightweight JavaScript framework',
            'homepage' => 'https://alpinejs.dev',
            'enabled' => false, // Set to true to include
        ],
    ],
    
    // MonkeysCMS own JS version
    'monkeyscms' => [
        'version' => '1.0.0',
        'filename' => 'monkeyscms.js',
    ],
];
