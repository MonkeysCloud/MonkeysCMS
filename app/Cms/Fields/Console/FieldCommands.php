<?php

declare(strict_types=1);

namespace App\Cms\Fields\Console;

use App\Cms\Fields\Database\FieldMigration;
use App\Cms\Fields\FieldRepository;
use App\Cms\Fields\Widget\WidgetFactory;
use App\Cms\Fields\Widget\WidgetRegistry;

/**
 * FieldCommands - CLI commands for field management
 * 
 * Usage:
 *   php artisan fields:migrate       - Run field migrations
 *   php artisan fields:list          - List all fields
 *   php artisan fields:create        - Create a new field
 *   php artisan fields:delete        - Delete a field
 *   php artisan fields:widgets       - List available widgets
 *   php artisan fields:export        - Export field definitions
 *   php artisan fields:import        - Import field definitions
 */
class FieldCommands
{
    private \PDO $db;
    private ?FieldRepository $repository = null;
    private ?WidgetRegistry $widgets = null;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get field repository
     */
    private function getRepository(): FieldRepository
    {
        if (!$this->repository) {
            $this->repository = new FieldRepository($this->db);
        }
        return $this->repository;
    }

    /**
     * Get widget registry
     */
    private function getWidgets(): WidgetRegistry
    {
        if (!$this->widgets) {
            $this->widgets = WidgetFactory::create();
        }
        return $this->widgets;
    }

    /**
     * Run field migrations
     */
    public function migrate(array $options = []): int
    {
        $migration = new FieldMigration($this->db);

        $action = $options['action'] ?? 'up';

        try {
            switch ($action) {
                case 'up':
                    $this->output("Running field migrations...");
                    $migration->up();
                    $this->output("Migrations completed successfully.", 'success');
                    break;

                case 'down':
                    $this->output("Rolling back field migrations...");
                    $migration->down();
                    $this->output("Rollback completed successfully.", 'success');
                    break;

                case 'seed':
                    $this->output("Seeding default fields...");
                    $migration->seed();
                    $this->output("Seeding completed successfully.", 'success');
                    break;

                case 'fresh':
                    $this->output("Running fresh migration...");
                    $migration->down();
                    $migration->up();
                    $migration->seed();
                    $this->output("Fresh migration completed successfully.", 'success');
                    break;

                default:
                    $this->output("Unknown action: {$action}", 'error');
                    return 1;
            }

            return 0;
        } catch (\Exception $e) {
            $this->output("Error: " . $e->getMessage(), 'error');
            return 1;
        }
    }

    /**
     * List all fields
     */
    public function list(array $options = []): int
    {
        try {
            $fields = $this->getRepository()->findAll();

            if (empty($fields)) {
                $this->output("No fields found.");
                return 0;
            }

            $format = $options['format'] ?? 'table';

            if ($format === 'json') {
                echo json_encode(array_map(fn($f) => $f->toArray(), $fields), JSON_PRETTY_PRINT);
                return 0;
            }

            // Table format
            $this->output("\n" . str_repeat('=', 80));
            $this->output(sprintf(
                "%-5s %-20s %-25s %-15s %-8s",
                "ID", "Name", "Machine Name", "Type", "Required"
            ));
            $this->output(str_repeat('-', 80));

            foreach ($fields as $field) {
                $this->output(sprintf(
                    "%-5d %-20s %-25s %-15s %-8s",
                    $field->getId() ?? 0,
                    substr($field->getName(), 0, 20),
                    $field->getMachineName(),
                    $field->getType(),
                    $field->isRequired() ? 'Yes' : 'No'
                ));
            }

            $this->output(str_repeat('=', 80));
            $this->output(sprintf("Total: %d fields\n", count($fields)));

            return 0;
        } catch (\Exception $e) {
            $this->output("Error: " . $e->getMessage(), 'error');
            return 1;
        }
    }

    /**
     * Create a new field
     */
    public function create(array $options = []): int
    {
        try {
            $name = $options['name'] ?? $this->prompt("Field name");
            $machineName = $options['machine_name'] ?? $this->prompt(
                "Machine name",
                $this->slugify($name)
            );
            $type = $options['type'] ?? $this->prompt("Field type", "string");
            $description = $options['description'] ?? $this->prompt("Description", "");
            $required = isset($options['required']) 
                ? (bool) $options['required'] 
                : $this->confirm("Required?", false);

            // Create field definition
            $field = new \App\Cms\Fields\FieldDefinition([
                'name' => $name,
                'machine_name' => 'field_' . $machineName,
                'field_type' => $type,
                'description' => $description,
                'required' => $required,
            ]);

            // Save to database
            $this->getRepository()->save($field);

            $this->output("\nField created successfully!", 'success');
            $this->output("  ID: " . $field->getId());
            $this->output("  Machine Name: " . $field->getMachineName());

            return 0;
        } catch (\Exception $e) {
            $this->output("Error: " . $e->getMessage(), 'error');
            return 1;
        }
    }

