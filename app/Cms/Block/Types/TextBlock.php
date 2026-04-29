<?php

declare(strict_types=1);

namespace App\Cms\Block\Types;

use App\Cms\Block\BlockTypeInterface;

/**
 * TextBlock — Simple rich text content block for the Mosaic editor.
 */
final class TextBlock implements BlockTypeInterface
{
    public static function getId(): string { return 'text'; }
    public static function getLabel(): string { return 'Text'; }
    public static function getDescription(): string { return 'Rich text content block'; }
    public static function getIcon(): string { return '📝'; }
    public static function getCategory(): string { return 'Content'; }

    public static function getFields(): array
    {
        return [
            'body' => ['type' => 'html', 'label' => 'Body', 'required' => true],
            'format' => ['type' => 'select', 'label' => 'Format', 'default' => 'html', 'options' => ['html' => 'HTML', 'markdown' => 'Markdown']],
        ];
    }

    public function render(array $data, array $settings = []): string
    {
        $body = $data['body'] ?? '';
        return '<div class="block-text">' . $body . '</div>';
    }
}
