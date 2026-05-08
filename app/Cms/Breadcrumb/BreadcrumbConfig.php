<?php

declare(strict_types=1);

namespace App\Cms\Breadcrumb;

/**
 * BreadcrumbConfig — Per-type breadcrumb settings stored in `breadcrumb_configs`.
 */
class BreadcrumbConfig
{
    public ?int $id = null;

    /** Entity scope: 'node', 'term', 'listing', or 'global' */
    public string $entity_type = 'global';

    /** Content type ID, vocabulary machine_name, or '*' for global default */
    public string $bundle = '*';

    /** Whether breadcrumbs are enabled for this scope */
    public bool $enabled = true;

    /** Show "Home" as the first crumb */
    public bool $show_home = true;

    /** Show the current page title as the last (non-linked) crumb */
    public bool $show_current = true;

    /** Include the content type name in the trail (e.g. Home › Articles › Title) */
    public bool $show_content_type = true;

    /** Include the primary taxonomy term in the trail */
    public bool $show_taxonomy = false;

    /** Visual separator between crumbs */
    public string $separator = '›';

    /** Output JSON-LD structured data for SEO */
    public bool $json_ld = true;

    public ?\DateTimeImmutable $created_at = null;
    public ?\DateTimeImmutable $updated_at = null;

    public function hydrate(array $data): static
    {
        $this->id                = isset($data['id']) ? (int) $data['id'] : $this->id;
        $this->entity_type       = $data['entity_type'] ?? $this->entity_type;
        $this->bundle            = $data['bundle'] ?? $this->bundle;
        $this->enabled           = (bool) ($data['enabled'] ?? $this->enabled);
        $this->show_home         = (bool) ($data['show_home'] ?? $this->show_home);
        $this->show_current      = (bool) ($data['show_current'] ?? $this->show_current);
        $this->show_content_type = (bool) ($data['show_content_type'] ?? $this->show_content_type);
        $this->show_taxonomy     = (bool) ($data['show_taxonomy'] ?? $this->show_taxonomy);
        $this->separator         = $data['separator'] ?? $this->separator;
        $this->json_ld           = (bool) ($data['json_ld'] ?? $this->json_ld);
        $this->created_at        = isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : $this->created_at;
        $this->updated_at        = isset($data['updated_at']) ? new \DateTimeImmutable($data['updated_at']) : $this->updated_at;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id'                => $this->id,
            'entity_type'       => $this->entity_type,
            'bundle'            => $this->bundle,
            'enabled'           => $this->enabled,
            'show_home'         => $this->show_home,
            'show_current'      => $this->show_current,
            'show_content_type' => $this->show_content_type,
            'show_taxonomy'     => $this->show_taxonomy,
            'separator'         => $this->separator,
            'json_ld'           => $this->json_ld,
        ];
    }
}
