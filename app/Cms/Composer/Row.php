<?php

declare(strict_types=1);

namespace App\Cms\Composer;

/**
 * Row - Flex/grid container within a section
 * 
 * Rows define the column layout (e.g., 1-1, 1-2, 2-1, 1-1-1).
 * They contain columns which hold the actual blocks.
 */
final class Row implements \JsonSerializable
{
    /**
     * Built-in layout presets
     * Format: array of column width percentages
     */
    public const LAYOUTS = [
        '1' => [100],
        '1-1' => [50, 50],
        '1-2' => [33.33, 66.67],
        '2-1' => [66.67, 33.33],
        '1-1-1' => [33.33, 33.33, 33.33],
        '1-1-1-1' => [25, 25, 25, 25],
        '1-2-1' => [25, 50, 25],
        '1-3' => [25, 75],
        '3-1' => [75, 25],
    ];

    public function __construct(
        private string $id,
        private array $columns = [],
        private array $settings = [],
    ) {
    }

    public static function create(string $layout = '1', array $settings = []): self
    {
        $widths = self::LAYOUTS[$layout] ?? self::LAYOUTS['1'];
        $columns = [];

        foreach ($widths as $width) {
            $columns[] = Column::create($width);
        }

        return new self(
            id: 'row-' . uniqid(),
            columns: $columns,
            settings: array_merge([
                'layout' => $layout,
                'gap' => 20,
                'verticalAlign' => 'top',
                'reverseOnMobile' => false,
                'cssClass' => '',
            ], $settings),
        );
    }

    public static function fromArray(array $data): self
    {
        $columns = [];
        foreach ($data['columns'] ?? [] as $colData) {
            $columns[] = Column::fromArray($colData);
        }

        return new self(
            id: $data['id'] ?? 'row-' . uniqid(),
            columns: $columns,
            settings: $data['settings'] ?? [],
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function getLayout(): string
    {
        return $this->settings['layout'] ?? '1';
    }

    public function addColumn(Column $column): self
    {
        $this->columns[] = $column;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'settings' => $this->settings,
            'columns' => array_map(fn(Column $col) => $col->toArray(), $this->columns),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Get all available layout options
     */
    public static function getAvailableLayouts(): array
    {
        return array_keys(self::LAYOUTS);
    }
}
