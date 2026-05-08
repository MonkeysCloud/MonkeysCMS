<?php

declare(strict_types=1);

namespace App\Cms\Breadcrumb;

use PDO;

/**
 * BreadcrumbRepository — CRUD for breadcrumb configuration.
 *
 * Auto-creates the `breadcrumb_configs` table on first access
 * to avoid requiring a migration step.
 */
final class BreadcrumbRepository
{
    private bool $tableChecked = false;

    public function __construct(private readonly PDO $pdo) {}

    // ── Read ────────────────────────────────────────────────────────────

    /**
     * Find configuration for a specific entity_type + bundle.
     */
    public function findConfig(string $entityType, string $bundle): ?BreadcrumbConfig
    {
        $this->ensureTable();

        $stmt = $this->pdo->prepare(
            'SELECT * FROM breadcrumb_configs WHERE entity_type = :et AND bundle = :b LIMIT 1'
        );
        $stmt->execute(['et' => $entityType, 'b' => $bundle]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (new BreadcrumbConfig())->hydrate($row) : null;
    }

    /**
     * Find the global default configuration.
     */
    public function findGlobal(): ?BreadcrumbConfig
    {
        return $this->findConfig('global', '*');
    }

    /**
     * Resolve the effective config: specific override → global fallback → hardcoded defaults.
     */
    public function resolveConfig(string $entityType, string $bundle): BreadcrumbConfig
    {
        // Try specific first
        $config = $this->findConfig($entityType, $bundle);
        if ($config !== null) {
            return $config;
        }

        // Try global fallback
        $global = $this->findGlobal();
        if ($global !== null) {
            return $global;
        }

        // Return hardcoded defaults (everything enabled)
        return new BreadcrumbConfig();
    }

    /**
     * Get all configs for the admin listing.
     *
     * @return list<BreadcrumbConfig>
     */
    public function findAll(): array
    {
        $this->ensureTable();

        $stmt = $this->pdo->query(
            'SELECT * FROM breadcrumb_configs ORDER BY entity_type ASC, bundle ASC'
        );

        return array_map(
            static fn(array $row) => (new BreadcrumbConfig())->hydrate($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    // ── Write ───────────────────────────────────────────────────────────

    /**
     * Save (upsert) a breadcrumb config.
     */
    public function save(BreadcrumbConfig $config): BreadcrumbConfig
    {
        $this->ensureTable();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        // Check for existing by entity_type + bundle
        $existing = $this->findConfig($config->entity_type, $config->bundle);

        if ($existing !== null) {
            $this->pdo->prepare(
                'UPDATE breadcrumb_configs SET
                    enabled = :enabled,
                    show_home = :show_home,
                    show_current = :show_current,
                    show_content_type = :show_content_type,
                    show_taxonomy = :show_taxonomy,
                    `separator` = :separator,
                    json_ld = :json_ld,
                    updated_at = :updated_at
                 WHERE id = :id'
            )->execute([
                'id'                => $existing->id,
                'enabled'           => (int) $config->enabled,
                'show_home'         => (int) $config->show_home,
                'show_current'      => (int) $config->show_current,
                'show_content_type' => (int) $config->show_content_type,
                'show_taxonomy'     => (int) $config->show_taxonomy,
                'separator'         => $config->separator,
                'json_ld'           => (int) $config->json_ld,
                'updated_at'        => $now,
            ]);
            $config->id = $existing->id;
        } else {
            $this->pdo->prepare(
                'INSERT INTO breadcrumb_configs
                    (entity_type, bundle, enabled, show_home, show_current,
                     show_content_type, show_taxonomy, `separator`, json_ld,
                     created_at, updated_at)
                 VALUES
                    (:entity_type, :bundle, :enabled, :show_home, :show_current,
                     :show_content_type, :show_taxonomy, :separator, :json_ld,
                     :created_at, :updated_at)'
            )->execute([
                'entity_type'       => $config->entity_type,
                'bundle'            => $config->bundle,
                'enabled'           => (int) $config->enabled,
                'show_home'         => (int) $config->show_home,
                'show_current'      => (int) $config->show_current,
                'show_content_type' => (int) $config->show_content_type,
                'show_taxonomy'     => (int) $config->show_taxonomy,
                'separator'         => $config->separator,
                'json_ld'           => (int) $config->json_ld,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
            $config->id = (int) $this->pdo->lastInsertId();
        }

        return $config;
    }

    /**
     * Delete a config by ID.
     */
    public function delete(int $id): bool
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare('DELETE FROM breadcrumb_configs WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ── Schema ──────────────────────────────────────────────────────────

    /**
     * Auto-create the table if it doesn't exist.
     */
    public function ensureTable(): void
    {
        if ($this->tableChecked) {
            return;
        }

        // Check if table already exists
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'breadcrumb_configs'"
        );
        if ((int) $stmt->fetchColumn() > 0) {
            $this->tableChecked = true;
            return;
        }

        $sql = <<<'SQL'
CREATE TABLE breadcrumb_configs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(32) NOT NULL DEFAULT 'global',
    bundle VARCHAR(64) NOT NULL DEFAULT '*',
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    show_home TINYINT(1) NOT NULL DEFAULT 1,
    show_current TINYINT(1) NOT NULL DEFAULT 1,
    show_content_type TINYINT(1) NOT NULL DEFAULT 1,
    show_taxonomy TINYINT(1) NOT NULL DEFAULT 0,
    `separator` VARCHAR(16) NOT NULL DEFAULT '>',
    json_ld TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bc_type_bundle (entity_type, bundle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

        $this->pdo->exec($sql);
        $this->tableChecked = true;
    }
}
