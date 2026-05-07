<?php

declare(strict_types=1);

namespace App\Cms\Admin;

use App\Cms\User\PermissionRegistry;

/**
 * AdminPermissions — Type-safe constants for admin permission strings.
 *
 * These constants reference the SAME keys defined in PermissionRegistry.
 * Always use these constants in code (menu items, middleware, controllers)
 * instead of raw strings to get IDE autocompletion and refactoring support.
 *
 * The PermissionRegistry is the single source of truth for labels and grouping.
 * AdminPermissions provides the typed constant API that code references.
 *
 * Plugin authors: register custom permissions via PermissionRegistry::register()
 * and optionally define constants in their own class:
 *
 *   PermissionRegistry::register('Ecommerce', [
 *       'ecommerce.view_orders' => 'View orders',
 *   ]);
 */
final class AdminPermissions
{
    // ── Dashboard ──────────────────────────────────────────────────────
    public const string DASHBOARD_VIEW = 'dashboard.view';

    // ── Content ────────────────────────────────────────────────────────
    public const string CONTENT_VIEW    = 'content.view';
    public const string CONTENT_CREATE  = 'content.create';
    public const string CONTENT_EDIT    = 'content.edit';
    public const string CONTENT_EDIT_OWN = 'content.edit_own';
    public const string CONTENT_PUBLISH = 'content.publish';
    public const string CONTENT_DELETE  = 'content.delete';

    // ── Workflow ───────────────────────────────────────────────────────
    public const string WORKFLOW_VIEW   = 'workflow.view';
    public const string WORKFLOW_MANAGE = 'workflow.manage';

    // ── Media ──────────────────────────────────────────────────────────
    public const string MEDIA_VIEW   = 'media.view';
    public const string MEDIA_UPLOAD = 'media.upload';
    public const string MEDIA_DELETE = 'media.delete';

    // ── Comments ───────────────────────────────────────────────────────
    public const string COMMENTS_VIEW     = 'comments.view';
    public const string COMMENTS_MODERATE = 'comments.moderate';

    // ── Webforms ───────────────────────────────────────────────────────
    public const string WEBFORMS_VIEW   = 'webforms.view';
    public const string WEBFORMS_MANAGE = 'webforms.manage';

    // ── Taxonomy ───────────────────────────────────────────────────────
    public const string TAXONOMY_VIEW   = 'taxonomy.view';
    public const string TAXONOMY_MANAGE = 'taxonomy.manage';

    // ── Blocks ─────────────────────────────────────────────────────────
    public const string BLOCKS_VIEW   = 'blocks.view';
    public const string BLOCKS_MANAGE = 'blocks.manage';

    // ── Menus ──────────────────────────────────────────────────────────
    public const string MENUS_MANAGE = 'menus.manage';

    // ── Structure ──────────────────────────────────────────────────────
    public const string CONTENT_TYPES_MANAGE = 'content_types.manage';
    public const string URL_ALIASES_MANAGE   = 'url_aliases.manage';
    public const string BREADCRUMBS_MANAGE   = 'breadcrumbs.manage';
    public const string REDIRECTS_MANAGE     = 'redirects.manage';
    public const string SEARCH_MANAGE        = 'search.manage';
    public const string LANGUAGES_MANAGE     = 'languages.manage';

    // ── Users ──────────────────────────────────────────────────────────
    public const string USERS_VIEW      = 'users.view';
    public const string USERS_MANAGE    = 'users.manage';
    public const string ROLES_MANAGE    = 'roles.manage';
    public const string ACCESS_MANAGE   = 'access.manage';
    public const string TWOFACTOR_SETUP = '2fa.setup';

    // ── Appearance ─────────────────────────────────────────────────────
    public const string APPEARANCE_MANAGE = 'appearance.manage';

    // ── System ─────────────────────────────────────────────────────────
    public const string SETTINGS_MANAGE = 'settings.manage';
    public const string CACHE_MANAGE    = 'cache.manage';
    public const string ACTIVITY_VIEW   = 'activity.view';
    public const string WEBHOOKS_MANAGE = 'webhooks.manage';
    public const string AI_ACCESS       = 'ai.access';
    public const string CRON_MANAGE     = 'cron.manage';
    public const string PLUGINS_MANAGE  = 'plugins.manage';

    /**
     * All permissions grouped — delegates to PermissionRegistry (single source of truth).
     *
     * @return array<string, array<string, string>> [group => [permission => label]]
     */
    public static function all(): array
    {
        return PermissionRegistry::all();
    }

    /**
     * Flat list of all permission keys.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return PermissionRegistry::keys();
    }
}