    /**
     * Delete a field
     */
    public function delete(array $options = []): int
    {
        try {
            $id = $options['id'] ?? null;
            $machineName = $options['machine_name'] ?? null;

            if (!$id && !$machineName) {
                $this->output("Please provide --id or --machine_name", 'error');
                return 1;
            }

            $repo = $this->getRepository();

            $field = $id 
                ? $repo->find((int) $id) 
                : $repo->findByMachineName($machineName);

            if (!$field) {
                $this->output("Field not found.", 'error');
                return 1;
            }

            if (!$this->confirm("Delete field '{$field->getName()}'?")) {
                $this->output("Cancelled.");
                return 0;
            }

            $repo->delete($field->getId());
            $this->output("Field deleted successfully.", 'success');

            return 0;
        } catch (\Exception $e) {
            $this->output("Error: " . $e->getMessage(), 'error');
            return 1;
        }
    }

    /**
     * List available widgets
     */
    public function widgets(array $options = []): int
    {
        try {
            $widgets = $this->getWidgets();
            $all = $widgets->all();

            $type = $options['type'] ?? null;
            $category = $options['category'] ?? null;

            // Group by category
            $grouped = [];
            foreach ($all as $widget) {
                if ($type && !in_array($type, $widget->getSupportedTypes())) {
                    continue;
                }

                $cat = $widget->getCategory();
                if ($category && strtolower($cat) !== strtolower($category)) {
                    continue;
                }

                $grouped[$cat][] = $widget;
            }

            if (empty($grouped)) {
                $this->output("No widgets found matching criteria.");
                return 0;
            }

            foreach ($grouped as $cat => $widgetList) {
                $this->output("\n{$cat}:");
                $this->output(str_repeat('-', 60));

                foreach ($widgetList as $widget) {
                    $this->output(sprintf(
                        "  %s %-20s - %s",
                        $widget->getIcon(),
                        $widget->getId(),
                        $widget->getLabel()
                    ));

                    if (isset($options['verbose'])) {
                        $types = implode(', ', $widget->getSupportedTypes());
                        $this->output("       Types: {$types}");
                    }
                }
            }

            return 0;
        } catch (\Exception $e) {
            $this->output("Error: " . $e->getMessage(), 'error');
            return 1;
        }
    }

    /**
     * Export field definitions to JSON
     */
    public function export(array $options = []): int
    {
        try {
            $fields = $this->getRepository()->findAll();
            $file = $options['file'] ?? 'fields.json';

            $data = [
                'version' => '1.0',
                'exported_at' => date('c'),
                'fields' => array_map(fn($f) => $f->toArray(), $fields),
            ];

            if ($file === 'stdout') {
                echo json_encode($data, JSON_PRETTY_PRINT);
            } else {
                file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
                $this->output("Exported " . count($fields) . " fields to {$file}", 'success');
            }

            return 0;
        } catch (\Exception $e) {
            $this->output("Error: " . $e->getMessage(), 'error');
            return 1;
        }
    }

    /**
     * Import field definitions from JSON
     */
    public function import(array $options = []): int
    {
        try {
            $file = $options['file'] ?? 'fields.json';

            if (!file_exists($file)) {
                $this->output("File not found: {$file}", 'error');
                return 1;
            }

            $content = file_get_contents($file);
            $data = json_decode($content, true);

            if (!$data || !isset($data['fields'])) {
                $this->output("Invalid import file format.", 'error');
                return 1;
            }

            $repo = $this->getRepository();
            $imported = 0;
            $skipped = 0;

            foreach ($data['fields'] as $fieldData) {
                $machineName = $fieldData['machine_name'] ?? null;

                // Check if exists
                if ($machineName && $repo->findByMachineName($machineName)) {
                    if (!isset($options['force'])) {
                        $this->output("  Skipping existing field: {$machineName}");
                        $skipped++;
                        continue;
                    }
                }

                // Remove ID for new import
                unset($fieldData['id']);

                $field = new \App\Cms\Fields\FieldDefinition($fieldData);
                $repo->save($field);
                $imported++;

                $this->output("  Imported: {$machineName}");
            }

            $this->output("\nImport complete: {$imported} imported, {$skipped} skipped", 'success');

            return 0;
        } catch (\Exception $e) {
            $this->output("Error: " . $e->getMessage(), 'error');
            return 1;
        }
    }

