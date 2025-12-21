<?php

declare(strict_types=1);

/**
 * Migration: Create menus tables
 */
return new class {
    public function up(\PDO $db): void
    {
        // Menus table
        $db->exec("
            CREATE TABLE IF NOT EXISTS menus (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                machine_name VARCHAR(100) NOT NULL,
                description TEXT NULL,
                is_system TINYINT(1) DEFAULT 0,
                
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                UNIQUE KEY unique_machine_name (machine_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Menu items table
        $db->exec("
            CREATE TABLE IF NOT EXISTS menu_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                menu_id INT NOT NULL,
                parent_id INT NULL,
                
                -- Link info
                title VARCHAR(255) NOT NULL,
                link_type ENUM('content', 'taxonomy', 'external', 'route', 'custom') DEFAULT 'custom',
                link_uri VARCHAR(500) NULL,
                link_target VARCHAR(20) DEFAULT '_self',
                
                -- Reference IDs for content/taxonomy links
                entity_type VARCHAR(50) NULL,
                entity_id INT NULL,
                
                -- Options
                options JSON NULL,
                
                -- Display
                weight INT DEFAULT 0,
                expanded TINYINT(1) DEFAULT 0,
                enabled TINYINT(1) DEFAULT 1,
                
                -- Access
                roles JSON NULL,
                
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_menu (menu_id),
                INDEX idx_parent (parent_id),
                INDEX idx_weight (weight),
                INDEX idx_enabled (enabled),
                
                FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE,
                FOREIGN KEY (parent_id) REFERENCES menu_items(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Seed default menus
        $db->exec("
            INSERT INTO menus (name, machine_name, description, is_system) VALUES
            ('Main Navigation', 'main', 'Primary site navigation', 1),
            ('Footer', 'footer', 'Footer links', 1),
            ('User Menu', 'user', 'User account menu', 1),
            ('Admin', 'admin', 'Administration menu', 1)
        ");

        // Seed main menu items
        $db->exec("
            INSERT INTO menu_items (menu_id, title, link_type, link_uri, weight) VALUES
            (1, 'Home', 'route', '/', 0),
            (1, 'About', 'custom', '/about', 10),
            (1, 'Blog', 'custom', '/blog', 20),
            (1, 'Contact', 'custom', '/contact', 30)
        ");

        // Seed footer menu items
        $db->exec("
            INSERT INTO menu_items (menu_id, title, link_type, link_uri, weight) VALUES
            (2, 'Privacy Policy', 'custom', '/privacy', 0),
            (2, 'Terms of Service', 'custom', '/terms', 10),
            (2, 'Contact', 'custom', '/contact', 20)
        ");

        // Seed user menu items
        $db->exec("
            INSERT INTO menu_items (menu_id, title, link_type, link_uri, weight) VALUES
            (3, 'Profile', 'route', '/profile', 0),
            (3, 'Settings', 'route', '/settings', 10),
            (3, 'Logout', 'route', '/logout', 100)
        ");

        // Seed admin menu items
        $db->exec("
            INSERT INTO menu_items (menu_id, title, link_type, link_uri, weight, expanded) VALUES
            (4, 'Dashboard', 'route', '/admin', 0, 0),
            (4, 'Content', 'route', '/admin/content', 10, 1),
            (4, 'Media', 'route', '/admin/media', 20, 0),
            (4, 'Structure', 'route', '/admin/structure', 30, 1),
            (4, 'Users', 'route', '/admin/users', 40, 0),
            (4, 'Settings', 'route', '/admin/settings', 50, 1)
        ");

        // Add sub-items to Content
        $db->exec("
            INSERT INTO menu_items (menu_id, parent_id, title, link_type, link_uri, weight) VALUES
            (4, 2, 'All Content', 'route', '/admin/content', 0),
            (4, 2, 'Add Content', 'route', '/admin/content/create', 10)
        ");

        // Add sub-items to Structure
        $db->exec("
            INSERT INTO menu_items (menu_id, parent_id, title, link_type, link_uri, weight) VALUES
            (4, 4, 'Content Types', 'route', '/admin/structure/content-types', 0),
            (4, 4, 'Taxonomies', 'route', '/admin/structure/taxonomies', 10),
            (4, 4, 'Menus', 'route', '/admin/structure/menus', 20),
            (4, 4, 'Blocks', 'route', '/admin/structure/blocks', 30)
        ");

        // Add sub-items to Settings
        $db->exec("
            INSERT INTO menu_items (menu_id, parent_id, title, link_type, link_uri, weight) VALUES
            (4, 6, 'General', 'route', '/admin/settings', 0),
            (4, 6, 'Themes', 'route', '/admin/settings/themes', 10),
            (4, 6, 'Modules', 'route', '/admin/settings/modules', 20)
        ");
    }

    public function down(\PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS menu_items");
        $db->exec("DROP TABLE IF EXISTS menus");
    }
};
