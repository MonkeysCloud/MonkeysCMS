<?php

declare(strict_types=1);

namespace App\Cms\Fields\Http;

/**
 * Field API Routes
 *
 * This file defines all API routes for the field widget system.
 * Import this into your application's router configuration.
 *
 * Example usage with a PSR-7 compatible router:
 *
 * ```php
 * $router->group('/api/fields', function($router) {
 *     FieldRoutes::register($router);
 * });
 * ```
 */
final class FieldRoutes
{
    /**
     * Register all field-related routes
     *
     * @param object $router Any router with get/post/put/delete methods
     */
    public static function register(object $router): void
    {
        // Field CRUD
        $router->get('/', [FieldController::class, 'index']);
        $router->post('/', [FieldController::class, 'store']);
        $router->get('/{id}', [FieldController::class, 'show']);
        $router->put('/{id}', [FieldController::class, 'update']);
        $router->delete('/{id}', [FieldController::class, 'destroy']);

        // Field types
        $router->get('/types', [FieldController::class, 'listTypes']);

        // Widget endpoints
        $router->get('/widgets', [FieldController::class, 'listWidgets']);
        $router->get('/widgets/{id}', [FieldController::class, 'showWidget']);
        $router->get('/widgets/for-type/{type}', [FieldController::class, 'widgetsForType']);

        // Rendering endpoints
        $router->post('/{id}/render', [FieldController::class, 'render']);
        $router->post('/{id}/render-display', [FieldController::class, 'renderDisplay']);
        $router->post('/render-form', [FieldController::class, 'renderForm']);

        // Value operations
        $router->post('/{id}/validate', [FieldController::class, 'validate']);
        $router->post('/validate-multiple', [FieldController::class, 'validateMultiple']);
        $router->post('/{id}/prepare', [FieldController::class, 'prepare']);
        $router->post('/{id}/format', [FieldController::class, 'format']);
    }

    /**
     * Get route definitions as an array
     *
     * Useful for frameworks that use array-based route definitions.
     *
     * @return array<array{method: string, path: string, handler: array}>
     */
    public static function getDefinitions(): array
    {
        return [
            // Field CRUD
            ['method' => 'GET', 'path' => '/api/fields', 'handler' => [FieldController::class, 'index']],
            ['method' => 'POST', 'path' => '/api/fields', 'handler' => [FieldController::class, 'store']],
            ['method' => 'GET', 'path' => '/api/fields/{id}', 'handler' => [FieldController::class, 'show']],
            ['method' => 'PUT', 'path' => '/api/fields/{id}', 'handler' => [FieldController::class, 'update']],
            ['method' => 'DELETE', 'path' => '/api/fields/{id}', 'handler' => [FieldController::class, 'destroy']],

            // Field types
            ['method' => 'GET', 'path' => '/api/fields/types', 'handler' => [FieldController::class, 'listTypes']],

            // Widget endpoints
            ['method' => 'GET', 'path' => '/api/fields/widgets', 'handler' => [FieldController::class, 'listWidgets']],
            ['method' => 'GET', 'path' => '/api/fields/widgets/{id}', 'handler' => [FieldController::class, 'showWidget']],
            ['method' => 'GET', 'path' => '/api/fields/widgets/for-type/{type}', 'handler' => [FieldController::class, 'widgetsForType']],

            // Rendering endpoints
            ['method' => 'POST', 'path' => '/api/fields/{id}/render', 'handler' => [FieldController::class, 'render']],
            ['method' => 'POST', 'path' => '/api/fields/{id}/render-display', 'handler' => [FieldController::class, 'renderDisplay']],
            ['method' => 'POST', 'path' => '/api/fields/render-form', 'handler' => [FieldController::class, 'renderForm']],

            // Value operations
            ['method' => 'POST', 'path' => '/api/fields/{id}/validate', 'handler' => [FieldController::class, 'validate']],
            ['method' => 'POST', 'path' => '/api/fields/validate-multiple', 'handler' => [FieldController::class, 'validateMultiple']],
            ['method' => 'POST', 'path' => '/api/fields/{id}/prepare', 'handler' => [FieldController::class, 'prepare']],
            ['method' => 'POST', 'path' => '/api/fields/{id}/format', 'handler' => [FieldController::class, 'format']],
        ];
    }
}
