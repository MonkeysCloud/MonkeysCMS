<?php

declare(strict_types=1);

namespace App\Cms\Composer;

/**
 * Column - Individual cell within a row
 * 
 * Columns have a width (percentage) and contain blocks.
 */
final class Column implements \JsonSerializable
{
    public function __construct(
        private string $id,
        private float $width,
        private array $blocks = [],
        private array $settings = [],
    ) {
    }

    public static function create(float $width = 100, array $settings = []): self
    {
        return new self(
            id: 'col-' . uniqid(),
            width: $width,
            blocks: [],
            settings: array_merge([
                'padding' => ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0],
                'verticalAlign' => 'top',
                'cssClass' => '',
            ], $settings),
        );
    }

    public static function fromArray(array $data): self
    {
        $blocks = [];
        foreach ($data['blocks'] ?? [] as $blockData) {
            $blocks[] = ComposerBlock::fromArray($blockData);
        }

        return new self(
            id: $data['id'] ?? 'col-' . uniqid(),
            width: (float) ($data['width'] ?? 100),
            blocks: $blocks,
            settings: $data['settings'] ?? [],
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getWidth(): float
    {
        return $this->width;
    }

    public function getBlocks(): array
    {
        return $this->blocks;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function addBlock(ComposerBlock $block): self
    {
        $this->blocks[] = $block;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'width' => $this->width,
            'settings' => $this->settings,
            'blocks' => array_map(fn(ComposerBlock $b) => $b->toArray(), $this->blocks),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
