<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Config\ConfigManager;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * ConfigController — Admin UI for config export/import.
 *
 * Routes:
 *   GET  /admin/config/export           — Export page
 *   POST /admin/config/export           — Run export to sync/
 *   GET  /admin/config/export/archive   — Download zip archive
 *   GET  /admin/config/import           — Import page
 *   POST /admin/config/import/preview   — Preview diff (JSON)
 *   POST /admin/config/import           — Execute import
 */
#[RoutePrefix('/admin/config')]
final class ConfigController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly ConfigManager $manager,
    ) {}

    // ── Export ───────────────────────────────────────────────────────────

    #[Route('GET', '/export', name: 'admin.config.export')]
    public function exportPage(): Response
    {
        $collectors = $this->manager->getAvailableCollectors();
        $syncDir = $this->manager->getSyncDir();
        $syncFiles = is_dir($syncDir) ? glob($syncDir . '/*.mlc') : [];

        return Response::html($this->renderer->render('config.export', [
            'title'      => 'Export Configuration',
            'collectors' => $collectors,
            'syncDir'    => $syncDir,
            'syncFiles'  => array_map('basename', $syncFiles ?: []),
            'hasSyncFiles' => !empty($syncFiles),
        ]));
    }

    #[Route('POST', '/export', name: 'admin.config.export.run')]
    public function runExport(ServerRequestInterface $request): Response
    {
        $body = (array) $request->getParsedBody();
        $sections = $body['sections'] ?? [];

        // Export to config/sync/
        $files = $this->manager->export($sections);

        return Response::json([
            'success' => true,
            'message' => count($files) . ' files exported to config/sync/',
            'files'   => $files,
        ]);
    }

    #[Route('GET', '/export/archive', name: 'admin.config.export.archive')]
    public function downloadArchive(ServerRequestInterface $request): Response
    {
        $params = $request->getQueryParams();
        $sections = isset($params['sections']) ? explode(',', $params['sections']) : [];

        $archivePath = $this->manager->exportArchive($sections);
        return Response::download($archivePath);
    }

    // ── Import ──────────────────────────────────────────────────────────

    #[Route('GET', '/import', name: 'admin.config.import')]
    public function importPage(): Response
    {
        $syncDir = $this->manager->getSyncDir();
        $syncFiles = is_dir($syncDir) ? glob($syncDir . '/*.mlc') : [];

        // Get diff
        $diff = [];
        if (!empty($syncFiles)) {
            $diff = $this->manager->diff();
        }

        return Response::html($this->renderer->render('config.import', [
            'title'      => 'Import Configuration',
            'syncDir'    => $syncDir,
            'syncFiles'  => array_map('basename', $syncFiles ?: []),
            'hasSyncFiles' => !empty($syncFiles),
            'diff'       => $diff,
            'collectors' => $this->manager->getAvailableCollectors(),
        ]));
    }

    #[Route('POST', '/import/preview', name: 'admin.config.import.preview')]
    public function previewImport(ServerRequestInterface $request): Response
    {
        // Handle file upload
        $files = $request->getUploadedFiles();
        if (!empty($files['archive'])) {
            $uploaded = $files['archive'];
            if ($uploaded->getError() === UPLOAD_ERR_OK) {
                $tmpPath = sys_get_temp_dir() . '/cms_config_import_' . time() . '.zip';
                $uploaded->moveTo($tmpPath);

                // Extract to sync dir
                $syncDir = $this->manager->getSyncDir();
                if (!is_dir($syncDir)) {
                    mkdir($syncDir, 0755, true);
                }

                $zip = new \ZipArchive();
                if ($zip->open($tmpPath) === true) {
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $name = $zip->getNameIndex($i);
                        $relative = preg_replace('#^config/sync/#', '', $name);
                        if ($relative && str_ends_with($relative, '.mlc')) {
                            $content = $zip->getFromIndex($i);
                            file_put_contents($syncDir . '/' . $relative, $content);
                        }
                    }
                    $zip->close();
                }

                @unlink($tmpPath);
            }
        }

        $diff = $this->manager->diff();

        return Response::json([
            'success' => true,
            'diff'    => $diff,
            'count'   => [
                'create' => count(array_filter($diff, fn($d) => $d['status'] === 'create')),
                'update' => count(array_filter($diff, fn($d) => $d['status'] === 'update')),
                'orphan' => count(array_filter($diff, fn($d) => $d['status'] === 'orphan')),
            ],
        ]);
    }

    #[Route('POST', '/import', name: 'admin.config.import.run')]
    public function runImport(ServerRequestInterface $request): Response
    {
        $body = (array) $request->getParsedBody();
        $overwrite = (bool) ($body['overwrite'] ?? false);
        $sync = (bool) ($body['sync'] ?? false);

        $result = $this->manager->import(overwrite: $overwrite, sync: $sync);

        return Response::json([
            'success' => !$result->hasErrors(),
            'result'  => $result->toArray(),
        ]);
    }
}
