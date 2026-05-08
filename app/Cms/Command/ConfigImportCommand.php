<?php

declare(strict_types=1);

namespace App\Cms\Command;

use App\Cms\Config\ConfigManager;
use MonkeysLegion\Cli\Console\Attributes\Command;
use MonkeysLegion\Cli\Console\Command as BaseCommand;

/**
 * ConfigImportCommand — Import CMS configuration from config/sync/.
 *
 * Usage:
 *   php bin/ml config:import                         # Import from config/sync/
 *   php bin/ml config:import --source=backup.zip     # Import from archive
 *   php bin/ml config:import --dry-run               # Preview without writing
 *   php bin/ml config:import --force                 # Overwrite existing
 */
#[Command(
    signature: 'config:import',
    description: 'Import CMS configuration from config/sync/ directory',
)]
final class ConfigImportCommand extends BaseCommand
{
    public function __construct(
        private readonly ConfigManager $manager,
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        $argv = $_SERVER['argv'] ?? [];
        $dryRun = in_array('--dry-run', $argv, true);
        $force = in_array('--force', $argv, true);
        $source = null;

        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--source=')) {
                $source = substr($arg, 9);
            }
        }

        if ($dryRun) {
            $this->comment('🔍 Dry-run mode — no changes will be written.');
        }

        if ($source) {
            $this->info("📦 Importing from archive: {$source}");
            $result = $this->manager->importArchive($source, $force);
        } else {
            $syncDir = $this->manager->getSyncDir();
            $this->info("📁 Importing from: {$syncDir}");
            $result = $this->manager->import(overwrite: $force, dryRun: $dryRun);
        }

        // Show results
        foreach ($result->created as $item) {
            $this->info("  + {$item}");
        }
        foreach ($result->updated as $item) {
            $this->comment("  ~ {$item}");
        }
        foreach ($result->skipped as $item) {
            $this->comment("  - {$item} (skipped)");
        }
        foreach ($result->warnings as $warning) {
            $this->comment("  ⚠ {$warning}");
        }
        foreach ($result->errors as $error) {
            $this->error("  ✗ {$error}");
        }

        $summary = $result->toArray()['summary'];
        $this->info("📊 {$summary}");

        return $result->hasErrors() ? self::FAILURE : self::SUCCESS;
    }
}
