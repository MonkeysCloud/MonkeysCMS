<?php

declare(strict_types=1);

namespace App\Cms\Database;

use PDO;

/**
 * MigrationManager — MLC-driven database migration system.
 *
 * Reads migration declarations from database/migrations.mlc,
 * resolves dependencies, and executes pending migrations.
 * Tracks state in the `cms_migrations` table.
 */
final class MigrationManager
{
    private string $trackingTable = 'cms_migrations';
    private string $migrationsPath = 'resources/migrations';
    private string $order = 'dependency';

    /** @var MigrationConfig[] */
    private array $registry = [];

    private readonly SchemaBuilder $schemaBuilder;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $basePath,
    ) {
        $this->schemaBuilder = new SchemaBuilder();
        $this->parseRegistry();
    }

    // ── Registry Parsing ────────────────────────────────────────────────

    private function parseRegistry(): void
    {
        $mlcPath = $this->basePath . '/database/migrations.mlc';
        if (!file_exists($mlcPath)) return;

        $content = file_get_contents($mlcPath);

        // Strip comment lines (lines starting with #)
        $content = preg_replace('/^\s*#.*$/m', '', $content);

        // Parse settings
        if (preg_match('/tracking_table\s*=\s*"([^"]+)"/', $content, $m)) {
            $this->trackingTable = $m[1];
        }
        if (preg_match('/migrations_path\s*=\s*"([^"]+)"/', $content, $m)) {
            $this->migrationsPath = $m[1];
        }
        if (preg_match('/order\s*=\s*"([^"]+)"/', $content, $m)) {
            $this->order = $m[1];
        }

        // Parse migration blocks
        preg_match_all(
            '/migration\s+"([^"]+)"\s*\{([^}]+)\}/s',
            $content,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $id = $match[1];
            $body = $match[2];

            $this->registry[$id] = new MigrationConfig(
                id: $id,
                description: $this->extractValue($body, 'description') ?? '',
                file: $this->extractValue($body, 'file') ?? '',
                module: $this->extractValue($body, 'module') ?? 'core',
                version: $this->extractValue($body, 'version') ?? '2.0.0',
                requires: $this->extractArray($body, 'requires'),
                reversible: ($this->extractValue($body, 'reversible') ?? 'false') === 'true',
                rollbackFile: $this->extractValue($body, 'rollback'),
            );
        }
    }

    // ── Public API ──────────────────────────────────────────────────────

    /**
     * Ensure the tracking table exists
     */
    public function bootstrap(): void
    {
        $bootstrapMigration = $this->registry['migration_tracking'] ?? null;
        if ($bootstrapMigration) {
            $this->executeMigrationFile($bootstrapMigration->file);
        }
    }

    /**
     * Get all pending (not yet executed) migrations in order
     *
     * @return MigrationConfig[]
     */
    public function getPending(): array
    {
        $this->bootstrap();
        $executed = $this->getExecutedIds();

        $pending = array_filter(
            $this->registry,
            fn(MigrationConfig $m) => !in_array($m->id, $executed, true)
                && $m->id !== 'migration_tracking',
        );

        if ($this->order === 'dependency') {
            return $this->resolveDependencyOrder($pending, $executed);
        }

        // Sequential order (sort by ID)
        ksort($pending);
        return array_values($pending);
    }

    /**
     * Get all executed migration IDs
     *
     * @return string[]
     */
    public function getExecutedIds(): array
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT migration_id FROM {$this->trackingTable} WHERE status = 'completed' ORDER BY executed_at"
            );
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\PDOException) {
            return [];
        }
    }

    /**
     * Get full status of all migrations
     */
    public function getStatus(): array
    {
        $this->bootstrap();
        $executed = $this->getExecutedRecords();
        $status = [];

        foreach ($this->registry as $id => $config) {
            if ($id === '00000000_migration_tracking') continue;

            $record = $executed[$id] ?? null;
            $status[] = [
                'id' => $id,
                'description' => $config->description,
                'module' => $config->module,
                'version' => $config->version,
                'file' => $config->file,
                'status' => $record ? $record['status'] : 'pending',
                'executed_at' => $record['executed_at'] ?? null,
                'execution_time_ms' => $record['execution_time_ms'] ?? null,
                'batch' => $record['batch'] ?? null,
            ];
        }

        return $status;
    }

    /**
     * Run all pending migrations
     *
     * @return array{executed: string[], errors: array}
     */
    public function migrate(bool $dryRun = false): array
    {
        $pending = $this->getPending();
        $batch = $this->getNextBatch();
        $results = ['executed' => [], 'errors' => [], 'batch' => $batch];

        foreach ($pending as $migration) {
            $filePath = $this->basePath . '/' . $migration->file;

            if (!file_exists($filePath)) {
                $results['errors'][] = [
                    'id' => $migration->id,
                    'error' => "Migration file not found: {$migration->file}",
                ];
                continue;
            }

            $content = file_get_contents($filePath);
            $checksum = hash('sha256', $content);

            // Resolve SQL — either from MLC schema or raw SQL
            $sqlStatements = $this->resolveStatements($migration->file, $content);

            if ($dryRun) {
                $results['executed'][] = [
                    'id' => $migration->id,
                    'description' => $migration->description,
                    'file' => $migration->file,
                    'statements' => count($sqlStatements),
                    'dry_run' => true,
                ];
                continue;
            }

            $start = microtime(true);

            try {
                foreach ($sqlStatements as $stmt) {
                    $trimmed = trim($stmt);
                    if ($trimmed) {
                        $this->pdo->exec($trimmed);
                    }
                }
                $elapsed = (int) round((microtime(true) - $start) * 1000);

                $this->recordMigration($migration, $batch, $checksum, $elapsed, 'completed');

                $results['executed'][] = [
                    'id' => $migration->id,
                    'description' => $migration->description,
                    'time_ms' => $elapsed,
                ];
            } catch (\PDOException $e) {
                $elapsed = (int) round((microtime(true) - $start) * 1000);

                $this->recordMigration($migration, $batch, $checksum, $elapsed, 'failed', $e->getMessage());

                $results['errors'][] = [
                    'id' => $migration->id,
                    'error' => $e->getMessage(),
                ];

                // Stop on error
                break;
            }
        }

        return $results;
    }

    /**
     * Rollback the last batch of migrations
     */
    public function rollback(): array
    {
        $lastBatch = $this->getLastBatch();
        if (!$lastBatch) {
            return ['rolled_back' => [], 'errors' => []];
        }

        $stmt = $this->pdo->prepare(
            "SELECT migration_id FROM {$this->trackingTable}
             WHERE batch = :batch AND status = 'completed'
             ORDER BY executed_at DESC"
        );
        $stmt->execute(['batch' => $lastBatch]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $results = ['rolled_back' => [], 'errors' => [], 'batch' => $lastBatch];

        foreach ($ids as $migrationId) {
            $config = $this->registry[$migrationId] ?? null;
            if (!$config || !$config->reversible) {
                $results['errors'][] = ['id' => $migrationId, 'error' => 'Not reversible'];
                continue;
            }

            try {
                // Check for inline rollback in MLC file first
                $migrationPath = $this->basePath . '/' . $config->file;
                $rollbackStmts = [];

                if (str_ends_with($config->file, '.mlc') && file_exists($migrationPath)) {
                    $parsed = $this->schemaBuilder->parse(file_get_contents($migrationPath));
                    $rollbackStmts = $parsed['rollbacks'];
                } elseif ($config->rollbackFile) {
                    $rbPath = $this->basePath . '/' . $config->rollbackFile;
                    if (file_exists($rbPath)) {
                        $rollbackStmts = [file_get_contents($rbPath)];
                    }
                }

                if (!$rollbackStmts) {
                    $results['errors'][] = ['id' => $migrationId, 'error' => 'No rollback SQL found'];
                    continue;
                }

                foreach ($rollbackStmts as $stmt) {
                    $this->pdo->exec(trim($stmt));
                }

                $stmt2 = $this->pdo->prepare(
                    "UPDATE {$this->trackingTable} SET status = 'rolled_back' WHERE migration_id = :id"
                );
                $stmt2->execute(['id' => $migrationId]);

                $results['rolled_back'][] = $migrationId;
            } catch (\PDOException $e) {
                $results['errors'][] = ['id' => $migrationId, 'error' => $e->getMessage()];
                break;
            }
        }

        return $results;
    }

    /**
     * Get all registered migrations
     *
     * @return MigrationConfig[]
     */
    public function getRegistry(): array
    {
        return $this->registry;
    }

    // ── Private Helpers ─────────────────────────────────────────────────

    private function recordMigration(
        MigrationConfig $config,
        int $batch,
        string $checksum,
        int $elapsed,
        string $status,
        ?string $error = null,
    ): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->trackingTable}
             (migration_id, description, module, version, file, checksum, batch, execution_time_ms, status, error_message)
             VALUES (:id, :desc, :mod, :ver, :file, :checksum, :batch, :time, :status, :error)"
        );
        $stmt->execute([
            'id' => $config->id,
            'desc' => $config->description,
            'mod' => $config->module,
            'ver' => $config->version,
            'file' => $config->file,
            'checksum' => $checksum,
            'batch' => $batch,
            'time' => $elapsed,
            'status' => $status,
            'error' => $error,
        ]);
    }

    private function getExecutedRecords(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT * FROM {$this->trackingTable} ORDER BY executed_at");
            $records = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $records[$row['migration_id']] = $row;
            }
            return $records;
        } catch (\PDOException) {
            return [];
        }
    }

    private function getNextBatch(): int
    {
        try {
            $max = $this->pdo->query("SELECT MAX(batch) FROM {$this->trackingTable}")->fetchColumn();
            return ((int) $max) + 1;
        } catch (\PDOException) {
            return 1;
        }
    }

    private function getLastBatch(): ?int
    {
        try {
            $max = $this->pdo->query(
                "SELECT MAX(batch) FROM {$this->trackingTable} WHERE status = 'completed'"
            )->fetchColumn();
            return $max ? (int) $max : null;
        } catch (\PDOException) {
            return null;
        }
    }

    /**
     * Topological sort based on requires[] dependencies
     *
     * @return MigrationConfig[]
     */
    private function resolveDependencyOrder(array $pending, array $alreadyExecuted): array
    {
        $sorted = [];
        $visited = [];
        $allResolved = array_merge($alreadyExecuted, ['00000000_migration_tracking']);

        $visit = function (string $id) use (&$visit, &$sorted, &$visited, $pending, $allResolved): void {
            if (isset($visited[$id])) return;
            $visited[$id] = true;

            $config = $pending[$id] ?? null;
            if (!$config) return;

            foreach ($config->requires as $dep) {
                if (!in_array($dep, $allResolved, true) && isset($pending[$dep])) {
                    $visit($dep);
                }
            }

            $sorted[] = $config;
        };

        foreach (array_keys($pending) as $id) {
            $visit($id);
        }

        return $sorted;
    }

    // ── File Resolution ──────────────────────────────────────────────────

    /**
     * Resolve an MLC or SQL migration file into executable SQL statements
     */
    private function resolveStatements(string $filePath, string $content): array
    {
        if (str_ends_with($filePath, '.mlc')) {
            $parsed = $this->schemaBuilder->parse($content);
            return array_merge($parsed['tables'], $parsed['seeds']);
        }

        // Raw SQL — split on semicolons
        return array_filter(
            array_map('trim', explode(';', $content)),
            fn(string $s) => $s !== '',
        );
    }

    /**
     * Execute a single migration file (used by bootstrap)
     */
    private function executeMigrationFile(string $relPath): void
    {
        $fullPath = $this->basePath . '/' . $relPath;
        if (!file_exists($fullPath)) return;

        $content = file_get_contents($fullPath);
        $stmts = $this->resolveStatements($relPath, $content);

        foreach ($stmts as $stmt) {
            $trimmed = trim($stmt);
            if ($trimmed) {
                $this->pdo->exec($trimmed);
            }
        }
    }

    // ── MLC Parsing ─────────────────────────────────────────────────────

    private function extractValue(string $body, string $key): ?string
    {
        if (preg_match('/\b' . preg_quote($key) . '\s*=\s*"([^"]*)"/', $body, $m)) {
            return $m[1];
        }
        if (preg_match('/\b' . preg_quote($key) . '\s*=\s*(\S+)/', $body, $m)) {
            return $m[1];
        }
        return null;
    }

    private function extractArray(string $body, string $key): array
    {
        if (preg_match('/\b' . preg_quote($key) . '\s*=\s*\[(.*?)\]/s', $body, $m)) {
            preg_match_all('/"([^"]+)"/', $m[1], $items);
            return $items[1] ?? [];
        }
        return [];
    }
}
