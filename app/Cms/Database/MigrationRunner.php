<?php

declare(strict_types=1);

namespace App\Cms\Database;

/**
 * MigrationRunner - Executes database migrations
 * 
 * Manages the migration lifecycle:
 * - Running pending migrations
 * - Rolling back migrations
 * - Tracking migration status
 * 
 * Usage:
 * ```php
 * $runner = new MigrationRunner($pdo, __DIR__ . '/migrations');
 * $runner->migrate();      // Run all pending
 * $runner->rollback();     // Rollback last batch
 * $runner->reset();        // Rollback all
 * $runner->fresh();        // Drop all + migrate
 * ```
 */
class MigrationRunner
{
    private \PDO $db;
    private string $migrationsPath;
    private string $migrationsTable = 'migrations';

    /** @var callable|null */
    private $output = null;

    public function __construct(\PDO $db, string $migrationsPath)
    {
        $this->db = $db;
        $this->migrationsPath = rtrim($migrationsPath, '/');
    }

    /**
     * Set output callback for logging
     */
    public function setOutput(callable $output): void
    {
        $this->output = $output;
    }

    /**
     * Run all pending migrations
     * 
     * @return string[] Migrated files
     */
    public function migrate(): array
    {
        $this->ensureMigrationsTable();

        $pending = $this->getPendingMigrations();
        $migrated = [];

        if (empty($pending)) {
            $this->log("Nothing to migrate.");
            return [];
        }

        $batch = $this->getNextBatchNumber();

        foreach ($pending as $migration) {
            $this->log("Migrating: {$migration}");

            $this->runMigration($migration, 'up');
            $this->recordMigration($migration, $batch);

            $migrated[] = $migration;
            $this->log("Migrated:  {$migration}");
        }

        return $migrated;
    }

    /**
     * Rollback the last batch of migrations
     * 
     * @return string[] Rolled back files
     */
    public function rollback(): array
    {
        $this->ensureMigrationsTable();

        $batch = $this->getLastBatchNumber();
        
        if ($batch === 0) {
            $this->log("Nothing to rollback.");
            return [];
        }

        $migrations = $this->getMigrationsForBatch($batch);
        $rolledBack = [];

        foreach (array_reverse($migrations) as $migration) {
            $this->log("Rolling back: {$migration}");

            $this->runMigration($migration, 'down');
            $this->removeMigration($migration);

            $rolledBack[] = $migration;
            $this->log("Rolled back: {$migration}");
        }

        return $rolledBack;
    }

    /**
     * Rollback all migrations
     * 
     * @return string[] Rolled back files
     */
    public function reset(): array
    {
        $this->ensureMigrationsTable();

        $migrations = $this->getRunMigrations();
        $rolledBack = [];

        foreach (array_reverse($migrations) as $migration) {
            $this->log("Rolling back: {$migration}");

            $this->runMigration($migration, 'down');
            $this->removeMigration($migration);

            $rolledBack[] = $migration;
            $this->log("Rolled back: {$migration}");
        }

        return $rolledBack;
    }

    /**
     * Drop all tables and re-run all migrations
     * 
     * @return string[] Migrated files
     */
    public function fresh(): array
    {
        $this->log("Dropping all tables...");

        // Get all tables
        $stmt = $this->db->query("SHOW TABLES");
        $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        // Disable foreign key checks
        $this->db->exec("SET FOREIGN_KEY_CHECKS = 0");

        // Drop all tables
        foreach ($tables as $table) {
            $this->db->exec("DROP TABLE IF EXISTS `{$table}`");
            $this->log("Dropped: {$table}");
        }

        // Re-enable foreign key checks
        $this->db->exec("SET FOREIGN_KEY_CHECKS = 1");

        $this->log("Running migrations...");
        return $this->migrate();
    }

    /**
     * Get migration status
     * 
     * @return array<array{migration: string, batch: int|null, status: string}>
     */
    public function status(): array
    {
        $this->ensureMigrationsTable();

        $files = $this->getMigrationFiles();
        $run = $this->getRunMigrationsWithBatch();

        $status = [];

        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $status[] = [
                'migration' => $name,
                'batch' => $run[$name] ?? null,
                'status' => isset($run[$name]) ? 'Ran' : 'Pending',
            ];
        }

