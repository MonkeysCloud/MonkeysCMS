<?php

declare(strict_types=1);

namespace App\Cms\Admin;

use App\Cms\User\PermissionRegistry;
use MonkeysLegion\DI\Attributes\Singleton;
use PDO;

/**
 * AdminMenuRegistry — Central registry for the admin sidebar menu.
 *
 * Core CMS and plugins register items programmatically. The menu tree
 * is built on-demand, filtered by the current user's role permissions.
 *
 * Features:
 *   - Groups (Content, Structure, System, Plugins, custom)
 *   - Items with optional children (expandable sub-menus)
 *   - Permission-based visibility (multi-role support)
 *   - Weight-based ordering at every level
 *   - Plugin API: addGroup(), addItem(), addChild(), removeItem(), modifyItem()
 *   - Dynamic items (content types, vocabularies injected by core provider)
 *   - Badge support (counts, status indicators)
 *
 * Usage:
 *   // In a provider or plugin boot():
 *   $registry = $container->get(AdminMenuRegistry::class);
 *   $registry->addGroup('analytics', 'Analytics', weight: 25);
 *   $registry->addItem('analytics_dash', 'Dashboard', '/admin/analytics', 'analytics',
 *       icon: 'bar-chart', permission: 'analytics.view');
 */
#[Singleton]
final class AdminMenuRegistry
{
    /** @var array<string, AdminMenuGroup> */
    private array $groups = [];

    /** @var array<string, AdminMenuItem> All items indexed by ID */
    private array $items = [];

    /** @var list<string> IDs to remove (by plugins) */
    private array $removals = [];

    /** @var ?AdminMenuItem Dashboard item (outside groups) */
    private ?AdminMenuItem $dashboard = null;

    public function __construct(
        private readonly PDO $pdo,
    ) {}

    // ── Group API ──────────────────────────────────────────────────────

    /**
     * Register a menu group (section header).
     */
    public function addGroup(string $id, string $label, int $weight = 0): static
    {
        if (!isset($this->groups[$id])) {
            $this->groups[$id] = new AdminMenuGroup($id, $label, $weight);
        }
        return $this;
    }

    /**
     * Remove a group and all its items.
     */
    public function removeGroup(string $id): static
    {
        unset($this->groups[$id]);
        return $this;
    }

    // ── Item API ───────────────────────────────────────────────────────

    /**
     * Set the dashboard item (always first, outside groups).
     */
    public function setDashboard(
        string $label,
        string $url,
        ?string $icon = null,
        ?string $permission = null,
    ): static {
        $this->dashboard = new AdminMenuItem(
            id: 'dashboard',
            label: $label,
            url: $url,
            group: '_dashboard',
            icon: $icon,
            permission: $permission,
        );
        return $this;
    }

    /**
     * Register a top-level menu item.
     */
    public function addItem(
        string $id,
        string $label,
        string $url,
        string $group,
        ?string $icon = null,
        int $weight = 0,
        ?string $permission = null,
        ?string $badge = null,
        ?string $badgeVariant = null,
        ?string $target = null,
        array $attributes = [],
    ): static {
        $item = new AdminMenuItem(
            id: $id,
            label: $label,
            url: $url,
            group: $group,
            icon: $icon,
            permission: $permission,
            weight: $weight,
            badge: $badge,
            badgeVariant: $badgeVariant,
            target: $target,
        );
        $item->attributes = $attributes;

        $this->items[$id] = $item;

        // Auto-add to group
        if (isset($this->groups[$group])) {
            $this->groups[$group]->addItem($item);
        }

        return $this;
    }

    /**
     * Register a child sub-item under a parent.
     */
    public function addChild(
        string $parentId,
        string $id,
        string $label,
        string $url,
        int $weight = 0,
        ?string $permission = null,
        ?string $icon = null,
        ?string $badge = null,
        ?string $badgeVariant = null,
        array $attributes = [],
    ): static {
        $child = new AdminMenuItem(
            id: $id,
            label: $label,
            url: $url,
            group: $this->items[$parentId]->group ?? '',
            icon: $icon,
            permission: $permission,
            weight: $weight,
            badge: $badge,
            badgeVariant: $badgeVariant,
        );
        $child->attributes = $attributes;

        if (isset($this->items[$parentId])) {
            $this->items[$parentId]->addChild($child);
        }

        $this->items[$id] = $child;

        return $this;
    }

    /**
     * Remove an item by ID (used by plugins to hide core items).
     */
    public function removeItem(string $id): static
    {
        $this->removals[] = $id;
        return $this;
    }

