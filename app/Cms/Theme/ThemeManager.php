<?php

declare(strict_types=1);

namespace App\Cms\Theme;

use PDO;

class ThemeManager
{
    private ?PDO $pdo;
    private string $basePath;

    public function __construct(?PDO $pdo, string $basePath)
    {
        $this->pdo = $pdo;
        $this->basePath = $basePath;
    }

    public function getActiveTheme(): string
    {
        // Default theme
        $theme = 'default';

        if ($this->pdo) {
            try {
                $stmt = $this->pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'active_theme' LIMIT 1");
                $stmt->execute();
                $result = $stmt->fetchColumn();
                if ($result) {
                    $theme = $result;
                }
            } catch (\Exception $e) {
                // Table might not exist yet (during install)
            }
        }

        return $theme;
    }

    public function getThemeViewPath(): ?string
    {
        $theme = $this->getActiveTheme();
        
        // Find theme directory in any subdirectory of 'themes' (e.g. custom, contrib, etc.)
        // Pattern: /path/to/themes/*/{theme_name}
        $pattern = $this->basePath . '/themes/*/' . $theme;
        $dirs = glob($pattern, GLOB_ONLYDIR) ?: [];
        
        foreach ($dirs as $dir) {
            $configFile = $dir . '/' . $theme . '.theme.mlc';
            
            if (file_exists($configFile)) {
                // Parse configuration
                try {
                    $parser = new \MonkeysLegion\Mlc\Parser();
                    $config = $parser->parseFile($configFile);
                    
                    // Determine view path from config or default to 'views'
                    $viewDir = $config['theme']['paths']['views'] ?? 'views';
                    
                    $fullPath = $dir . '/' . $viewDir;
                    if (is_dir($fullPath)) {
                        return $fullPath;
                    }
                } catch (\Exception $e) {
                    // Log error or fallback?
                    // For now, if config fails, maybe fallback to 'views' folder if exists
                    if (is_dir($dir . '/views')) {
                        return $dir . '/views';
                    }
                }
            }
        }
        
        return null;
    }
}
