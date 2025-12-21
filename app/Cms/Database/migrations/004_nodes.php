<?php

declare(strict_types=1);

/**
 * Migration: Create nodes table (content)
 */

return new class {
    public function up(\PDO $db): void
    {
        // Nodes table - main content storage
        $db->exec("
            CREATE TABLE IF NOT EXISTS nodes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type VARCHAR(100) NOT NULL,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NULL,
                status ENUM('draft', 'published', 'archived', 'pending', 'scheduled') DEFAULT 'draft',
                
                -- Authorship
                author_id INT NULL,
                
                -- Revisions
                revision_id INT DEFAULT 1,
                
                -- Timestamps
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                published_at TIMESTAMP NULL,
                deleted_at TIMESTAMP NULL,
                
                -- Indexes
                INDEX idx_type (type),
                INDEX idx_status (status),
                INDEX idx_slug (slug),
                INDEX idx_author (author_id),
                INDEX idx_published (published_at),
                INDEX idx_created (created_at),
                
                -- Foreign keys
                FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Node revisions table
        $db->exec("
            CREATE TABLE IF NOT EXISTS node_revisions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                node_id INT NOT NULL,
                revision_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                data JSON NULL,
                author_id INT NULL,
                log_message TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                INDEX idx_node (node_id),
                INDEX idx_revision (node_id, revision_id),
                UNIQUE KEY unique_revision (node_id, revision_id),
                
                FOREIGN KEY (node_id) REFERENCES nodes(id) ON DELETE CASCADE,
                FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // URL aliases table
        $db->exec("
            CREATE TABLE IF NOT EXISTS url_aliases (
                id INT AUTO_INCREMENT PRIMARY KEY,
                alias VARCHAR(255) NOT NULL,
                source VARCHAR(255) NOT NULL,
                langcode VARCHAR(10) DEFAULT 'en',
                
                UNIQUE KEY unique_alias (alias, langcode),
                INDEX idx_source (source)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS url_aliases");
        $db->exec("DROP TABLE IF EXISTS node_revisions");
        $db->exec("DROP TABLE IF EXISTS nodes");
    }
};
