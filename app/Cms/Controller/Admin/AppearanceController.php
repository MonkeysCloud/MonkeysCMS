<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Log\ActivityLogger;
use App\Cms\Service\SettingsService;
use App\Cms\Theme\ThemeManager;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Session\SessionManager;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * AppearanceController — Theme management, site identity, and theme editor.
 *
 * Routes:
 *   GET  /admin/appearance                — Theme gallery
 *   POST /admin/appearance/activate       — Activate a theme (JSON)
 *   POST /admin/appearance/delete         — Delete a theme (JSON)
 *   GET  /admin/appearance/site-identity  — Site identity settings
 *   POST /admin/appearance/site-identity  — Save site identity
 *   GET  /admin/appearance/editor         — Theme file editor
 *   POST /admin/appearance/editor/save    — Save edited file (JSON)
 */
#[RoutePrefix('/admin/appearance')]
final class AppearanceController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly ThemeManager $themeManager,
        private readonly SettingsService $settings,
        private readonly SessionManager $session,
        private readonly ActivityLogger $activity,
    ) {}

    // ── Theme Gallery ───────────────────────────────────────────────────

    #[Route('GET', '/', name: 'admin::appearance.index')]
    public function index(): Response
    {
        $allThemes = $this->themeManager->getAllThemes();

        // Build theme data with screenshots
        $frontendThemes = [];
        $adminThemes = [];

        foreach ($allThemes as $theme) {
            $data = $theme->toArray();
            $data['screenshot'] = $this->themeManager->getScreenshotUrl($theme);
            $data['is_active'] = false;
            $data['can_delete'] = $theme->tier !== 'core';
            $data['parent_label'] = null;

            if ($theme->parent && isset($allThemes[$theme->parent])) {
                $data['parent_label'] = $allThemes[$theme->parent]->label;
            }

            // Determine if this theme is active
            $activeTheme = $theme->type === 'admin'
                ? $this->themeManager->getAdminTheme()
                : $this->themeManager->getActiveTheme();
            if ($activeTheme && $activeTheme->name === $theme->name) {
                $data['is_active'] = true;
            }

            if ($theme->type === 'admin') {
                $adminThemes[] = $data;
            } else {
                $frontendThemes[] = $data;
            }
        }

        return Response::html($this->renderer->render('admin::appearance.index', [
            'title'           => 'Appearance',
            'frontendThemes'  => $frontendThemes,
            'adminThemes'     => $adminThemes,
            'flashSuccess'    => $this->session->getFlash('appearance_success'),
            'flashError'      => $this->session->getFlash('appearance_error'),
        ]));
    }

    // ── Activate Theme ──────────────────────────────────────────────────

    #[Route('POST', '/activate', name: 'admin::appearance.activate')]
    public function activate(ServerRequestInterface $request): Response
    {
        $raw = (string) $request->getBody();
        $body = json_decode($raw, true) ?? $request->getParsedBody() ?? [];
        $themeName = $body['theme'] ?? '';
        $themeType = $body['type'] ?? 'frontend';

        $theme = $this->themeManager->getTheme($themeName);
        if (!$theme) {
            return Response::json(['error' => "Theme '{$themeName}' not found."], 404);
        }

        $settingKey = $themeType === 'admin' ? 'active_admin_theme' : 'active_frontend_theme';
        $this->settings->set($settingKey, $themeName, 'appearance');

        $this->activity->setContext($request);
        $this->activity->log('activated', 'theme', null, $theme->label, [
            'theme' => $themeName,
            'type'  => $themeType,
        ]);

        return Response::json([
            'message' => "{$theme->label} activated as {$themeType} theme.",
            'theme'   => $themeName,
        ]);
    }

    // ── Delete Theme ────────────────────────────────────────────────────

    #[Route('POST', '/delete', name: 'admin::appearance.delete')]
    public function delete(ServerRequestInterface $request): Response
    {
        $raw = (string) $request->getBody();
        $body = json_decode($raw, true) ?? $request->getParsedBody() ?? [];
        $themeName = $body['theme'] ?? '';

        try {
            $theme = $this->themeManager->getTheme($themeName);
            $label = $theme?->label ?? $themeName;

            $this->themeManager->deleteTheme($themeName);

            $this->activity->setContext($request);
            $this->activity->log('deleted', 'theme', null, $label, [
                'theme' => $themeName,
            ]);

            return Response::json([
                'message' => "Theme '{$label}' has been deleted.",
            ]);
        } catch (\RuntimeException $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }
    }

    // ── Site Identity ───────────────────────────────────────────────────

    #[Route('GET', '/site-identity', name: 'admin::appearance.site_identity')]
    public function siteIdentity(): Response
    {
        return Response::html($this->renderer->render('admin::appearance.site-identity', [
            'title'        => 'Site Identity',
            'settings'     => $this->settings->all(),
            'flashSuccess' => $this->session->getFlash('appearance_success'),
            'flashError'   => $this->session->getFlash('appearance_error'),
        ]));
    }

    #[Route('POST', '/site-identity', name: 'admin::appearance.site_identity.save')]
    public function saveSiteIdentity(ServerRequestInterface $request): Response
    {
        $body = (array) $request->getParsedBody();

        $keys = ['site_name', 'site_tagline', 'site_logo', 'site_favicon'];
        foreach ($keys as $key) {
            if (array_key_exists($key, $body)) {
                $group = in_array($key, ['site_name', 'site_tagline']) ? 'general' : 'appearance';
                $this->settings->set($key, trim($body[$key] ?? ''), $group);
            }
        }

        $this->activity->setContext($request);
        $this->activity->log('updated', 'setting', null, 'Site Identity', [
            'changed_keys' => array_keys($body),
        ]);

        $this->session->flash('appearance_success', 'Site identity saved successfully.');
        return Response::redirect('/admin/appearance/site-identity');
    }

    // ── Theme Editor ────────────────────────────────────────────────────

    #[Route('GET', '/editor', name: 'admin::appearance.editor')]
    public function editor(ServerRequestInterface $request): Response
    {
        $params = $request->getQueryParams();
        $themeName = $params['theme'] ?? null;

        $allThemes = $this->themeManager->getAllThemes();

        // Default to active frontend theme
        if (!$themeName || !isset($allThemes[$themeName])) {
            $active = $this->themeManager->getActiveTheme();
            $themeName = $active?->name ?? 'front';
        }

        $theme = $allThemes[$themeName] ?? null;
        $files = [];
        $selectedFile = $params['file'] ?? null;
        $fileContent = '';

        if ($theme) {
            $files = $this->getEditableFiles($theme->basePath);

            // Load selected file content
            if ($selectedFile) {
                $fullPath = $theme->basePath . '/' . $selectedFile;
                if (file_exists($fullPath) && $this->isEditableFile($fullPath)) {
                    $fileContent = file_get_contents($fullPath);
                }
            }
        }

        return Response::html($this->renderer->render('admin::appearance.editor', [
            'title'        => 'Theme Editor',
            'themes'       => $allThemes,
            'currentTheme' => $themeName,
            'theme'        => $theme,
            'files'        => $files,
            'selectedFile' => $selectedFile,
            'fileContent'  => $fileContent,
        ]));
    }

    #[Route('POST', '/editor/save', name: 'admin::appearance.editor.save')]
    public function saveFile(ServerRequestInterface $request): Response
    {
        $raw = (string) $request->getBody();
        $body = json_decode($raw, true) ?? $request->getParsedBody() ?? [];
        $themeName = $body['theme'] ?? '';
        $filePath = $body['file'] ?? '';
        $content = $body['content'] ?? '';

        $theme = $this->themeManager->getTheme($themeName);
        if (!$theme) {
            return Response::json(['error' => 'Theme not found.'], 404);
        }

        // Security: prevent path traversal
        $realBase = realpath($theme->basePath);
        $fullPath = $theme->basePath . '/' . $filePath;
        $realFile = realpath(dirname($fullPath)) . '/' . basename($fullPath);

        if (!$realBase || !str_starts_with($realFile, $realBase)) {
            return Response::json(['error' => 'Invalid file path.'], 422);
        }

        if (!$this->isEditableFile($fullPath)) {
            return Response::json(['error' => 'File type not editable.'], 422);
        }

        // Core themes: block writes
        if ($theme->tier === 'core') {
            return Response::json(['error' => 'Cannot edit core theme files. Override them in a child theme.'], 422);
        }

        file_put_contents($fullPath, $content);

        $this->activity->setContext($request);
        $this->activity->log('edited', 'theme_file', null, "{$themeName}/{$filePath}", [
            'theme' => $themeName,
            'file'  => $filePath,
        ]);

        return Response::json([
            'message' => 'File saved successfully.',
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Get all editable files in a theme directory (recursively).
     *
     * @return list<array{path: string, name: string, type: string, size: int}>
     */
    private function getEditableFiles(string $basePath): array
    {
        $files = [];
        $editableExtensions = ['css', 'js', 'mlc', 'php', 'html', 'json'];

        if (!is_dir($basePath)) return $files;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $ext = strtolower($item->getExtension());
                if (in_array($ext, $editableExtensions, true)) {
                    $relativePath = str_replace($basePath . '/', '', $item->getPathname());
                    $files[] = [
                        'path' => $relativePath,
                        'name' => $item->getFilename(),
                        'type' => $ext,
                        'size' => $item->getSize(),
                    ];
                }
            }
        }

        // Sort by path
        usort($files, fn($a, $b) => strcmp($a['path'], $b['path']));

        return $files;
    }

    private function isEditableFile(string $path): bool
    {
        $editableExtensions = ['css', 'js', 'mlc', 'php', 'html', 'json'];
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, $editableExtensions, true);
    }
}
