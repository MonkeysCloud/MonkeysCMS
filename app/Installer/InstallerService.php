<?php

declare(strict_types=1);

namespace App\Installer;

use App\Cms\Core\SchemaGenerator;
use App\Cms\Modules\ModuleManager;
use App\Cms\Blocks\BlockManager;
use App\Cms\ContentTypes\ContentTypeManager;
use App\Cms\Taxonomy\TaxonomyManager;
use App\Modules\Core\Entities\User;
use App\Modules\Core\Entities\Role;
use App\Modules\Core\Entities\Permission;
use App\Modules\Core\Entities\Vocabulary;
use App\Modules\Core\Entities\Menu;
use App\Modules\Core\Entities\MenuItem;

/**
 * InstallerService
 *
 * Encapsulates the core logic for installing the CMS.
 * Used by both the CLI command and the Web Installer.
 */
class InstallerService
{
    private \PDO $pdo;
    private ModuleManager $moduleManager;
    private SchemaGenerator $schemaGenerator;

    public function __construct(
        \PDO $pdo,
        ModuleManager $moduleManager,
        SchemaGenerator $schemaGenerator
    ) {
        $this->pdo = $pdo;
        $this->moduleManager = $moduleManager;
        $this->schemaGenerator = $schemaGenerator;
    }