        return $status;
    }

    // =========================================================================
    // Migration Execution
    // =========================================================================

    /**
     * Run a single migration
     */
    private function runMigration(string $migration, string $method): void
    {
        $file = $this->getMigrationPath($migration);

        if (!file_exists($file)) {
            throw new \RuntimeException("Migration file not found: {$file}");
        }

        $class = require $file;

        if (is_object($class) && method_exists($class, $method)) {
            $class->$method($this->db);
        } elseif (is_array($class) && isset($class[$method])) {
            if (is_callable($class[$method])) {
                $class[$method]($this->db);
            } elseif (is_string($class[$method])) {
                $this->db->exec($class[$method]);
            }
        } else {
            throw new \RuntimeException("Migration {$migration} does not have a {$method} method");
        }
    }

    /**
     * Get migration file path
     */
    private function getMigrationPath(string $migration): string
    {
        return "{$this->migrationsPath}/{$migration}.php";
    }

    // =========================================================================
    // Migration Tracking
    // =========================================================================

    /**
     * Ensure migrations table exists
     */
    private function ensureMigrationsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                batch INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    /**
     * Record a migration as run
     */
    private function recordMigration(string $migration, int $batch): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->migrationsTable} (migration, batch) VALUES (:migration, :batch)"
        );
        $stmt->execute(['migration' => $migration, 'batch' => $batch]);
    }

    /**
     * Remove a migration record
     */
    private function removeMigration(string $migration): void
    {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->migrationsTable} WHERE migration = :migration"
        );
        $stmt->execute(['migration' => $migration]);
    }

    /**
     * Get list of run migrations
     * 
     * @return string[]
     */
    private function getRunMigrations(): array
    {
        $stmt = $this->db->query(
            "SELECT migration FROM {$this->migrationsTable} ORDER BY migration"
        );
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Get run migrations with batch numbers
     * 
     * @return array<string, int>
     */
    private function getRunMigrationsWithBatch(): array
    {
        $stmt = $this->db->query(
            "SELECT migration, batch FROM {$this->migrationsTable}"
        );
        
        $result = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $result[$row['migration']] = (int) $row['batch'];
        }

        return $result;
    }

    /**
     * Get pending migrations
     * 
     * @return string[]
     */
    private function getPendingMigrations(): array
    {
        $files = $this->getMigrationFiles();
        $run = $this->getRunMigrations();

        $pending = [];

        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if (!in_array($name, $run)) {
                $pending[] = $name;
            }
        }

        sort($pending);
        return $pending;
    }

    /**
     * Get all migration files
     * 
     * @return string[]
     */
    private function getMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = glob("{$this->migrationsPath}/*.php");
        return array_map(fn($f) => basename($f), $files ?: []);
    }

    /**
     * Get migrations for a specific batch
     * 
     * @return string[]
     */
    private function getMigrationsForBatch(int $batch): array
    {
        $stmt = $this->db->prepare(
            "SELECT migration FROM {$this->migrationsTable} WHERE batch = :batch ORDER BY migration"
        );
        $stmt->execute(['batch' => $batch]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Get the next batch number
     */
    private function getNextBatchNumber(): int
    {
        return $this->getLastBatchNumber() + 1;
    }

    /**
     * Get the last batch number
     */
    private function getLastBatchNumber(): int
    {
        $stmt = $this->db->query(
            "SELECT MAX(batch) FROM {$this->migrationsTable}"
        );
        return (int) $stmt->fetchColumn();
    }

    // =========================================================================
    // Logging
    // =========================================================================

    /**
     * Log a message
     */
    private function log(string $message): void
    {
        if ($this->output) {
            ($this->output)($message);
        }
    }
}

/**
 * Migration - Base migration class
 */
abstract class Migration
{
    /**
     * Run the migrations
     */
    abstract public function up(\PDO $db): void;

    /**
     * Reverse the migrations
     */
    abstract public function down(\PDO $db): void;

    /**
     * Execute raw SQL
     */
    protected function execute(\PDO $db, string $sql): void
    {
        $db->exec($sql);
    }

    /**
     * Create a table
     */
    protected function createTable(\PDO $db, string $name, array $columns, array $options = []): void
    {
        $defs = [];
        $indexes = [];

        foreach ($columns as $col => $def) {
            if (is_string($def)) {
                $defs[] = "{$col} {$def}";
            } elseif (is_array($def)) {
                $type = $def['type'] ?? 'VARCHAR(255)';
                $nullable = ($def['nullable'] ?? false) ? '' : 'NOT NULL';
                $default = isset($def['default']) ? "DEFAULT " . $this->formatDefault($def['default']) : '';
                $extra = $def['extra'] ?? '';
                
                $defs[] = trim("{$col} {$type} {$nullable} {$default} {$extra}");

                if ($def['index'] ?? false) {
                    $indexes[] = "INDEX idx_{$name}_{$col} ({$col})";
                }
                if ($def['unique'] ?? false) {
                    $indexes[] = "UNIQUE KEY unique_{$name}_{$col} ({$col})";
                }
            }
        }

        // Add primary key
        if (isset($options['primary'])) {
            $pk = is_array($options['primary']) 
                ? implode(', ', $options['primary']) 
                : $options['primary'];
            $defs[] = "PRIMARY KEY ({$pk})";
        }

        // Add foreign keys
        foreach ($options['foreign'] ?? [] as $fk) {
            $defs[] = "FOREIGN KEY ({$fk['column']}) REFERENCES {$fk['references']}({$fk['on']}) " .
                     "ON DELETE " . ($fk['onDelete'] ?? 'CASCADE') .
                     " ON UPDATE " . ($fk['onUpdate'] ?? 'CASCADE');
        }

        // Add additional indexes
        $defs = array_merge($defs, $indexes);

        $sql = "CREATE TABLE {$name} (\n  " . implode(",\n  ", $defs) . "\n)";
        
        if (isset($options['engine'])) {
            $sql .= " ENGINE={$options['engine']}";
        }
        
        if (isset($options['charset'])) {
            $sql .= " DEFAULT CHARSET={$options['charset']}";
        }

        $db->exec($sql);
    }

    /**
     * Drop a table
     */
    protected function dropTable(\PDO $db, string $name): void
    {
        $db->exec("DROP TABLE IF EXISTS {$name}");
    }

    /**
     * Format default value for SQL
     */
    private function formatDefault(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if ($value === 'CURRENT_TIMESTAMP') {
            return $value;
        }
        return "'" . addslashes((string) $value) . "'";
    }
}

/**
 * Schema - Fluent schema builder
 */
class Schema
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Create a table
     */
    public function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        $this->db->exec($blueprint->toSql());
    }

    /**
     * Drop a table
     */
    public function drop(string $table): void
    {
        $this->db->exec("DROP TABLE IF EXISTS `{$table}`");
    }

    /**
     * Drop table if exists
     */
    public function dropIfExists(string $table): void
    {
        $this->drop($table);
    }

    /**
     * Check if table exists
     */
    public function hasTable(string $table): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_name = :table"
        );
        $stmt->execute(['table' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    }
}

