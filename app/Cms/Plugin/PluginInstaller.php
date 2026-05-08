<?php

declare(strict_types=1);

namespace App\Cms\Plugin;

use Psr\Http\Message\UploadedFileInterface;

/**
 * PluginInstaller — Handles plugin ZIP upload, extraction, and validation.
 *
 * Extracts uploaded ZIP files into the plugins/contrib/ directory,
 * validates the required plugin structure, and prepares for activation.
 */
final class PluginInstaller
{
    private const int MAX_FILE_SIZE = 50 * 1024 * 1024; // 50 MB

    public function __construct(
        private readonly string $basePath,
    ) {}

    /**
     * Install a plugin from an uploaded ZIP file.
     *
     * @return string Machine name (vendor/name) of the installed plugin
     * @throws \RuntimeException On validation or extraction failure
     */
    public function installFromUpload(UploadedFileInterface $file): string
    {
        // Validate file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \RuntimeException('Plugin ZIP exceeds maximum file size of 50 MB.');
        }

        // Create temp directory
        $tmpDir = $this->basePath . '/var/tmp/plugin_' . uniqid();
        if (!mkdir($tmpDir, 0755, true)) {
            throw new \RuntimeException('Failed to create temporary directory.');
        }

        try {
            // Move uploaded file to temp
            $zipPath = $tmpDir . '/plugin.zip';
            $file->moveTo($zipPath);

            return $this->installFromZip($zipPath);
        } finally {
            // Cleanup temp
            $this->removeDir($tmpDir);
        }
    }

    /**
     * Install a plugin from a ZIP file on disk.
     *
     * @return string Machine name (vendor/name)
     * @throws \RuntimeException On validation failure
     */
    public function installFromZip(string $zipPath): string
    {
        if (!file_exists($zipPath)) {
            throw new \RuntimeException("ZIP file not found: {$zipPath}");
        }

        $zip = new \ZipArchive();
        $result = $zip->open($zipPath);

        if ($result !== true) {
            throw new \RuntimeException("Failed to open ZIP file (code: {$result}).");
        }

        // Extract to temp dir
        $extractDir = $this->basePath . '/var/tmp/extract_' . uniqid();
        mkdir($extractDir, 0755, true);

        try {
            $zip->extractTo($extractDir);
            $zip->close();

            // Find the plugin.mlc file
            $mlcFile = $this->findPluginMlc($extractDir);
            if (!$mlcFile) {
                throw new \RuntimeException('Invalid plugin: no *.plugin.mlc file found.');
            }

            // Parse metadata
            $pluginDir = dirname($mlcFile);
            $content = file_get_contents($mlcFile);
            $metadata = PluginMetadata::fromMlc($content, $pluginDir, 'contrib');

            if (empty($metadata->vendor) || empty($metadata->name)) {
                throw new \RuntimeException('Invalid plugin.mlc: vendor and name are required.');
            }

            // Target: plugins/contrib/vendor/name/
            $targetDir = $this->basePath . '/plugins/contrib/' . $metadata->vendor . '/' . $metadata->name;

            if (is_dir($targetDir)) {
                // Backup existing version
                $backupDir = $targetDir . '.bak.' . date('YmdHis');
                rename($targetDir, $backupDir);
            }

            // Move extracted plugin to target
            mkdir(dirname($targetDir), 0755, true);
            rename($pluginDir, $targetDir);

            return $metadata->machineName;

        } finally {
            $this->removeDir($extractDir);
        }
    }

    /**
     * Find the *.plugin.mlc file in the extracted directory.
     *
     * Supports both flat layout and nested vendor/name layout.
     */
    private function findPluginMlc(string $dir, int $depth = 0): ?string
    {
        if ($depth > 3) {
            return null;
        }

        foreach (new \DirectoryIterator($dir) as $item) {
            if ($item->isDot()) {
                continue;
            }

            if ($item->isFile() && str_ends_with($item->getFilename(), '.plugin.mlc')) {
                return $item->getPathname();
            }

            if ($item->isDir()) {
                $found = $this->findPluginMlc($item->getPathname(), $depth + 1);
                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Recursively remove a directory.
     */
    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
