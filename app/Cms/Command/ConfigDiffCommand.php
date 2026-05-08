<?php

declare(strict_types=1);

namespace App\Cms\Command;

use App\Cms\Config\ConfigManager;
use MonkeysLegion\Cli\Console\Attributes\Command;
use MonkeysLegion\Cli\Console\Command as BaseCommand;

/**
 * ConfigDiffCommand — Show differences between config/sync/ and active database.
 *
 * Usage:
 *   php bin/ml config:diff       # Show all differences
 */
#[Command(
    signature: 'config:diff',
    description: 'Show differences between config/sync/ and active database config',
)]
final class ConfigDiffCommand extends BaseCommand
{
    public function __construct(
        private readonly ConfigManager $manager,
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        $this->info('🔍 Comparing config/sync/ with active database...');

        $diff = $this->manager->diff();

        if (empty($diff)) {
            $this->info('✅ No differences found. Config is in sync.');
            return self::SUCCESS;
        }

        $creates = 0;
        $updates = 0;
        $orphans = 0;

        foreach ($diff as $key => $entry) {
            $status = $entry['status'];

            match ($status) {
                'create' => (function () use ($key, &$creates) {
                    $this->info("  + {$key} (would be created)");
                    $creates++;
                })(),
                'update' => (function () use ($key, &$updates) {
                    $this->comment("  ~ {$key} (would be updated)");
                    $updates++;
                })(),
                'orphan' => (function () use ($key, &$orphans) {
                    $this->comment("  ? {$key} (in DB but not in sync/)");
                    $orphans++;
                })(),
                default  => null,
            };
        }

        $this->info('');
        $this->info("📊 {$creates} to create, {$updates} to update, {$orphans} orphaned.");
        $this->comment('Run `php bin/ml config:import` to apply changes.');
        $this->comment('Run `php bin/ml config:import --force` to overwrite existing items.');

        return self::SUCCESS;
    }
}
