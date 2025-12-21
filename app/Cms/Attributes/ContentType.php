<?php

declare(strict_types=1);

namespace App\Cms\Attributes;

use Attribute;

/**
 * ContentType Attribute - Defines a CMS content type (similar to Drupal's Entity Type)
 * 
 * This attribute marks a class as a CMS content type, allowing the system
 * to auto-generate database tables and manage the entity through the admin UI.
 * 
 * Unlike WordPress custom post types (which share a single table), each content type
 * gets its own normalized table - improving query performance and data integrity.
 * 
 * Unlike Drupal, no YAML or UI configuration is needed - everything is code-first.
 * 
 * @example
 * ```php
 * #[ContentType(
 *     tableName: 'products',
 *     label: 'Product',
 *     labelPlural: 'Products',
 *     description: 'E-commerce product content type'
 * )]
 * class Product extends BaseEntity {}
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ContentType
{
    /**
     * @param string      $tableName    Database table name (snake_case recommended)
     * @param string      $label        Human-readable singular label for admin UI
     * @param string|null $labelPlural  Human-readable plural label (auto-generated if null)
     * @param string      $description  Brief description shown in admin panel
     * @param string      $icon         Icon identifier for admin menu (e.g., 'shopping-cart', 'file-text')
     * @param bool        $revisionable Whether to track content revisions (like WordPress post revisions)
     * @param bool        $publishable  Whether content supports draft/published workflow
     * @param bool        $translatable Whether content supports i18n translations
     * @param int         $menuWeight   Order in admin menu (lower = higher priority)
     */
    public function __construct(
        public string $tableName,
        public string $label,
        public ?string $labelPlural = null,
        public string $description = '',
        public string $icon = 'file',
        public bool $revisionable = false,
        public bool $publishable = true,
        public bool $translatable = false,
        public int $menuWeight = 0,
    ) {}

    /**
     * Get the plural label, auto-generating if not provided
     */
    public function getPluralLabel(): string
    {
        return $this->labelPlural ?? $this->label . 's';
    }
}
