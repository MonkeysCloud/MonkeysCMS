<?php

/**
 * Example Module
 * 
 * Demonstrates how to create custom field widgets in a module.
 * This module provides:
 * - RatingWidget - Star rating selector
 * - IconPickerWidget - Icon selector with search
 * 
 * @package MonkeysCMS
 * @module Example
 */

return [
    'name' => 'Example',
    'machine_name' => 'example',
    'description' => 'Example module demonstrating custom field widgets',
    'version' => '1.0.0',
    'author' => 'MonkeysCMS',
    'dependencies' => [],
    'enabled' => false, // Disabled by default, enable to use custom widgets
    
    /**
     * Module boot function
     * Called when module is enabled and loaded
     */
    'boot' => function ($container) {
        // Get the widget manager from container
        $widgetManager = $container->get(\App\Cms\Fields\Widgets\FieldWidgetManager::class);
        
        // Register custom widgets
        $widgetManager->register(new \App\Modules\Example\Widgets\RatingWidget());
        $widgetManager->register(new \App\Modules\Example\Widgets\IconPickerWidget());
    },
    
    /**
     * Module install function
     * Called when module is first installed
     */
    'install' => function ($container) {
        // No database tables needed for this module
    },
    
    /**
     * Module uninstall function
     * Called when module is uninstalled
     */
    'uninstall' => function ($container) {
        // Nothing to clean up
    },
    
    /**
     * Permissions provided by this module
     */
    'permissions' => [],
    
    /**
     * Admin menu items
     */
    'menu' => [],
    
    /**
     * Routes defined by this module
     */
    'routes' => [],
];
