<?php

declare(strict_types=1);

namespace App\Cms\Apex\Tools;

use MonkeysLegion\Apex\Tool\Attribute\Tool;
use MonkeysLegion\Apex\Tool\Attribute\ToolParam;

/**
 * SEOTools — AI-powered SEO optimization for CMS content.
 */
final class SEOTools
{
    #[Tool(name: 'generate_meta_title', description: 'Generate an SEO-optimized page title (max 60 characters)')]
    public function generateMetaTitle(
        #[ToolParam(description: 'Content title or body text', required: true)]
        string $content,
        #[ToolParam(description: 'Focus keyword to include')]
        string $focusKeyword = '',
        #[ToolParam(description: 'Brand name to append')]
        string $brandName = '',
    ): array {
        return ['content' => $content, 'focus_keyword' => $focusKeyword, 'brand' => $brandName, 'action' => 'meta_title'];
    }

    #[Tool(name: 'generate_meta_description', description: 'Generate an SEO meta description (max 160 characters)')]
    public function generateMetaDescription(
        #[ToolParam(description: 'Content to describe', required: true)]
        string $content,
        #[ToolParam(description: 'Focus keyword to include')]
        string $focusKeyword = '',
        #[ToolParam(description: 'Call-to-action style', enum: ['informative', 'persuasive', 'urgent', 'curious'])]
        string $ctaStyle = 'informative',
    ): array {
        return ['content' => $content, 'focus_keyword' => $focusKeyword, 'cta_style' => $ctaStyle, 'action' => 'meta_description'];
    }

    #[Tool(name: 'generate_slug', description: 'Generate an SEO-friendly URL slug')]
    public function generateSlug(
        #[ToolParam(description: 'Content title', required: true)]
        string $title,
        #[ToolParam(description: 'Maximum slug length')]
        int $maxLength = 60,
    ): array {
        return ['title' => $title, 'max_length' => $maxLength, 'action' => 'slug'];
    }

    #[Tool(name: 'analyze_seo', description: 'Score content for SEO best practices')]
    public function analyzeSeo(
        #[ToolParam(description: 'Content body to analyze', required: true)]
        string $content,
        #[ToolParam(description: 'Page title')]
        string $title = '',
        #[ToolParam(description: 'Current meta description')]
        string $metaDescription = '',
        #[ToolParam(description: 'Focus keyword for analysis')]
        string $focusKeyword = '',
    ): array {
        return [
            'content' => $content, 'title' => $title,
            'meta_description' => $metaDescription, 'focus_keyword' => $focusKeyword,
            'action' => 'analyze_seo',
        ];
    }

    #[Tool(name: 'generate_keywords', description: 'Extract and suggest focus keywords from content')]
    public function generateKeywords(
        #[ToolParam(description: 'Content to extract keywords from', required: true)]
        string $content,
        #[ToolParam(description: 'Maximum number of keywords')]
        int $count = 10,
    ): array {
        return ['content' => $content, 'count' => $count, 'action' => 'keywords'];
    }

    #[Tool(name: 'generate_og_description', description: 'Generate an Open Graph / social media description')]
    public function generateOgDescription(
        #[ToolParam(description: 'Content to describe', required: true)]
        string $content,
        #[ToolParam(description: 'Social platform target', enum: ['general', 'twitter', 'linkedin', 'facebook'])]
        string $platform = 'general',
    ): array {
        return ['content' => $content, 'platform' => $platform, 'action' => 'og_description'];
    }

    /**
     * Build system prompt for SEO operations.
     */
    public static function buildSystemPrompt(string $action): string
    {
        return match ($action) {
            'meta_title'       => 'You are an SEO expert. Generate a compelling, keyword-optimized page title. Maximum 60 characters. Return ONLY the title text, nothing else.',
            'meta_description' => 'You are an SEO expert. Generate a compelling meta description that drives clicks. Maximum 160 characters. Include the focus keyword naturally. Return ONLY the description text.',
            'slug'             => 'You are an SEO expert. Generate a clean, SEO-friendly URL slug. Use lowercase, hyphens only, no special characters. Maximum 60 characters. Return ONLY the slug.',
            'analyze_seo'      => "You are an SEO analyst. Analyze the content and return a JSON object with:\n- score: 0-100 overall SEO score\n- readability: 'easy'|'medium'|'hard'\n- keyword_density: percentage as float\n- word_count: integer\n- issues: array of {type, severity: 'low'|'medium'|'high', message}\n- suggestions: array of actionable improvement strings\nReturn ONLY valid JSON.",
            'keywords'         => 'You are an SEO expert. Extract and suggest focus keywords from the content. Return ONLY a JSON array of keyword strings, ordered by relevance.',
            'og_description'   => 'You are a social media expert. Generate an engaging description optimized for social sharing. Maximum 200 characters. Return ONLY the description text.',
            default            => 'You are an SEO expert helping optimize web content.',
        };
    }
}
