<?php

declare(strict_types=1);

namespace App\Cms\Apex\Schema;

use MonkeysLegion\Apex\Schema\Attribute\ArrayOf;
use MonkeysLegion\Apex\Schema\Attribute\Constrain;
use MonkeysLegion\Apex\Schema\Attribute\Description;
use MonkeysLegion\Apex\Schema\Attribute\Example;
use MonkeysLegion\Apex\Schema\Attribute\Optional;
use MonkeysLegion\Apex\Schema\Schema;

/**
 * TaxonomySuggestionSchema — Typed extraction for taxonomy/tag suggestions.
 */
final class TaxonomySuggestionSchema extends Schema
{
    #[Description('Ordered list of tag suggestions')]
    #[ArrayOf(TagSuggestion::class)]
    #[Constrain(minItems: 1, maxItems: 15)]
    public array $tags;

    #[Description('Ordered list of category suggestions')]
    #[ArrayOf(CategorySuggestion::class)]
    #[Constrain(maxItems: 5)]
    #[Optional]
    public array $categories = [];

    #[Description('Brief reasoning for the suggestions')]
    #[Constrain(maxLength: 500)]
    #[Optional]
    public string $reasoning = '';
}

/**
 * TagSuggestion — A single tag suggestion with confidence.
 */
final class TagSuggestion extends Schema
{
    #[Description('The tag name')]
    #[Constrain(minLength: 1, maxLength: 100)]
    #[Example('php', 'web-development', 'best-practices')]
    public string $name;

    #[Description('Confidence score from 0.0 to 1.0')]
    #[Constrain(min: 0, max: 1)]
    #[Example(0.92)]
    public float $confidence;

    #[Description('Whether this tag already exists in the vocabulary')]
    #[Optional]
    public bool $isExisting = false;

    #[Description('Taxonomy vocabulary this tag belongs to')]
    #[Optional]
    public string $vocabulary = '';
}

/**
 * CategorySuggestion — A category assignment suggestion.
 */
final class CategorySuggestion extends Schema
{
    #[Description('Category ID if matching an existing term')]
    #[Optional]
    public ?int $id = null;

    #[Description('Category name')]
    #[Constrain(minLength: 1, maxLength: 255)]
    #[Example('Technology', 'Business', 'Science')]
    public string $name;

    #[Description('Confidence score from 0.0 to 1.0')]
    #[Constrain(min: 0, max: 1)]
    #[Example(0.85)]
    public float $confidence;
}
