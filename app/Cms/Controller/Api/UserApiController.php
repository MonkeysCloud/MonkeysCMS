<?php

declare(strict_types=1);

namespace App\Cms\Controller\Api;

use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use Psr\Http\Message\ServerRequestInterface;
use PDO;

/**
 * UserApiController — User search for autocomplete fields.
 */
final class UserApiController
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * GET /api/cms/users/search?q=query
     * Returns matching users for autocomplete.
     */
    #[Route('GET', '/api/cms/users/search', name: 'api.cms.users.search')]
    public function search(ServerRequestInterface $request): Response
    {
        $q = trim($request->getQueryParams()['q'] ?? '');

        try {
            if (strlen($q) < 1) {
                $stmt = $this->pdo->query(
                    'SELECT id, name, email, avatar FROM cms_users WHERE active = 1 ORDER BY name ASC LIMIT 10'
                );
            } else {
                $like = '%' . $q . '%';
                $stmt = $this->pdo->prepare(
                    'SELECT id, name, email, avatar FROM cms_users WHERE active = 1 AND (name LIKE :qn OR email LIKE :qe) ORDER BY name ASC LIMIT 10'
                );
                $stmt->execute(['qn' => $like, 'qe' => $like]);
            }

            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            $users = [];
        }

        return Response::json([
            'data' => array_map(fn(array $u) => [
                'id'     => (int) $u['id'],
                'name'   => $u['name'],
                'email'  => $u['email'],
                'avatar' => $u['avatar'] ?: null,
            ], $users),
        ]);
    }
}
