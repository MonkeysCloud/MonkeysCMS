<?php

declare(strict_types=1);

namespace App\Cms\Render\Atoms;

use App\Cms\Render\AbstractComponent;

class Label extends AbstractComponent
{
    private string $text = '';

    public static function create(string $for, string $text): self
    {
        $label = new self();
        $label->setAttribute('for', $for);
        $label->text = $text;
        return $label;
    }

    public function getText(): string
    {
        return $this->text;
    }

    protected function getViewName(): string
    {
        return 'admin::components.atoms.label';
    }
}
