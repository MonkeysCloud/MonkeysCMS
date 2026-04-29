<?php

declare(strict_types=1);

namespace App\Cms\Provider;

use App\Cms\Menu\MenuRepository;
use Psr\Container\ContainerInterface;
use PDO;

final class MenuProvider
{
    public static function getDefinitions(): array
    {
        return [
            MenuRepository::class => fn(ContainerInterface $c) => new MenuRepository($c->get(PDO::class)),
        ];
    }
}