    /**
     * Show field details
     */
    public function show(array $options = []): int
    {
        try {
            $id = $options['id'] ?? null;
            $machineName = $options['machine_name'] ?? null;

            if (!$id && !$machineName) {
                $this->output("Please provide --id or --machine_name", 'error');
                return 1;
            }

            $repo = $this->getRepository();
            $field = $id 
                ? $repo->find((int) $id) 
                : $repo->findByMachineName($machineName);

            if (!$field) {
                $this->output("Field not found.", 'error');
                return 1;
            }

            $this->output("\nField Details:");
            $this->output(str_repeat('=', 50));
            $this->output("ID:           " . $field->getId());
            $this->output("Name:         " . $field->getName());
            $this->output("Machine Name: " . $field->getMachineName());
            $this->output("Type:         " . $field->getType());
            $this->output("Widget:       " . ($field->getWidget() ?? 'default'));
            $this->output("Required:     " . ($field->isRequired() ? 'Yes' : 'No'));
            $this->output("Multiple:     " . ($field->isMultiple() ? 'Yes' : 'No'));
            $this->output("Searchable:   " . ($field->isSearchable() ? 'Yes' : 'No'));
            $this->output("Description:  " . ($field->getDescription() ?? 'N/A'));

            if ($field->getSettings()) {
                $this->output("\nSettings:");
                $this->output(json_encode($field->getSettings(), JSON_PRETTY_PRINT));
            }

            if ($field->getWidgetSettings()) {
                $this->output("\nWidget Settings:");
                $this->output(json_encode($field->getWidgetSettings(), JSON_PRETTY_PRINT));
            }

            return 0;
        } catch (\Exception $e) {
            $this->output("Error: " . $e->getMessage(), 'error');
            return 1;
        }
    }

    /**
     * Attach a field to an entity type
     */
    public function attach(array $options = []): int
    {
        try {
            $fieldId = $options['field_id'] ?? $this->prompt("Field ID");
            $entityType = $options['entity_type'] ?? $this->prompt("Entity type");
            $bundleId = $options['bundle_id'] ?? $this->prompt("Bundle ID (optional)", null);

            $repo = $this->getRepository();
            $field = $repo->find((int) $fieldId);

            if (!$field) {
                $this->output("Field not found.", 'error');
                return 1;
            }

            $repo->attachToEntity(
                (int) $fieldId,
                $entityType,
                $bundleId ? (int) $bundleId : null
            );

            $this->output("Field attached successfully.", 'success');

            return 0;
        } catch (\Exception $e) {
            $this->output("Error: " . $e->getMessage(), 'error');
            return 1;
        }
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Output text with optional style
     */
    private function output(string $text, string $style = ''): void
    {
        $colors = [
            'error' => "\033[31m",
            'success' => "\033[32m",
            'warning' => "\033[33m",
            'info' => "\033[34m",
            'reset' => "\033[0m",
        ];

        if ($style && isset($colors[$style])) {
            echo $colors[$style] . $text . $colors['reset'] . "\n";
        } else {
            echo $text . "\n";
        }
    }

    /**
     * Prompt for input
     */
    private function prompt(string $question, ?string $default = null): ?string
    {
        $suffix = $default !== null ? " [{$default}]" : "";
        echo "{$question}{$suffix}: ";
        
        $input = trim(fgets(STDIN));
        
        return $input !== '' ? $input : $default;
    }

    /**
     * Confirm yes/no
     */
    private function confirm(string $question, bool $default = false): bool
    {
        $suffix = $default ? " [Y/n]" : " [y/N]";
        echo "{$question}{$suffix}: ";
        
        $input = strtolower(trim(fgets(STDIN)));
        
        if ($input === '') {
            return $default;
        }
        
        return in_array($input, ['y', 'yes', '1', 'true']);
    }

    /**
     * Convert string to slug
     */
    private function slugify(string $text): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $text));
    }
}

/**
 * Simple CLI runner for standalone use
 */
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($argv[0] ?? '')) {
    // Parse arguments
    $command = $argv[1] ?? 'help';
    $options = [];

    for ($i = 2; $i < count($argv); $i++) {
        $arg = $argv[$i];
        
        if (str_starts_with($arg, '--')) {
            $parts = explode('=', substr($arg, 2), 2);
            $options[$parts[0]] = $parts[1] ?? true;
        }
    }

    // Database connection
    $dsn = $options['dsn'] ?? getenv('DATABASE_URL') ?? 'mysql:host=localhost;dbname=monkeyscms';
    $user = $options['user'] ?? getenv('DATABASE_USER') ?? 'root';
    $pass = $options['pass'] ?? getenv('DATABASE_PASS') ?? '';

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $commands = new FieldCommands($pdo);

        $result = match ($command) {
            'migrate' => $commands->migrate($options),
            'list' => $commands->list($options),
            'create' => $commands->create($options),
            'delete' => $commands->delete($options),
            'widgets' => $commands->widgets($options),
            'export' => $commands->export($options),
            'import' => $commands->import($options),
            'show' => $commands->show($options),
            'attach' => $commands->attach($options),
            default => (function() use ($command) {
                echo "Unknown command: {$command}\n\n";
                echo "Available commands:\n";
                echo "  migrate    - Run database migrations\n";
                echo "  list       - List all fields\n";
                echo "  create     - Create a new field\n";
                echo "  delete     - Delete a field\n";
                echo "  show       - Show field details\n";
                echo "  widgets    - List available widgets\n";
                echo "  export     - Export fields to JSON\n";
                echo "  import     - Import fields from JSON\n";
                echo "  attach     - Attach field to entity type\n";
                return 1;
            })(),
        };

        exit($result);
    } catch (PDOException $e) {
        echo "Database error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
