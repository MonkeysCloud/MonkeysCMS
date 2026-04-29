<?php

declare(strict_types=1);

namespace App\Cms\Block\Types;

use App\Cms\Block\BlockTypeInterface;

/**
 * HeadingBlock — Section heading with configurable level.
 */
final class HeadingBlock implements BlockTypeInterface
{
    public static function getId(): string { return 'heading'; }
    public static function getLabel(): string { return 'Heading'; }
    public static function getDescription(): string { return 'Section heading (H2–H6)'; }
    public static function getIcon(): string { return '🔤'; }
    public static function getCategory(): string { return 'Content'; }

    public static function getFields(): array
    {
        return [
            'text' => ['type' => 'string', 'label' => 'Heading Text', 'required' => true],
            'level' => ['type' => 'select', 'label' => 'Level', 'default' => 'h2', 'options' => [
                'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6',
            ]],
            'id' => ['type' => 'string', 'label' => 'Anchor ID'],
        ];
    }

    public function render(array $data, array $settings = []): string
    {
        $text = htmlspecialchars($data['text'] ?? '');
        $level = in_array($data['level'] ?? 'h2', ['h2', 'h3', 'h4', 'h5', 'h6']) ? $data['level'] : 'h2';
        $id = $data['id'] ?? '';
        $idAttr = $id ? ' id="' . htmlspecialchars($id) . '"' : '';

        return "<{$level}{$idAttr} class=\"block-heading\">{$text}</{$level}>";
    }
}
