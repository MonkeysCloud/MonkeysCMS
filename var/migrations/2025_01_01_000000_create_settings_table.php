<?php

return new class {
    public function up(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        
        $sql = match($driver) {
            'pgsql' => "
                CREATE TABLE IF NOT EXISTS settings (
                    id SERIAL PRIMARY KEY,
                    setting_key VARCHAR(255) NOT NULL UNIQUE,
                    setting_value TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
            ",
            'sqlite' => "
                CREATE TABLE IF NOT EXISTS settings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    setting_key VARCHAR(255) NOT NULL UNIQUE,
                    setting_value TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
            ",
            'sqlsrv' => "
                IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='settings' AND xtype='U')
                CREATE TABLE settings (
                    id INT IDENTITY(1,1) PRIMARY KEY,
                    setting_key NVARCHAR(255) NOT NULL UNIQUE,
                    setting_value NVARCHAR(MAX),
                    created_at DATETIME DEFAULT GETDATE(),
                    updated_at DATETIME DEFAULT GETDATE()
                );
            ",
            default => "
                CREATE TABLE IF NOT EXISTS settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(255) NOT NULL UNIQUE,
                    setting_value TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            "
        };

        $pdo->exec($sql);
        
        // Seed default theme
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
        try {
            $stmt->execute(['active_theme', 'default']);
        } catch (\Exception $e) {
            // Ignore unique constraint violation if already seeded
        }
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS settings");
    }
};
