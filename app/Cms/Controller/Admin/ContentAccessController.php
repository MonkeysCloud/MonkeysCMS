<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Access\ContentAccessService;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Session\SessionManager;
use MonkeysLegion\Template\Renderer;
use PDO;
use Psr\Http\Message\ServerRequestInterface;

/**
 * ContentAccessController — Manage role-based content access rules.
 *
 * Provides a matrix UI where admins can set which roles can
 * view/create/edit/delete each content type.
 */
#[RoutePrefix('/admin/access')]
final class ContentAccessController
{
    private const array PERMISSIONS = ['view', 'create', 'edit', 'delete'];

    public function __construct(
        private readonly Renderer $renderer,
        private readonly ContentAccessService $access,
        private readonly SessionManager $session,
        private readonly PDO $pdo,
    ) {}

    // ── GET /admin/access ─────────────────────────────────────────────

    #[Route('GET', '/', name: 'admin::access.index')]
    public function index(ServerRequestInterface $request): Response
    {
        // Load all content types
        $contentTypes = $this->pdo->query(
            "SELECT type_id, label FROM content_types ORDER BY label"
        )->fetchAll(PDO::FETCH_ASSOC);

        // Load all roles
        $roles = $this->pdo->query(
            "SELECT id, label, machine_name, is_super_admin FROM cms_roles ORDER BY weight, label"
        )->fetchAll(PDO::FETCH_ASSOC);

        // Build access matrix: [type_id => [permission => [role_ids]]]
        $matrix = [];
        foreach ($contentTypes as $ct) {
            $matrix[$ct['type_id']] = $this->access->getTypeRules($ct['type_id']);
        }

        $flash = $this->session->get('_flash_access', '');
        $this->session->forget('_flash_access');

        return Response::html($this->renderer->render('access.index', [
            'title'        => 'Content Access Control',
            'contentTypes' => $contentTypes,
            'roles'        => $roles,
            'permissions'  => self::PERMISSIONS,
            'matrix'       => $matrix,
            'flash'        => $flash,
        ]));
    }

    // ── POST /admin/access ────────────────────────────────────────────

    #[Route('POST', '/', name: 'admin::access.save')]
    public function save(ServerRequestInterface $request): Response
    {
        $body = $request->getParsedBody() ?? [];

        // Format: access[article][view][] = 2, access[article][edit][] = 1
        $accessData = $body['access'] ?? [];

        // Load all content types to iterate
        $contentTypes = $this->pdo->query(
            "SELECT type_id FROM content_types"
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach ($contentTypes as $typeId) {
            foreach (self::PERMISSIONS as $perm) {
                $roleIds = array_map('intval', $accessData[$typeId][$perm] ?? []);
                $this->access->setTypeAccess($typeId, $perm, $roleIds);
            }
        }

        $this->session->set('_flash_access', 'Access rules saved successfully.');

        return Response::redirect('/admin/access');
    }
}
