<?php

declare(strict_types=1);

namespace App\Cms\Fields\Widget;

/**
 * WidgetMetadata - Value object for widget information
 */
final class WidgetMetadata
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $category,
        public readonly string $icon,
        public readonly int $priority,
        public readonly array $supportedTypes,
        public readonly bool $supportsMultiple,
    ) {}

    public static function create(
        string $id,
        string $label,
        string $category = 'General',
        string $icon = '📝',
        int $priority = 0,
        array $supportedTypes = ['string'],
        bool $supportsMultiple = false,
    ): self {
        return new self(
            $id,
            $label,
            $category,
            $icon,
            $priority,
            $supportedTypes,
            $supportsMultiple,
        );
    }

    public static function fromWidget(WidgetInterface $widget): self
    {
        return new self(
            $widget->getId(),
            $widget->getLabel(),
            $widget->getCategory(),
            $widget->getIcon(),
            $widget->getPriority(),
            $widget->getSupportedTypes(),
            $widget->supportsMultiple(),
        );
    }

    public function supportsType(string $type): bool
    {
        return in_array($type, $this->supportedTypes, true);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'category' => $this->category,
            'icon' => $this->icon,
            'priority' => $this->priority,
            'supported_types' => $this->supportedTypes,
            'supports_multiple' => $this->supportsMultiple,
        ];
    }
}