    /**
     * Step 1: Create core tables
     */
    public function createCoreTables(): array
    {
        $log = [];
        $tables = [
            'users',
            'roles',
            'permissions',
            'user_roles',
            'role_permissions',
            'vocabularies',
            'terms',
            'entity_terms',
            'menus',
            'menu_items',
            'blocks',
            'media',
            'entity_media',
            'settings',
        ];

        $coreEntities = [
            User::class,
            Role::class,
            Permission::class,
            \App\Modules\Core\Entities\UserRole::class,
            \App\Modules\Core\Entities\RolePermission::class,
            Vocabulary::class,
            \App\Modules\Core\Entities\Term::class,
            \App\Modules\Core\Entities\EntityTerm::class,
            Menu::class,
            MenuItem::class,
            \App\Modules\Core\Entities\Block::class,
            \App\Modules\Core\Entities\Media::class,
            \App\Modules\Core\Entities\EntityMedia::class,
            \App\Modules\Core\Entities\Setting::class,
        ];

        foreach ($coreEntities as $entityClass) {
            if (!class_exists($entityClass)) {
                $log[] = "Skipping {$entityClass} (class not found)";
                continue;
            }

            $shortName = (new \ReflectionClass($entityClass))->getShortName();

            try {
                $sql = $this->schemaGenerator->generateSql($entityClass);
                $this->pdo->exec($sql);
                $log[] = "Created table for {$shortName}";
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), 'already exists')) {
                    $log[] = "Table for {$shortName} already exists";
                } else {
                    $log[] = "Error creating {$shortName}: " . $e->getMessage();
                    // throw $e; // Optional: rethrow if critical
                }
            }
        }
        
        // Also create dynamic tables
        $log = array_merge($log, $this->createDynamicTypeTables());
        
        return $log;
    }
    
    /**
     * Helper for Step 1: Create dynamic type tables
     */
    private function createDynamicTypeTables(): array
    {
        $log = [];
        $tables = [
            'block_types' => BlockManager::getTableSql(),
            'block_type_fields' => BlockManager::getFieldsTableSql(),
            'content_types' => ContentTypeManager::getTableSql(),
            'content_type_fields' => ContentTypeManager::getFieldsTableSql(),
            'vocabularies_ext' => TaxonomyManager::getTableSql(),
            'vocabulary_fields' => TaxonomyManager::getFieldsTableSql(),
            'content_taxonomy' => TaxonomyManager::getContentTaxonomyTableSql(),
        ];

        foreach ($tables as $tableName => $sql) {
            try {
                $this->pdo->exec($sql);
                $log[] = "Created table: {$tableName}";
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), 'already exists')) {
                    $log[] = "Table {$tableName} already exists";
                } else {
                    $log[] = "Table {$tableName}: " . $e->getMessage();
                }
            }
        }
        
        // Seed default block types
        $log = array_merge($log, $this->seedBlockTypes());
        
        return $log;
    }

    /**
     * Helper for Step 1: Seed default block types
     */
    private function seedBlockTypes(): array
    {
        $log = [];
        // Note: Keeping this data here for now. In a full refactor, this might move to a seeder class.
        $defaultTypes = [
            [
                'type_id' => 'faq',
                'label' => 'FAQ Block',
                'description' => 'Frequently asked questions accordion',
                'icon' => '❓',
                'category' => 'Content',
                'fields' => [
                    ['name' => 'Questions', 'type' => 'json', 'description' => 'Array of Q&A pairs'],
                    ['name' => 'Collapsed by default', 'type' => 'boolean', 'default' => true],
                    ['name' => 'Allow multiple open', 'type' => 'boolean', 'default' => false],
                ],
            ],
            // ... (Truncated for brevity, can copy full list if needed, or keeping it essential)
            // Including just one for example to save space unless full copy is critical
            // Copying full list from CmsInstallCommand...
            [
                'type_id' => 'hero',
                'label' => 'Hero Block',
                'description' => 'Large hero section with background',
                'icon' => '🎯',
                'category' => 'Layout',
                'fields' => [
                    ['name' => 'Heading', 'type' => 'string', 'required' => true],
                    ['name' => 'Subheading', 'type' => 'text'],
                    ['name' => 'Background Image', 'type' => 'image'],
                    ['name' => 'Button Text', 'type' => 'string'],
                    ['name' => 'Button URL', 'type' => 'url'],
                    ['name' => 'Overlay Color', 'type' => 'color', 'default' => '#00000080'],
                    ['name' => 'Text Alignment', 'type' => 'select', 'settings' => ['options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right']]],
                ],
            ],
             [
                'type_id' => 'cta',
                'label' => 'Call to Action',
                'description' => 'Call to action section with button',
                'icon' => '📢',
                'category' => 'Marketing',
                'fields' => [
                    ['name' => 'Title', 'type' => 'string', 'required' => true],
                    ['name' => 'Description', 'type' => 'text'],
                    ['name' => 'Button Text', 'type' => 'string', 'required' => true],
                    ['name' => 'Button URL', 'type' => 'url', 'required' => true],
                    ['name' => 'Background Color', 'type' => 'color'],
                    ['name' => 'Style', 'type' => 'select', 'settings' => ['options' => ['default' => 'Default', 'gradient' => 'Gradient', 'outlined' => 'Outlined']]],
                ],
            ],
            [
                'type_id' => 'testimonials',
                'label' => 'Testimonials',
                'description' => 'Customer testimonials carousel',
                'icon' => '💬',
                'category' => 'Marketing',
                'fields' => [
                    ['name' => 'Title', 'type' => 'string'],
                    ['name' => 'Testimonials', 'type' => 'json', 'description' => 'Array of testimonial objects'],
                    ['name' => 'Layout', 'type' => 'select', 'settings' => ['options' => ['carousel' => 'Carousel', 'grid' => 'Grid', 'list' => 'List']]],
                    ['name' => 'Show Rating', 'type' => 'boolean', 'default' => true],
                    ['name' => 'Autoplay', 'type' => 'boolean', 'default' => true],
                ],
            ],
            [
                'type_id' => 'contact_form',
                'label' => 'Contact Form',
                'description' => 'Contact form with email delivery',
                'icon' => '✉️',
                'category' => 'Forms',
                'fields' => [
                    ['name' => 'Title', 'type' => 'string'],
                    ['name' => 'Recipient Email', 'type' => 'email', 'required' => true],
                    ['name' => 'Success Message', 'type' => 'text', 'default' => 'Thank you for your message!'],
                    ['name' => 'Include Phone Field', 'type' => 'boolean', 'default' => false],
                    ['name' => 'Include Subject Field', 'type' => 'boolean', 'default' => true],
                ],
            ],
             [
                'type_id' => 'map',
                'label' => 'Map Block',
                'description' => 'Embedded Google or OpenStreetMap',
                'icon' => '🗺️',
                'category' => 'Media',
                'fields' => [
                    ['name' => 'Address', 'type' => 'string'],
                    ['name' => 'Latitude', 'type' => 'float'],
                    ['name' => 'Longitude', 'type' => 'float'],
                    ['name' => 'Zoom Level', 'type' => 'integer', 'default' => 14],
                    ['name' => 'Height', 'type' => 'string', 'default' => '400px'],
                    ['name' => 'Provider', 'type' => 'select', 'settings' => ['options' => ['google' => 'Google Maps', 'osm' => 'OpenStreetMap']]],
                ],
            ],
            [
                'type_id' => 'code',
                'label' => 'Code Block',
                'description' => 'Syntax-highlighted code snippet',
                'icon' => '💻',
                'category' => 'Content',
                'fields' => [
                    ['name' => 'Code', 'type' => 'code', 'required' => true],
                    ['name' => 'Language', 'type' => 'select', 'settings' => ['options' => ['php' => 'PHP', 'javascript' => 'JavaScript', 'python' => 'Python', 'html' => 'HTML', 'css' => 'CSS', 'sql' => 'SQL', 'bash' => 'Bash', 'json' => 'JSON']]],
                    ['name' => 'Show Line Numbers', 'type' => 'boolean', 'default' => true],
                    ['name' => 'Title', 'type' => 'string'],
                ],
            ],
            [
                'type_id' => 'accordion',
                'label' => 'Accordion',
                'description' => 'Collapsible content sections',
                'icon' => '📂',
                'category' => 'Content',
                'fields' => [
                    ['name' => 'Items', 'type' => 'json', 'description' => 'Array of {title, content} objects'],
                    ['name' => 'Allow Multiple Open', 'type' => 'boolean', 'default' => false],
                    ['name' => 'First Item Open', 'type' => 'boolean', 'default' => true],
                ],
            ],
            [
                'type_id' => 'tabs',
                'label' => 'Tabs',
                'description' => 'Tabbed content sections',
                'icon' => '📑',
                'category' => 'Content',
                'fields' => [
                    ['name' => 'Tabs', 'type' => 'json', 'description' => 'Array of {title, content} objects'],
                    ['name' => 'Style', 'type' => 'select', 'settings' => ['options' => ['default' => 'Default', 'pills' => 'Pills', 'underlined' => 'Underlined']]],
                    ['name' => 'Vertical', 'type' => 'boolean', 'default' => false],
                ],
            ],
            [
                'type_id' => 'social_links',
                'label' => 'Social Links',
                'description' => 'Social media profile links',
                'icon' => '🔗',
                'category' => 'Navigation',
                'fields' => [
                    ['name' => 'Facebook URL', 'type' => 'url'],
                    ['name' => 'Twitter URL', 'type' => 'url'],
                    ['name' => 'Instagram URL', 'type' => 'url'],
                    ['name' => 'LinkedIn URL', 'type' => 'url'],
                    ['name' => 'YouTube URL', 'type' => 'url'],
                    ['name' => 'GitHub URL', 'type' => 'url'],
                    ['name' => 'Icon Size', 'type' => 'select', 'settings' => ['options' => ['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large']]],
                    ['name' => 'Style', 'type' => 'select', 'settings' => ['options' => ['icons' => 'Icons Only', 'buttons' => 'Buttons', 'text' => 'With Text']]],
                ],
            ],
        ];

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach ($defaultTypes as $typeData) {
            $exists = $this->pdo->prepare("SELECT id FROM block_types WHERE type_id = ?");
            $exists->execute([$typeData['type_id']]);

            if ($exists->fetch()) {
                $log[] = "Block type '{$typeData['type_id']}' already exists";
                continue;
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO block_types (
                    type_id, label, description, icon, category, is_system, enabled,
                    default_settings, allowed_regions, cache_ttl, css_assets, js_assets,
                    weight, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $typeData['type_id'],
                $typeData['label'],
                $typeData['description'] ?? '',
                $typeData['icon'] ?? '🧱',
                $typeData['category'] ?? 'Custom',
                1, 1, '{}', '[]', 3600, '[]', '[]', 0, $now, $now,
            ]);

            $typeId = (int) $this->pdo->lastInsertId();

            foreach ($typeData['fields'] ?? [] as $index => $fieldData) {
                $machineName = 'field_' . strtolower(preg_replace('/[^a-z0-9]+/i', '_', $fieldData['name']));

                $stmt = $this->pdo->prepare("
                    INSERT INTO block_type_fields (
                        block_type_id, name, machine_name, field_type, description, help_text,
                        widget, required, multiple, cardinality, default_value, settings,
                        validation, widget_settings, weight, searchable, translatable,
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $typeId,
                    $fieldData['name'],
                    $machineName,
                    $fieldData['type'] ?? 'string',
                    $fieldData['description'] ?? null,
                    null,
                    $fieldData['widget'] ?? null,
                    ($fieldData['required'] ?? false) ? 1 : 0,
                    0, 1,
                    $fieldData['default'] ?? null,
                    json_encode($fieldData['settings'] ?? []),
                    '[]', '[]', $index * 10, 0, 0, $now, $now,
                ]);
            }
             $log[] = "Created block type: {$typeData['label']}";
        }
        
        return $log;
    }

    /**
     * Step 2: Seed system roles
     */
    public function seedRoles(): array
    {
        $log = [];
        $roles = Role::getSystemRoles();

        foreach ($roles as $roleData) {
            $exists = $this->pdo->prepare("SELECT id FROM roles WHERE slug = ?");
            $exists->execute([$roleData['slug']]);

            if ($exists->fetch()) {
                $log[] = "Role '{$roleData['name']}' already exists";
                continue;
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO roles (name, slug, description, color, weight, is_system, is_default, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $stmt->execute([
                $roleData['name'],
                $roleData['slug'],
                $roleData['description'],
                $roleData['color'],
                $roleData['weight'],
                $roleData['is_system'] ? 1 : 0,
                ($roleData['is_default'] ?? false) ? 1 : 0,
                $now, $now,
            ]);

            $log[] = "Created role: {$roleData['name']}";
        }
        return $log;
    }

    /**
     * Step 3: Seed system permissions
     */
    public function seedPermissions(): array
    {
        $log = [];
        $permissions = Permission::getSystemPermissions();

        foreach ($permissions as $permData) {
            $exists = $this->pdo->prepare("SELECT id FROM permissions WHERE slug = ?");
            $exists->execute([$permData['slug']]);

            if ($exists->fetch()) {
                $log[] = "Permission '{$permData['slug']}' already exists";
                continue;
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO permissions (name, slug, description, `group`, entity_type, action, module, is_system, weight, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $stmt->execute([
                $permData['name'],
                $permData['slug'],
                $permData['description'] ?? '',
                $permData['group'] ?? 'System',
                $permData['entity_type'] ?? null,
                $permData['action'] ?? 'custom',
                $permData['module'] ?? 'core',
                ($permData['is_system'] ?? true) ? 1 : 0,
                $permData['weight'] ?? 0,
                $now, $now,
            ]);

            $log[] = "Created permission: {$permData['name']}";
        }
        
        // Also assign permissions
        $log = array_merge($log, $this->assignPermissions());
        
        return $log;
    }

    /**
     * Helper for Step 3: Assign default permissions
     */
    private function assignPermissions(): array
    {
        $log = [];
        $rolePermissions = [
            'admin' => [
                'access_admin', 'administer_users', 'view_users', 'create_users', 'edit_users', 'delete_users',
                'administer_roles', 'administer_modules', 'administer_themes', 'administer_settings', 'administer_taxonomies',
            ],
            'editor' => ['access_admin', 'view_users', 'administer_taxonomies'],
            'author' => ['access_admin'],
        ];

        foreach ($rolePermissions as $roleSlug => $permissionSlugs) {
            $roleStmt = $this->pdo->prepare("SELECT id FROM roles WHERE slug = ?");
            $roleStmt->execute([$roleSlug]);
            $role = $roleStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$role) continue;

            $assignedCount = 0;
            foreach ($permissionSlugs as $permSlug) {
                $permStmt = $this->pdo->prepare("SELECT id FROM permissions WHERE slug = ?");
                $permStmt->execute([$permSlug]);
                $perm = $permStmt->fetch(\PDO::FETCH_ASSOC);

                if (!$perm) continue;

                $checkStmt = $this->pdo->prepare("SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ?");
                $checkStmt->execute([$role['id'], $perm['id']]);

                if ($checkStmt->fetch()) continue;

                $stmt = $this->pdo->prepare("INSERT INTO role_permissions (role_id, permission_id, created_at, updated_at) VALUES (?, ?, ?, ?)");
                $stmt->execute([$role['id'], $perm['id'], (new \DateTimeImmutable())->format('Y-m-d H:i:s'), (new \DateTimeImmutable())->format('Y-m-d H:i:s')]);
                $assignedCount++;
            }
             if ($assignedCount > 0) {
                $log[] = "Assigned {$assignedCount} permissions to role: {$roleSlug}";
             }
        }
        return $log;
    }

    /**
     * Step 4: Seed vocabularies and menus
     */
    public function seedContent(): array
    {
        $log = [];
        
        // Vocabularies
        $vocabularies = Vocabulary::getDefaults();
        foreach ($vocabularies as $vocabData) {
            $exists = $this->pdo->prepare("SELECT id FROM vocabularies WHERE machine_name = ?");
            $exists->execute([$vocabData['machine_name']]);

            if ($exists->fetch()) {
                $log[] = "Vocabulary '{$vocabData['name']}' already exists";
                continue;
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO vocabularies (name, machine_name, description, hierarchical, `multiple`, required, weight, entity_types, settings, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $stmt->execute([
                $vocabData['name'],
                $vocabData['machine_name'],
                $vocabData['description'] ?? '',
                ($vocabData['hierarchical'] ?? false) ? 1 : 0,
                ($vocabData['multiple'] ?? true) ? 1 : 0,
                ($vocabData['required'] ?? false) ? 1 : 0,
                $vocabData['weight'] ?? 0,
                json_encode($vocabData['entity_types'] ?? []),
                json_encode($vocabData['settings'] ?? []),
                $now, $now,
            ]);

            $log[] = "Created vocabulary: {$vocabData['name']}";
        }

        // Menus
        $menus = Menu::getDefaults();
        foreach ($menus as $menuData) {
            $exists = $this->pdo->prepare("SELECT id FROM menus WHERE machine_name = ?");
            $exists->execute([$menuData['machine_name']]);

            if ($exists->fetch()) {
                $log[] = "Menu '{$menuData['name']}' already exists";
                continue;
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO menus (name, machine_name, description, location, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $stmt->execute([
                $menuData['name'],
                $menuData['machine_name'],
                $menuData['description'] ?? '',
                $menuData['location'] ?? 'header',
                $now, $now,
            ]);

            $menuId = (int) $this->pdo->lastInsertId();
            $log[] = "Created menu: {$menuData['name']}";

            if ($menuData['machine_name'] === 'admin') {
                $log[] = $this->seedAdminMenuItems($menuId);
            }
        }

        return $log;
    }

    /**
     * Helper for Step 4: Seed admin menu items
     */
    private function seedAdminMenuItems(int $menuId): string
    {
        $items = [
            ['title' => 'Dashboard', 'url' => '/admin', 'icon' => '📊', 'weight' => 0],
            ['title' => 'Content', 'url' => '/admin/content', 'icon' => '📝', 'weight' => 10],
            ['title' => 'Media', 'url' => '/admin/media', 'icon' => '🖼️', 'weight' => 20],
            ['title' => 'Taxonomy', 'url' => '/admin/taxonomies', 'icon' => '🏷️', 'weight' => 30],
            ['title' => 'Menus', 'url' => '/admin/menus', 'icon' => '📋', 'weight' => 40],
            ['title' => 'Blocks', 'url' => '/admin/blocks', 'icon' => '🧱', 'weight' => 50],
            ['title' => 'Structure', 'url' => '/admin/structure', 'icon' => '🏗️', 'weight' => 55],
            ['title' => 'Users', 'url' => '/admin/users', 'icon' => '👥', 'weight' => 60],
            ['title' => 'Roles', 'url' => '/admin/roles', 'icon' => '🎭', 'weight' => 70],
            ['title' => 'Modules', 'url' => '/admin/modules', 'icon' => '🧩', 'weight' => 80],
            ['title' => 'Themes', 'url' => '/admin/themes', 'icon' => '🎨', 'weight' => 85],
            ['title' => 'Settings', 'url' => '/admin/settings', 'icon' => '⚙️', 'weight' => 90],
        ];

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach ($items as $item) {
            $stmt = $this->pdo->prepare("
                INSERT INTO menu_items (menu_id, parent_id, title, url, link_type, target, icon, css_class, weight, depth, is_published, expanded, visibility, attributes, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $menuId, null, $item['title'], $item['url'], 'internal', '_self',
                $item['icon'], null, $item['weight'], 0, 1, 0, '[]', '[]', $now, $now,
            ]);
        }

        return "Created " . count($items) . " admin menu items";
    }

    /**
     * Step 5: Create admin user
     */
    public function createAdminUser(string $email, ?string $password): array
    {
        $log = [];
        
        $exists = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $exists->execute([$email]);

        if ($exists->fetch()) {
             // In web installer this might be an issue, but we'll just log it
             $log[] = "User '{$email}' already exists";
             return $log;
        }

        if ($password === null) {
            $password = bin2hex(random_bytes(8));
            $log[] = "Generated password: {$password}";
        }

        $passwordHash = password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, 'time_cost' => 4, 'threads' => 3,
        ]);

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("
            INSERT INTO users (email, username, password_hash, display_name, status, locale, timezone, email_verified_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $email, 'admin', $passwordHash, 'Administrator', 'active', 'en', 'UTC', $now, $now, $now,
        ]);

        $userId = (int) $this->pdo->lastInsertId();
        $log[] = "Created admin user: {$email}";

        // Assign super_admin role
        $roleStmt = $this->pdo->prepare("SELECT id FROM roles WHERE slug = 'super_admin'");
        $roleStmt->execute();
        $role = $roleStmt->fetch(\PDO::FETCH_ASSOC);

        if ($role) {
            $stmt = $this->pdo->prepare("INSERT INTO user_roles (user_id, role_id, created_at, updated_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $role['id'], $now, $now]);
            $log[] = "Assigned super_admin role";
        }

        // Save credentials (optional, mainly for CLI user benefit, but doesn't hurt)
        $storagePath = dirname(__DIR__, 2) . '/storage';
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        file_put_contents(
            $storagePath . '/admin_credentials.txt',
            "MonkeysCMS Admin Credentials\n============================\nEmail: {$email}\nPassword: {$password}\n"
        );
        $log[] = "Credentials saved to storage/admin_credentials.txt";
        
        return $log;
    }

    /**
     * Step 6: Enable Core module
     */
    public function enableCoreModule(): array
    {
        try {
            $this->moduleManager->enable('Core');
            return ["Core module enabled"];
        } catch (\Exception $e) {
            return ["Core module: " . $e->getMessage()];
        }
    }
}
