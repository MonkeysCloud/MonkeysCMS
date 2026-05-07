<?php

declare(strict_types=1);

namespace App\Cms\Render\Atoms;

use App\Cms\Render\AbstractComponent;

class Button extends AbstractComponent
{
    private string $text = '';
    private ?string $icon = null;

    public static function submit(string $text): self
    {
        $button = new self();
        $button->setAttribute('type', 'submit');
        $button->text = $text;
        return $button;
    }

    public static function button(string $text): self
    {
        $button = new self();
        $button->setAttribute('type', 'button');
        $button->text = $text;
        return $button;
    }

    public function setIcon(string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    protected function getViewName(): string
    {
        return 'admin::components.atoms.button';
    }
}
