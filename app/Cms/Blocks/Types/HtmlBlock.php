<?php

declare(strict_types=1);

namespace App\Cms\Blocks\Types;

use App\Modules\Core\Entities\Block;

class HtmlBlock implements BlockTypeInterface
{
    public static function getId(): string
    {
        return 'html_block';
    }

    public static function getLabel(): string
    {
        return 'HTML Block (Code)';
    }

    public static function getDescription(): string
    {
        return 'A simple block defined in code that renders raw HTML';
    }

    public static function getIcon(): string
    {
        return 'code';
    }

    public static function getCategory(): string
    {
        return 'Core';
    }

    public static function getFields(): array
    {
        return [
            'content' => [
                'type' => 'textarea',
                'label' => 'HTML Content',
                'description' => 'Enter raw HTML here',
                'required' => true,
                'widget' => 'code_editor',
                'settings' => ['language' => 'html'],
            ],
            'classes' => [
                'type' => 'text',
                'label' => 'CSS Classes',
                'description' => 'Additional CSS classes for the wrapper',
            ]
        ];
    }

    public static function getDefaultSettings(): array
    {
        return [
            'content' => '<div>Hello World</div>',
            'classes' => '',
        ];
    }

    public function render(Block $block, array $context = []): string
    {
        $settings = $block->settings ?? [];
        $content = $settings['content'] ?? '';
        $classes = $settings['classes'] ?? '';

        return sprintf(
            '<div class="html-block %s">%s</div>',
            htmlspecialchars($classes),
            $content
        );
    }

    public function validate(array $data): array
    {
        $errors = [];
        if (empty($data['content'])) {
            $errors['content'] = 'Content is required';
        }
        return $errors;
    }

    public function processData(array $data): array
    {
        return $data;
    }

    public function getCacheTags(Block $block): array
    {
        return ['block:' . $block->id];
    }

    public function getCacheTtl(): int
    {
        return 3600;
    }

    public function canBePlacedInRegion(string $region): bool
    {
        return true;
    }

    public static function getJsAssets(): array
    {
        return [];
    }

    public static function getCssAssets(): array
    {
        return [];
    }
}
