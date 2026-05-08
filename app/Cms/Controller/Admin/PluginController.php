<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Log\ActivityLogger;
use App\Cms\Plugin\HookManager;
use App\Cms\Plugin\PluginManager;
use App\Cms\Plugin\PluginSettingsService;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * PluginController — Admin UI for managing MonkeysCMS plugins.
 *
 * All mutating actions are standard form POST with redirect (no AJAX).
 * Mirrors the Drupal-style "Extend" page.
 */
#[RoutePrefix('/admin/plugins')]
final class PluginController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly PluginManager $pluginManager,
        private readonly PluginSettingsService $settings,
        private readonly ActivityLogger $activity,
    ) {}

    // ── List All Plugins ───────────────────────────────────────────────

    #[Route('GET', '/', name: 'admin::plugins.index')]
    public function index(ServerRequestInterface $request): Response
    {
        $plugins = $this->pluginManager->getAll();

        // Separate by type
        $custom  = array_filter($plugins, fn(array $p) => $p['metadata']->type === 'custom');
        $contrib = array_filter($plugins, fn(array $p) => $p['metadata']->type === 'contrib');

        return Response::html($this->renderer->render('plugins.index', [
            'title'   => 'Extend',
            'custom'  => array_values($custom),
            'contrib' => array_values($contrib),
            'total'   => count($plugins),
            'enabled' => count(array_filter($plugins, fn(array $p) => $p['enabled'])),
            'flash'   => $this->getFlash($request),
        ]));
    }

    // ── Enable Plugin ──────────────────────────────────────────────────

    #[Route('POST', '/enable', name: 'admin::plugins.enable')]
    public function enable(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        $machineName = $body['plugin'] ?? '';

        if ($machineName && $this->pluginManager->get($machineName)) {
            $container = $this->getContainer($request);
            $success = $this->pluginManager->enable($machineName, $container);

            $this->activity->setContext($request);
            $this->activity->log(
                $success ? 'enabled' : 'enable_failed',
                'plugin',
                null,
                $machineName,
            );
        }

        return Response::redirect('/admin/plugins');
    }

    // ── Disable Plugin ─────────────────────────────────────────────────

    #[Route('POST', '/disable', name: 'admin::plugins.disable')]
    public function disable(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        $machineName = $body['plugin'] ?? '';

        if ($machineName && $this->pluginManager->get($machineName)) {
            $container = $this->getContainer($request);
            $this->pluginManager->disable($machineName, $container);

            $this->activity->setContext($request);
            $this->activity->log('disabled', 'plugin', null, $machineName);
        }

        return Response::redirect('/admin/plugins');
    }

    // ── Uninstall Plugin ───────────────────────────────────────────────

    #[Route('POST', '/uninstall', name: 'admin::plugins.uninstall')]
    public function uninstall(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);
        $machineName = $body['plugin'] ?? '';

        if ($machineName && $this->pluginManager->get($machineName)) {
            $container = $this->getContainer($request);
            $this->pluginManager->uninstall($machineName, $container);

            $this->activity->setContext($request);
            $this->activity->log('uninstalled', 'plugin', null, $machineName);
        }

        return Response::redirect('/admin/plugins');
    }

    // ── Plugin Settings ────────────────────────────────────────────────

    #[Route('GET', '/{vendor}/{name}/settings', name: 'admin::plugins.settings')]
    public function settingsForm(ServerRequestInterface $request, string $vendor, string $name): Response
    {
        $machineName = $vendor . '/' . $name;
        $metadata = $this->pluginManager->get($machineName);

        if (!$metadata) {
            return Response::redirect('/admin/plugins');
        }

        $settingsDef = $this->settings->parseSettingsDefinition($metadata->path);
        $currentValues = $this->settings->getAll($machineName);

        return Response::html($this->renderer->render('plugins.settings', [
            'title'        => $metadata->name . ' Settings',
            'plugin'       => $metadata,
            'settings_def' => $settingsDef,
            'values'       => $currentValues,
            'flash'        => $this->getFlash($request),
        ]));
    }

    #[Route('POST', '/{vendor}/{name}/settings', name: 'admin::plugins.settings.save')]
    public function saveSettings(ServerRequestInterface $request, string $vendor, string $name): Response
    {
        $machineName = $vendor . '/' . $name;
        $metadata = $this->pluginManager->get($machineName);

        if (!$metadata) {
            return Response::redirect('/admin/plugins');
        }

        $body = $this->parseBody($request);
        $settingsDef = $this->settings->parseSettingsDefinition($metadata->path);

        // Save each defined setting
        foreach ($settingsDef as $key => $def) {
            $value = $body[$key] ?? null;

            // Handle boolean (checkbox sends value only when checked)
            if (($def['type'] ?? 'string') === 'boolean') {
                $value = $value !== null ? '1' : '0';
            }

            if ($value !== null) {
                $this->settings->set($machineName, $key, (string) $value);
            }
        }

        $this->activity->setContext($request);
        $this->activity->log('settings_updated', 'plugin', null, $machineName);

        return Response::redirect('/admin/plugins/' . $vendor . '/' . $name . '/settings');
    }

    // ── Upload Plugin ──────────────────────────────────────────────────

    #[Route('GET', '/upload', name: 'admin::plugins.upload')]
    public function uploadForm(): Response
    {
        return Response::html($this->renderer->render('plugins.upload', [
            'title' => 'Upload Plugin',
        ]));
    }

    #[Route('POST', '/upload', name: 'admin::plugins.upload.submit')]
    public function upload(ServerRequestInterface $request): Response
    {
        $files = $request->getUploadedFiles();
        $file = $files['plugin_zip'] ?? null;

        if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
            return Response::redirect('/admin/plugins/upload');
        }

        try {
            $installer = new \App\Cms\Plugin\PluginInstaller(base_path());
            $machineName = $installer->installFromUpload($file);

            $this->activity->setContext($request);
            $this->activity->log('uploaded', 'plugin', null, $machineName);

            // Re-discover plugins
            $this->pluginManager->discover();
        } catch (\Throwable $e) {
            error_log('[PluginUpload] ' . $e->getMessage());
        }

        return Response::redirect('/admin/plugins');
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function parseBody(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();
        return is_array($body) ? $body : [];
    }

    private function getFlash(ServerRequestInterface $request): array
    {
        return [];
    }

    private function getContainer(ServerRequestInterface $request): \Psr\Container\ContainerInterface
    {
        // The container is available via the request attribute set by the DI middleware
        $container = $request->getAttribute('container');
        if ($container instanceof \Psr\Container\ContainerInterface) {
            return $container;
        }

        // Fallback: create a minimal wrapper
        return new class implements \Psr\Container\ContainerInterface {
            public function get(string $id): mixed { return null; }
            public function has(string $id): bool { return false; }
        };
    }
}
