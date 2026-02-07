<?php

declare(strict_types=1);

namespace App\Cms\Composer\Layout;

use App\Cms\Composer\Row;

/**
 * CoreLayoutProvider - Built-in layout presets
 * 
 * Provides the standard row layouts that come with the composer.
 */
class CoreLayoutProvider implements LayoutProviderInterface
{
    public function getProviderId(): string
    {
        return 'core';
    }

    public function getLayouts(): array
    {
        $layouts = [];

        foreach (Row::LAYOUTS as $id => $widths) {
            $layoutId = (string) $id;
            $layouts[] = [
                'id' => $layoutId,
                'label' => $this->getLabel($layoutId),
                'widths' => $widths,
                'icon' => $this->getIcon($layoutId),
            ];
        }

        return $layouts;
    }

    private function getLabel(string $id): string
    {
        return match ($id) {
            '1' => 'Full Width',
            '1-1' => 'Two Equal Columns',
            '1-2' => 'One Third / Two Thirds',
            '2-1' => 'Two Thirds / One Third',
            '1-1-1' => 'Three Equal Columns',
            '1-1-1-1' => 'Four Equal Columns',
            '1-2-1' => 'Sidebar / Main / Sidebar',
            '1-3' => 'Quarter / Three Quarters',
            '3-1' => 'Three Quarters / Quarter',
            default => $id,
        };
    }

    private function getIcon(string $id): string
    {
        return match ($id) {
            '1' => '▓▓▓▓▓▓▓▓',
            '1-1' => '▓▓▓▓│▓▓▓▓',
            '1-2' => '▓▓│▓▓▓▓▓▓',
            '2-1' => '▓▓▓▓▓▓│▓▓',
            '1-1-1' => '▓▓│▓▓│▓▓',
            '1-1-1-1' => '▓│▓│▓│▓',
            '1-2-1' => '▓│▓▓▓▓│▓',
            '1-3' => '▓│▓▓▓▓▓▓',
            '3-1' => '▓▓▓▓▓▓│▓',
            default => '▓▓▓▓▓▓▓▓',
        };
    }
}
