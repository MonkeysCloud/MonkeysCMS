<?php

declare(strict_types=1);

namespace App\Cms\Provider;

use App\Cms\Media\MediaRepository;
use Psr\Container\ContainerInterface;
use PDO;

final class MediaProvider
{
    public static function getDefinitions(): array
    {
        return [
            MediaRepository::class => fn(ContainerInterface $c) => new MediaRepository($c->get(PDO::class)),
        ];
    }
}
