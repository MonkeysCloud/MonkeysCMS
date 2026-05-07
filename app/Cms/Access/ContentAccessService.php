<?php

declare(strict_types=1);

namespace App\Cms\Access;

use MonkeysLegion\DI\Attributes\Singleton;
use PDO;

/**
 * ContentAccessService — Role-based content access control.
 *
 * Permissions checked at two levels:
 *   1. Content Type — Which roles can view/create/edit/delete each content type
 *   2. Per-Node     — Override access for specific content items
 *
 * Users may have multiple roles. Access is granted if ANY assigned role
 * has the required permission.
 *
 * Permission types: view, create, edit, delete
 */
#[Singleton]
final class ContentAccessService
{
    private const string TABLE = 'content_access';

    /** @var array<string, array<string, list<int>>> Cache: entity_type:entity_id => [permission => [role_ids]] */
    private array $cache = [];

    public function __construct(
        private readonly PDO $pdo,
    ) {}

    // ── Permission Checks ──────────────────────────────────────────────

    /**
     * Check if a user (by role IDs) can perform an action on a content type.
     *
     * @param list<int> $userRoleIds User's assigned role IDs
     * @param string    $contentTypeId Machine name of content type (e.g., 'article')
     * @param string    $permission   'view', 'create', 'edit', 'delete'
     */
    public function canAccessType(array $userRoleIds, string $contentTypeId, string $permission): bool
    {
        // Super-admin bypass: role with is_super_admin = 1 always has access
        if ($this->isSuperAdmin($userRoleIds)) {
            return true;
        }

        $rules = $this->getRules('content_type', $contentTypeId);

        // If no rules defined for this type, grant access by default (open access)
        if (!isset($rules[$permission]) || empty($rules[$permission])) {
            return true;
        }

        // Check if any user role is in the allowed list
        return !empty(array_intersect($userRoleIds, $rules[$permission]));
    }

    /**
     * Check if a user can perform an action on a specific node.
     *
     * Node-level rules OVERRIDE content-type rules when present.
     *
     * @param list<int> $userRoleIds
     */
    public function canAccessNode(array $userRoleIds, int $nodeId, string $contentTypeId, string $permission): bool
    {
        if ($this->isSuperAdmin($userRoleIds)) {
            return true;
        }

        // Check node-level rules first (override)
        $nodeRules = $this->getRules('node', (string) $nodeId);
        if (isset($nodeRules[$permission]) && !empty($nodeRules[$permission])) {
            return !empty(array_intersect($userRoleIds, $nodeRules[$permission]));
        }

        // Fall back to content-type rules
        return $this->canAccessType($userRoleIds, $contentTypeId, $permission);
    }

    // ── Rule Management ────────────────────────────────────────────────

    /**
     * Set access rules for a content type.
     *
     * @param string    $contentTypeId
     * @param string    $permission 'view', 'create', 'edit', 'delete'
     * @param list<int> $roleIds    Roles that have this permission
     */
    public function setTypeAccess(string $contentTypeId, string $permission, array $roleIds): void
    {
        $this->setRules('content_type', $contentTypeId, $permission, $roleIds);
    }

    /**
     * Set access rules for a specific node (override).
     */
    public function setNodeAccess(int $nodeId, string $permission, array $roleIds): void
    {
        $this->setRules('node', (string) $nodeId, $permission, $roleIds);
    }

    /**
     * Remove all per-node access overrides (revert to type-level rules).
     */
    public function clearNodeAccess(int $nodeId): void
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM " . self::TABLE . " WHERE entity_type = 'node' AND entity_id = :id"
        );
        $stmt->execute([':id' => (string) $nodeId]);
        unset($this->cache['node:' . $nodeId]);
    }

    /**
     * Get all access rules for a content type (for admin UI).
     *
     * @return array<string, list<int>> [permission => [role_ids]]
     */
    public function getTypeRules(string $contentTypeId): array
    {
        return $this->getRules('content_type', $contentTypeId);
    }

    /**
     * Get per-node overrides.
     *
     * @return array<string, list<int>>
     */
    public function getNodeRules(int $nodeId): array
    {
        return $this->getRules('node', (string) $nodeId);
    }

    /**
     * Get allowed content type IDs for a user's view permission.
     *
     * @param list<int> $userRoleIds
     * @return list<string>|null null = all types allowed, list = specific type IDs
     */
    public function getAllowedTypes(array $userRoleIds): ?array
    {
        if ($this->isSuperAdmin($userRoleIds)) {
            return null; // All types
        }

        // Check if there are any content_type rules at all
        $stmt = $this->pdo->query(
            "SELECT DISTINCT entity_id FROM " . self::TABLE . " WHERE entity_type = 'content_type' AND permission = 'view'"
        );
        $restrictedTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($restrictedTypes)) {
            return null; // No restrictions defined
        }

        // Filter to only types the user can view
        $allowed = [];
        foreach ($restrictedTypes as $typeId) {
            if ($this->canAccessType($userRoleIds, $typeId, 'view')) {
                $allowed[] = $typeId;
            }
        }

        return $allowed;
    }

    // ── Bulk Check ─────────────────────────────────────────────────────

    /**
     * Filter a list of node IDs to only those the user can access.
     *
     * @param list<int> $userRoleIds
     * @param list<int> $nodeIds
     * @return list<int> Accessible node IDs
     */
    public function filterAccessibleNodes(array $userRoleIds, array $nodeIds, string $contentTypeId, string $permission): array
    {
        if ($this->isSuperAdmin($userRoleIds)) {
            return $nodeIds;
        }

        return array_values(array_filter(
            $nodeIds,
            fn(int $id) => $this->canAccessNode($userRoleIds, $id, $contentTypeId, $permission),
        ));
    }

    // ── Internal ───────────────────────────────────────────────────────

    /**
     * @return array<string, list<int>>
     */
    private function getRules(string $entityType, string $entityId): array
    {
        $cacheKey = $entityType . ':' . $entityId;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $stmt = $this->pdo->prepare(
            "SELECT permission, role_id FROM " . self::TABLE . " WHERE entity_type = :type AND entity_id = :id"
        );
        $stmt->execute([':type' => $entityType, ':id' => $entityId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $rules = [];
        foreach ($rows as $row) {
            $rules[$row['permission']][] = (int) $row['role_id'];
        }

        $this->cache[$cacheKey] = $rules;

        return $rules;
    }

    private function setRules(string $entityType, string $entityId, string $permission, array $roleIds): void
    {
        // Delete existing rules for this entity/permission
        $stmt = $this->pdo->prepare(
            "DELETE FROM " . self::TABLE . " WHERE entity_type = :type AND entity_id = :id AND permission = :perm"
        );
        $stmt->execute([':type' => $entityType, ':id' => $entityId, ':perm' => $permission]);

        // Insert new rules
        if (!empty($roleIds)) {
            $insert = $this->pdo->prepare(
                "INSERT INTO " . self::TABLE . " (entity_type, entity_id, role_id, permission) VALUES (:type, :id, :role, :perm)"
            );
            foreach ($roleIds as $roleId) {
                $insert->execute([
                    ':type' => $entityType,
                    ':id'   => $entityId,
                    ':role' => (int) $roleId,
                    ':perm' => $permission,
                ]);
            }
        }

        // Invalidate cache
        unset($this->cache[$entityType . ':' . $entityId]);
    }

    /**
     * Check if any of the given role IDs is super-admin.
     *
     * @param list<int> $roleIds
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