/**
 * Blueprint - Table schema definition
 */
class Blueprint
{
    private string $table;
    private array $columns = [];
    private array $indexes = [];
    private array $foreignKeys = [];
    private ?string $primaryKey = null;
    private array $uniqueKeys = [];

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    // Column definitions
    public function id(string $name = 'id'): self
    {
        $this->columns[$name] = 'INT AUTO_INCREMENT PRIMARY KEY';
        return $this;
    }

    public function bigId(string $name = 'id'): self
    {
        $this->columns[$name] = 'BIGINT AUTO_INCREMENT PRIMARY KEY';
        return $this;
    }

    public function string(string $name, int $length = 255): self
    {
        $this->columns[$name] = "VARCHAR({$length})";
        return $this;
    }

    public function text(string $name): self
    {
        $this->columns[$name] = 'TEXT';
        return $this;
    }

    public function longText(string $name): self
    {
        $this->columns[$name] = 'LONGTEXT';
        return $this;
    }

    public function integer(string $name): self
    {
        $this->columns[$name] = 'INT';
        return $this;
    }

    public function bigInteger(string $name): self
    {
        $this->columns[$name] = 'BIGINT';
        return $this;
    }

    public function decimal(string $name, int $precision = 10, int $scale = 2): self
    {
        $this->columns[$name] = "DECIMAL({$precision},{$scale})";
        return $this;
    }

