<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Cms\Themes\ThemeManager;
use MonkeysLegion\Router\Attributes\Route;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * ThemeController - Admin API for theme management
 */
final class ThemeController
{
    public function __construct(
        private readonly ThemeManager $themeManager,
    ) {
    }

    /**
     * List all available themes
     */
    #[Route('GET', '/api/admin/themes')]
    public function index(): ResponseInterface
    {
        $themes = $this->themeManager->discoverThemes();
        $activeTheme = $this->themeManager->getActiveTheme();

        $list = [];
        foreach ($themes as $name => $info) {
            $data = $info->toArray();
            $data['is_active'] = ($name === $activeTheme->name);
            $list[] = $data;
        }

        return json([
            'themes' => $list,
            'active' => $activeTheme->name,
            'admin_theme' => $this->themeManager->getAdminTheme()->name,
        ]);
    }

    /**
     * Get contrib themes only
     */
    #[Route('GET', '/api/admin/themes/contrib')]
    public function contribThemes(): ResponseInterface
    {
        $themes = $this->themeManager->getContribThemes();

        return json([
            'themes' => array_map(fn($t) => $t->toArray(), $themes),
            'count' => count($themes),
        ]);
    }

    /**
     * Get custom themes only
     */
    #[Route('GET', '/api/admin/themes/custom')]
    public function customThemes(): ResponseInterface
    {
        $themes = $this->themeManager->getCustomThemes();

        return json([
            'themes' => array_map(fn($t) => $t->toArray(), $themes),
            'count' => count($themes),
        ]);
    }

    /**
     * Get theme details
     */
    #[Route('GET', '/api/admin/themes/{theme}')]
    public function show(string $theme): ResponseInterface
    {
        if (!$this->themeManager->themeExists($theme)) {
            return json([
                'error' => "Theme '{$theme}' not found",
            ], 404);
        }

        $themes = $this->themeManager->discoverThemes();
        $info = $themes[$theme] ?? null;

        if ($info === null) {
            return json([
                'error' => "Theme '{$theme}' not found",
            ], 404);
        }

        $activeTheme = $this->themeManager->getActiveTheme();

        $data = $info->toArray();
        $data['is_active'] = ($theme === $activeTheme->name);
        $data['validation'] = $this->themeManager->validateTheme($theme);

        return json($data);
    }

    /**
     * Activate a theme
     */
    #[Route('POST', '/api/admin/themes/{theme}/activate')]
    public function activate(string $theme): ResponseInterface
    {
        if (!$this->themeManager->themeExists($theme)) {
            return json([
                'error' => "Theme '{$theme}' not found",
            ], 404);
        }

        // Validate theme first
        $errors = $this->themeManager->validateTheme($theme);
        if (!empty($errors)) {
            return json([
                'error' => 'Theme validation failed',
                'validation_errors' => $errors,
            ], 400);
        }

        try {
            $this->themeManager->setActiveTheme($theme);

            return json([
                'success' => true,
                'message' => "Theme '{$theme}' activated successfully",
                'theme' => $theme,
            ]);
        } catch (RuntimeException $e) {
            return json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new custom theme
     */
    #[Route('POST', '/api/admin/themes')]
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true) ?? [];

        $name = $data['name'] ?? null;
        $parent = $data['parent'] ?? null;
        $description = $data['description'] ?? '';
        $author = $data['author'] ?? '';

        if (empty($name)) {
            return json([
                'error' => 'Theme name is required',
            ], 400);
        }

        // Validate name (alphanumeric, hyphens, underscores)
        if (!preg_match('/^[a-z][a-z0-9_-]*$/i', $name)) {
            return json([
                'error' => 'Invalid theme name. Use alphanumeric characters, hyphens, and underscores.',
            ], 400);
        }

        // Check if parent theme exists
        if ($parent !== null && !$this->themeManager->themeExists($parent)) {
            return json([
                'error' => "Parent theme '{$parent}' not found",
            ], 400);
        }

        try {
            $info = $this->themeManager->createTheme(
                name: $name,
                source: 'custom',
                parentTheme: $parent,
                metadata: [
                    'description' => $description,
                    'author' => $author,
                ]
            );

            return json([
                'success' => true,
                'message' => "Theme '{$name}' created successfully",
                'theme' => $info->toArray(),
            ], 201);
        } catch (RuntimeException $e) {
            return json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate a theme
     */
    #[Route('GET', '/api/admin/themes/{theme}/validate')]
    public function validate(string $theme): ResponseInterface
    {
        if (!$this->themeManager->themeExists($theme)) {
            return json([
                'error' => "Theme '{$theme}' not found",
            ], 404);
        }

        $errors = $this->themeManager->validateTheme($theme);

        return json([
            'theme' => $theme,
            'valid' => empty($errors),
            'errors' => $errors,
        ]);
    }

    /**
     * Get view paths for active theme
     */
    #[Route('GET', '/api/admin/themes/paths/views')]
    public function viewPaths(): ResponseInterface
    {
        return json([
            'paths' => $this->themeManager->getViewPaths(),
        ]);
    }

    /**
     * Get component paths for active theme
     */
    #[Route('GET', '/api/admin/themes/paths/components')]
    public function componentPaths(): ResponseInterface
    {
        return json([
            'paths' => $this->themeManager->getComponentPaths(),
        ]);
    }

    /**
     * Clear theme cache
     */
    #[Route('POST', '/api/admin/themes/cache/clear')]
    public function clearCache(): ResponseInterface
    {
        $this->themeManager->clearCache();

        return json([
            'success' => true,
            'message' => 'Theme cache cleared',
        ]);
    }
}
