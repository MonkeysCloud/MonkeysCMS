<?php

declare(strict_types=1);

namespace App\Cms\I18n;

/**
 * TranslatableInterface — Contract for entities that support translation.
 *
 * Any entity class can implement this to declare translation support.
 * Custom modules implement this for their own entity types.
 *
 * Example:
 *   class ProductEntity implements TranslatableInterface {
 *       public function getTranslatableType(): string { return 'product'; }
 *       public function getTranslatableId(): int { return $this->id; }
 *       public function getLanguage(): string { return $this->language; }
 *   }
 */
interface TranslatableInterface
{
    /**
     * The entity type identifier used in the entity_translations table.
     * E.g. 'node', 'term', 'menu_item', 'block', 'webform', 'product'.
     */
    public function getTranslatableType(): string;

    /**
     * The entity's primary key.
     */
    public function getTranslatableId(): int;

    /**
     * The entity's current language code.
     */
    public function getLanguage(): string;
}
