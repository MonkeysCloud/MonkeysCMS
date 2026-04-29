<?php

declare(strict_types=1);

namespace App\Cms\Controller\Api;

use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use Psr\Http\Message\ServerRequestInterface;
use PDO;

/**
 * SettingsApiController — Admin REST API for CMS settings.
 */
#[RoutePrefix('/admin/api/settings')]
final class SettingsApiController
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    #[Route('GET', '/', name: 'admin.api.settings.index')]
    public function index(ServerRequestInterface $request): Response
    {
        $group = $request->getQueryParams()['group'] ?? null;

        $sql = 'SELECT * FROM settings';
        $params = [];

        if ($group) {
            $sql .= ' WHERE `group` = :group';
            $params['group'] = $group;
        }

        $sql .= ' ORDER BY `group`, `key`';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group settings by group name
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['group']][$row['key']] = $this->castValue($row['value'], $row['type']);
        }

        return Response::json(['data' => $grouped]);
    }

    #[Route('PUT', '/', name: 'admin.api.settings.update')]
    public function update(ServerRequestInterface $request): Response
    {
        $body = json_decode((string) $request->getBody(), true);
        if (!is_array($body)) {
            return Response::json(['error' => 'Invalid JSON'], 422);
        }

        $upsert = $this->pdo->prepare(
            'INSERT INTO settings (`group`, `key`, `value`, `type`) VALUES (:group, :key, :value, :type)
             ON DUPLICATE KEY UPDATE `value` = :value2, `type` = :type2'
        );

        $count = 0;
        foreach ($body as $group => $settings) {
            if (!is_array($settings)) continue;
            foreach ($settings as $key => $value) {
                $type = $this->detectType($value);
                $val = is_bool($value) ? ($value ? '1' : '0') : (is_array($value) ? json_encode($value) : (string) $value);
                $upsert->execute([
                    'group' => $group, 'key' => $key,
                    'value' => $val, 'type' => $type,
                    'value2' => $val, 'type2' => $type,
                ]);
                $count++;
            }
        }

        return Response::json(['meta' => ['updated' => $count]]);
    }

    #[Route('GET', '/groups', name: 'admin.api.settings.groups')]
    public function groups(): Response
    {
        $stmt = $this->pdo->query('SELECT DISTINCT `group` FROM settings ORDER BY `group`');
        $groups = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return Response::json(['data' => $groups]);
    }

    private function castValue(string $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'float' => (float) $value,
            'boolean' => $value === '1' || $value === 'true',
            'json' => json_decode($value, true) ?? [],
            default => $value,
        };
    }

    private function detectType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            is_array($value) => 'json',
            default => 'string',
        };
    }
}
