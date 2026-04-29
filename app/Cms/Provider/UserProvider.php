<?php

declare(strict_types=1);

namespace App\Cms\Provider;

use Psr\Container\ContainerInterface;

final class UserProvider
{
    public static function getDefinitions(): array
    {
        return [
            // UserRepository will be registered here
        ];
    }
}
