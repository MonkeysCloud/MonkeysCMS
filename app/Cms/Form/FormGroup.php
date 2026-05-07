<?php

declare(strict_types=1);

namespace App\Cms\Form;

/**
 * FormGroup — A logical grouping of form elements rendered as a card.
 *
 * Maps to `admin-card` in the admin theme UI.
 */
final class FormGroup
{
    /** @var list<FormElement> */
    private array $elements = [];

    public function __construct(
        public readonly string $name,
        public readonly string $title,
        public readonly string $icon = '',
        public readonly string $description = '',
        public readonly int $weight = 0,
        public readonly ?string $columns = null,
    ) {}

    public function addElement(FormElement $element): void
    {
        $this->elements[] = $element;
    }

    /** @return list<FormElement> */
    public function getElements(): array
    {
        return $this->elements;
    }

    public function isEmpty(): bool
    {
        return count($this->elements) === 0;
    }
}
