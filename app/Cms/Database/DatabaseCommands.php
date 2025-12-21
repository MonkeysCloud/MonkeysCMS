<?php

declare(strict_types=1);

namespace App\Cms\Database;

/**
 * DatabaseCommands - CLI commands for database operations
 * 
 * Usage:
 *   php DatabaseCommands.php migrate              Run pending migrations
 *   php DatabaseCommands.php migrate:rollback     Rollback last batch
 *   php DatabaseCommands.php migrate:reset        Rollback all migrations
 *   php DatabaseCommands.php migrate:fresh        Drop all tables and re-migrate
 *   php DatabaseCommands.php migrate:status       Show migration status
 *   php DatabaseCommands.php db:seed              Run database seeders
 */
class DatabaseCommands
{
    private \PDO $db;
    private MigrationRunner $runner;
    private string $migrationsPath;

    public function __construct(\PDO $db, string $migrationsPath)
    {
        $this->db = $db;
        $this->migrationsPath = $migrationsPath;
        $this->runner = new MigrationRunner($db, $migrationsPath);
        
        // Set output callback
        $this->runner->setOutput(function(string $message) {
            $this->output($message);
        });
    }

    /**
     * Run command from CLI arguments
     */
    public function run(array $args): int
    {
        $command = $args[1] ?? 'help';
        $options = array_slice($args, 2);

        return match ($command) {
            'migrate' => $this->migrate($options),
            'migrate:rollback' => $this->rollback($options),
            'migrate:reset' => $this->reset($options),
            'migrate:fresh' => $this->fresh($options),
            'migrate:status' => $this->status($options),
            'db:seed' => $this->seed($options),
            'db:create' => $this->createDatabase($options),
            'help', '--help', '-h' => $this->help(),
            default => $this->unknown($command),
        };
    }

