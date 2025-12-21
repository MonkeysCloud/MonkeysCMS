<?php

declare(strict_types=1);

namespace App\Modules\Custom\Ecommerce\Entities;

use App\Cms\Attributes\ContentType;
use App\Cms\Attributes\Field;
use App\Cms\Attributes\Id;
use App\Cms\Attributes\Relation;
use App\Cms\Core\BaseEntity;

/**
 * Product Entity - Demonstrates the CMS entity system
 *
 * This entity showcases how to define a content type using attributes.
 * When the Ecommerce module is enabled, the ModuleManager will:
 * 1. Discover this class via reflection
 * 2. Read the #[ContentType] and #[Field] attributes
 * 3. Generate and execute the CREATE TABLE SQL
 *
 * No migrations, no hook_install(), no activate_plugin() - just enable and go.
 *
 * @example
 * ```php
 * // Create a product
 * $product = new Product();
 * $product->name = 'Premium Widget';
 * $product->sku = 'WDG-001';
 * $product->price = 29.99;
 * $product->description = 'The best widget money can buy';
 * $product->stock_quantity = 100;
 *
 * $repo->save($product);
 * ```
 */
#[ContentType(
    tableName: 'products',
    label: 'Product',
    labelPlural: 'Products',
    description: 'E-commerce product for sale',
    icon: 'shopping-cart',
    revisionable: true,
    publishable: true,
    menuWeight: 10
)]
class Product extends BaseEntity
{
    /**
     * Product name/title
     */
    #[Field(
        type: 'string',
        label: 'Product Name',
        required: true,
        length: 255,
        searchable: true,
        listable: true,
        filterable: true,
        description: 'The display name of the product'
    )]
    public string $name = '';

    /**
     * Stock Keeping Unit - unique product identifier
     */
    #[Field(
        type: 'string',
        label: 'SKU',
        required: true,
        length: 100,
        unique: true,
        indexed: true,
        searchable: true,
        listable: true,
        description: 'Unique product identifier for inventory'
    )]
    public string $sku = '';

    /**
     * URL-friendly slug
     */
    #[Field(
        type: 'string',
        label: 'Slug',
        required: true,
        length: 255,
        unique: true,
        indexed: true,
        description: 'URL-friendly identifier'
    )]
    public string $slug = '';

    /**
     * Short product description
     */
    #[Field(
        type: 'string',
        label: 'Short Description',
        length: 500,
        searchable: true,
        widget: 'textarea',
        description: 'Brief product summary for listings'
    )]
    public string $short_description = '';

    /**
     * Full product description with rich content
     */
    #[Field(
        type: 'text',
        label: 'Description',
        searchable: true,
        widget: 'wysiwyg',
        description: 'Full product description with formatting'
    )]
    public string $description = '';

    /**
     * Product price in the default currency
     */
    #[Field(
        type: 'decimal',
        label: 'Price',
        required: true,
        precision: 10,
        scale: 2,
        listable: true,
        filterable: true,
        description: 'Product selling price'
    )]
    public float $price = 0.00;

    /**
     * Compare-at price (original price before discount)
     */
    #[Field(
        type: 'decimal',
        label: 'Compare At Price',
        precision: 10,
        scale: 2,
        description: 'Original price to show discount'
    )]
    public ?float $compare_at_price = null;

    /**
     * Cost price (for profit calculation)
     */
    #[Field(
        type: 'decimal',
        label: 'Cost',
        precision: 10,
        scale: 2,
        description: 'Product cost for margin calculation'
    )]
    public ?float $cost = null;

    /**
     * Current stock quantity
     */
    #[Field(
        type: 'int',
        label: 'Stock Quantity',
        required: true,
        default: 0,
        listable: true,
        filterable: true,
        description: 'Available inventory count'
    )]
    public int $stock_quantity = 0;

    /**
     * Low stock threshold for alerts
     */
    #[Field(
        type: 'int',
        label: 'Low Stock Threshold',
        default: 5,
        description: 'Alert when stock drops below this'
    )]
    public int $low_stock_threshold = 5;

    /**
     * Weight in kilograms (for shipping)
     */
    #[Field(
        type: 'decimal',
        label: 'Weight (kg)',
        precision: 8,
        scale: 3,
        description: 'Product weight for shipping calculation'
    )]
    public ?float $weight = null;

    /**
     * Product status (draft, published, archived)
     */
    #[Field(
        type: 'string',
        label: 'Status',
        required: true,
        length: 20,
        default: 'draft',
        indexed: true,
        listable: true,
        filterable: true,
        widget: 'select',
        options: ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'],
        description: 'Publication status'
    )]
    public string $status = 'draft';

    /**
     * Whether the product is featured
     */
    #[Field(
        type: 'boolean',
        label: 'Featured',
        default: false,
        indexed: true,
        filterable: true,
        description: 'Show on featured products section'
    )]
    public bool $is_featured = false;

    /**
     * Whether the product is taxable
     */
    #[Field(
        type: 'boolean',
        label: 'Taxable',
        default: true,
        description: 'Whether tax should be charged'
    )]
    public bool $is_taxable = true;

    /**
     * SEO meta title
     */
    #[Field(
        type: 'string',
        label: 'Meta Title',
        length: 255,
        group: 'seo',
        description: 'Custom title for search engines'
    )]
    public string $meta_title = '';

    /**
     * SEO meta description
     */
    #[Field(
        type: 'string',
        label: 'Meta Description',
        length: 500,
        group: 'seo',
        widget: 'textarea',
        description: 'Description for search engine results'
    )]
    public string $meta_description = '';

    /**
     * Product images (stored as JSON array of image data)
     */
    #[Field(
        type: 'json',
        label: 'Images',
        widget: 'image_gallery',
        description: 'Product images'
    )]
    public array $images = [];

    /**
     * Custom attributes/specifications (stored as JSON)
     */
    #[Field(
        type: 'json',
        label: 'Attributes',
        widget: 'key_value',
        description: 'Custom product attributes (color, size, material, etc.)'
    )]
    public array $attributes = [];

    /**
     * Category relationship (example of ManyToOne - commented until Category entity exists)
     *
     * Uncomment when Category entity is created:
     *
     * #[Relation(
     *     type: Relation::MANY_TO_ONE,
     *     target: Category::class,
     *     label: 'Category',
     *     inversedBy: 'products',
     *     onDelete: 'SET NULL'
     * )]
     * public ?Category $category = null;
     */

    /**
     * Generate slug from name if not set
     */
    public function prePersist(): void
    {
        parent::prePersist();

        if (empty($this->slug)) {
            $this->slug = $this->generateSlug($this->name);
        }
    }

    /**
     * Generate URL-friendly slug
     */
    private function generateSlug(string $text): string
    {
        // Convert to lowercase
        $slug = strtolower($text);

        // Replace non-alphanumeric with hyphens
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        // Remove leading/trailing hyphens
        return trim($slug, '-');
    }

    /**
     * Check if product is in stock
     */
    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
    }

    /**
     * Check if product is low on stock
     */
    public function isLowStock(): bool
    {
        return $this->stock_quantity > 0 && $this->stock_quantity <= $this->low_stock_threshold;
    }

    /**
     * Check if product is on sale
     */
    public function isOnSale(): bool
    {
        return $this->compare_at_price !== null && $this->compare_at_price > $this->price;
    }

    /**
     * Get discount percentage
     */
    public function getDiscountPercentage(): float
    {
        if (!$this->isOnSale() || $this->compare_at_price === null) {
            return 0.0;
        }

        return round((($this->compare_at_price - $this->price) / $this->compare_at_price) * 100, 1);
    }

    /**
     * Calculate profit margin
     */
    public function getProfitMargin(): ?float
    {
        if ($this->cost === null || $this->cost <= 0) {
            return null;
        }

        return round((($this->price - $this->cost) / $this->price) * 100, 2);
    }
}
