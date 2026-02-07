<?php

declare(strict_types=1);

namespace App\Cms\Composer;

/**
 * Section - Full-width container in a composer layout
 * 
 * Sections are the top-level containers that hold rows.
 * They can have backgrounds, padding, and other styling.
 */
final class Section implements \JsonSerializable
{
    public function __construct(
        private string $id,
        private array $rows = [],
        private array $settings = [],
    ) {
    }

    public static function create(array $settings = []): self
    {
        return new self(
            id: 'section-' . uniqid(),
            rows: [],
            settings: array_merge([
                'background' => ['type' => 'none', 'value' => null],
                'padding' => ['top' => 40, 'bottom' => 40],
                'fullWidth' => false,
                'cssClass' => '',
                'cssId' => '',
            ], $settings),
        );
    }

    public static function fromArray(array $data): self
    {
        $rows = [];
        foreach ($data['rows'] ?? [] as $rowData) {
            $rows[] = Row::fromArray($rowData);
        }

        return new self(
            id: $data['id'] ?? 'section-' . uniqid(),
            rows: $rows,
            settings: $data['settings'] ?? [],
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getRows(): array
    {
        return $this->rows;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function addRow(Row $row): self
    {
        $this->rows[] = $row;
        return $this;
    }

    public function withSettings(array $settings): self
    {
        return new self($this->id, $this->rows, array_merge($this->settings, $settings));
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'settings' => $this->settings,
            'rows' => array_map(fn(Row $row) => $row->toArray(), $this->rows),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
