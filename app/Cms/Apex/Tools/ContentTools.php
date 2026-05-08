<?php

declare(strict_types=1);

namespace App\Cms\Apex\Tools;

use MonkeysLegion\Apex\Tool\Attribute\Tool;
use MonkeysLegion\Apex\Tool\Attribute\ToolParam;

/**
 * ContentTools — AI-powered content operations for CMS nodes.
 *
 * Provides tool-attributed methods that the AI can call directly,
 * plus helper methods for the CMS API layer.
 */
final class ContentTools
{
    #[Tool(name: 'generate_content', description: 'Generate a full article/page body from a title and optional instructions')]
    public function generateContent(
        #[ToolParam(description: 'Title of the content to generate', required: true)]
        string $title,
        #[ToolParam(description: 'Specific instructions or topic details')]
        string $instructions = '',
        #[ToolParam(description: 'Content type (article, page, blog, etc.)')]
        string $contentType = 'article',
        #[ToolParam(description: 'Desired word count', enum: ['short:250', 'medium:500', 'long:1000', 'extended:2000'])]
        string $length = 'medium:500',
        #[ToolParam(description: 'Content format', enum: ['html', 'markdown', 'plain'])]
        string $format = 'html',
    ): array {
        $wordCount = (int) explode(':', $length)[1];
        return [
            'title'        => $title,
            'content_type' => $contentType,
            'word_count'   => $wordCount,
            'format'       => $format,
            'instructions' => $instructions,
            'action'       => 'generate',
        ];
    }

    #[Tool(name: 'rewrite_content', description: 'Rewrite existing content with a specific tone and style')]
    public function rewriteContent(
        #[ToolParam(description: 'The content to rewrite', required: true)]
        string $content,
        #[ToolParam(description: 'Target tone', enum: ['formal', 'casual', 'technical', 'creative', 'friendly', 'academic', 'journalistic'])]
        string $tone = 'professional',
        #[ToolParam(description: 'Specific rewrite instructions')]
        string $instructions = '',
        #[ToolParam(description: 'Whether to preserve the original structure')]
        bool $preserveStructure = true,
    ): array {
        return [
            'content'            => $content,
            'tone'               => $tone,
            'instructions'       => $instructions,
            'preserve_structure' => $preserveStructure,
            'action'             => 'rewrite',
        ];
    }

    #[Tool(name: 'summarize_content', description: 'Generate a concise summary of content')]
    public function summarizeContent(
        #[ToolParam(description: 'The content to summarize', required: true)]
        string $content,
        #[ToolParam(description: 'Maximum words for the summary')]
        int $maxWords = 150,
        #[ToolParam(description: 'Summary style', enum: ['executive', 'bullet_points', 'paragraph', 'abstract'])]
        string $style = 'paragraph',
    ): array {
        return [
            'content'   => $content,
            'max_words' => $maxWords,
            'style'     => $style,
            'action'    => 'summarize',
        ];
    }

    #[Tool(name: 'expand_content', description: 'Expand short content into a longer, more detailed version')]
    public function expandContent(
        #[ToolParam(description: 'The content to expand', required: true)]
        string $content,
        #[ToolParam(description: 'Target word count for expanded version')]
        int $targetWords = 500,
        #[ToolParam(description: 'What to focus the expansion on')]
        string $focus = '',
    ): array {
        return [
            'content'      => $content,
            'target_words' => $targetWords,
            'focus'        => $focus,
            'action'       => 'expand',
        ];
    }

    #[Tool(name: 'translate_content', description: 'Translate content to a target language')]
    public function translateContent(
        #[ToolParam(description: 'The content to translate', required: true)]
        string $content,
        #[ToolParam(description: 'Target language code or name', required: true)]
        string $targetLanguage,
        #[ToolParam(description: 'Whether to preserve HTML formatting')]
        bool $preserveFormatting = true,
    ): array {
        return [
            'content'             => $content,
            'target_language'     => $targetLanguage,
            'preserve_formatting' => $preserveFormatting,
            'action'              => 'translate',
        ];
    }

