<?php

declare(strict_types=1);

namespace App\Cms\Plugin;

use MonkeysLegion\DI\Attributes\Singleton;
use PDO;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * PluginManager — Auto-discovers, loads, and manages MonkeysCMS plugins.
 *
 * Scans two directories (Drupal-style):
 *   plugins/contrib/  — third-party / community plugins
 *   plugins/custom/   — site-specific plugins
 *
 * Each plugin directory must contain a plugin.mlc metadata file
 * and a provider class implementing PluginInterface.
 *
 * Discovery is automatic — no manual registration needed.
 */
#[Singleton]
final class PluginManager
{
    private const string TABLE = 'cms_plugins';

    /** @var array<string, PluginMetadata> All discovered plugins (keyed by machineName) */
    private array $discovered = [];

    /** @var array<string, PluginInterface> Instantiated & registered plugins */
    private array $loaded = [];

    /** @var array<string, array<string, mixed>> DB records (keyed by machineName) */
    private array $dbState = [];

    private bool $booted = false;

    public function __construct(
        private readonly PDO $pdo,
        private readonly HookManager $hooks,
        private readonly string $basePath,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    // ── Discovery ──────────────────────────────────────────────────────

    /**
     * Scan the plugins directory and discover all plugins.
     *
     * Structure: plugins/{custom|contrib}/vendor/name/plugin.mlc
     */
    public function discover(): void
    {
        $this->discovered = [];
        $pluginsDir = $this->basePath . '/plugins';

        foreach (['contrib', 'custom'] as $type) {
            $typeDir = $pluginsDir . '/' . $type;
            if (!is_dir($typeDir)) {
                continue;
            }

            // Scan vendor directories
            foreach (new \DirectoryIterator($typeDir) as $vendorDir) {
                if ($vendorDir->isDot() || !$vendorDir->isDir()) {
                    continue;
                }

                // Scan plugin directories within each vendor
                foreach (new \DirectoryIterator($vendorDir->getPathname()) as $pluginDir) {
                    if ($pluginDir->isDot() || !$pluginDir->isDir()) {
                        continue;
                    }

                    // Look for {name}.plugin.mlc (Drupal-style naming)
                    $mlcPath = null;
                    foreach (new \DirectoryIterator($pluginDir->getPathname()) as $file) {
                        if (!$file->isDot() && str_ends_with($file->getFilename(), '.plugin.mlc')) {
                            $mlcPath = $file->getPathname();
                            break;
                        }
                    }

                    if ($mlcPath === null) {
                        $this->logger?->warning('Plugin directory missing *.plugin.mlc', [
                            'path' => $pluginDir->getPathname(),
                        ]);
                        continue;
                    }

                    try {
                        $content  = file_get_contents($mlcPath);
                        $metadata = PluginMetadata::fromMlc($content, $pluginDir->getPathname(), $type);
                        $this->discovered[$metadata->machineName] = $metadata;
                    } catch (\Throwable $e) {
                        $this->logger?->error('Failed to parse plugin.mlc', [
                            'path'  => $mlcPath,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        $this->loadDbState();
    }

    // ── Boot Sequence ──────────────────────────────────────────────────

    /**
     * Load and register all enabled plugins.
     *
     * Call order: discover() → loadEnabled() → bootAll()
     */
    public function loadEnabled(ContainerInterface $container): void
    {
        foreach ($this->discovered as $name => $metadata) {
            if (!$this->isEnabled($name)) {
                continue;
            }

            try {
                $plugin = $this->instantiate($metadata);
                $plugin->register($container, $this->hooks);
                $this->loaded[$name] = $plugin;

                // Register plugin's PSR-4 autoload
                $this->registerAutoload($metadata);

            } catch (\Throwable $e) {
                $this->logger?->error('Failed to load plugin', [
                    'plugin' => $name,
                    'error'  => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Boot all loaded plugins (called after all register() calls complete).
     */
    public function bootAll(ContainerInterface $container): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->loaded as $name => $plugin) {
            try {
                $plugin->boot($container);
            } catch (\Throwable $e) {
                $this->logger?->error('Failed to boot plugin', [
                    'plugin' => $name,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        $this->booted = true;
    }

    // ── Lifecycle Actions ──────────────────────────────────────────────

    /**
     * Enable (activate) a plugin.
     */
    public function enable(string $machineName, ContainerInterface $container): bool
    {
        $metadata = $this->discovered[$machineName] ?? null;
        if (!$metadata) {
            return false;
        }

        // Check dependencies
        if (!$this->checkDependencies($metadata)) {
            return false;
        }

        // Insert or update DB record
        $this->upsertDbRecord($metadata, true);

        // Instantiate and activate
        try {
            $plugin = $this->instantiate($metadata);
            $plugin->activate($container);
            $this->loaded[$machineName] = $plugin;
            return true;
        } catch (\Throwable $e) {
            $this->logger?->error('Failed to activate plugin', [
                'plugin' => $machineName,
                'error'  => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Disable (deactivate) a plugin. Data is preserved.
     */
    public function disable(string $machineName, ContainerInterface $container): bool
    {
        // Deactivate if loaded
        if (isset($this->loaded[$machineName])) {
            try {
                $this->loaded[$machineName]->deactivate($container);
            } catch (\Throwable $e) {
                $this->logger?->error('Failed to deactivate plugin', [
                    'plugin' => $machineName,
                    'error'  => $e->getMessage(),
                ]);
            }
            unset($this->loaded[$machineName]);
        }

        // Update DB
        $stmt = $this->pdo->prepare(
            "UPDATE " . self::TABLE . " SET enabled = 0, updated_at = NOW() WHERE vendor = :vendor AND name = :name"
        );
        $metadata = $this->discovered[$machineName] ?? null;
        if ($metadata) {
            $stmt->execute([':vendor' => $metadata->vendor, ':name' => $metadata->name]);
        }

        return true;
    }

    /**
     * Fully uninstall a plugin (remove data).
     */
    public function uninstall(string $machineName, ContainerInterface $container): bool
    {
        $metadata = $this->discovered[$machineName] ?? null;
        if (!$metadata) {
            return false;
        }

        // Call uninstall lifecycle
        try {
            $plugin = $this->instantiate($metadata);
            $plugin->uninstall($container);
        } catch (\Throwable $e) {
            $this->logger?->error('Failed to uninstall plugin', [
                'plugin' => $machineName,
                'error'  => $e->getMessage(),
            ]);
        }

        // Remove DB record
        $stmt = $this->pdo->prepare(
            "DELETE FROM " . self::TABLE . " WHERE vendor = :vendor AND name = :name"
        );
        $stmt->execute([':vendor' => $metadata->vendor, ':name' => $metadata->name]);

        unset($this->loaded[$machineName], $this->discovered[$machineName]);

        return true;
    }

    // ── Queries ────────────────────────────────────────────────────────

    /**
     * Get all discovered plugins with their status.
     *
     * @return list<array{metadata: PluginMetadata, enabled: bool, installed: bool}>
     */
    public function getAll(): array
    {
        $result = [];
        foreach ($this->discovered as $name => $metadata) {
            $dbRecord = $this->dbState[$name] ?? null;
            $result[] = [
                'metadata'  => $metadata,
                'enabled'   => $dbRecord !== null && (bool) ($dbRecord['enabled'] ?? false),
                'installed' => $dbRecord !== null,
            ];
        }

        // Sort: custom first, then contrib; alphabetically within each
        usort($result, static function (array $a, array $b): int {
            $typeOrder = ['custom' => 0, 'contrib' => 1];
            $ta = $typeOrder[$a['metadata']->type] ?? 2;
            $tb = $typeOrder[$b['metadata']->type] ?? 2;
            return $ta <=> $tb ?: $a['metadata']->machineName <=> $b['metadata']->machineName;
        });

        return $result;
    }

    /**
     * Get a single plugin's metadata.
     */
    public function get(string $machineName): ?PluginMetadata
    {
        return $this->discovered[$machineName] ?? null;
    }

    /**
     * Check if a plugin is enabled.
     */
    public function isEnabled(string $machineName): bool
    {
        $record = $this->dbState[$machineName] ?? null;
        return $record !== null && (bool) ($record['enabled'] ?? false);
    }

    /**
     * Get the HookManager instance.
     */
    public function getHookManager(): HookManager
    {
        return $this->hooks;
    }

    /**
     * Get all loaded (active) plugins.
     *
     * @return array<string, PluginInterface>
     */
    public function getLoaded(): array
    {
        return $this->loaded;
    }

    // ── Internal ───────────────────────────────────────────────────────

    private function loadDbState(): void
    {
        $this->dbState = [];

        try {
            $rows = $this->pdo->query("SELECT * FROM " . self::TABLE)->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $key = ($row['vendor'] ?? '') . '/' . ($row['name'] ?? '');
                $this->dbState[$key] = $row;
            }
        } catch (\PDOException) {
            // Table may not exist yet (pre-migration)
        }
    }

    private function instantiate(PluginMetadata $metadata): PluginInterface
    {
        $providerClass = $metadata->provider;

        // Auto-load if class doesn't exist yet
        if (!class_exists($providerClass)) {
            $this->registerAutoload($metadata);
        }

        if (!class_exists($providerClass)) {
            throw new \RuntimeException("Plugin provider class not found: {$providerClass}");
        }

        $plugin = new $providerClass();

        if (!$plugin instanceof PluginInterface) {
            throw new \RuntimeException("Plugin {$metadata->machineName}: provider must implement PluginInterface");
        }

        if ($plugin instanceof AbstractPlugin) {
            $plugin->metadata = $metadata;
        }

        return $plugin;
    }

    private function registerAutoload(PluginMetadata $metadata): void
    {
        if (empty($metadata->namespace) || empty($metadata->path)) {
            return;
        }

        $srcDir   = rtrim($metadata->path, '/') . '/src/';
        $nsPrefix = rtrim($metadata->namespace, '\\') . '\\';

        spl_autoload_register(static function (string $class) use ($nsPrefix, $srcDir): void {
            if (!str_starts_with($class, $nsPrefix)) {
                return;
            }

            $relative = substr($class, strlen($nsPrefix));
            $file     = $srcDir . str_replace('\\', '/', $relative) . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        });
    }

    private function checkDependencies(PluginMetadata $metadata): bool
    {
        foreach ($metadata->requires as $requirement) {
            // Format: "vendor/name:>=1.0.0" or "core:>=2.0.0"
            $parts = explode(':', $requirement, 2);
            $depName = $parts[0] ?? '';

            if ($depName === 'core') {
                continue; // Core version check — skip for now
            }

            // Check if dependency plugin is enabled
            if (!$this->isEnabled($depName)) {
                $this->logger?->warning('Plugin dependency not met', [
                    'plugin'     => $metadata->machineName,
                    'requires'   => $requirement,
                ]);
                return false;
            }
        }

        return true;
    }

    private function upsertDbRecord(PluginMetadata $metadata, bool $enabled): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (name, vendor, version, namespace, path, enabled, installed_at)
            VALUES (:name, :vendor, :version, :namespace, :path, :enabled, NOW())
            ON DUPLICATE KEY UPDATE
                version    = VALUES(version),
                namespace  = VALUES(namespace),
                path       = VALUES(path),
                enabled    = VALUES(enabled),
                updated_at = NOW()
        ");

        $stmt->execute([
            ':name'      => $metadata->name,
            ':vendor'    => $metadata->vendor,
            ':version'   => $metadata->version,
            ':namespace' => $metadata->namespace,
            ':path'      => $metadata->path,
            ':enabled'   => $enabled ? 1 : 0,
        ]);

        // Refresh DB state
        $this->loadDbState();
    }
}
