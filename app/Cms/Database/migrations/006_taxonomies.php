<?php

declare(strict_types=1);

/**
 * Migration: Create taxonomies tables
 */
return new class {
    public function up(\PDO $db): void
    {
        // Vocabularies table
        $db->exec("
            CREATE TABLE IF NOT EXISTS vocabularies (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                machine_name VARCHAR(100) NOT NULL,
                description TEXT NULL,
                hierarchy TINYINT(1) DEFAULT 0,
                weight INT DEFAULT 0,
                
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                UNIQUE KEY unique_machine_name (machine_name),
                INDEX idx_weight (weight)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Terms table
        $db->exec("
            CREATE TABLE IF NOT EXISTS terms (
                id INT AUTO_INCREMENT PRIMARY KEY,
                vocabulary_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NULL,
                description TEXT NULL,
                parent_id INT NULL,
                weight INT DEFAULT 0,
                
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_vocabulary (vocabulary_id),
                INDEX idx_parent (parent_id),
                INDEX idx_slug (slug),
                INDEX idx_weight (weight),
                
                FOREIGN KEY (vocabulary_id) REFERENCES vocabularies(id) ON DELETE CASCADE,
                FOREIGN KEY (parent_id) REFERENCES terms(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Entity-Term relationship (polymorphic)
        $db->exec("
            CREATE TABLE IF NOT EXISTS entity_terms (
                id INT AUTO_INCREMENT PRIMARY KEY,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INT NOT NULL,
                term_id INT NOT NULL,
                delta INT DEFAULT 0,
                
                INDEX idx_entity (entity_type, entity_id),
                INDEX idx_term (term_id),
                UNIQUE KEY unique_entity_term (entity_type, entity_id, term_id),
                
                FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Seed default vocabularies
        $db->exec("
            INSERT INTO vocabularies (name, machine_name, description, hierarchy) VALUES
            ('Tags', 'tags', 'Free tagging vocabulary for content', 0),
            ('Categories', 'categories', 'Hierarchical content categories', 1)
        ");

        // Seed some default categories
        $db->exec("
            INSERT INTO terms (vocabulary_id, name, slug, weight) VALUES
            (2, 'Uncategorized', 'uncategorized', 0),
            (2, 'News', 'news', 10),
            (2, 'Blog', 'blog', 20),
            (2, 'Tutorials', 'tutorials', 30)
        ");
    }

    public function down(\PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS entity_terms");
        $db->exec("DROP TABLE IF EXISTS terms");
        $db->exec("DROP TABLE IF EXISTS vocabularies");
    }
};
