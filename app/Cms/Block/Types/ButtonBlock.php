<?php

declare(strict_types=1);

namespace App\Cms\Block\Types;

use App\Cms\Block\BlockTypeInterface;

/**
 * ButtonBlock — Call-to-action button with link.
 */
final class ButtonBlock implements BlockTypeInterface
{
    public static function getId(): string { return 'button'; }
    public static function getLabel(): string { return 'Button'; }
    public static function getDescription(): string { return 'Call-to-action button with link'; }
    public static function getIcon(): string { return '🔘'; }
    public static function getCategory(): string { return 'Content'; }

    public static function getFields(): array
    {
        return [
            'text' => ['type' => 'string', 'label' => 'Button Text', 'required' => true, 'default' => 'Click Here'],
            'url' => ['type' => 'url', 'label' => 'Link URL', 'required' => true],
            'style' => ['type' => 'select', 'label' => 'Style', 'default' => 'primary', 'options' => [
                'primary' => 'Primary', 'secondary' => 'Secondary', 'outline' => 'Outline',
            ]],
            'target' => ['type' => 'select', 'label' => 'Open in', 'default' => '_self', 'options' => [
                '_self' => 'Same window', '_blank' => 'New window',
            ]],
        ];
    }

    public function render(array $data, array $settings = []): string
    {
        $text = htmlspecialchars($data['text'] ?? 'Click Here');
        $url = htmlspecialchars($data['url'] ?? '#');
        $style = $data['style'] ?? 'primary';
        $target = $data['target'] ?? '_self';

        return '<div class="block-button"><a href="' . $url . '" class="btn btn-' . $style . '" target="' . $target . '">' . $text . '</a></div>';
    }
}
