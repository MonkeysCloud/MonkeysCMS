<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Cms\Themes\ThemeManager;
use MonkeysLegion\Http\Attribute\Route;
use MonkeysLegion\Http\Request;
use MonkeysLegion\Http\Response;
use MonkeysLegion\Http\JsonResponse;
use RuntimeException;

/**
 * ThemeController - Admin API for theme management
 */
final class ThemeController
{
    public function __construct(
        private readonly ThemeManager $themeManager,
    ) {}
    
    /**
     * List all available themes
     */
    #[Route('GET', '/admin/themes')]
    public function index(): JsonResponse
    {
        $themes = $this->themeManager->discoverThemes();
        $activeTheme = $this->themeManager->getActiveTheme();
        
        $list = [];
        foreach ($themes as $name => $info) {
            $data = $info->toArray();
            $data['is_active'] = ($name === $activeTheme->name);
            $list[] = $data;
        }
        
        return new JsonResponse([
            'themes' => $list,
            'active' => $activeTheme->name,
            'admin_theme' => $this->themeManager->getAdminTheme()->name,
        ]);
    }
    
    /**
     * Get contrib themes only
     */
    #[Route('GET', '/admin/themes/contrib')]
    public function contribThemes(): JsonResponse
    {
        $themes = $this->themeManager->getContribThemes();
        
        return new JsonResponse([
            'themes' => array_map(fn($t) => $t->toArray(), $themes),
            'count' => count($themes),
        ]);
    }
    
    /**
     * Get custom themes only
     */
    #[Route('GET', '/admin/themes/custom')]
    public function customThemes(): JsonResponse
    {
        $themes = $this->themeManager->getCustomThemes();
        
        return new JsonResponse([
            'themes' => array_map(fn($t) => $t->toArray(), $themes),
            'count' => count($themes),
        ]);
    }
    
    /**
     * Get theme details
     */
    #[Route('GET', '/admin/themes/{theme}')]
    public function show(string $theme): JsonResponse
    {
        if (!$this->themeManager->themeExists($theme)) {
            return new JsonResponse([
                'error' => "Theme '{$theme}' not found",
            ], 404);
        }
        
        $themes = $this->themeManager->discoverThemes();
        $info = $themes[$theme] ?? null;
        
        if ($info === null) {
            return new JsonResponse([
                'error' => "Theme '{$theme}' not found",
            ], 404);
        }
        
        $activeTheme = $this->themeManager->getActiveTheme();
        
        $data = $info->toArray();
        $data['is_active'] = ($theme === $activeTheme->name);
        $data['validation'] = $this->themeManager->validateTheme($theme);
        
        return new JsonResponse($data);
    }
    
    /**
     * Activate a theme
     */
    #[Route('POST', '/admin/themes/{theme}/activate')]
    public function activate(string $theme): JsonResponse
    {
        if (!$this->themeManager->themeExists($theme)) {
            return new JsonResponse([
                'error' => "Theme '{$theme}' not found",
            ], 404);
        }
        
        // Validate theme first
        $errors = $this->themeManager->validateTheme($theme);
        if (!empty($errors)) {
            return new JsonResponse([
                'error' => 'Theme validation failed',
                'validation_errors' => $errors,
            ], 400);
        }
        
        try {
            $this->themeManager->setActiveTheme($theme);
            
            return new JsonResponse([
                'success' => true,
                'message' => "Theme '{$theme}' activated successfully",
                'theme' => $theme,
            ]);
        } catch (RuntimeException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Create a new custom theme
     */
    #[Route('POST', '/admin/themes')]
    public function create(Request $request): JsonResponse
    {
        $data = $request->getParsedBody();
        
        $name = $data['name'] ?? null;
        $parent = $data['parent'] ?? null;
        $description = $data['description'] ?? '';
        $author = $data['author'] ?? '';
        
        if (empty($name)) {
            return new JsonResponse([
                'error' => 'Theme name is required',
            ], 400);
        }
        
        // Validate name (alphanumeric, hyphens, underscores)
        if (!preg_match('/^[a-z][a-z0-9_-]*$/i', $name)) {
            return new JsonResponse([
                'error' => 'Invalid theme name. Use alphanumeric characters, hyphens, and underscores.',
            ], 400);
        }
        
        // Check if parent theme exists
        if ($parent !== null && !$this->themeManager->themeExists($parent)) {
            return new JsonResponse([
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
            
            return new JsonResponse([
                'success' => true,
                'message' => "Theme '{$name}' created successfully",
                'theme' => $info->toArray(),
            ], 201);
        } catch (RuntimeException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Validate a theme
     */
    #[Route('GET', '/admin/themes/{theme}/validate')]
    public function validate(string $theme): JsonResponse
    {
        if (!$this->themeManager->themeExists($theme)) {
            return new JsonResponse([
                'error' => "Theme '{$theme}' not found",
            ], 404);
        }
        
        $errors = $this->themeManager->validateTheme($theme);
        
        return new JsonResponse([
            'theme' => $theme,
            'valid' => empty($errors),
            'errors' => $errors,
        ]);
    }
    
    /**
     * Get view paths for active theme
     */
    #[Route('GET', '/admin/themes/paths/views')]
    public function viewPaths(): JsonResponse
    {
        return new JsonResponse([
            'paths' => $this->themeManager->getViewPaths(),
        ]);
    }
    
    /**
     * Get component paths for active theme
     */
    #[Route('GET', '/admin/themes/paths/components')]
    public function componentPaths(): JsonResponse
    {
        return new JsonResponse([
            'paths' => $this->themeManager->getComponentPaths(),
        ]);
    }
    
    /**
     * Clear theme cache
     */
    #[Route('POST', '/admin/themes/cache/clear')]
    public function clearCache(): JsonResponse
    {
        $this->themeManager->clearCache();
        
        return new JsonResponse([
            'success' => true,
            'message' => 'Theme cache cleared',
        ]);
    }
}
