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
 * ContentSchema — Typed extraction for full content generation.
 *
 * Used with `ApexService::extract()` to get structured content output
 * from unstructured text or from a generation prompt.
 */
final class ContentSchema extends Schema
{
    #[Description('The content title')]
    #[Constrain(minLength: 1, maxLength: 255)]
    #[Example('10 Essential Tips for Better PHP Code')]
    public string $title;

    #[Description('The main body content in HTML format')]
    #[Constrain(minLength: 10)]
    public string $body;

    #[Description('A short summary of the content (1-3 sentences)')]
    #[Constrain(maxLength: 500)]
    #[Optional]
    public string $summary = '';

    #[Description('SEO meta title (max 60 characters)')]
    #[Constrain(maxLength: 60)]
    #[Example('PHP Tips for Clean Code | Blog')]
    #[Optional]
    public string $metaTitle = '';

    #[Description('SEO meta description (max 160 characters)')]
    #[Constrain(maxLength: 160)]
    #[Example('Learn 10 essential tips to write cleaner, more maintainable PHP code.')]
    #[Optional]
    public string $metaDescription = '';

    #[Description('Suggested taxonomy tags for the content')]
    #[ArrayOf('string')]
    #[Constrain(maxItems: 10)]
    #[Example('php', 'coding', 'best-practices')]
    #[Optional]
    public array $suggestedTags = [];

    #[Description('URL-friendly slug derived from the title')]
    #[Constrain(maxLength: 100, pattern: '^[a-z0-9]+(?:-[a-z0-9]+)*$')]
    #[Example('10-essential-tips-better-php-code')]
    #[Optional]
    public string $slug = '';

    #[Description('Content category suggestion')]
    #[Optional]
    public string $category = '';

    #[Description('Estimated reading time in minutes')]
    #[Constrain(min: 1, max: 120)]
    #[Optional]
    public int $readingTimeMinutes = 0;
}
