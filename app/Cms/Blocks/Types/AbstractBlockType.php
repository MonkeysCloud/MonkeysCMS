<?php

declare(strict_types=1);

namespace App\Cms\Blocks\Types;

use App\Modules\Core\Entities\Block;

/**
 * AbstractBlockType - Base implementation for block types
 * 
 * Extend this class to create a new block type with sensible defaults.
 */
abstract class AbstractBlockType implements BlockTypeInterface
{
    protected const ID = 'abstract';
    protected const LABEL = 'Abstract Block';
    protected const DESCRIPTION = '';
    protected const ICON = '🧱';
    protected const CATEGORY = 'General';
    protected const CACHE_TTL = 3600;

    public static function getId(): string
    {
        return static::ID;
    }

    public static function getLabel(): string
    {
        return static::LABEL;
    }

    public static function getDescription(): string
    {
        return static::DESCRIPTION;
    }

    public static function getIcon(): string
    {
        return static::ICON;
    }

    public static function getCategory(): string
    {
        return static::CATEGORY;
    }

    public static function getFields(): array
    {
        return [];
    }

    public static function getDefaultSettings(): array
    {
        return [];
    }

    public function validate(array $data): array
    {
        $errors = [];
        $fields = static::getFields();

        foreach ($fields as $name => $field) {
            $value = $data[$name] ?? null;
            $isRequired = $field['required'] ?? false;

            if ($isRequired && ($value === null || $value === '')) {
                $errors[$name] = sprintf('%s is required', $field['label'] ?? $name);
            }
        }

        return $errors;
    }

    public function processData(array $data): array
    {
        return $data;
    }

    public function getCacheTags(Block $block): array
    {
        return [
            'block',
            'block:' . $block->id,
            'block_type:' . static::ID,
        ];
    }

    public function getCacheTtl(): int
    {
        return static::CACHE_TTL;
    }

    public function canBePlacedInRegion(string $region): bool
    {
        return true; // All regions by default
    }

    public static function getJsAssets(): array
    {
        return [];
    }

    public static function getCssAssets(): array
    {
        return [];
    }

    /**
     * Get field value from block with type casting
     */
    protected function getFieldValue(Block $block, string $field, mixed $default = null): mixed
    {
        $settings = $block->settings ?? [];
        return $settings[$field] ?? $default;
    }

    /**
     * Helper to escape HTML
     */
    protected function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Helper to render a template file
     */
    protected function renderTemplate(string $template, array $variables = []): string
    {
        extract($variables);
        ob_start();
        include $template;
        return ob_get_clean() ?: '';
    }
}
