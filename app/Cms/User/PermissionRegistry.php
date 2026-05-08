<?php

declare(strict_types=1);

namespace App\Cms\User;

/**
 * PermissionRegistry — Single source of truth for ALL CMS permissions.
 *
 * Core permissions are defined here. Modules and plugins can register
 * additional permissions at boot time via `PermissionRegistry::register()`.
 *
 * This is used by:
 *   - AdminMenuRegistry → filters sidebar items by role permissions
 *   - RoleController → presents grouped checkboxes for role assignment
 *   - AdminPermissions → typed constants delegate here for all() and keys()
 *   - ContentAccessService → content-level access checks
 *   - Middleware/Controllers → route-level access checks
 *
 * Plugin usage:
 *   // In your plugin's boot() method:
 *   PermissionRegistry::register('Ecommerce', [
 *       'ecommerce.view_orders'   => 'View orders',
 *       'ecommerce.manage_orders' => 'Manage orders',
 *       'ecommerce.refund'        => 'Process refunds',
 *   ]);
 */
final class PermissionRegistry
{
    /** @var array<string, array<string, string>> Additional module permissions */
    private static array $custom = [];

    // ── Core Permissions ────────────────────────────────────────────────

    /**
     * Built-in core permissions grouped by module.
     *
     * @return array<string, array<string, string>> group => [permission => label]
     */
    private static function core(): array
    {
        return [
            'Dashboard' => [
                'dashboard.view' => 'Access admin dashboard',
            ],
            'Content' => [
                'content.view'     => 'View content list',
                'content.create'   => 'Create content',
                'content.edit'     => 'Edit any content',
                'content.edit_own' => 'Edit own content only',
                'content.publish'  => 'Publish / unpublish content',
                'content.delete'   => 'Delete content',
            ],
            'Workflow' => [
                'workflow.view'   => 'View workflow',
                'workflow.manage' => 'Manage workflow states',
            ],
            'Media' => [
                'media.view'   => 'View media library',
                'media.upload' => 'Upload media files',
                'media.delete' => 'Delete media files',
            ],
            'Comments' => [
                'comments.view'     => 'View comments',
                'comments.moderate' => 'Moderate & delete comments',
            ],
            'Webforms' => [
                'webforms.view'   => 'View webform submissions',
                'webforms.manage' => 'Create & manage webforms',
            ],
            'Taxonomy' => [
                'taxonomy.view'   => 'View vocabularies & terms',
                'taxonomy.manage' => 'Create, edit & delete vocabularies',
            ],
            'Blocks' => [
                'blocks.view'   => 'View block library',
                'blocks.manage' => 'Create, edit & delete blocks',
            ],
            'Menus' => [
                'menus.manage' => 'Create & manage navigation menus',
            ],
            'Content Types' => [
                'content_types.manage' => 'Create, edit & delete content types',
            ],
            'URL & Navigation' => [
                'url_aliases.manage'  => 'Manage URL aliases',
                'breadcrumbs.manage'  => 'Manage breadcrumbs',
                'redirects.manage'    => 'Manage URL redirects',
                'search.manage'       => 'Configure search settings',
            ],
            'Internationalization' => [
                'languages.manage' => 'Manage languages & translations',
            ],
            'Users & Access' => [
                'users.view'    => 'View user list',
                'users.manage'  => 'Create, edit & delete users',
                'roles.manage'  => 'Manage roles & permissions',
                'access.manage' => 'Manage content access rules',
                '2fa.setup'     => 'Configure two-factor authentication',
            ],
            'Appearance' => [
                'appearance.manage' => 'Manage themes & appearance',
            ],
            'Site Configuration' => [
                'settings.manage' => 'Manage site settings',
                'cache.manage'    => 'Manage & clear cache',
            ],
            'Developer Tools' => [
                'activity.view'   => 'View activity log',
                'webhooks.manage' => 'Manage webhooks',
                'ai.access'       => 'Access AI assistant',
                'cron.manage'     => 'Manage scheduled cron jobs',
                'plugins.manage'  => 'Install, enable & configure plugins',
            ],
        ];
    }

    // ── Extension API ───────────────────────────────────────────────────

    /**
     * Register additional permissions for a custom module or plugin.
     *
     * Call this from your plugin's boot() method:
     *
     *   PermissionRegistry::register('Ecommerce', [
     *       'ecommerce.view_orders'   => 'View orders',
     *       'ecommerce.manage_orders' => 'Manage orders',
     *       'ecommerce.refund'        => 'Process refunds',
     *   ]);
     *
     * @param string                $group Human-readable group name
     * @param array<string, string> $permissions key => label
     */
    public static function register(string $group, array $permissions): void
    {
        if (isset(self::$custom[$group])) {
            self::$custom[$group] = array_merge(self::$custom[$group], $permissions);
        } else {
            self::$custom[$group] = $permissions;
        }
    }

    /**
     * Unregister a specific permission.
     */
    public static function unregister(string $permissionKey): void
    {
        foreach (self::$custom as $group => &$perms) {
            unset($perms[$permissionKey]);
            if (empty($perms)) {
                unset(self::$custom[$group]);
            }
        }
    }

    // ── Query API ───────────────────────────────────────────────────────

    /**
     * All permissions: core + registered modules, merged.
     *
     * @return array<string, array<string, string>> group => [permission => label]
     */
    public static function all(): array
    {
        $merged = self::core();

        foreach (self::$custom as $group => $perms) {
            if (isset($merged[$group])) {
                $merged[$group] = array_merge($merged[$group], $perms);
            } else {
                $merged[$group] = $perms;
            }
        }

        return $merged;
    }

    /**
     * Flat list of all permission keys.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        $keys = [];
        foreach (self::all() as $perms) {
            foreach ($perms as $key => $_label) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    /**
     * Check if a permission set (JSON array) grants a specific permission.
     * Supports wildcards: '*' grants everything, 'content.*' grants all content.*.
     */
    public static function check(array $granted, string $permission): bool
    {
        // Wildcard: full admin
        if (in_array('*', $granted, true) || in_array('admin.*', $granted, true)) {
            return true;
        }

        // Direct match
        if (in_array($permission, $granted, true)) {
            return true;
        }

        // Namespace wildcard: 'content.*' matches 'content.create'
        foreach ($granted as $p) {
            if (str_ends_with($p, '.*')) {
                $prefix = substr($p, 0, -1); // 'content.'
                if (str_starts_with($permission, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get permission label for a key, or null if not found.
     */
    public static function label(string $permissionKey): ?string
    {
        foreach (self::all() as $perms) {
            if (isset($perms[$permissionKey])) {
                return $perms[$permissionKey];
            }
        }
        return null;
    }

    /**
     * Get the group name for a permission key, or null if not found.
     */
    public static function group(string $permissionKey): ?string
    {
        foreach (self::all() as $group => $perms) {
            if (isset($perms[$permissionKey])) {
                return $group;
            }
        }
        return null;
    }

    /**
     * Reset custom registrations (useful for testing).
     */
    public static function reset(): void
    {
        self::$custom = [];
    }
}
