<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Modules\Core\Services\SettingsService;
use App\Modules\Core\Entities\Setting;
use MonkeysLegion\Router\Attributes\Route;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * SettingsController - Admin API for site settings
 */
final class SettingsController
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {
    }

    /**
     * Get all settings grouped
     */
    #[Route('GET', '/admin/settings')]
    public function index(): ResponseInterface
    {
        $grouped = $this->settings->getAllGrouped();

        $result = [];
        foreach ($grouped as $group => $settings) {
            $result[$group] = array_map(fn(Setting $s) => array_merge($s->toArray(), [
                'typed_value' => $s->getTypedValue(),
            ]), $settings);
        }

        return json([
            'settings' => $result,
            'groups' => array_keys($result),
        ]);
    }

    /**
     * Get settings for a specific group
     */
    #[Route('GET', '/admin/settings/group/{group}')]
    public function getGroup(string $group): ResponseInterface
    {
        $settings = $this->settings->getGroup($group);

        return json([
            'group' => $group,
            'settings' => $settings,
        ]);
    }

    /**
     * Get a single setting
     */
    #[Route('GET', '/admin/settings/{key}')]
    public function show(string $key): ResponseInterface
    {
        // Handle dots in key (URL encoded)
        $key = str_replace('_', '.', $key);

        $setting = $this->settings->getSetting($key);

        if (!$setting) {
            return json(['error' => 'Setting not found'], 404);
        }

        return json(array_merge($setting->toArray(), [
            'typed_value' => $setting->getTypedValue(),
        ]));
    }

    /**
     * Update a single setting
     */
    #[Route('PUT', '/admin/settings/{key}')]
    public function update(string $key, ServerRequestInterface $request): ResponseInterface
    {
        $key = str_replace('_', '.', $key);
        $data = json_decode((string) $request->getBody(), true) ?? [];

        if (!isset($data['value'])) {
            return json(['error' => 'Value is required'], 422);
        }

        $setting = $this->settings->getSetting($key);

        if ($setting && $setting->is_system && isset($data['key'])) {
            return json(['error' => 'Cannot change key of system setting'], 400);
        }

        $this->settings->set($key, $data['value'], $data['type'] ?? null);

        return json([
            'success' => true,
            'message' => 'Setting updated',
            'key' => $key,
            'value' => $this->settings->get($key),
        ]);
    }

    /**
     * Create a new setting
     */
    #[Route('POST', '/admin/settings')]
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true) ?? [];

        if (empty($data['key'])) {
            return json(['errors' => ['key' => 'Key is required']], 422);
        }

        if ($this->settings->has($data['key'])) {
            return json(['errors' => ['key' => 'Setting already exists']], 422);
        }

        $setting = new Setting();
        $setting->key = $data['key'];
        $setting->type = $data['type'] ?? 'string';
        $setting->group = $data['group'] ?? 'custom';
        $setting->label = $data['label'] ?? null;
        $setting->description = $data['description'] ?? null;
        $setting->autoload = $data['autoload'] ?? true;
        $setting->setTypedValue($data['value'] ?? null);

        $this->settings->saveSetting($setting);

        return json([
            'success' => true,
            'message' => 'Setting created',
            'setting' => $setting->toArray(),
        ], 201);
    }

    /**
     * Delete a setting
     */
    #[Route('DELETE', '/admin/settings/{key}')]
    public function delete(string $key): ResponseInterface
    {
        $key = str_replace('_', '.', $key);

        $setting = $this->settings->getSetting($key);

        if (!$setting) {
            return json(['error' => 'Setting not found'], 404);
        }

        if ($setting->is_system) {
            return json(['error' => 'Cannot delete system setting'], 400);
        }

        $this->settings->delete($key);

        return json([
            'success' => true,
            'message' => 'Setting deleted',
        ]);
    }

    /**
     * Bulk update settings
     */
    #[Route('PUT', '/admin/settings/bulk')]
    public function bulkUpdate(ServerRequestInterface $request): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true) ?? [];
        $settings = $data['settings'] ?? [];

        $updated = [];
        $errors = [];

        foreach ($settings as $key => $value) {
            try {
                $this->settings->set($key, $value);
                $updated[] = $key;
            } catch (\Exception $e) {
                $errors[$key] = $e->getMessage();
            }
        }

        return json([
            'success' => empty($errors),
            'updated' => $updated,
            'errors' => $errors,
        ]);
    }

    /**
     * Clear settings cache
     */
    #[Route('POST', '/admin/settings/cache/clear')]
    public function clearCache(): ResponseInterface
    {
        $this->settings->clearCache();

        return json([
            'success' => true,
            'message' => 'Settings cache cleared',
        ]);
    }

    /**
     * Reset settings to defaults
     */
    #[Route('POST', '/admin/settings/reset')]
    public function resetDefaults(): ResponseInterface
    {
        $this->settings->seedDefaults();

        return json([
            'success' => true,
            'message' => 'Default settings restored',
        ]);
    }
}
