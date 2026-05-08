<?php

declare(strict_types=1);

namespace App\Cms\Slug;

/**
 * SlugPattern — Defines a URL pattern for an entity type + bundle.
 *
 * Supports tokens: [title], [type], [year], [month], [day], [id]
 * PHP 8.4 property hooks for computed metadata.
 */
final class SlugPattern
{
    public function __construct(
        public ?int $id = null,
        public string $entity_type = 'node',
        public string $bundle = '',
        public string $pattern = '[title]',
        public int $weight = 0,
        public ?\DateTimeImmutable $created_at = null,
        public ?\DateTimeImmutable $updated_at = null,
    ) {}

    // ── PHP 8.4 Property Hooks ──────────────────────────────────────

    /** Parsed list of tokens found in the pattern */
    public array $tokenList {
        get {
            preg_match_all('/\[([a-z_]+)]/', $this->pattern, $m);
            return $m[1] ?? [];
        }
    }

    /** Human-readable preview of the generated slug */
    public string $preview {
        get {
            $replacements = [
                '[title]' => 'my-example-title',
                '[type]'  => $this->bundle ?: 'article',
                '[year]'  => date('Y'),
                '[month]' => date('m'),
                '[day]'   => date('d'),
                '[id]'    => '42',
            ];
            return strtr($this->pattern, $replacements);
        }
    }

    /** Display label for the entity type */
    public string $entityLabel {
        get => match ($this->entity_type) {
            'node' => 'Content',
            'term' => 'Taxonomy Term',
            default => ucfirst($this->entity_type),
        };
    }

    // ── Hydration ───────────────────────────────────────────────────

    public static function fromRow(array $row): static
    {
        return new static(
            id: (int) $row['id'],
            entity_type: $row['entity_type'] ?? 'node',
            bundle: $row['bundle'] ?? '',
            pattern: $row['pattern'] ?? '[title]',
            weight: (int) ($row['weight'] ?? 0),
            created_at: isset($row['created_at']) ? new \DateTimeImmutable($row['created_at']) : null,
            updated_at: isset($row['updated_at']) ? new \DateTimeImmutable($row['updated_at']) : null,
        );
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'entity_type' => $this->entity_type,
            'bundle'      => $this->bundle,
            'pattern'     => $this->pattern,
            'weight'      => $this->weight,
            'preview'     => $this->preview,
            'tokens'      => $this->tokenList,
        ];
    }
}
