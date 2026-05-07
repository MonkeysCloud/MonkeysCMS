<?php

declare(strict_types=1);

namespace App\Cms\Render\Atoms;

use App\Cms\Render\AbstractComponent;

class CsrfToken extends AbstractComponent
{
    public static function create(): self
    {
        return new self();
    }

    protected function getViewName(): string
    {
        return 'admin::components.atoms.csrf';
    }
}
