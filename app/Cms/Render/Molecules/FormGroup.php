<?php

declare(strict_types=1);

namespace App\Cms\Render\Molecules;

use App\Cms\Render\AbstractComponent;
use App\Cms\Render\Atoms\Input;
use App\Cms\Render\Atoms\Label;

class FormGroup extends AbstractComponent
{
    private Label $label;
    private Input $input;
    private ?string $error = null;

    public static function create(string $name, string $labelText, string $type = 'text'): self
    {
        $group = new self();
        $group->input = Input::create($name, $type);
        $group->input->addClass('w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-slate-200 placeholder-slate-500 focus:outline-none focus:border-indigo-500/50 focus:bg-white/10 transition-all');

        $group->label = Label::create((string) $group->input->getAttribute('id'), $labelText);
        $group->label->addClass('block text-sm font-medium text-slate-300 mb-2');

        return $group;
    }

    public function getInput(): Input
    {
        return $this->input;
    }

    public function getLabel(): Label
    {
        return $this->label;
    }

    public function setError(string $error): self
    {
        $this->error = $error;
        return $this;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setRequired(bool $required = true): self
    {
        $this->input->setRequired($required);
        return $this;
    }

    public function setPlaceholder(string $placeholder): self
    {
        $this->input->setPlaceholder($placeholder);
        return $this;
    }

    protected function getViewName(): string
    {
        return 'admin::components.molecules.form-group';
    }
}
