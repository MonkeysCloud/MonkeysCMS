<?php

declare(strict_types=1);

namespace App\Cms\Database\Console;

use App\Cms\Database\MigrationRunner;

/**
 * MigrationCommands - CLI commands for database migrations
 *
 * Commands:
 * - migrate             Run all pending migrations
 * - migrate:rollback    Rollback the last batch
 * - migrate:reset       Rollback all migrations
 * - migrate:fresh       Drop all tables and re-run migrations
 * - migrate:status      Show migration status
 * - migrate:install     Create migrations table
 *
 * Usage:
 * ```bash
 * php cms migrate
 * php cms migrate:rollback
 * php cms migrate:fresh
 * php cms migrate:status
 * ```
 */
class MigrationCommands
{
    private MigrationRunner $runner;

    public function __construct(MigrationRunner $runner)
    {
        $this->runner = $runner;
        $this->runner->setOutput(fn($msg) => $this->info($msg));
    }

    /**
     * Run pending migrations
     */
    public function migrate(): int
    {
        $this->info("Running migrations...\n");

        try {
            $migrated = $this->runner->migrate();

            if (empty($migrated)) {
                $this->info("Nothing to migrate.");
            } else {
                $this->success("\nMigrated " . count($migrated) . " migration(s).");
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
    public function rollback(): int
    {
        $this->info("Rolling back last batch...\n");

        try {
            $rolledBack = $this->runner->rollback();

            if (empty($rolledBack)) {
                $this->info("Nothing to rollback.");
            } else {
                $this->success("\nRolled back " . count($rolledBack) . " migration(s).");
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
    public function reset(): int
    {
        $this->warning("This will rollback ALL migrations. Continue? [y/N] ");

        if (!$this->confirm()) {
            $this->info("Cancelled.");
            return 0;
        }

        $this->info("Resetting all migrations...\n");

        try {
            $rolledBack = $this->runner->reset();

            if (empty($rolledBack)) {
                $this->info("Nothing to reset.");
            } else {
                $this->success("\nReset " . count($rolledBack) . " migration(s).");
            }

            return 0;
        } catch (\Throwable $e) {
            $this->error("Reset failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Fresh migration (drop all + migrate)
     */
    public function fresh(): int
    {
        $this->warning("This will DROP ALL TABLES and re-run migrations. Continue? [y/N] ");

        if (!$this->confirm()) {
            $this->info("Cancelled.");
            return 0;
        }

        $this->info("Running fresh migration...\n");

        try {
            $migrated = $this->runner->fresh();
            $this->success("\nFresh migration complete. Migrated " . count($migrated) . " migration(s).");
            return 0;
        } catch (\Throwable $e) {
            $this->error("Fresh migration failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Show migration status
     */
    public function status(): int
    {
        $this->info("Migration Status\n");
        $this->info(str_repeat("=", 60) . "\n");

        try {
            $status = $this->runner->status();

            if (empty($status)) {
                $this->info("No migrations found.");
                return 0;
            }

            // Table header
            echo sprintf(
                " %-40s | %-8s | %s\n",
                "Migration",
                "Batch",
                "Status"
            );
            echo str_repeat("-", 60) . "\n";

            foreach ($status as $item) {
                $statusColor = $item['status'] === 'Ran' ? "\033[32m" : "\033[33m";
                $reset = "\033[0m";

                echo sprintf(
                    " %-40s | %-8s | %s%s%s\n",
                    $item['migration'],
                    $item['batch'] ?? '-',
                    $statusColor,
                    $item['status'],
                    $reset
                );
            }

            $ran = count(array_filter($status, fn($s) => $s['status'] === 'Ran'));
            $pending = count($status) - $ran;

            echo "\n";
            $this->info("Total: " . count($status) . " migrations");
            $this->success("Ran: {$ran}");
            if ($pending > 0) {
                $this->warning("Pending: {$pending}");
            }

            return 0;
        } catch (\Throwable $e) {
            $this->error("Failed to get status: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Seed the database
     */
    public function seed(?string $seeder = null): int
    {
        $this->info("Running seeders...\n");

        try {
            // TODO: Implement seeder system
            $this->success("Seeding complete.");
            return 0;
        } catch (\Throwable $e) {
            $this->error("Seeding failed: " . $e->getMessage());
            return 1;
        }
    }

    // =========================================================================
    // Output Helpers
    // =========================================================================

    private function info(string $message): void
    {
        echo $message . "\n";
    }

    private function success(string $message): void
    {
        echo "\033[32m{$message}\033[0m\n";
    }

    private function warning(string $message): void
    {
        echo "\033[33m{$message}\033[0m";
    }

    private function error(string $message): void
    {
        echo "\033[31m{$message}\033[0m\n";
    }

    private function confirm(): bool
    {
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        return strtolower(trim($line)) === 'y';
    }

    // =========================================================================
    // Static Runner
    // =========================================================================

    /**
     * Run commands from CLI
     *
     * @param string[] $argv Command line arguments
     */
    public static function run(array $argv, \PDO $db, string $migrationsPath): int
    {
        $runner = new MigrationRunner($db, $migrationsPath);
        $commands = new self($runner);

        $command = $argv[1] ?? 'migrate';

        // Map command to method
        $methodMap = [
            'migrate' => 'migrate',
            'migrate:up' => 'migrate',
            'migrate:rollback' => 'rollback',
            'migrate:down' => 'rollback',
            'migrate:reset' => 'reset',
            'migrate:fresh' => 'fresh',
            'migrate:status' => 'status',
            'db:seed' => 'seed',
        ];

        if (!isset($methodMap[$command])) {
            echo "Unknown command: {$command}\n";
            echo "Available commands:\n";
            foreach (array_keys($methodMap) as $cmd) {
                echo "  {$cmd}\n";
            }
            return 1;
        }

        $method = $methodMap[$command];
        return $commands->$method();
    }
}
