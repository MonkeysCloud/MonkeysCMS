<?php

declare(strict_types=1);

namespace App\Cli\Command;

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
    private Connection $connection;
    private ModuleManager $moduleManager;
    private SchemaGenerator $schemaGenerator;

    public function __construct(
        Connection $connection,
        ModuleManager $moduleManager,
        SchemaGenerator $schemaGenerator
    ) {
        $this->connection = $connection;
        $this->moduleManager = $moduleManager;
        $this->schemaGenerator = $schemaGenerator;
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
            $this->createCoreTables();

            // Step 1b: Create dynamic type tables
            $this->output("\n🔧 Step 1b: Creating dynamic type tables...\n");
            $this->createDynamicTypeTables();

            // Step 2: Seed system roles
            $this->output("\n👥 Step 2: Creating system roles...\n");
            $this->seedRoles();

            // Step 3: Seed system permissions
            $this->output("\n🔐 Step 3: Creating system permissions...\n");
            $this->seedPermissions();

            // Step 4: Assign permissions to roles
            $this->output("\n🔗 Step 4: Assigning permissions to roles...\n");
            $this->assignPermissions();

            // Step 5: Create default vocabularies
            $this->output("\n📂 Step 5: Creating default taxonomies...\n");
            $this->seedVocabularies();

            // Step 6: Create default menus
            $this->output("\n📋 Step 6: Creating default menus...\n");
            $this->seedMenus();

            // Step 7: Create admin user
            $adminEmail = $args['--email'] ?? 'admin@example.com';
            $adminPassword = $args['--password'] ?? null;
            $this->output("\n👤 Step 7: Creating admin user...\n");
            $this->createAdminUser($adminEmail, $adminPassword);

            // Step 8: Enable Core module
            $this->output("\n✨ Step 8: Enabling Core module...\n");
            $this->enableCoreModule();

            $this->output("\n✅ MonkeysCMS installed successfully!\n\n");

            return 0;
        } catch (\Exception $e) {
            $this->output("\n❌ Installation failed: " . $e->getMessage() . "\n");
            return 1;
        }
    }

    /**
     * Create core database tables
     */
    private function createCoreTables(): void
    {
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
                $this->output("  ⏭️  Skipping {$entityClass} (class not found)\n");
                continue;
            }

            $shortName = (new \ReflectionClass($entityClass))->getShortName();

            try {
                $sql = $this->schemaGenerator->generateCreateTable($entityClass);
                $this->connection->exec($sql);
                $this->output("  ✓ Created table for {$shortName}\n");
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), 'already exists')) {
                    $this->output("  ℹ️  Table for {$shortName} already exists\n");
                } else {
                    throw $e;
                }
            }
        }
    }

    /**
     * Seed system roles
     */
    private function seedRoles(): void
    {
        $roles = Role::getSystemRoles();

        foreach ($roles as $roleData) {
            $exists = $this->connection->prepare(
                "SELECT id FROM roles WHERE slug = ?"
            );
            $exists->execute([$roleData['slug']]);

            if ($exists->fetch()) {
                $this->output("  ℹ️  Role '{$roleData['name']}' already exists\n");
                continue;
            }

            $stmt = $this->connection->prepare("
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
                $now,
                $now,
            ]);

            $this->output("  ✓ Created role: {$roleData['name']}\n");
        }
    }

    /**
     * Seed system permissions
     */
    private function seedPermissions(): void
    {
        $permissions = Permission::getSystemPermissions();

        foreach ($permissions as $permData) {
            $exists = $this->connection->prepare(
                "SELECT id FROM permissions WHERE slug = ?"
            );
            $exists->execute([$permData['slug']]);

            if ($exists->fetch()) {
                $this->output("  ℹ️  Permission '{$permData['slug']}' already exists\n");
                continue;
            }

            $stmt = $this->connection->prepare("
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
                $now,
                $now,
            ]);

            $this->output("  ✓ Created permission: {$permData['name']}\n");
        }
    }

    /**
     * Assign default permissions to roles
     */
    private function assignPermissions(): void
    {
        $rolePermissions = [
            'admin' => [
                'access_admin',
                'administer_users',
                'view_users',
                'create_users',
                'edit_users',
                'delete_users',
                'administer_roles',
                'administer_modules',
                'administer_themes',
                'administer_settings',
                'administer_taxonomies',
            ],
            'editor' => [
                'access_admin',
                'view_users',
                'administer_taxonomies',
            ],
            'author' => [
                'access_admin',
            ],
        ];

        foreach ($rolePermissions as $roleSlug => $permissionSlugs) {
            $roleStmt = $this->connection->prepare("SELECT id FROM roles WHERE slug = ?");
            $roleStmt->execute([$roleSlug]);
            $role = $roleStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$role) {
                continue;
            }

            foreach ($permissionSlugs as $permSlug) {
                $permStmt = $this->connection->prepare("SELECT id FROM permissions WHERE slug = ?");
                $permStmt->execute([$permSlug]);
                $perm = $permStmt->fetch(\PDO::FETCH_ASSOC);

                if (!$perm) {
                    continue;
                }

                // Check if already assigned
                $checkStmt = $this->connection->prepare(
                    "SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ?"
                );
                $checkStmt->execute([$role['id'], $perm['id']]);

                if ($checkStmt->fetch()) {
                    continue;
                }

                $stmt = $this->connection->prepare("
                    INSERT INTO role_permissions (role_id, permission_id, created_at)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([
                    $role['id'],
                    $perm['id'],
                    (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ]);
            }

            $this->output("  ✓ Assigned permissions to role: {$roleSlug}\n");
        }
    }

    /**
     * Seed default vocabularies
     */
    private function seedVocabularies(): void
    {
        $vocabularies = Vocabulary::getDefaults();

        foreach ($vocabularies as $vocabData) {
            $exists = $this->connection->prepare(
                "SELECT id FROM vocabularies WHERE machine_name = ?"
            );
            $exists->execute([$vocabData['machine_name']]);

            if ($exists->fetch()) {
                $this->output("  ℹ️  Vocabulary '{$vocabData['name']}' already exists\n");
                continue;
            }

            $stmt = $this->connection->prepare("
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
                $now,
                $now,
            ]);

            $this->output("  ✓ Created vocabulary: {$vocabData['name']}\n");
        }
    }

    /**
     * Seed default menus
     */
    private function seedMenus(): void
    {
        $menus = Menu::getDefaults();

        foreach ($menus as $menuData) {
            $exists = $this->connection->prepare(
                "SELECT id FROM menus WHERE machine_name = ?"
            );
            $exists->execute([$menuData['machine_name']]);

            if ($exists->fetch()) {
                $this->output("  ℹ️  Menu '{$menuData['name']}' already exists\n");
                continue;
            }

            $stmt = $this->connection->prepare("
                INSERT INTO menus (name, machine_name, description, location, is_active, settings, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $stmt->execute([
                $menuData['name'],
                $menuData['machine_name'],
                $menuData['description'] ?? '',
                $menuData['location'] ?? 'header',
                1,
                json_encode($menuData['settings'] ?? []),
                $now,
                $now,
            ]);

            $menuId = (int) $this->connection->lastInsertId();
            $this->output("  ✓ Created menu: {$menuData['name']}\n");

            // Create default menu items for admin menu
            if ($menuData['machine_name'] === 'admin') {
                $this->seedAdminMenuItems($menuId);
            }
        }
    }

    /**
     * Seed admin menu items
     */
    private function seedAdminMenuItems(int $menuId): void
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
            $stmt = $this->connection->prepare("
                INSERT INTO menu_items (menu_id, parent_id, title, url, route_name, route_params, link_type, target, icon, css_class, weight, depth, is_active, is_expanded, permissions, attributes, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $menuId,
                null,
                $item['title'],
                $item['url'],
                null,
                '[]',
                'internal',
                '_self',
                $item['icon'],
                null,
                $item['weight'],
                0,
                1,
                0,
                '[]',
                '[]',
                $now,
                $now,
            ]);
        }

        $this->output("    ✓ Created " . count($items) . " admin menu items\n");
    }

    /**
     * Create admin user
     */
    private function createAdminUser(string $email, ?string $password): void
    {
        // Check if admin exists
        $exists = $this->connection->prepare(
            "SELECT id FROM users WHERE email = ?"
        );
        $exists->execute([$email]);

        if ($exists->fetch()) {
            $this->output("  ℹ️  User '{$email}' already exists\n");
            return;
        }

        // Generate password if not provided
        if ($password === null) {
            $password = bin2hex(random_bytes(8));
            $this->output("  📝 Generated password: {$password}\n");
        }

        $passwordHash = password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3,
        ]);

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $stmt = $this->connection->prepare("
            INSERT INTO users (email, username, password_hash, display_name, status, locale, timezone, email_verified_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $email,
            'admin',
            $passwordHash,
            'Administrator',
            'active',
            'en',
            'UTC',
            $now,
            $now,
            $now,
        ]);

        $userId = (int) $this->connection->lastInsertId();
        $this->output("  ✓ Created admin user: {$email}\n");

        // Assign super_admin role
        $roleStmt = $this->connection->prepare("SELECT id FROM roles WHERE slug = 'super_admin'");
        $roleStmt->execute();
        $role = $roleStmt->fetch(\PDO::FETCH_ASSOC);

        if ($role) {
            $stmt = $this->connection->prepare("
                INSERT INTO user_roles (user_id, role_id, created_at)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$userId, $role['id'], $now]);
            $this->output("  ✓ Assigned super_admin role\n");
        }

        // Save credentials to file
        $storagePath = dirname(__DIR__, 3) . '/storage';
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        file_put_contents(
            $storagePath . '/admin_credentials.txt',
            "MonkeysCMS Admin Credentials\n" .
            "============================\n" .
            "Email: {$email}\n" .
            "Password: {$password}\n\n" .
            "Delete this file after your first login!\n"
        );
        $this->output("  📄 Credentials saved to storage/admin_credentials.txt\n");
    }

    /**
     * Enable Core module
     */
    private function enableCoreModule(): void
    {
        try {
            $this->moduleManager->enableModule('Core');
            $this->output("  ✓ Core module enabled\n");
        } catch (\Exception $e) {
            $this->output("  ℹ️  Core module: " . $e->getMessage() . "\n");
        }
    }

    /**
     * Create dynamic type tables (block types, content types, vocabulary fields)
     */
    private function createDynamicTypeTables(): void
    {
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
                $this->connection->exec($sql);
                $this->output("  ✓ Created table: {$tableName}\n");
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), 'already exists')) {
                    $this->output("  ℹ️  Table {$tableName} already exists\n");
                } else {
                    // Some tables might fail due to foreign key constraints - log but continue
                    $this->output("  ⚠️  Table {$tableName}: " . $e->getMessage() . "\n");
                }
            }
        }

        // Seed default block types
        $this->seedBlockTypes();
    }

    /**
     * Seed default block types
     */
    private function seedBlockTypes(): void
    {
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
            // Check if exists
            $exists = $this->connection->prepare("SELECT id FROM block_types WHERE type_id = ?");
            $exists->execute([$typeData['type_id']]);

            if ($exists->fetch()) {
                $this->output("  ℹ️  Block type '{$typeData['type_id']}' already exists\n");
                continue;
            }

            // Insert block type
            $stmt = $this->connection->prepare("
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
                1, // is_system
                1, // enabled
                '{}',
                '[]',
                3600,
                '[]',
                '[]',
                0,
                $now,
                $now,
            ]);

            $typeId = (int) $this->connection->lastInsertId();

            // Insert fields
            foreach ($typeData['fields'] ?? [] as $index => $fieldData) {
                $machineName = 'field_' . strtolower(preg_replace('/[^a-z0-9]+/i', '_', $fieldData['name']));

                $stmt = $this->connection->prepare("
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
                    0,
                    1,
                    $fieldData['default'] ?? null,
                    json_encode($fieldData['settings'] ?? []),
                    '[]',
                    '[]',
                    $index * 10,
                    0,
                    0,
                    $now,
                    $now,
                ]);
            }

            $this->output("  ✓ Created block type: {$typeData['label']}\n");
        }
    }

    /**
     * Output message
     */
    private function output(string $message): void
    {
        echo $message;
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
