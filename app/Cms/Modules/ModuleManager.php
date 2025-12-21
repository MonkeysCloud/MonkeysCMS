<?php

declare(strict_types=1);

namespace App\Cms\Modules;

use App\Cms\Attributes\ContentType;
use App\Cms\Core\SchemaGenerator;
use MonkeysLegion\Database\Connection;
use MonkeysLegion\Mlc\Parser;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;

/**
 * ModuleManager - Central hub for CMS module lifecycle management
 * 
 * This class handles:
 * - Enabling/disabling modules via API or admin UI
 * - Auto-discovery of module entities
 * - Automatic database schema synchronization (the "auto-sync" feature)
 * - Module dependency resolution
 * - Module state persistence
 * 
 * Key improvements over Drupal:
 * - No hook_install() or hook_update_N() - schema syncs automatically from entities
 * - No drush updb needed - just enable the module
 * - Instant schema changes, no waiting for cron
 * 
 * Key improvements over WordPress:
 * - Proper module isolation (namespaces, not global functions)
 * - No activation hooks that can fail silently
 * - Real dependency management
 * 
 * @example
 * ```php
 * $moduleManager = $container->get(ModuleManager::class);
 * $moduleManager->enable('Ecommerce'); // Automatically creates all tables
 * ```
 */
final class ModuleManager
{
    /**
     * Path to modules directory
     */
    private string $modulesBasePath;

    /**
     * Path to modules state file
     */
    private string $modulesStatePath;

    /**
     * Cached module states
     * @var array<string, array<string, mixed>>
     */
    private array $moduleStates = [];

    /**
     * Available module paths
     * @var array<string, string>
     */
    private array $availableModules = [];

    /**
     * @param SchemaGenerator $schemaGenerator SQL generator from entity classes
     * @param Connection $database Database connection for executing SQL
     * @param LoggerInterface|null $logger Optional PSR-3 logger
     * @param EventDispatcherInterface|null $dispatcher Optional event dispatcher
     * @param string $basePath Application base path
     */
    public function __construct(
        private readonly SchemaGenerator $schemaGenerator,
        private readonly Connection $database,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?EventDispatcherInterface $dispatcher = null,
        string $basePath = '',
    ) {
        $this->modulesBasePath = $basePath ?: (defined('ML_BASE_PATH') ? ML_BASE_PATH : getcwd());
        $this->modulesStatePath = $this->modulesBasePath . '/storage/modules.json';
        
        $this->loadModuleStates();
        $this->discoverAvailableModules();
    }

    /**
     * Enable a module and sync its database schema
     * 
     * @param string $moduleName Module name (e.g., 'Ecommerce', 'Contrib/SeoPack')
     * @param bool $syncSchema Whether to automatically sync database schema
     * @return bool True if module was enabled successfully
     * @throws ModuleException If module not found or dependencies not met
     */
    public function enable(string $moduleName, bool $syncSchema = true): bool
    {
        $this->log('info', "Enabling module: {$moduleName}");

        // Normalize module name
        $moduleName = $this->normalizeModuleName($moduleName);

        // Check if module exists
        if (!$this->moduleExists($moduleName)) {
            throw new ModuleException("Module '{$moduleName}' not found");
        }

        // Check if already enabled
        if ($this->isEnabled($moduleName)) {
            $this->log('info', "Module '{$moduleName}' is already enabled");
            return true;
        }

        // Load module metadata
        $metadata = $this->getModuleMetadata($moduleName);

        // Check dependencies
        $this->checkDependencies($moduleName, $metadata);

        // Dispatch pre-enable event
        $this->dispatchEvent('cms.module.pre_enable', [
            'module' => $moduleName,
            'metadata' => $metadata,
        ]);

        // Discover and sync entities
        if ($syncSchema) {
            $entities = $this->discoverEntities($moduleName);
            foreach ($entities as $entityClass) {
                $this->syncEntitySchema($entityClass);
            }
        }

        // Run module's Loader if exists
        $this->runModuleLoader($moduleName, 'onEnable');

        // Update module state
        $this->moduleStates[$moduleName] = [
            'enabled' => true,
            'enabled_at' => date('c'),
            'version' => $metadata['version'] ?? '1.0.0',
        ];
        $this->saveModuleStates();

        // Dispatch post-enable event
        $this->dispatchEvent('cms.module.enabled', [
            'module' => $moduleName,
        ]);

        $this->log('info', "Module '{$moduleName}' enabled successfully");

        return true;
    }

