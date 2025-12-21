<?php

declare(strict_types=1);

/**
 * Migration: Create roles and permissions tables
 */

return new class {
    public function up(\PDO $db): void
    {
        // Roles table
        $db->exec("
            CREATE TABLE IF NOT EXISTS roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                machine_name VARCHAR(100) NOT NULL,
                description TEXT NULL,
                is_system TINYINT(1) DEFAULT 0,
                weight INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                UNIQUE KEY unique_machine_name (machine_name),
                INDEX idx_weight (weight)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Permissions table
        $db->exec("
            CREATE TABLE IF NOT EXISTS permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                machine_name VARCHAR(100) NOT NULL,
                description TEXT NULL,
                module VARCHAR(100) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                UNIQUE KEY unique_machine_name (machine_name),
                INDEX idx_module (module)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Role-Permission pivot table
        $db->exec("
            CREATE TABLE IF NOT EXISTS role_permissions (
                role_id INT NOT NULL,
                permission_id INT NOT NULL,
                
                PRIMARY KEY (role_id, permission_id),
                FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
                FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // User-Role pivot table
        $db->exec("
            CREATE TABLE IF NOT EXISTS user_roles (
                user_id INT NOT NULL,
                role_id INT NOT NULL,
                
                PRIMARY KEY (user_id, role_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Seed default roles
        $db->exec("
            INSERT INTO roles (name, machine_name, description, is_system, weight) VALUES
            ('Administrator', 'admin', 'Full system access', 1, 0),
            ('Editor', 'editor', 'Can create and edit content', 1, 10),
            ('Author', 'author', 'Can create own content', 1, 20),
            ('Authenticated', 'authenticated', 'Logged in users', 1, 30),
            ('Anonymous', 'anonymous', 'Not logged in', 1, 100)
        ");

        // Seed default permissions
        $db->exec("
            INSERT INTO permissions (name, machine_name, module) VALUES
            ('Administer site', 'administer_site', 'system'),
            ('Access admin', 'access_admin', 'system'),
            ('Manage users', 'manage_users', 'user'),
            ('Manage roles', 'manage_roles', 'user'),
            ('Create content', 'create_content', 'content'),
            ('Edit own content', 'edit_own_content', 'content'),
            ('Edit any content', 'edit_any_content', 'content'),
            ('Delete own content', 'delete_own_content', 'content'),
            ('Delete any content', 'delete_any_content', 'content'),
            ('Publish content', 'publish_content', 'content'),
            ('Manage content types', 'manage_content_types', 'content'),
            ('Manage fields', 'manage_fields', 'field'),
            ('Manage taxonomies', 'manage_taxonomies', 'taxonomy'),
            ('Manage menus', 'manage_menus', 'menu'),
            ('Manage blocks', 'manage_blocks', 'block'),
            ('Manage media', 'manage_media', 'media'),
            ('Upload media', 'upload_media', 'media'),
            ('Manage themes', 'manage_themes', 'theme'),
            ('Manage modules', 'manage_modules', 'module'),
            ('View content revisions', 'view_revisions', 'content'),
            ('Revert content revisions', 'revert_revisions', 'content')
        ");

        // Give admin all permissions
        $db->exec("
            INSERT INTO role_permissions (role_id, permission_id)
            SELECT 1, id FROM permissions
        ");

        // Give editor content permissions
        $db->exec("
            INSERT INTO role_permissions (role_id, permission_id)
            SELECT 2, id FROM permissions 
            WHERE machine_name IN (
                'access_admin', 'create_content', 'edit_any_content', 
                'delete_any_content', 'publish_content', 'upload_media',
                'view_revisions', 'revert_revisions'
            )
        ");

        // Give author basic permissions
        $db->exec("
            INSERT INTO role_permissions (role_id, permission_id)
            SELECT 3, id FROM permissions 
            WHERE machine_name IN (
                'access_admin', 'create_content', 'edit_own_content', 
                'delete_own_content', 'upload_media'
            )
        ");
    }

    public function down(\PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS user_roles");
        $db->exec("DROP TABLE IF EXISTS role_permissions");
        $db->exec("DROP TABLE IF EXISTS permissions");
        $db->exec("DROP TABLE IF EXISTS roles");
    }
};