    #[Tool(name: 'grammar_check', description: 'Check and fix grammar, spelling, and punctuation')]
    public function grammarCheck(
        #[ToolParam(description: 'The content to check', required: true)]
        string $content,
        #[ToolParam(description: 'Language of the content')]
        string $language = 'en',
    ): array {
        return [
            'content'  => $content,
            'language' => $language,
            'action'   => 'grammar_check',
        ];
    }

    #[Tool(name: 'generate_titles', description: 'Generate multiple title suggestions for content')]
    public function generateTitles(
        #[ToolParam(description: 'Topic or existing content to generate titles for', required: true)]
        string $topic,
        #[ToolParam(description: 'Number of title suggestions')]
        int $count = 5,
        #[ToolParam(description: 'Style of titles', enum: ['clickbait', 'informative', 'question', 'how_to', 'listicle', 'professional'])]
        string $style = 'informative',
    ): array {
        return [
            'topic'  => $topic,
            'count'  => min($count, 10),
            'style'  => $style,
            'action' => 'generate_titles',
        ];
    }

    #[Tool(name: 'generate_outline', description: 'Generate a structured outline for content')]
    public function generateOutline(
        #[ToolParam(description: 'Topic to create an outline for', required: true)]
        string $topic,
        #[ToolParam(description: 'Number of main sections')]
        int $sections = 5,
        #[ToolParam(description: 'Content type context')]
        string $contentType = 'article',
    ): array {
        return [
            'topic'        => $topic,
            'sections'     => min($sections, 15),
            'content_type' => $contentType,
            'action'       => 'generate_outline',
        ];
    }

    // ─── Prompt Builders ────────────────────────────────────────────────────

    /**
     * Build the system prompt for content generation.
     */
    public static function buildSystemPrompt(string $action, string $contentType, string $format = 'html'): string
    {
        $formatInstruction = match ($format) {
            'html'     => 'Output clean, semantic HTML. Use <h2>, <h3>, <p>, <ul>, <ol>, <blockquote> tags. Do NOT include <html>, <head>, or <body> wrappers. Do NOT wrap your output in markdown code fences.',
            'markdown' => 'Output clean Markdown. Use ## for headings, - for lists, > for blockquotes. Do NOT wrap your output in code fences.',
            'plain'    => 'Output plain text only. No HTML tags or Markdown formatting. Do NOT wrap your output in code fences.',
            default    => 'Output clean HTML. Do NOT wrap your output in markdown code fences.',
        };

        $base = "You are a professional content writer for a CMS. Content type: {$contentType}. {$formatInstruction}";

        return match ($action) {
            'generate'         => "{$base}\n\nGenerate high-quality, original content. Structure it with clear headings and paragraphs. Make it engaging and informative.",
            'rewrite'          => "{$base}\n\nRewrite the provided content while maintaining its key message and information. Improve clarity, flow, and engagement.",
            'summarize'        => "{$base}\n\nCreate a concise, accurate summary that captures the main points. Maintain the original intent and key facts.",
            'expand'           => "{$base}\n\nExpand the provided content with additional detail, examples, and depth. Maintain the original voice and structure.",
            'translate'        => "{$base}\n\nTranslate accurately while preserving the original tone, style, and meaning. Adapt idioms and cultural references appropriately.",
            'grammar_check'    => "{$base}\n\nFix grammar, spelling, and punctuation errors. Return the corrected version. Only fix errors, do not rewrite the content.",
            'generate_titles'  => "Generate creative, compelling titles. Return ONLY a JSON array of title strings.",
            'generate_outline' => "Generate a structured content outline. Return ONLY a JSON object with 'sections' array, each having 'heading' and 'points' (array of strings).",
            default            => $base,
        };
    }
}
