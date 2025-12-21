<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Cms\Modules\ModuleManager;
use App\Cms\Repository\CmsRepository;
use MonkeysLegion\Router\Attributes\Route;
use Psr\Http\Message\ResponseInterface;

/**
 * DashboardController - Admin dashboard API
 *
 * Provides overview data for the CMS admin panel.
 */
#[Route('/admin', name: 'admin')]
final class DashboardController
{
    public function __construct(
        private readonly ModuleManager $moduleManager,
        private readonly CmsRepository $repository,
    ) {
    }

    /**
     * Dashboard overview data
     */
    #[Route('GET', '/dashboard', name: 'dashboard')]
    public function index(): ResponseInterface
    {
        $enabledModules = $this->moduleManager->getEnabledModules();
        $availableModules = $this->moduleManager->getAvailableModules();

        // Collect stats from enabled modules
        $contentStats = [];
        foreach ($enabledModules as $moduleName) {
            try {
                $entities = $this->moduleManager->discoverEntities($moduleName);
                foreach ($entities as $entityClass) {
                    if (class_exists($entityClass)) {
                        $shortName = (new \ReflectionClass($entityClass))->getShortName();
                        $contentStats[$shortName] = $this->repository->count($entityClass);
                    }
                }
            } catch (\Exception $e) {
                // Silently skip modules with issues
            }
        }

        return json([
            'success' => true,
            'data' => [
                'modules' => [
                    'enabled' => count($enabledModules),
                    'available' => count($availableModules),
                    'list' => $enabledModules,
                ],
                'content' => $contentStats,
                'system' => [
                    'php_version' => PHP_VERSION,
                    'cms_version' => '1.0.0',
                    'memory_usage' => $this->formatBytes(memory_get_usage(true)),
                    'peak_memory' => $this->formatBytes(memory_get_peak_usage(true)),
                ],
            ],
        ]);
    }

    /**
     * Health check endpoint
     */
    #[Route('GET', '/health', name: 'health')]
    public function health(): ResponseInterface
    {
        return json([
            'status' => 'healthy',
            'timestamp' => date('c'),
        ]);
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
