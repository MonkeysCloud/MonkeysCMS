<?php

declare(strict_types=1);

namespace App\Modules\Core;

use App\Modules\Core\Entities\Role;
use App\Modules\Core\Entities\Permission;
use App\Modules\Core\Entities\Vocabulary;
use App\Modules\Core\Entities\User;
use MonkeysLegion\Database\Connection;

/**
 * Core Module Loader
 *
 * Handles initialization of core CMS functionality including:
 * - System roles creation
 * - System permissions creation
 * - Default vocabularies creation
 * - Super admin user creation
 */
class Loader
{
    private ?Connection $connection = null;

    /**
     * Called when module is enabled
     */
    public function onEnable(): void
    {
        // Core module setup happens in seed()
    }

    /**
     * Called when module is disabled
     */
    public function onDisable(): void
    {
        // Core module should not be disabled
        throw new \RuntimeException('Core module cannot be disabled');
    }

    /**
     * Get module dependencies
     */
    public function getDependencies(): array
    {
        return []; // Core has no dependencies
    }

    /**
     * Get services provided by this module
     */
    public function getServices(): array
    {
        return [
            'permission' => \App\Cms\Security\PermissionService::class,
            'taxonomy' => \App\Modules\Core\Services\TaxonomyService::class,
        ];
    }

    /**
     * Seed initial data after schema sync
     */
    public function seed(Connection $connection): void
    {
        $this->connection = $connection;

        // Create system roles
        $this->seedRoles();

        // Create system permissions
        $this->seedPermissions();

        // Assign permissions to roles
        $this->assignDefaultPermissions();

        // Create default vocabularies
        $this->seedVocabularies();

        // Create default menus
        $this->seedMenus();

        // Create default settings
        $this->seedSettings();

        // Create default admin user if none exists
        $this->seedAdminUser();
    }

