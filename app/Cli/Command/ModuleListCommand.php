<?php

declare(strict_types=1);

namespace App\Cli\Command;

use App\Cms\Modules\ModuleManager;
use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;

/**
 * ModuleListCommand - List all CMS modules
 * 
 * Usage: ./monkeys cms:module:list
 */
#[CommandAttr('cms:module:list', 'List all available CMS modules and their status')]
final class ModuleListCommand extends Command
{
    public function __construct(
        private readonly ModuleManager $moduleManager,
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        $modules = $this->moduleManager->getAvailableModules();

        if (empty($modules)) {
            $this->line('No modules found in app/Modules/');
            return self::SUCCESS;
        }

        $this->info('Available CMS Modules:');
        $this->line('');

        // Group by category (Contrib/Custom)
        $grouped = ['Custom' => [], 'Contrib' => [], 'Other' => []];
        
        foreach ($modules as $name => $info) {
            if (str_starts_with($name, 'Custom/')) {
                $grouped['Custom'][$name] = $info;
            } elseif (str_starts_with($name, 'Contrib/')) {
                $grouped['Contrib'][$name] = $info;
            } else {
                $grouped['Other'][$name] = $info;
            }
        }

        foreach ($grouped as $category => $categoryModules) {
            if (empty($categoryModules)) {
                continue;
            }

            $this->line("  {$category}:");
            
            foreach ($categoryModules as $name => $info) {
                $status = ($info['enabled'] ?? false) ? "\033[32m✓\033[0m" : "\033[90m○\033[0m";
                $version = $info['version'] ?? '1.0.0';
                $description = $info['description'] ?? '';
                
                // Format output
                $shortName = str_replace([$category . '/', 'Custom/', 'Contrib/'], '', $name);
                $this->line("    {$status} {$shortName} (v{$version})");
                
                if (!empty($description)) {
                    $this->line("      {$description}");
                }
            }
            
            $this->line('');
        }

        // Summary
        $enabledCount = count($this->moduleManager->getEnabledModules());
        $totalCount = count($modules);
        
        $this->line("Total: {$enabledCount}/{$totalCount} modules enabled");

        return self::SUCCESS;
    }
}
