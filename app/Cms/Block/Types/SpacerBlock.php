<?php

declare(strict_types=1);

namespace App\Cms\Block\Types;

use App\Cms\Block\BlockTypeInterface;

/**
 * SpacerBlock — Visual spacer for layout control.
 */
final class SpacerBlock implements BlockTypeInterface
{
    public static function getId(): string { return 'spacer'; }
    public static function getLabel(): string { return 'Spacer'; }
    public static function getDescription(): string { return 'Vertical spacing between blocks'; }
    public static function getIcon(): string { return '↕️'; }
    public static function getCategory(): string { return 'Layout'; }

    public static function getFields(): array
    {
        return [
            'height' => ['type' => 'string', 'label' => 'Height', 'default' => '2rem'],
        ];
    }

    public function render(array $data, array $settings = []): string
    {
        $height = htmlspecialchars($data['height'] ?? '2rem');
        return '<div class="block-spacer" style="height:' . $height . ';"></div>';
    }
}
