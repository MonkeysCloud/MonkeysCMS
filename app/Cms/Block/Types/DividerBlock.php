<?php

declare(strict_types=1);

namespace App\Cms\Block\Types;

use App\Cms\Block\BlockTypeInterface;

/**
 * DividerBlock — Horizontal rule / separator.
 */
final class DividerBlock implements BlockTypeInterface
{
    public static function getId(): string { return 'divider'; }
    public static function getLabel(): string { return 'Divider'; }
    public static function getDescription(): string { return 'Horizontal line separator'; }
    public static function getIcon(): string { return '➖'; }
    public static function getCategory(): string { return 'Layout'; }

    public static function getFields(): array
    {
        return [
            'style' => ['type' => 'select', 'label' => 'Style', 'default' => 'solid', 'options' => [
                'solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted',
            ]],
            'color' => ['type' => 'string', 'label' => 'Color', 'default' => '#334155'],
        ];
    }

    public function render(array $data, array $settings = []): string
    {
        $style = htmlspecialchars($data['style'] ?? 'solid');
        $color = htmlspecialchars($data['color'] ?? '#334155');
        return '<hr class="block-divider" style="border:none;border-top:1px ' . $style . ' ' . $color . ';margin:1rem 0;">';
    }
}
