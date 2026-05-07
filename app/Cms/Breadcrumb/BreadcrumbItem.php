<?php

declare(strict_types=1);

namespace App\Cms\Breadcrumb;

/**
 * BreadcrumbItem — A single crumb in a breadcrumb trail.
 *
 * Immutable value object representing one navigation step.
 */
final class BreadcrumbItem
{
    public function __construct(
        public readonly string  $label,
        public readonly ?string $url = null,
        public readonly ?string $icon = null,
    ) {}

    public function isLink(): bool
    {
        return $this->url !== null && $this->url !== '';
    }

    public function isCurrent(): bool
    {
        return $this->url === null;
    }

    public function toArray(): array
    {
        return array_filter([
            'label' => $this->label,
            'url'   => $this->url,
            'icon'  => $this->icon,
        ], static fn(mixed $v) => $v !== null);
    }
}