    /**
     * Run pending migrations
     */
    public function migrate(array $options = []): int
    {
        $this->info("Running migrations...");
        
        try {
            $migrated = $this->runner->migrate();
            
            if (empty($migrated)) {
                $this->info("Nothing to migrate.");
            } else {
                $this->success(sprintf("Migrated %d migration(s).", count($migrated)));
            }
            
            return 0;
        } catch (\Throwable $e) {
            $this->error("Migration failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Rollback last batch
     */
    public function rollback(array $options = []): int
    {
        $this->info("Rolling back last batch...");
        
        try {
            $rolledBack = $this->runner->rollback();
            
            if (empty($rolledBack)) {
                $this->info("Nothing to rollback.");
            } else {
                $this->success(sprintf("Rolled back %d migration(s).", count($rolledBack)));
            }
            
            return 0;
        } catch (\Throwable $e) {
            $this->error("Rollback failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Reset all migrations
     */
    public function reset(array $options = []): int
    {
        if (!$this->confirm("This will rollback ALL migrations. Continue?")) {
            $this->info("Cancelled.");
            return 0;
        }

        $this->info("Resetting all migrations...");
        
        try {
            $rolledBack = $this->runner->reset();
            $this->success(sprintf("Reset %d migration(s).", count($rolledBack)));
            return 0;
        } catch (\Throwable $e) {
            $this->error("Reset failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Fresh migration (drop all + migrate)
     */
    public function fresh(array $options = []): int
    {
        if (!$this->confirm("This will DROP ALL TABLES and re-run migrations. Continue?")) {
            $this->info("Cancelled.");
            return 0;
        }

        $this->info("Dropping all tables and re-running migrations...");
        
        try {
            $migrated = $this->runner->fresh();
            $this->success(sprintf("Fresh migration completed. Ran %d migration(s).", count($migrated)));
            
            // Optionally run seeders
            if (in_array('--seed', $options)) {
                $this->seed([]);
            }
            
            return 0;
        } catch (\Throwable $e) {
            $this->error("Fresh migration failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Show migration status
     */
    public function status(array $options = []): int
    {
        $this->info("Migration Status:");
        $this->output("");
        
        try {
            $status = $this->runner->status();
            
            if (empty($status)) {
                $this->info("No migrations found.");
                return 0;
            }

            // Table header
            $this->output(sprintf("%-50s %-10s %-8s", "Migration", "Batch", "Status"));
            $this->output(str_repeat("-", 70));

            foreach ($status as $item) {
                $batchStr = $item['batch'] !== null ? (string) $item['batch'] : '';
                $statusStr = $item['status'] === 'Ran' ? "\033[32mRan\033[0m" : "\033[33mPending\033[0m";
                $this->output(sprintf("%-50s %-10s %-8s", $item['migration'], $batchStr, $statusStr));
            }

            return 0;
        } catch (\Throwable $e) {
            $this->error("Failed to get status: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Run database seeders
     */
    public function seed(array $options = []): int
    {
        $this->info("Running seeders...");
        
        $seederPath = dirname($this->migrationsPath) . '/seeders';
        
        if (!is_dir($seederPath)) {
            $this->info("No seeders directory found.");
            return 0;
        }

        $files = glob("{$seederPath}/*.php");
        $count = 0;

        foreach ($files as $file) {
            $seeder = require $file;
            
            if (is_callable($seeder)) {
                $this->output("Seeding: " . basename($file));
                $seeder($this->db);
                $count++;
            } elseif (is_object($seeder) && method_exists($seeder, 'run')) {
                $this->output("Seeding: " . basename($file));
                $seeder->run($this->db);
                $count++;
            }
        }

        $this->success(sprintf("Ran %d seeder(s).", $count));
        return 0;
    }

    /**
     * Create database
     */
    public function createDatabase(array $options = []): int
    {
        $dbName = $options[0] ?? null;
        
        if (!$dbName) {
            $this->error("Database name required: php DatabaseCommands.php db:create <name>");
            return 1;
        }

        try {
            $this->db->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->success("Database '{$dbName}' created.");
            return 0;
        } catch (\Throwable $e) {
            $this->error("Failed to create database: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Show help
     */
    public function help(): int
    {
        $this->output("
MonkeysCMS Database Commands

Usage:
  php DatabaseCommands.php <command> [options]

Commands:
  migrate              Run pending migrations
  migrate:rollback     Rollback last batch of migrations
  migrate:reset        Rollback all migrations
  migrate:fresh        Drop all tables and re-run migrations
  migrate:status       Show migration status
  db:seed              Run database seeders
  db:create <name>     Create a new database
  help                 Show this help message

Options:
  --seed               Run seeders after fresh migration

Examples:
  php DatabaseCommands.php migrate
  php DatabaseCommands.php migrate:fresh --seed
  php DatabaseCommands.php migrate:status
");
        return 0;
    }

    /**
     * Handle unknown command
     */
    private function unknown(string $command): int
    {
        $this->error("Unknown command: {$command}");
        $this->output("Run 'php DatabaseCommands.php help' for usage.");
        return 1;
    }

    // =========================================================================
    // Output Helpers
    // =========================================================================

    private function output(string $message): void
    {
        echo $message . PHP_EOL;
    }

    private function info(string $message): void
    {
        echo "\033[34m{$message}\033[0m" . PHP_EOL;
    }

    private function success(string $message): void
    {
        echo "\033[32m✓ {$message}\033[0m" . PHP_EOL;
    }

    private function error(string $message): void
    {
        echo "\033[31m✗ {$message}\033[0m" . PHP_EOL;
    }

    private function confirm(string $question): bool
    {
        echo "\033[33m{$question} [y/N] \033[0m";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        return strtolower(trim($line)) === 'y';
    }

    // =========================================================================
    // Static Factory
    // =========================================================================

    /**
     * Create from config file
     */
    public static function createFromConfig(string $configPath): self
    {
        if (!file_exists($configPath)) {
            throw new \RuntimeException("Config file not found: {$configPath}");
        }

        $config = require $configPath;
        $dbConfig = $config['database'] ?? [];

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $dbConfig['host'] ?? 'localhost',
            $dbConfig['port'] ?? 3306,
            $dbConfig['database'] ?? 'monkeyscms',
            $dbConfig['charset'] ?? 'utf8mb4'
        );

        $pdo = new \PDO(
            $dsn,
            $dbConfig['username'] ?? 'root',
            $dbConfig['password'] ?? ''
        );
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $migrationsPath = $config['migrations']['path'] ?? __DIR__ . '/migrations';

        return new self($pdo, $migrationsPath);
    }
}

// =========================================================================
// CLI Runner
// =========================================================================

if (php_sapi_name() === 'cli' && realpath($argv[0]) === __FILE__) {
    // Load config
    $configPath = dirname(__DIR__, 3) . '/config/database.php';
    
    if (!file_exists($configPath)) {
        // Create default config
        $config = [
            'host' => getenv('DB_HOST') ?: 'localhost',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'database' => getenv('DB_DATABASE') ?: 'monkeyscms',
            'username' => getenv('DB_USERNAME') ?: 'root',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
        ];
    } else {
        $config = require $configPath;
    }

    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'] ?? 'localhost',
            $config['port'] ?? 3306,
            $config['database'] ?? 'monkeyscms',
            $config['charset'] ?? 'utf8mb4'
        );

        $pdo = new \PDO($dsn, $config['username'] ?? 'root', $config['password'] ?? '');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $commands = new DatabaseCommands($pdo, __DIR__ . '/migrations');
        exit($commands->run($argv));
    } catch (\PDOException $e) {
        echo "\033[31mDatabase connection failed: " . $e->getMessage() . "\033[0m" . PHP_EOL;
        exit(1);
    }
}
