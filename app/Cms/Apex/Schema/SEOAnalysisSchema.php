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
 * SEOAnalysisSchema — Typed extraction for SEO analysis results.
 */
final class SEOAnalysisSchema extends Schema
{
    #[Description('Overall SEO score from 0 to 100')]
    #[Constrain(min: 0, max: 100)]
    #[Example(72)]
    public int $score;

    #[Description('Readability level of the content')]
    #[Constrain(enum: ['easy', 'medium', 'hard'])]
    #[Example('medium')]
    public string $readability;

    #[Description('Focus keyword density as a percentage (0.0-100.0)')]
    #[Constrain(min: 0, max: 100)]
    #[Example(2.4)]
    public float $keywordDensity;

    #[Description('Total word count of the analyzed content')]
    #[Constrain(min: 0)]
    #[Example(1250)]
    public int $wordCount;

    #[Description('Number of headings found (h1-h6)')]
    #[Constrain(min: 0)]
    #[Optional]
    public int $headingCount = 0;

    #[Description('Number of internal links found')]
    #[Constrain(min: 0)]
    #[Optional]
    public int $internalLinks = 0;

    #[Description('Number of external links found')]
    #[Constrain(min: 0)]
    #[Optional]
    public int $externalLinks = 0;

    #[Description('Number of images found')]
    #[Constrain(min: 0)]
    #[Optional]
    public int $imageCount = 0;

    #[Description('Number of images missing alt text')]
    #[Constrain(min: 0)]
    #[Optional]
    public int $imagesWithoutAlt = 0;

    #[Description('List of SEO issues found')]
    #[ArrayOf(SEOIssue::class)]
    #[Constrain(maxItems: 20)]
    public array $issues = [];

    #[Description('Actionable SEO improvement suggestions')]
    #[ArrayOf('string')]
    #[Constrain(maxItems: 10)]
    #[Example('Add the focus keyword to the first paragraph', 'Include more internal links')]
    public array $suggestions = [];

    #[Description('Suggested meta title (max 60 chars)')]
    #[Constrain(maxLength: 60)]
    #[Optional]
    public string $suggestedMetaTitle = '';

    #[Description('Suggested meta description (max 160 chars)')]
    #[Constrain(maxLength: 160)]
    #[Optional]
    public string $suggestedMetaDescription = '';
}

/**
 * SEOIssue — A single SEO issue within the analysis.
 */
final class SEOIssue extends Schema
{
    #[Description('Issue type identifier')]
    #[Constrain(enum: [
        'missing_meta_title', 'meta_title_too_long', 'meta_title_too_short',
        'missing_meta_description', 'meta_description_too_long', 'meta_description_too_short',
        'missing_h1', 'multiple_h1', 'low_keyword_density', 'high_keyword_density',
        'no_images', 'images_missing_alt', 'no_internal_links', 'no_external_links',
        'short_content', 'readability', 'missing_structured_data', 'other',
    ])]
    #[Example('low_keyword_density')]
    public string $type;

    #[Description('Severity of the issue')]
    #[Constrain(enum: ['low', 'medium', 'high'])]
    #[Example('medium')]
    public string $severity;

    #[Description('Human-readable description of the issue')]
    #[Example('The focus keyword appears only once in the content. Aim for 2-3% density.')]
    public string $message;
}