    /**
     * Disable a module
     * 
     * @param string $moduleName Module name
     * @param bool $removeTables Whether to drop module tables (dangerous!)
     * @return bool True if module was disabled successfully
     */
    public function disable(string $moduleName, bool $removeTables = false): bool
    {
        $moduleName = $this->normalizeModuleName($moduleName);

        if (!$this->isEnabled($moduleName)) {
            return true;
        }

        // Check if other modules depend on this one
        $dependents = $this->findDependentModules($moduleName);
        if (!empty($dependents)) {
            throw new ModuleException(
                "Cannot disable '{$moduleName}': required by " . implode(', ', $dependents)
            );
        }

        // Dispatch pre-disable event
        $this->dispatchEvent('cms.module.pre_disable', [
            'module' => $moduleName,
        ]);

        // Run module's Loader
        $this->runModuleLoader($moduleName, 'onDisable');

        // Optionally remove tables (dangerous - requires explicit confirmation)
        if ($removeTables) {
            $this->removeModuleTables($moduleName);
        }

        // Update state
        $this->moduleStates[$moduleName]['enabled'] = false;
        $this->moduleStates[$moduleName]['disabled_at'] = date('c');
        $this->saveModuleStates();

        // Dispatch post-disable event
        $this->dispatchEvent('cms.module.disabled', [
            'module' => $moduleName,
        ]);

        $this->log('info', "Module '{$moduleName}' disabled");

        return true;
    }

    /**
     * Discover all entity classes in a module's Entities folder
     * 
     * @param string $moduleName Module name
     * @return array<string> List of fully qualified entity class names
     */
    public function discoverEntities(string $moduleName): array
    {
        $modulePath = $this->getModulePath($moduleName);
        $entitiesPath = $modulePath . '/Entities';

        if (!is_dir($entitiesPath)) {
            $this->log('debug', "No Entities folder found for module: {$moduleName}");
            return [];
        }

        $entities = [];
        $namespace = $this->getModuleNamespace($moduleName);

        // Scan for PHP files
        $files = glob($entitiesPath . '/*.php');
        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);
            $fqcn = $namespace . '\\Entities\\' . $className;

