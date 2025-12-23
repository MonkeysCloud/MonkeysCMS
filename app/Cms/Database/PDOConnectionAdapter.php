<?php

declare(strict_types=1);

namespace App\Cms\Database;

use MonkeysLegion\Database\Contracts\ConnectionInterface;
use PDO;

class PDOConnectionAdapter implements ConnectionInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function connect(): void
    {
        // Already connected
    }

    public function disconnect(): void
    {
        // No-op
    }

    public function isConnected(): bool
    {
        return true;
    }

    public function isAlive(): bool
    {
        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}