    public function boolean(string $name): self
    {
        $this->columns[$name] = 'TINYINT(1) DEFAULT 0';
        return $this;
    }

    public function date(string $name): self
    {
        $this->columns[$name] = 'DATE';
        return $this;
    }

    public function datetime(string $name): self
    {
        $this->columns[$name] = 'DATETIME';
        return $this;
    }

    public function timestamp(string $name): self
    {
        $this->columns[$name] = 'TIMESTAMP';
        return $this;
    }

    public function timestamps(): self
    {
        $this->columns['created_at'] = 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';
        $this->columns['updated_at'] = 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
        return $this;
    }

    public function softDeletes(): self
    {
        $this->columns['deleted_at'] = 'TIMESTAMP NULL DEFAULT NULL';
        return $this;
    }

    public function json(string $name): self
    {
        $this->columns[$name] = 'JSON';
        return $this;
    }

    public function enum(string $name, array $values): self
    {
        $vals = implode("','", $values);
        $this->columns[$name] = "ENUM('{$vals}')";
        return $this;
    }

    public function foreignId(string $name): self
    {
        $this->columns[$name] = 'INT';
        return $this;
    }

    // Modifiers
    public function nullable(): self
    {
        $keys = array_keys($this->columns);
        $lastKey = end($keys);
        $this->columns[$lastKey] .= ' NULL';
        return $this;
    }

    public function default(mixed $value): self
    {
        $keys = array_keys($this->columns);
        $lastKey = end($keys);
        
        if (is_string($value) && $value !== 'CURRENT_TIMESTAMP') {
            $value = "'{$value}'";
        } elseif (is_null($value)) {
            $value = 'NULL';
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
        }
        
        $this->columns[$lastKey] .= " DEFAULT {$value}";
        return $this;
    }

    public function unique(): self
    {
        $keys = array_keys($this->columns);
        $lastKey = end($keys);
        $this->uniqueKeys[] = $lastKey;
        return $this;
    }

    public function index(string ...$columns): self
    {
        $this->indexes[] = $columns;
        return $this;
    }

    public function foreign(string $column): ForeignKeyDefinition
    {
        $fk = new ForeignKeyDefinition($column);
        $this->foreignKeys[] = $fk;
        return $fk;
    }

    public function primary(string ...$columns): self
    {
        $this->primaryKey = implode(', ', $columns);
        return $this;
    }

    /**
     * Generate SQL
     */
    public function toSql(): string
    {
        $defs = [];

        foreach ($this->columns as $name => $type) {
            $defs[] = "`{$name}` {$type}";
        }

        foreach ($this->uniqueKeys as $col) {
            $defs[] = "UNIQUE KEY `unique_{$this->table}_{$col}` (`{$col}`)";
        }

        foreach ($this->indexes as $cols) {
            $name = 'idx_' . $this->table . '_' . implode('_', $cols);
            $defs[] = "INDEX `{$name}` (`" . implode('`, `', $cols) . "`)";
        }

        foreach ($this->foreignKeys as $fk) {
            $defs[] = $fk->toSql();
        }

        return "CREATE TABLE `{$this->table}` (\n  " . implode(",\n  ", $defs) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    }
}

/**
 * ForeignKeyDefinition - Defines a foreign key constraint
 */
class ForeignKeyDefinition
{
    private string $column;
    private string $references = '';
    private string $on = '';
    private string $onDelete = 'CASCADE';
    private string $onUpdate = 'CASCADE';

    public function __construct(string $column)
    {
        $this->column = $column;
    }

    public function references(string $column): self
    {
        $this->on = $column;
        return $this;
    }

    public function on(string $table): self
    {
        $this->references = $table;
        return $this;
    }

    public function onDelete(string $action): self
    {
        $this->onDelete = $action;
        return $this;
    }

    public function onUpdate(string $action): self
    {
        $this->onUpdate = $action;
        return $this;
    }

    public function nullOnDelete(): self
    {
        $this->onDelete = 'SET NULL';
        return $this;
    }

    public function toSql(): string
    {
        return "FOREIGN KEY (`{$this->column}`) REFERENCES `{$this->references}`(`{$this->on}`) " .
               "ON DELETE {$this->onDelete} ON UPDATE {$this->onUpdate}";
    }
}
