<?php

declare(strict_types=1);

namespace App\Cms\Composer;

/**
 * ComposerBlock - A block instance within a composer layout
 * 
 * This represents an inline block instance, NOT the CMS Block entity.
 * It references a block type and contains instance-specific data.
 */
final class ComposerBlock implements \JsonSerializable
{
    /**
     * Special block type for content type field placeholders
     */
    public const TYPE_FIELD_PLACEHOLDER = '_field_placeholder';

    /**
     * Special block type for referencing saved CMS blocks
     */
    public const TYPE_SAVED_BLOCK = '_saved_block';

    public function __construct(
        private string $id,
        private string $type,
        private array $data = [],
        private array $settings = [],
    ) {
    }

    /**
     * Create a new block instance
     */
    public static function create(string $type, array $data = [], array $settings = []): self
    {
        return new self(
            id: 'block-' . uniqid(),
            type: $type,
            data: $data,
            settings: array_merge([
                'margin' => ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0],
                'animation' => 'none',
                'cssClass' => '',
                'hideOnMobile' => false,
                'hideOnDesktop' => false,
            ], $settings),
        );
    }

    /**
     * Create a field placeholder block
     */
    public static function fieldPlaceholder(string $fieldName, array $options = []): self
    {
        return self::create(self::TYPE_FIELD_PLACEHOLDER, [
            'field_name' => $fieldName,
            'view_mode' => $options['view_mode'] ?? 'default',
            'hide_label' => $options['hide_label'] ?? false,
        ]);
    }

    /**
     * Create a reference to a saved CMS block
     */
    public static function savedBlock(int $blockId): self
    {
        return self::create(self::TYPE_SAVED_BLOCK, [
            'block_id' => $blockId,
        ]);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? 'block-' . uniqid(),
            type: $data['type'] ?? 'text',
            data: $data['data'] ?? [],
            settings: $data['settings'] ?? [],
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function isFieldPlaceholder(): bool
    {
        return $this->type === self::TYPE_FIELD_PLACEHOLDER;
    }

    public function isSavedBlock(): bool
    {
        return $this->type === self::TYPE_SAVED_BLOCK;
    }

    public function withData(array $data): self
    {
        return new self($this->id, $this->type, array_merge($this->data, $data), $this->settings);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'data' => $this->data,
            'settings' => $this->settings,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
