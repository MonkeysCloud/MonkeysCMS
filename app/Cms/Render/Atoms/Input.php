<?php

declare(strict_types=1);

namespace App\Cms\Render\Atoms;

use App\Cms\Render\AbstractComponent;

class Input extends AbstractComponent
{
    public static function create(string $name, string $type = 'text'): self
    {
        $input = new self();
        $input->setAttribute('name', $name);
        $input->setAttribute('id', 'edit-' . str_replace('_', '-', $name));
        $input->setType($type);
        return $input;
    }

    public function setType(string $type): self
    {
        $this->setAttribute('type', $type);
        return $this;
    }

    public function setValue(string $value): self
    {
        $this->setAttribute('value', $value);
        return $this;
    }

    public function setRequired(bool $required = true): self
    {
        $this->setAttribute('required', $required ? true : null);
        return $this;
    }

    public function setPlaceholder(string $placeholder): self
    {
        $this->setAttribute('placeholder', $placeholder);
        return $this;
    }

    protected function getViewName(): string
    {
        return 'admin::components.atoms.input';
    }
}
