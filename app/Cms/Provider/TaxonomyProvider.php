<?php

declare(strict_types=1);

namespace App\Cms\Provider;

use App\Cms\Taxonomy\TaxonomyRepository;
use Psr\Container\ContainerInterface;
use PDO;

final class TaxonomyProvider
{
    public static function getDefinitions(): array
    {
        return [
            TaxonomyRepository::class => fn(ContainerInterface $c) => new TaxonomyRepository($c->get(PDO::class)),
        ];
    }
}
