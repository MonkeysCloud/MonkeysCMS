<?php

declare(strict_types=1);

namespace App\Cms\Provider;

use App\Cms\Admin\AdminMenuRegistry;
use App\Cms\Admin\AdminPermissions as P;
use PDO;
use Psr\Container\ContainerInterface;

/**
 * AdminMenuProvider — Registers the AdminMenuRegistry in the DI container
 * and populates it with all core CMS admin sidebar menu items.
 *
 * Called during application boot. Plugins can later add/remove/modify
 * items via the registry API in their own boot() method.
 */
final class AdminMenuProvider
{
    /**
     * DI definitions for the admin menu system.
     */
    public static function getDefinitions(): array
    {
        return [
            AdminMenuRegistry::class => function (ContainerInterface $c): AdminMenuRegistry {
                $pdo = $c->get(PDO::class);
                $registry = new AdminMenuRegistry($pdo);

                // Populate with core menu items
                self::populateRegistry($registry, $pdo);

                return $registry;
            },
        ];
    }

    /**
     * Register all core CMS admin sidebar items.
     * Public to allow lazy initialization from middleware.
     */
    public static function populateRegistry(AdminMenuRegistry $registry, PDO $pdo): void
    {
        // ── Dashboard ──────────────────────────────────────────────────
        $registry->setDashboard('Dashboard', '/admin', icon: 'layout-dashboard', permission: P::DASHBOARD_VIEW);

        // ── Groups (3 compact groups) ──────────────────────────────────
        $registry->addGroup('content', 'Content', weight: 0);
        $registry->addGroup('structure', 'Structure', weight: 10);
        $registry->addGroup('admin', 'Administration', weight: 20);

        // ════════════════════════════════════════════════════════════════
        // CONTENT — Daily editorial work
        // ════════════════════════════════════════════════════════════════

        // Content (expandable)
        $registry->addItem('content', 'Content', '/admin/content', 'content',
            icon: 'file-text', weight: 0, permission: P::CONTENT_VIEW);
        $registry->addChild('content', 'content.all', 'All Content', '/admin/content', weight: 0);

        // Dynamic: "New <ContentType>" links
        try {
            $types = $pdo->query("SELECT type_id, label FROM content_types ORDER BY label")
                ->fetchAll(PDO::FETCH_ASSOC);
            foreach ($types as $i => $ct) {
                $registry->addChild('content', 'content.new_' . $ct['type_id'],
                    'New ' . $ct['label'],
                    '/admin/content/create/' . $ct['type_id'],
                    weight: 10 + $i,
                    permission: P::CONTENT_CREATE,
                );
            }
        } catch (\Throwable) {
            // DB not ready yet
        }

        $registry->addChild('content', 'content.workflow', 'Workflow', '/admin/workflow',
            weight: 80, permission: P::WORKFLOW_VIEW);
        $registry->addChild('content', 'content.trash', '🗑️ Trash', '/admin/content/trash',
            weight: 99, permission: P::CONTENT_DELETE);

        // Media (expandable)
        $registry->addItem('media', 'Media', '/admin/media', 'content',
            icon: 'image', weight: 10, permission: P::MEDIA_VIEW);
        $registry->addChild('media', 'media.library', 'Library', '/admin/media', weight: 0);
        $registry->addChild('media', 'media.upload', 'Upload', '/admin/media/upload',
            weight: 10, permission: P::MEDIA_UPLOAD);

        // Comments
        $registry->addItem('comments', 'Comments', '/admin/comments', 'content',
            icon: 'message-circle', weight: 20, permission: P::COMMENTS_VIEW);

        // Webforms
        $registry->addItem('webforms', 'Webforms', '/admin/webforms', 'content',
            icon: 'file-input', weight: 30, permission: P::WEBFORMS_VIEW);

        // ════════════════════════════════════════════════════════════════
        // STRUCTURE — Content architecture
        // ════════════════════════════════════════════════════════════════

        // Content Types
        $registry->addItem('content_types', 'Content Types', '/admin/content-types', 'structure',
            icon: 'database', weight: 0, permission: P::CONTENT_TYPES_MANAGE);

        // Taxonomy (expandable)
        $registry->addItem('taxonomy', 'Taxonomy', '/admin/taxonomy', 'structure',
            icon: 'tags', weight: 5, permission: P::TAXONOMY_VIEW);
        $registry->addChild('taxonomy', 'taxonomy.all', 'All Vocabularies', '/admin/taxonomy', weight: 0);

        // Dynamic: vocabulary links
        try {
            $vocabs = $pdo->query("SELECT id, label FROM vocabularies ORDER BY label")
                ->fetchAll(PDO::FETCH_ASSOC);
            foreach ($vocabs as $i => $v) {
                $registry->addChild('taxonomy', 'taxonomy.vocab_' . $v['id'],
                    $v['label'],
                    '/admin/taxonomy/' . $v['id'] . '/terms',
                    weight: 10 + $i,
                );
            }
        } catch (\Throwable) {
            // DB not ready yet
        }

        $registry->addChild('taxonomy', 'taxonomy.create', '+ Add Vocabulary', '/admin/taxonomy/create',
            weight: 99, permission: P::TAXONOMY_MANAGE);

        // Blocks (expandable)
        $registry->addItem('blocks', 'Blocks', '/admin/blocks', 'structure',
            icon: 'blocks', weight: 10, permission: P::BLOCKS_VIEW);
        $registry->addChild('blocks', 'blocks.library', 'Library', '/admin/blocks', weight: 0);
        $registry->addChild('blocks', 'blocks.create_db', 'Database Block', '/admin/blocks/create',
            weight: 10, permission: P::BLOCKS_MANAGE);
        $registry->addChild('blocks', 'blocks.create_theme', 'Theme Component', '/admin/blocks/theme/create',
            weight: 20, permission: P::BLOCKS_MANAGE);

        // Menus
        $registry->addItem('menus', 'Menus', '/admin/menus', 'structure',
            icon: 'menu', weight: 15, permission: P::MENUS_MANAGE);

        // ════════════════════════════════════════════════════════════════
        // ADMINISTRATION — Users, appearance, settings, tools
        // ════════════════════════════════════════════════════════════════

        // Users (expandable)
        $registry->addItem('users', 'Users', '/admin/users', 'admin',
            icon: 'users', weight: 0, permission: P::USERS_VIEW);
        $registry->addChild('users', 'users.all', 'All Users', '/admin/users', weight: 0);
        $registry->addChild('users', 'users.create', 'Add User', '/admin/users/create',
            weight: 10, permission: P::USERS_MANAGE);
        $registry->addChild('users', 'users.roles', 'Roles', '/admin/roles',
            weight: 20, permission: P::ROLES_MANAGE);
        $registry->addChild('users', 'users.access', 'Content Permissions', '/admin/access',
            weight: 30, permission: P::ACCESS_MANAGE);
        $registry->addChild('users', 'users.2fa', 'Two-Factor Auth', '/admin/2fa/setup',
            weight: 40, permission: P::TWOFACTOR_SETUP);

        // Appearance (expandable)
        $registry->addItem('appearance', 'Appearance', '/admin/appearance', 'admin',
            icon: 'paintbrush', weight: 5, permission: P::APPEARANCE_MANAGE);
        $registry->addChild('appearance', 'appearance.themes', 'Themes', '/admin/appearance', weight: 0);
        $registry->addChild('appearance', 'appearance.editor', 'Theme Editor', '/admin/appearance/editor', weight: 10);

        // Settings (expandable — consolidates config, URLs, languages, cache)
        $registry->addItem('settings', 'Settings', '/admin/settings', 'admin',
            icon: 'settings', weight: 10, permission: P::SETTINGS_MANAGE);
        $registry->addChild('settings', 'settings.general', 'General', '/admin/settings', weight: 0);
        $registry->addChild('settings', 'settings.languages', 'Languages', '/admin/languages',
            weight: 10, permission: P::LANGUAGES_MANAGE);
        $registry->addChild('settings', 'settings.urls', 'URL Aliases', '/admin/url-aliases',
            weight: 20, permission: P::URL_ALIASES_MANAGE);
        $registry->addChild('settings', 'settings.breadcrumbs', 'Breadcrumbs', '/admin/breadcrumbs',
            weight: 25, permission: P::BREADCRUMBS_MANAGE);
        $registry->addChild('settings', 'settings.redirects', 'Redirects', '/admin/redirects',
            weight: 30, permission: P::REDIRECTS_MANAGE);
        $registry->addChild('settings', 'settings.search', 'Search', '/admin/search',
            weight: 35, permission: P::SEARCH_MANAGE);
        $registry->addChild('settings', 'settings.cache', 'Cache', '/admin/cache',
            weight: 40, permission: P::CACHE_MANAGE);
        $registry->addChild('settings', 'settings.cache_clear', 'Clear All Cache', '/admin/cache/clear',
            weight: 41,
            permission: P::CACHE_MANAGE,
            attributes: [
                '_form_action' => '/admin/cache/clear',
                '_form_fields' => ['target' => 'all'],
            ],
        );

        // Extend / Plugins
        $registry->addItem('plugins', 'Extend', '/admin/plugins', 'admin',
            icon: 'puzzle', weight: 15, permission: P::PLUGINS_MANAGE);

        // Tools (expandable — groups utility features)
        $registry->addItem('tools', 'Tools', '/admin/activity', 'admin',
            icon: 'wrench', weight: 20, permission: P::ACTIVITY_VIEW);
        $registry->addChild('tools', 'tools.activity', 'Activity Log', '/admin/activity',
            weight: 0, permission: P::ACTIVITY_VIEW);
        $registry->addChild('tools', 'tools.webhooks', 'Webhooks', '/admin/webhooks',
            weight: 10, permission: P::WEBHOOKS_MANAGE);
        $registry->addChild('tools', 'tools.ai', 'AI Assistant', '/admin/ai',
            weight: 20, permission: P::AI_ACCESS);
        $registry->addChild('tools', 'tools.cron', 'Cron', '/admin/cron',
            weight: 30, permission: P::CRON_MANAGE);
    }
}
