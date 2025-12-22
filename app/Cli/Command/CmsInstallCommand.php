<?php

declare(strict_types=1);

namespace App\Cli\Command;

use App\Installer\InstallerService;
use App\Cms\Modules\ModuleManager;
use App\Cms\Core\SchemaGenerator;
use MonkeysLegion\Database\Connection;

/**
 * CMS Install Command
 *
 * Sets up the CMS with initial data:
 * - System roles
 * - System permissions
 * - Default vocabularies
 * - Navigation menus
 * - Super admin user
 * - Block types system
 * - Content types system
 */
class CmsInstallCommand
{
    private InstallerService $installerService;

    public function __construct(InstallerService $installerService)
    {
        $this->installerService = $installerService;
    }

    private function output(string $message): void
    {
        echo $message;
    }

    /**
     * Execute the install command
     */
    public function __invoke(array $args = []): int
    {
        $this->output("🚀 Installing MonkeysCMS...\n");

        try {
            // Step 1: Create core tables
            $this->output("\n📦 Step 1: Creating database tables...\n");
            $log = $this->installerService->createCoreTables();
            foreach ($log as $line) $this->output("  ✓ $line\n");

            // Step 2: Seed system roles
            $this->output("\n👥 Step 2: Creating system roles...\n");
            $log = $this->installerService->seedRoles();
            foreach ($log as $line) $this->output("  ✓ $line\n");

            // Step 3: Seed system permissions
            $this->output("\n🔐 Step 3: Creating system permissions...\n");
            $log = $this->installerService->seedPermissions();
            foreach ($log as $line) $this->output("  ✓ $line\n");

            // Step 4: Create default content (vocabularies & menus)
            $this->output("\n📂 Step 4: Creating default content...\n");
            $log = $this->installerService->seedContent();
            foreach ($log as $line) $this->output("  ✓ $line\n");

            // Step 5: Create admin user
            $adminEmail = $args['--email'] ?? 'admin@example.com';
            $adminPassword = $args['--password'] ?? null;
            $this->output("\n👤 Step 5: Creating admin user...\n");
            $log = $this->installerService->createAdminUser($adminEmail, $adminPassword);
            foreach ($log as $line) $this->output("  ✓ $line\n");

            // Step 6: Enable Core module
            $this->output("\n✨ Step 6: Enabling Core module...\n");
            $log = $this->installerService->enableCoreModule();
            foreach ($log as $line) $this->output("  ✓ $line\n");

            $this->output("\n✅ MonkeysCMS installed successfully!\n\n");

            return 0;
        } catch (\Exception $e) {
            $this->output("\n❌ Installation failed: " . $e->getMessage() . "\n");
            return 1;
        }
    }



    /**
     * Get command name
     */
    public static function getName(): string
    {
        return 'cms:install';
    }

    /**
     * Get command description
     */
    public static function getDescription(): string
    {
        return 'Install MonkeysCMS with initial data (roles, permissions, admin user)';
    }

    /**
     * Get command usage
     */
    public static function getUsage(): string
    {
        return <<<USAGE
Usage: ./monkeys cms:install [options]

Options:
  --email=EMAIL       Admin email address (default: admin@example.com)
  --password=PASS     Admin password (generated if not provided)

Examples:
  ./monkeys cms:install
  ./monkeys cms:install --email=admin@mysite.com
  ./monkeys cms:install --email=admin@mysite.com --password=SecurePass123
USAGE;
    }
}
