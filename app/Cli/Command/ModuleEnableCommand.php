<?php

declare(strict_types=1);

namespace App\Cli\Command;

use App\Cms\Core\SchemaGenerator;
use App\Cms\Modules\ModuleManager;
use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;

/**
 * ModuleEnableCommand - Enable a CMS module via CLI
 *
 * Usage: ./monkeys cms:module:enable ModuleName
 *
 * This command:
 * 1. Discovers the module in app/Modules
 * 2. Finds all entity classes with #[ContentType]
 * 3. Generates and executes CREATE TABLE statements
 * 4. Marks the module as enabled in modules.json
 */
#[CommandAttr('cms:module:enable', 'Enable a CMS module and sync its database schema')]
final class ModuleEnableCommand extends Command
{
    public function __construct(
        private readonly ModuleManager $moduleManager,
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        $moduleName = $this->argument(0);

        if (empty($moduleName)) {
            $this->error('Module name is required.');
            $this->line('Usage: cms:module:enable <ModuleName>');
            $this->line('');
            $this->line('Available modules:');

            foreach ($this->moduleManager->getAvailableModules() as $name => $info) {
                $status = $info['enabled'] ? '✓' : ' ';
                $this->line("  [{$status}] {$name}");
            }

            return self::FAILURE;
        }

        $this->info("Enabling module: {$moduleName}");

        try {
            // Check if module exists
            if (!$this->moduleManager->moduleExists($moduleName)) {
                $this->error("Module '{$moduleName}' not found.");
                return self::FAILURE;
            }

            // Check if already enabled
            if ($this->moduleManager->isEnabled($moduleName)) {
                $this->line("Module '{$moduleName}' is already enabled.");
                return self::SUCCESS;
            }

            // Enable the module (with auto-sync)
            $this->moduleManager->enable($moduleName, syncSchema: true);

            // Show discovered entities
            $entities = $this->moduleManager->discoverEntities($moduleName);
            if (!empty($entities)) {
                $this->success("Module enabled with " . count($entities) . " entities:");
                foreach ($entities as $entity) {
                    $this->line("  • {$entity}");
                }
            } else {
                $this->success("Module enabled (no entities found).");
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to enable module: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