            // Verify class exists and has ContentType attribute
            if ($this->loadClassIfNotLoaded($fqcn, $file)) {
                if (class_exists($fqcn)) {
                    $reflection = new ReflectionClass($fqcn);
                    $attrs = $reflection->getAttributes(ContentType::class);
                    
                    if (!empty($attrs)) {
                        $entities[] = $fqcn;
                        $this->log('debug', "Discovered entity: {$fqcn}");
                    }
                }
            }
        }

        return $entities;
    }

    /**
     * Synchronize database schema for a single entity
     * 
     * @param string $entityClass Fully qualified entity class name
     */
    public function syncEntitySchema(string $entityClass): void
    {
        $this->log('info', "Syncing schema for entity: {$entityClass}");

        try {
            $sql = $this->schemaGenerator->generateSql($entityClass);
            
            // Execute each statement
            $statements = array_filter(
                explode(';', $sql),
                fn(string $s) => !empty(trim($s))
            );

            foreach ($statements as $statement) {
                $this->database->pdo()->exec(trim($statement) . ';');
                $this->log('debug', "Executed: " . substr(trim($statement), 0, 100) . '...');
            }
        } catch (\Exception $e) {
            $this->log('error', "Schema sync failed for {$entityClass}: " . $e->getMessage());
            throw new ModuleException("Failed to sync schema for {$entityClass}: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if a module is enabled
     */
    public function isEnabled(string $moduleName): bool
    {
        $moduleName = $this->normalizeModuleName($moduleName);
        return ($this->moduleStates[$moduleName]['enabled'] ?? false) === true;
    }

    /**
     * Check if a module exists in the filesystem
     */
    public function moduleExists(string $moduleName): bool
    {
        $moduleName = $this->normalizeModuleName($moduleName);
        return isset($this->availableModules[$moduleName]);
    }

    /**
     * Get list of all available modules
     * 
     * @return array<string, array<string, mixed>> Module name => metadata
     */
    public function getAvailableModules(): array
    {
        $modules = [];
        
        foreach ($this->availableModules as $name => $path) {
            $modules[$name] = array_merge(
                $this->getModuleMetadata($name),
                [
                    'enabled' => $this->isEnabled($name),
                    'path' => $path,
                ]
            );
        }

        return $modules;
    }

    /**
     * Get list of enabled modules
     * 
     * @return array<string>
     */
    public function getEnabledModules(): array
    {
        return array_keys(
            array_filter(
                $this->moduleStates,
                fn(array $state) => ($state['enabled'] ?? false) === true
            )
        );
    }

    /**
     * Discover all available modules in the Modules directory
     */
    private function discoverAvailableModules(): void
    {
        $this->availableModules = [];
        
        $modulesPath = $this->modulesBasePath . '/app/Modules';

        // Scan Contrib modules
        $contribPath = $modulesPath . '/Contrib';
        if (is_dir($contribPath)) {
            $this->scanModulesDirectory($contribPath, 'Contrib/');
        }

        // Scan Custom modules
        $customPath = $modulesPath . '/Custom';
        if (is_dir($customPath)) {
            $this->scanModulesDirectory($customPath, 'Custom/');
        }

        // Also support flat module structure (backwards compatibility)
        $this->scanModulesDirectory($modulesPath, '');
    }

    /**
     * Scan a directory for module folders
     */
    private function scanModulesDirectory(string $path, string $prefix): void
    {
        $dirs = glob($path . '/*', GLOB_ONLYDIR);
        if ($dirs === false) {
            return;
        }

        foreach ($dirs as $dir) {
            $dirName = basename($dir);
            
            // Skip Contrib/Custom folders when scanning flat
            if ($prefix === '' && in_array($dirName, ['Contrib', 'Custom'], true)) {
                continue;
            }

            // Check for module.mlc, module.json, or Loader.php
            if (file_exists($dir . '/module.mlc') || 
                file_exists($dir . '/module.json') || 
                file_exists($dir . '/Loader.php')) {
                $moduleName = $prefix . $dirName;
                $this->availableModules[$moduleName] = $dir;
            }
        }
    }

    /**
     * Get the filesystem path for a module
     */
    private function getModulePath(string $moduleName): string
    {
        $moduleName = $this->normalizeModuleName($moduleName);
        
        if (!isset($this->availableModules[$moduleName])) {
            throw new ModuleException("Module '{$moduleName}' not found");
        }

        return $this->availableModules[$moduleName];
    }

    /**
     * Get module metadata from module.mlc or module.json
     * 
     * @return array<string, mixed>
     */
    private function getModuleMetadata(string $moduleName): array
    {
        $modulePath = $this->getModulePath($moduleName);
        $mlcFile = $modulePath . '/module.mlc';
        $jsonFile = $modulePath . '/module.json';

        // Try .mlc first, then fall back to .json
        if (file_exists($mlcFile)) {
            return $this->parseMlcFile($mlcFile);
        }

        if (file_exists($jsonFile)) {
            $content = file_get_contents($jsonFile);
            if ($content === false) {
                return [];
            }
            return json_decode($content, true) ?? [];
        }

        return [
            'name' => basename($modulePath),
            'version' => '1.0.0',
            'description' => '',
            'dependencies' => [],
        ];
    }

    /**
     * Parse MLC configuration file
     */
    private function parseMlcFile(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return [];
        }
        
        $parser = new Parser();
        return $parser->parse($content);
    }

    /**
     * Get the PHP namespace for a module
     */
    private function getModuleNamespace(string $moduleName): string
    {
        // Convert module path to namespace
        // Custom/Ecommerce => App\Modules\Custom\Ecommerce
        $parts = explode('/', $moduleName);
        return 'App\\Modules\\' . implode('\\', $parts);
    }

    /**
     * Normalize module name (handle different path separators)
     */
    private function normalizeModuleName(string $moduleName): string
    {
        return str_replace(['\\', '/'], '/', trim($moduleName, '\\/'));
    }

    /**
     * Check module dependencies
     * 
     * @throws ModuleException If dependencies are not met
     */
    private function checkDependencies(string $moduleName, array $metadata): void
    {
        $dependencies = $metadata['dependencies'] ?? [];

        foreach ($dependencies as $dependency) {
            if (!$this->isEnabled($dependency)) {
                throw new ModuleException(
                    "Module '{$moduleName}' requires '{$dependency}' to be enabled first"
                );
            }
        }
    }

    /**
     * Find modules that depend on a given module
     * 
     * @return array<string>
     */
    private function findDependentModules(string $moduleName): array
    {
        $dependents = [];

        foreach ($this->getEnabledModules() as $enabled) {
            if ($enabled === $moduleName) {
                continue;
            }

            $metadata = $this->getModuleMetadata($enabled);
            $deps = $metadata['dependencies'] ?? [];

            if (in_array($moduleName, $deps, true)) {
                $dependents[] = $enabled;
            }
        }

        return $dependents;
    }

    /**
     * Run a module's Loader class method
     */
    private function runModuleLoader(string $moduleName, string $method): void
    {
        $namespace = $this->getModuleNamespace($moduleName);
        $loaderClass = $namespace . '\\Loader';

        if (!class_exists($loaderClass)) {
            $loaderFile = $this->getModulePath($moduleName) . '/Loader.php';
            if (file_exists($loaderFile)) {
                require_once $loaderFile;
            }
        }

        if (class_exists($loaderClass) && method_exists($loaderClass, $method)) {
            $loader = new $loaderClass();
            $loader->$method();
        }
    }

    /**
     * Remove all tables for a module's entities
     */
    private function removeModuleTables(string $moduleName): void
    {
        $entities = $this->discoverEntities($moduleName);

        foreach ($entities as $entityClass) {
            if (!class_exists($entityClass)) {
                continue;
            }

            $reflection = new ReflectionClass($entityClass);
            $attrs = $reflection->getAttributes(ContentType::class);
            
            if (!empty($attrs)) {
                $contentType = $attrs[0]->newInstance();
                $tableName = $contentType->tableName;

                // Drop revision table if exists
                if ($contentType->revisionable) {
                    $this->database->pdo()->exec("DROP TABLE IF EXISTS `{$tableName}_revision`;");
                }

                // Drop main table
                $this->database->pdo()->exec("DROP TABLE IF EXISTS `{$tableName}`;");
                
                $this->log('warning', "Dropped table: {$tableName}");
            }
        }
    }

    /**
     * Load class file if not already loaded
     */
    private function loadClassIfNotLoaded(string $fqcn, string $filePath): bool
    {
        if (class_exists($fqcn)) {
            return true;
        }

        if (file_exists($filePath)) {
            require_once $filePath;
            return class_exists($fqcn);
        }

        return false;
    }

    /**
     * Load module states from JSON file
     */
    private function loadModuleStates(): void
    {
        if (!file_exists($this->modulesStatePath)) {
            $this->moduleStates = [];
            return;
        }

        $content = file_get_contents($this->modulesStatePath);
        if ($content === false) {
            $this->moduleStates = [];
            return;
        }

        $this->moduleStates = json_decode($content, true) ?? [];
    }

    /**
     * Save module states to JSON file
     */
    private function saveModuleStates(): void
    {
        $dir = dirname($this->modulesStatePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->modulesStatePath,
            json_encode($this->moduleStates, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Log a message
     */
    private function log(string $level, string $message): void
    {
        $this->logger?->$level("[ModuleManager] {$message}");
    }

    /**
     * Dispatch an event
     */
    private function dispatchEvent(string $eventName, array $payload): void
    {
        // This would dispatch to PSR-14 event dispatcher
        // Implementation depends on your event system
    }
}
