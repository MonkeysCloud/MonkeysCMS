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
    
    // Toggle between CDN (true) and Local (false)
    'use_cdn' => env('ASSETS_USE_CDN', true),
    
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
            'enabled' => true, 
        ],

        'ckeditor' => [
            'version' => '41.2.0',
            'url' => 'https://cdn.ckeditor.com/ckeditor5/{version}/classic/ckeditor.js',
            'filename' => 'ckeditor.js',
            'description' => 'WYSIWYG Editor',
            'homepage' => 'https://ckeditor.com',
        ],

        'tailwind' => [
            'version' => '3.4.5',
            'url' => 'https://cdn.tailwindcss.com/{version}',
            'filename' => 'tailwind.js',
            'description' => 'Utility-first CSS framework',
            'homepage' => 'https://tailwindcss.com',
        ],

        // MonkeysCMS own JS version
        'monkeyscms' => [
            'version' => '1.0.0',
            'filename' => 'monkeyscms.js',
            'dependencies' => ['htmx'], // HTMX is required core
        ],
    ],
];
