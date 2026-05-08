<?php
declare(strict_types=1);

/**
 * MonkeysLegion v2 — Global Helper Functions.
 *
 * Loaded via composer autoload.files for use in templates and app code.
 */

use MonkeysLegion\I18n\Translator;

use Psr\Http\Message\ServerRequestInterface;

// ── Path Helpers ──────────────────────────────────────────────

if (!function_exists('base_path')) {
    /**
     * Get the application base path.
     */
    function base_path(string $path = ''): string
    {
        $base = defined('ML_BASE_PATH') ? ML_BASE_PATH : getcwd();

        return $path !== '' ? rtrim($base, '/') . '/' . ltrim($path, '/') : $base;
    }
}

if (!function_exists('app_path')) {
    /**
     * Get the app/ directory path.
     */
    function app_path(string $path = ''): string
    {
        return base_path('app' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
    }
}

if (!function_exists('config_path')) {
    /**
     * Get the config/ directory path.
     */
    function config_path(string $path = ''): string
    {
        return base_path('config' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get the storage/ directory path.
     */
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
    }
}

// ── Asset Helpers ──────────────────────────────────────────────

if (!function_exists('asset')) {
    /**
     * Generate a versioned asset URL.
     *
     * Reads public/assets/manifest.json for cache-busted filenames.
     * Falls back to appending ?v=filemtime.
     */
    function asset(string $path): string
    {
        static $manifest = null;

        if ($manifest === null) {
            $manifestPath = base_path('public/assets/manifest.json');
            if (is_file($manifestPath)) {
                $content = file_get_contents($manifestPath);
                $manifest = $content !== false ? (json_decode($content, true) ?: []) : [];
            } else {
                $manifest = [];
            }
        }

        $file = $manifest[$path] ?? ltrim($path, '/');
        $url = '/assets/' . $file;

        if (!isset($manifest[$path])) {
            $physical = base_path('public/assets/' . $file);
            if (is_file($physical)) {
                $url .= '?v=' . filemtime($physical);
            }
        }

        return $url;
    }
}

if (!function_exists('vite_asset')) {
    /**
     * Resolve a Vite entry-point to its built URL.
     *
     * Reads public/build/.vite/manifest.json and returns the hashed
     * file path. Falls back to serving the raw source path if no
     * build exists (dev mode).
     *
     * Usage in templates:
     *   <link rel="stylesheet" href="{{ vite_asset('themes/core/admin/css/admin.css') }}">
     */
    function vite_asset(string $entry): string
    {
        static $manifest = null;

        if ($manifest === null) {
            $manifestPath = base_path('public/build/.vite/manifest.json');
            if (is_file($manifestPath)) {
                $content = file_get_contents($manifestPath);
                $manifest = $content !== false ? (json_decode($content, true) ?: []) : [];
            } else {
                $manifest = [];
            }
        }

        if (isset($manifest[$entry]['file'])) {
            return '/build/' . $manifest[$entry]['file'];
        }

        // Fallback: serve the raw source path
        return '/' . ltrim($entry, '/');
    }
}

// ── Translation Helpers ────────────────────────────────────────

if (!function_exists('trans')) {
    /**
     * @param array<string, string> $replace
     */
    function trans(string $key, array $replace = []): string
    {
        /** @var Translator $t */
        $t = \MonkeysLegion\DI\Container::instance()->get(Translator::class);

        return $t->trans($key, $replace);
    }
}

// ── CSRF Helpers ───────────────────────────────────────────────

if (! function_exists('csrf_token')) {
    function csrf_token(): string
    {
        /** @var \MonkeysLegion\Session\Contracts\SessionInterface|null $session */
        $session = \MonkeysLegion\DI\Container::instance()->get(SessionManager::class);

        return $session !== null ? $session->token() : '';
    }
}

if (! function_exists('csrf_field')) {
    function csrf_field(): string
    {
        $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_csrf" value="' . $token . '" />';
    }
}

// ── Auth Helpers ───────────────────────────────────────────────

if (!function_exists('auth_user_id')) {
    function auth_user_id(): ?int
    {
        /** @var ServerRequestInterface $req */
        $req = \MonkeysLegion\DI\Container::instance()->get(ServerRequestInterface::class);

        return $req->getAttribute('userId');
    }
}

if (!function_exists('auth_check')) {
    function auth_check(): bool
    {
        return auth_user_id() !== null;
    }
}

// ── Media Helpers ──────────────────────────────────────────────

if (!function_exists('cms_media_url')) {
    /**
     * Generate a URL for a media item with a specific image style.
     *
     * Available styles (default): 'thumb' (150×150), 'medium' (600×600), 'large' (1200×1200)
     * Custom modules can register additional styles via MediaStyleRegistry.
     *
     * @param int|null    $mediaId  Media entity ID (returns '' if null/0)
     * @param string      $style    Image style name: 'thumb', 'medium', 'large', or 'original'
     * @return string     Public URL for the styled image
     *
     * Usage in .ml.php templates:
     *   <img src="{{ cms_media_url($id, 'medium') }}" alt="">
     */
    function cms_media_url(?int $mediaId, string $style = 'medium'): string
    {
        if (!$mediaId) {
            return '';
        }

        // Use the API route which serves files directly with proper
        // style resolution, on-the-fly generation, and caching headers.
        // Route: /api/cms/media/{id}/{style}
        // Available styles: 'thumb' (150×150), 'medium' (600×600), 'large' (1200×1200)
        // Use 'file' or 'original' for the unprocessed original.
        return '/api/cms/media/' . $mediaId . '/' . $style;
    }
}

if (!function_exists('cms_image')) {
    /**
     * Render a complete <img> tag for a media item with responsive srcset.
     *
     * Automatically generates srcset with available image styles for responsive loading.
     *
     * @param int|null    $mediaId   Media entity ID
     * @param string      $style     Primary image style (used as src)
     * @param string      $alt       Alt text
     * @param string      $class     CSS class(es)
     * @param string      $loading   Loading strategy: 'lazy' or 'eager'
     * @param array       $attrs     Extra HTML attributes ['data-x' => 'val']
     * @return string     Complete <img> HTML tag (or '' if no media)
     *
     * Usage in .ml.php templates:
     *   {!! cms_image($mediaId, 'medium', $title, 'block-card__img', 'lazy') !!}
     */
    function cms_image(
        ?int $mediaId,
        string $style = 'medium',
        string $alt = '',
        string $class = '',
        string $loading = 'lazy',
        array $attrs = [],
    ): string {
        if (!$mediaId) {
            return '';
        }

        $src = cms_media_url($mediaId, $style);
        $escapedAlt = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');

        // Build srcset from available styles
        // Try the registry first; fall back to built-in defaults
        $styleDefs = ['thumb' => 150, 'medium' => 600, 'large' => 1200];
        try {
            $container = \MonkeysLegion\DI\Container::instance();
            /** @var \App\Cms\Media\MediaModule $media */
            $media = $container->get(\App\Cms\Media\MediaModule::class);
            $registered = $media->getStyleRegistry()->getDefinitions();
            if (!empty($registered)) {
                $styleDefs = [];
                foreach ($registered as $name => $def) {
                    $styleDefs[$name] = (int) $def['width'];
                }
            }
        } catch (\Throwable) {
            // Use defaults
        }

        $srcsetParts = [];
        foreach ($styleDefs as $styleName => $width) {
            $srcsetParts[] = cms_media_url($mediaId, $styleName) . ' ' . $width . 'w';
        }

        $srcset = count($srcsetParts) > 1
            ? ' srcset="' . implode(', ', $srcsetParts) . '"'
            : '';

        $html = '<img src="' . $src . '"'
            . $srcset
            . ' alt="' . $escapedAlt . '"'
            . ($class ? ' class="' . htmlspecialchars($class, ENT_QUOTES) . '"' : '')
            . ' loading="' . $loading . '"';

        foreach ($attrs as $k => $v) {
            $html .= ' ' . htmlspecialchars($k) . '="' . htmlspecialchars((string) $v, ENT_QUOTES) . '"';
        }

        $html .= '>';
        return $html;
    }
}
