<?php

declare(strict_types=1);

namespace App\Cms\Render\Organisms;

use App\Cms\Render\AbstractComponent;
use App\Cms\Render\RenderableInterface;

class Form extends AbstractComponent
{
    private string $method = 'POST';
    private string $action = '';
    
    /** @var RenderableInterface[] */
    private array $fields = [];

    public static function create(string $action = '', string $method = 'POST'): self
    {
        $form = new self();
        $form->action = $action;
        $form->method = strtoupper($method);
        
        $form->setAttribute('action', $action);
        
        // Browsers only support GET and POST natively
        if (in_array($form->method, ['GET', 'POST'])) {
            $form->setAttribute('method', $form->method);
        } else {
            $form->setAttribute('method', 'POST');
        }

        return $form;
    }

    public function add(RenderableInterface $component): self
    {
        $this->fields[] = $component;
        return $this;
    }

    public function getFields(): array
    {
        return $this->fields;
    }
    
    public function getMethod(): string
    {
        return $this->method;
    }

    protected function getViewName(): string
    {
        return 'admin::components.organisms.form';
    }
}
