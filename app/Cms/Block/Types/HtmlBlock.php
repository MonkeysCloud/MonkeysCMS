<?php

declare(strict_types=1);

namespace App\Cms\Block\Types;

use App\Cms\Block\BlockTypeInterface;

/**
 * HtmlBlock — Raw HTML block for advanced users.
 */
final class HtmlBlock implements BlockTypeInterface
{
    public static function getId(): string { return 'html'; }
    public static function getLabel(): string { return 'HTML'; }
    public static function getDescription(): string { return 'Raw HTML content block'; }
    public static function getIcon(): string { return '🧱'; }
    public static function getCategory(): string { return 'Advanced'; }

    public static function getFields(): array
    {
        return [
            'content' => ['type' => 'code', 'label' => 'HTML Content', 'required' => true],
        ];
    }

    public function render(array $data, array $settings = []): string
    {
        return '<div class="block-html">' . ($data['content'] ?? '') . '</div>';
    }
}
