<?php

declare(strict_types=1);

namespace App\Cms\Apex\Tools;

use MonkeysLegion\Apex\Tool\Attribute\Tool;
use MonkeysLegion\Apex\Tool\Attribute\ToolParam;

/**
 * TaxonomyTools — AI-powered taxonomy suggestions for CMS content.
 */
final class TaxonomyTools
{
    #[Tool(name: 'suggest_tags', description: 'Auto-suggest taxonomy terms/tags from content body')]
    public function suggestTags(
        #[ToolParam(description: 'Content text to analyze', required: true)]
        string $content,
        #[ToolParam(description: 'Existing tags in the vocabulary (JSON array)')]
        string $existingTags = '[]',
        #[ToolParam(description: 'Maximum number of tag suggestions')]
        int $maxSuggestions = 10,
        #[ToolParam(description: 'Whether to prefer existing tags over new ones')]
        bool $preferExisting = true,
    ): array {
        return [
            'content'         => $content,
            'existing_tags'   => $existingTags,
            'max_suggestions' => $maxSuggestions,
            'prefer_existing' => $preferExisting,
            'action'          => 'suggest_tags',
        ];
    }

    #[Tool(name: 'categorize_content', description: 'Classify content into existing vocabulary categories')]
    public function categorizeContent(
        #[ToolParam(description: 'Content to categorize', required: true)]
        string $content,
        #[ToolParam(description: 'Available categories as JSON array of {id, name}', required: true)]
        string $categories,
        #[ToolParam(description: 'Maximum categories to assign')]
        int $maxCategories = 3,
    ): array {
        return [
            'content'        => $content,
            'categories'     => $categories,
            'max_categories' => $maxCategories,
            'action'         => 'categorize',
        ];
    }

    #[Tool(name: 'generate_term_description', description: 'Generate a description for a taxonomy term')]
    public function generateTermDescription(
        #[ToolParam(description: 'Term name', required: true)]
        string $termName,
        #[ToolParam(description: 'Vocabulary name for context')]
        string $vocabularyName = '',
        #[ToolParam(description: 'Maximum words')]
        int $maxWords = 50,
    ): array {
        return [
            'term_name'       => $termName,
            'vocabulary_name' => $vocabularyName,
            'max_words'       => $maxWords,
            'action'          => 'term_description',
        ];
    }

    #[Tool(name: 'suggest_related_terms', description: 'Suggest related terms for a given taxonomy term')]
    public function suggestRelatedTerms(
        #[ToolParam(description: 'The term to find related terms for', required: true)]
        string $termName,
        #[ToolParam(description: 'Existing terms in the vocabulary (JSON array)')]
        string $existingTerms = '[]',
        #[ToolParam(description: 'Number of suggestions')]
        int $count = 5,
    ): array {
        return [
            'term_name'      => $termName,
            'existing_terms' => $existingTerms,
            'count'          => $count,
            'action'         => 'related_terms',
        ];
    }

    /**
     * Build system prompt for taxonomy operations.
     */
    public static function buildSystemPrompt(string $action): string
    {
        return match ($action) {
            'suggest_tags'     => "You are a content analyst. Analyze the text and suggest relevant tags/keywords.\nIf existing tags are provided, prefer matching those over creating new ones.\nReturn ONLY a JSON array of objects: [{\"name\": \"tag-name\", \"confidence\": 0.95, \"is_existing\": true}]\nOrder by confidence descending.",
            'categorize'       => "You are a content classifier. Analyze the content and select the most appropriate categories from the provided list.\nReturn ONLY a JSON array of objects: [{\"id\": 1, \"name\": \"Category Name\", \"confidence\": 0.9}]\nOrder by confidence descending.",
            'term_description' => 'You are a content writer. Write a concise, informative description for the taxonomy term. Return ONLY the description text.',
            'related_terms'    => "You are a taxonomy expert. Suggest related terms that would complement the given term in a vocabulary.\nReturn ONLY a JSON array of strings with suggested term names.",
            default            => 'You are a taxonomy expert helping organize content.',
        };
    }
}
