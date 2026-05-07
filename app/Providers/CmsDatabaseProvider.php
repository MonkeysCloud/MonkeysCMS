<?php

declare(strict_types=1);

namespace App\Providers;

use MonkeysLegion\Contracts\AbstractServiceProvider;
use MonkeysLegion\Database\Contracts\ConnectionInterface;
use MonkeysLegion\Framework\Attributes\Provider;

/**
 * CmsDatabaseProvider — Bridges raw PDO for CMS controllers.
 *
 * The framework provides ConnectionInterface; CMS controllers
 * inject PDO directly. This provider bridges the two.
 */
#[Provider]
class CmsDatabaseProvider extends AbstractServiceProvider
{
    public function getDefinitions(): array
    {
        return [
            \PDO::class => static fn($c): \PDO => $c->get(
                ConnectionInterface::class
            )->pdo(),
        ];
    }
}