    /**
     * Modify an existing item's properties.
     */
    public function modifyItem(
        string $id,
        ?string $label = null,
        ?string $icon = null,
        ?int $weight = null,
        ?string $badge = null,
        ?string $badgeVariant = null,
        ?string $permission = null,
    ): static {
        // Modifications are applied during buildForRoles()
        // Store as pending modifications
        if (isset($this->items[$id])) {
            // Create a new item with modified properties (readonly constructor)
            $old = $this->items[$id];
            $new = new AdminMenuItem(
                id: $old->id,
                label: $label ?? $old->label,
                url: $old->url,
                group: $old->group,
                icon: $icon ?? $old->icon,
                permission: $permission ?? $old->permission,
                weight: $weight ?? $old->weight,
                badge: $badge ?? $old->badge,
                badgeVariant: $badgeVariant ?? $old->badgeVariant,
                target: $old->target,
                enabled: $old->enabled,
            );
            $new->children = $old->children;
            $new->attributes = $old->attributes;
            $this->items[$id] = $new;

            // Update in group
            foreach ($this->groups as $group) {
                foreach ($group->items as $i => $item) {
                    if ($item->id === $id) {
                        $group->items[$i] = $new;
                        break 2;
                    }
                }
            }
        }

        return $this;
    }

    // ── Build the Final Tree ───────────────────────────────────────────

    /**
     * Build the complete menu tree filtered by user role permissions.
     *
     * @param list<int> $userRoleIds User's assigned role IDs
     * @return array{dashboard: ?AdminMenuItem, groups: AdminMenuGroup[]}
     */
    public function buildForRoles(array $userRoleIds): array
    {
        $isSuperAdmin = $this->isSuperAdmin($userRoleIds);
        $userPermissions = $isSuperAdmin ? null : $this->loadRolePermissions($userRoleIds);

        // Apply removals
        foreach ($this->removals as $id) {
            unset($this->items[$id]);
            foreach ($this->groups as $group) {
                $group->removeItem($id);
            }
        }

        // Filter dashboard
        $dashboard = null;
        if ($this->dashboard !== null) {
            if ($this->canAccess($this->dashboard->permission, $isSuperAdmin, $userPermissions)) {
                $dashboard = $this->dashboard;
            }
        }

        // Filter groups
        $filteredGroups = [];
        foreach ($this->groups as $group) {
            $filteredItems = [];
            foreach ($group->items as $item) {
                if (!$this->canAccess($item->permission, $isSuperAdmin, $userPermissions)) {
                    continue;
                }

                // Filter children
                if (!empty($item->children)) {
                    $filteredChildren = [];
                    foreach ($item->children as $child) {
                        if ($this->canAccess($child->permission, $isSuperAdmin, $userPermissions)) {
                            $filteredChildren[] = $child;
                        }
                    }
                    // Create new item with filtered children
                    $filtered = new AdminMenuItem(
                        id: $item->id,
                        label: $item->label,
                        url: $item->url,
                        group: $item->group,
                        icon: $item->icon,
                        permission: $item->permission,
                        weight: $item->weight,
                        badge: $item->badge,
                        badgeVariant: $item->badgeVariant,
                        target: $item->target,
                        enabled: $item->enabled,
                    );
                    $filtered->children = $filteredChildren;
                    $filtered->attributes = $item->attributes;
                    $filteredItems[] = $filtered;
                } else {
                    $filteredItems[] = $item;
                }
            }

            if (!empty($filteredItems)) {
                $g = new AdminMenuGroup($group->id, $group->label, $group->weight);
                foreach ($filteredItems as $item) {
                    $g->addItem($item);
                }
                $filteredGroups[] = $g;
            }
        }

        // Sort groups by weight
        usort($filteredGroups, static fn(AdminMenuGroup $a, AdminMenuGroup $b): int => $a->weight <=> $b->weight);

        return [
            'dashboard' => $dashboard,
            'groups'    => $filteredGroups,
        ];
    }

    // ── Internal ───────────────────────────────────────────────────────

    /**
     * Check if the user has access to an item.
     *
     * @param ?string $permission Required permission (null = everyone)
     * @param bool $isSuperAdmin
     * @param ?list<string> $userPermissions null = super admin (all access)
     */
    private function canAccess(?string $permission, bool $isSuperAdmin, ?array $userPermissions): bool
    {
        // No permission required
        if ($permission === null) {
            return true;
        }

        // Super admin
        if ($isSuperAdmin) {
            return true;
        }

        // Use PermissionRegistry::check() for wildcard support
        // e.g., 'content.*' in role permissions matches 'content.view'
        return $userPermissions !== null && PermissionRegistry::check($userPermissions, $permission);
    }

    /**
     * Load merged permissions for a set of role IDs.
     *
     * @param list<int> $roleIds
     * @return list<string>
     */
    private function loadRolePermissions(array $roleIds): array
    {
        if (empty($roleIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT permissions FROM cms_roles WHERE id IN ({$placeholders})"
        );
        $stmt->execute(array_map('intval', $roleIds));
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Merge all permissions from all assigned roles (union)
        $merged = [];
        foreach ($rows as $json) {
            $perms = json_decode($json, true);
            if (is_array($perms)) {
                $merged = array_merge($merged, $perms);
            }
        }

        return array_unique($merged);
    }

    /**
     * Check if any of the given role IDs is super-admin.
     */
    private function isSuperAdmin(array $roleIds): bool
    {
        if (empty($roleIds)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM cms_roles WHERE id IN ({$placeholders}) AND is_super_admin = 1"
        );
        $stmt->execute(array_map('intval', $roleIds));

        return (int) $stmt->fetchColumn() > 0;
    }
}