    /**
     * Create system roles
     */
    private function seedRoles(): void
    {
        $roles = Role::getSystemRoles();

        foreach ($roles as $roleData) {
            // Check if role exists
            $stmt = $this->connection->prepare(
                "SELECT id FROM roles WHERE slug = :slug"
            );
            $stmt->execute(['slug' => $roleData['slug']]);

            if ($stmt->fetch()) {
                continue; // Already exists
            }

            $stmt = $this->connection->prepare("
                INSERT INTO roles (name, slug, description, color, weight, is_system, is_default, created_at, updated_at)
                VALUES (:name, :slug, :description, :color, :weight, :is_system, :is_default, :created_at, :updated_at)
            ");
            $stmt->execute([
                'name' => $roleData['name'],
                'slug' => $roleData['slug'],
                'description' => $roleData['description'],
                'color' => $roleData['color'],
                'weight' => $roleData['weight'],
                'is_system' => $roleData['is_system'] ? 1 : 0,
                'is_default' => ($roleData['is_default'] ?? false) ? 1 : 0,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Create system permissions
     */
    private function seedPermissions(): void
    {
        $permissions = Permission::getSystemPermissions();

        foreach ($permissions as $permData) {
            // Check if permission exists
            $stmt = $this->connection->prepare(
                "SELECT id FROM permissions WHERE slug = :slug"
            );
            $stmt->execute(['slug' => $permData['slug']]);

            if ($stmt->fetch()) {
                continue; // Already exists
            }

            $stmt = $this->connection->prepare("
                INSERT INTO permissions (name, slug, description, `group`, entity_type, action, module, is_system, weight, created_at, updated_at)
                VALUES (:name, :slug, :description, :group, :entity_type, :action, :module, :is_system, :weight, :created_at, :updated_at)
            ");
            $stmt->execute([
                'name' => $permData['name'],
                'slug' => $permData['slug'],
                'description' => $permData['description'] ?? '',
                'group' => $permData['group'] ?? 'System',
                'entity_type' => $permData['entity_type'] ?? null,
                'action' => $permData['action'] ?? 'custom',
                'module' => $permData['module'] ?? 'core',
                'is_system' => ($permData['is_system'] ?? true) ? 1 : 0,
                'weight' => $permData['weight'] ?? 0,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Assign default permissions to roles
     */
    private function assignDefaultPermissions(): void
    {
        $rolePermissions = [
            // Administrator gets all permissions except super_admin specific ones
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
            // Editor can manage content but not users/settings
            'editor' => [
                'access_admin',
                'view_users',
                'administer_taxonomies',
            ],
            // Author can create/edit own content
            'author' => [
                'access_admin',
            ],
            // Authenticated users have minimal access
            'authenticated' => [],
        ];

        foreach ($rolePermissions as $roleSlug => $permissionSlugs) {
            // Get role ID
            $stmt = $this->connection->prepare(
                "SELECT id FROM roles WHERE slug = :slug"
            );
            $stmt->execute(['slug' => $roleSlug]);
            $role = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$role) {
                continue;
            }

            foreach ($permissionSlugs as $permissionSlug) {
                // Get permission ID
                $stmt = $this->connection->prepare(
                    "SELECT id FROM permissions WHERE slug = :slug"
                );
                $stmt->execute(['slug' => $permissionSlug]);
                $permission = $stmt->fetch(\PDO::FETCH_ASSOC);

                if (!$permission) {
                    continue;
                }

                // Check if already assigned
                $stmt = $this->connection->prepare(
                    "SELECT id FROM role_permissions WHERE role_id = :role_id AND permission_id = :permission_id"
                );
                $stmt->execute([
                    'role_id' => $role['id'],
                    'permission_id' => $permission['id'],
                ]);

                if ($stmt->fetch()) {
                    continue; // Already assigned
                }

                // Assign permission
                $stmt = $this->connection->prepare("
                    INSERT INTO role_permissions (role_id, permission_id, created_at)
                    VALUES (:role_id, :permission_id, :created_at)
                ");
                $stmt->execute([
                    'role_id' => $role['id'],
                    'permission_id' => $permission['id'],
                    'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    /**
     * Create default vocabularies
     */
    private function seedVocabularies(): void
    {
        $vocabularies = Vocabulary::getDefaults();

        foreach ($vocabularies as $vocabData) {
            // Check if vocabulary exists
            $stmt = $this->connection->prepare(
                "SELECT id FROM vocabularies WHERE machine_name = :machine_name"
            );
            $stmt->execute(['machine_name' => $vocabData['machine_name']]);

            if ($stmt->fetch()) {
                continue; // Already exists
            }

            $stmt = $this->connection->prepare("
                INSERT INTO vocabularies (name, machine_name, description, hierarchical, `multiple`, required, weight, entity_types, settings, created_at, updated_at)
                VALUES (:name, :machine_name, :description, :hierarchical, :multiple, :required, :weight, :entity_types, :settings, :created_at, :updated_at)
            ");
            $stmt->execute([
                'name' => $vocabData['name'],
                'machine_name' => $vocabData['machine_name'],
                'description' => $vocabData['description'] ?? '',
                'hierarchical' => ($vocabData['hierarchical'] ?? false) ? 1 : 0,
                'multiple' => ($vocabData['multiple'] ?? true) ? 1 : 0,
                'required' => ($vocabData['required'] ?? false) ? 1 : 0,
                'weight' => $vocabData['weight'] ?? 0,
                'entity_types' => json_encode($vocabData['entity_types'] ?? []),
                'settings' => json_encode($vocabData['settings'] ?? []),
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Create default menus
     */
    private function seedMenus(): void
    {
        $menus = [
            ['name' => 'Main Menu', 'machine_name' => 'main', 'location' => 'header'],
            ['name' => 'Footer Menu', 'machine_name' => 'footer', 'location' => 'footer'],
        ];

        foreach ($menus as $menuData) {
            // Check if menu exists
            $stmt = $this->connection->prepare(
                "SELECT id FROM menus WHERE machine_name = :machine_name"
            );
            $stmt->execute(['machine_name' => $menuData['machine_name']]);

            if ($stmt->fetch()) {
                continue; // Already exists
            }

            $stmt = $this->connection->prepare("
                INSERT INTO menus (name, machine_name, description, location, created_at, updated_at)
                VALUES (:name, :machine_name, :description, :location, :created_at, :updated_at)
            ");
            $stmt->execute([
                'name' => $menuData['name'],
                'machine_name' => $menuData['machine_name'],
                'description' => '',
                'location' => $menuData['location'],
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Create default settings
     */
    private function seedSettings(): void
    {
        $defaults = \App\Modules\Core\Entities\Setting::getDefaults();

        foreach ($defaults as $data) {
            // Check if setting exists
            $stmt = $this->connection->prepare(
                "SELECT id FROM settings WHERE `key` = :key"
            );
            $stmt->execute(['key' => $data['key']]);

            if ($stmt->fetch()) {
                continue; // Already exists
            }

            $stmt = $this->connection->prepare("
                INSERT INTO settings (`key`, value, type, `group`, label, description, is_system, autoload, created_at, updated_at)
                VALUES (:key, :value, :type, :group, :label, :description, :is_system, :autoload, :created_at, :updated_at)
            ");
            $stmt->execute([
                'key' => $data['key'],
                'value' => $data['value'],
                'type' => $data['type'],
                'group' => $data['group'],
                'label' => $data['label'] ?? null,
                'description' => $data['description'] ?? null,
                'is_system' => ($data['is_system'] ?? false) ? 1 : 0,
                'autoload' => ($data['autoload'] ?? true) ? 1 : 0,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Create default admin user if none exists
     */
    private function seedAdminUser(): void
    {
        // Check if any super admin exists
        $stmt = $this->connection->query("
            SELECT u.id FROM users u
            INNER JOIN user_roles ur ON u.id = ur.user_id
            INNER JOIN roles r ON ur.role_id = r.id
            WHERE r.slug = 'super_admin'
            LIMIT 1
        ");

        if ($stmt->fetch()) {
            return; // Admin already exists
        }

        // Create default admin user
        $password = bin2hex(random_bytes(8)); // Generate random password
        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);

        $stmt = $this->connection->prepare("
            INSERT INTO users (email, username, password_hash, display_name, status, locale, timezone, email_verified_at, created_at, updated_at)
            VALUES (:email, :username, :password_hash, :display_name, :status, :locale, :timezone, :email_verified_at, :created_at, :updated_at)
        ");
        $stmt->execute([
            'email' => 'admin@example.com',
            'username' => 'admin',
            'password_hash' => $passwordHash,
            'display_name' => 'Administrator',
            'status' => 'active',
            'locale' => 'en',
            'timezone' => 'UTC',
            'email_verified_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $userId = (int) $this->connection->lastInsertId();

        // Get super_admin role
        $stmt = $this->connection->prepare(
            "SELECT id FROM roles WHERE slug = 'super_admin'"
        );
        $stmt->execute();
        $role = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($role) {
            // Assign super_admin role
            $stmt = $this->connection->prepare("
                INSERT INTO user_roles (user_id, role_id, created_at)
                VALUES (:user_id, :role_id, :created_at)
            ");
            $stmt->execute([
                'user_id' => $userId,
                'role_id' => $role['id'],
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }

        // Log the generated password (in a real app, this would be emailed or shown once)
        error_log("MonkeysCMS: Default admin created - Email: admin@example.com, Password: {$password}");

        // Store in a file for first-time setup
        $credsFile = dirname(__DIR__, 3) . '/storage/admin_credentials.txt';
        file_put_contents($credsFile, "Default Admin Credentials\n=========================\nEmail: admin@example.com\nPassword: {$password}\n\nDelete this file after logging in and changing your password.\n");
    }
}
