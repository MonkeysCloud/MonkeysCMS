<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;
use PDO;

/**
 * DashboardController — Admin dashboard with stats overview.
 */
final class DashboardController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly PDO $pdo,
    ) {}

    #[Route('GET', '/admin', name: 'admin.dashboard')]
    public function index(ServerRequestInterface $request): Response
    {
        $stats = [];

        // Content counts by type
        $stmt = $this->pdo->query("SELECT content_type, status, COUNT(*) as cnt FROM nodes WHERE deleted_at IS NULL GROUP BY content_type, status ORDER BY content_type");
        $contentStats = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $contentStats[$row['content_type']][$row['status']] = (int) $row['cnt'];
        }
        $stats['content'] = $contentStats;

        // Media count
        $stats['media'] = (int) $this->pdo->query("SELECT COUNT(*) FROM media")->fetchColumn();

        // Users count
        $stats['users'] = (int) $this->pdo->query("SELECT COUNT(*) FROM cms_users WHERE active = 1")->fetchColumn();

        return Response::html($this->renderer->render('admin.dashboard', [
            'title' => 'Dashboard',
            'stats' => $stats,
        ]));
    }
}
