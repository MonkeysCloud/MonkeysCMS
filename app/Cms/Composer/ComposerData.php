<?php

declare(strict_types=1);

namespace App\Cms\Composer;

/**
 * ComposerData - Complete composer layout data wrapper
 * 
 * This wraps the entire JSON structure stored in composer_data column.
 */
final class ComposerData implements \JsonSerializable
{
    public const VERSION = '1.0';

    public function __construct(
        private string $version,
        private array $sections = [],
        private array $meta = [],
    ) {
    }

    /**
     * Create empty composer data
     */
    public static function empty(): self
    {
        return new self(
            version: self::VERSION,
            sections: [],
            meta: [],
        );
    }

    /**
     * Create from JSON string
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return self::empty();
        }
        return self::fromArray($data);
    }

    /**
     * Create from array (database)
     */
    public static function fromArray(array $data): self
    {
        $sections = [];
        foreach ($data['sections'] ?? [] as $sectionData) {
            $sections[] = Section::fromArray($sectionData);
        }

        return new self(
            version: $data['version'] ?? self::VERSION,
            sections: $sections,
            meta: $data['meta'] ?? [],
        );
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @return Section[]
     */
    public function getSections(): array
    {
        return $this->sections;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function isEmpty(): bool
    {
        return empty($this->sections);
    }

    public function addSection(Section $section): self
    {
        $this->sections[] = $section;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'sections' => array_map(fn(Section $s) => $s->toArray(), $this->sections),
            'meta' => $this->meta,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Get all blocks in the layout (flattened)
     * 
     * @return ComposerBlock[]
     */
    public function getAllBlocks(): array
    {
        $blocks = [];
        foreach ($this->sections as $section) {
            foreach ($section->getRows() as $row) {
                foreach ($row->getColumns() as $column) {
                    foreach ($column->getBlocks() as $block) {
                        $blocks[] = $block;
                    }
                }
            }
        }
        return $blocks;
    }

    /**
     * Get all field placeholders used in the layout
     * 
     * @return string[] Field names
     */
    public function getUsedFieldNames(): array
    {
        $fields = [];
        foreach ($this->getAllBlocks() as $block) {
            if ($block->isFieldPlaceholder()) {
                $fields[] = $block->get('field_name');
            }
        }
        return array_unique($fields);
    }
}
