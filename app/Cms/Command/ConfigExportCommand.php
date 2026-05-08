<?php

declare(strict_types=1);

namespace App\Cms\Command;

use App\Cms\Config\ConfigManager;
use MonkeysLegion\Cli\Console\Attributes\Command;
use MonkeysLegion\Cli\Console\Command as BaseCommand;

/**
 * ConfigExportCommand — Export CMS configuration to config/sync/.
 *
 * Usage:
 *   php bin/ml config:export                                   # Export all
 *   php bin/ml config:export --sections=settings,content_type  # Selective
 *   php bin/ml config:export --archive                         # Export as zip
 */
#[Command(
    signature: 'config:export',
    description: 'Export CMS configuration to config/sync/ directory',
)]
final class ConfigExportCommand extends BaseCommand
{
    public function __construct(
        private readonly ConfigManager $manager,
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        $sections = [];

        // Parse --sections=a,b,c from argv
        foreach ($_SERVER['argv'] ?? [] as $arg) {
            if (str_starts_with($arg, '--sections=')) {
                $sections = explode(',', substr($arg, 11));
            }
        }

        $isArchive = in_array('--archive', $_SERVER['argv'] ?? [], true);

        $this->info('🔄 Exporting configuration...');

        // Show available collectors
        $collectors = $this->manager->getAvailableCollectors();
        $this->comment('Available collectors: ' . implode(', ', array_keys($collectors)));

        if (!empty($sections)) {
            $this->comment('Exporting sections: ' . implode(', ', $sections));
        } else {
            $this->comment('Exporting all sections.');
        }

        if ($isArchive) {
            $path = $this->manager->exportArchive($sections);
            $this->info("📦 Archive created: {$path}");
        } else {
            $files = $this->manager->export($sections);
            $syncDir = $this->manager->getSyncDir();

            $this->info("📁 Exported to: {$syncDir}");
            foreach ($files as $file) {
                $this->comment("  ✓ {$file}");
            }
        }

        $this->info('✅ Export complete.');
        return self::SUCCESS;
    }
}
