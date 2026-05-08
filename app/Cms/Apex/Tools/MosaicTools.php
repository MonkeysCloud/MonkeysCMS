<?php

declare(strict_types=1);

namespace App\Cms\Apex\Tools;

use MonkeysLegion\Apex\Tool\Attribute\Tool;
use MonkeysLegion\Apex\Tool\Attribute\ToolParam;

/**
 * MosaicTools — AI-powered Mosaic page builder operations.
 *
 * Generates Mosaic-compatible section/block structures that map
 * directly to the MosaicEntity sections format.
 */
final class MosaicTools
{
    #[Tool(name: 'generate_page_layout', description: 'Generate a full Mosaic page layout with sections and blocks from a description')]
    public function generatePageLayout(
        #[ToolParam(description: 'Page description / purpose', required: true)]
        string $description,
        #[ToolParam(description: 'Content type (article, page, landing, etc.)')]
        string $contentType = 'page',
        #[ToolParam(description: 'Number of sections to generate')]
        int $sectionCount = 4,
        #[ToolParam(description: 'Available block types as comma-separated list')]
        string $availableBlocks = 'text,heading,image,button,divider,spacer,video,html',
    ): array {
        return [
            'description'      => $description,
            'content_type'     => $contentType,
            'section_count'    => min($sectionCount, 12),
            'available_blocks' => $availableBlocks,
            'action'           => 'page_layout',
        ];
    }

    #[Tool(name: 'generate_section', description: 'Generate a single Mosaic section with blocks')]
    public function generateSection(
        #[ToolParam(description: 'What the section should contain', required: true)]
        string $prompt,
        #[ToolParam(description: 'Section layout', enum: ['full', '1/2-1/2', '1/3-1/3-1/3', '2/3-1/3', '1/3-2/3', '1/4-1/4-1/4-1/4'])]
        string $layout = 'full',
        #[ToolParam(description: 'Available block types')]
        string $availableBlocks = 'text,heading,image,button,divider,spacer',
    ): array {
        return [
            'prompt'           => $prompt,
            'layout'           => $layout,
            'available_blocks' => $availableBlocks,
            'action'           => 'section',
        ];
    }

    #[Tool(name: 'generate_block_content', description: 'Generate content for a specific Mosaic block type')]
    public function generateBlockContent(
        #[ToolParam(description: 'What the block should say/show', required: true)]
        string $prompt,
        #[ToolParam(description: 'Block type', required: true, enum: ['text', 'heading', 'button', 'html'])]
        string $blockType,
        #[ToolParam(description: 'Context about the page/section')]
        string $context = '',
    ): array {
        return [
            'prompt'     => $prompt,
            'block_type' => $blockType,
            'context'    => $context,
            'action'     => 'block_content',
        ];
    }

    #[Tool(name: 'suggest_layout', description: 'Suggest an optimal section layout based on purpose')]
    public function suggestLayout(
        #[ToolParam(description: 'Section purpose', required: true)]
        string $purpose,
        #[ToolParam(description: 'Content type')]
        string $contentType = 'page',
    ): array {
        return [
            'purpose'      => $purpose,
            'content_type' => $contentType,
            'action'       => 'suggest_layout',
        ];
    }

    #[Tool(name: 'improve_block', description: 'Rewrite and improve content within an existing block')]
    public function improveBlock(
        #[ToolParam(description: 'Current block content', required: true)]
        string $content,
        #[ToolParam(description: 'Block type')]
        string $blockType = 'text',
        #[ToolParam(description: 'Improvement instruction')]
        string $instruction = 'Improve clarity and engagement',
    ): array {
        return [
            'content'     => $content,
            'block_type'  => $blockType,
            'instruction' => $instruction,
            'action'      => 'improve_block',
        ];
    }

    /**
     * Build system prompt for Mosaic operations.
     */
    public static function buildSystemPrompt(string $action): string
    {
        $mosaicFormat = <<<'SCHEMA'
Mosaic Section format:
{
  "id": "unique-id",
  "layout": "full|1/2-1/2|1/3-1/3-1/3|2/3-1/3|1/3-2/3|1/4-1/4-1/4-1/4",
  "settings": {"background": "#ffffff", "padding": "md"},
  "columns": [
    {
      "blocks": [
        {"type": "heading", "data": {"content": "...", "level": 2}, "settings": {}},
        {"type": "text", "data": {"content": "<p>...</p>"}, "settings": {}},
        {"type": "image", "data": {"src": "", "alt": "..."}, "settings": {}},
        {"type": "button", "data": {"text": "...", "url": "#", "style": "primary"}, "settings": {}},
        {"type": "divider", "data": {}, "settings": {}},
        {"type": "spacer", "data": {"height": 40}, "settings": {}}
      ]
    }
  ]
}
SCHEMA;

        return match ($action) {
            'page_layout'    => "You are a web page designer. Generate a Mosaic page layout.\n\n{$mosaicFormat}\n\nReturn ONLY a JSON object with 'sections' array containing section objects.\nUse realistic, professional content. Generate complete text — never use placeholder text like 'Lorem ipsum'.",
            'section'        => "You are a web page designer. Generate a single Mosaic section.\n\n{$mosaicFormat}\n\nReturn ONLY a single section JSON object.\nUse realistic content that matches the prompt.",
            'block_content'  => "You are a web content writer. Generate content for a Mosaic block.\nReturn ONLY the content value appropriate for the block type.\nFor 'text' blocks: return HTML content.\nFor 'heading' blocks: return the heading text string.\nFor 'button' blocks: return a JSON {\"text\": \"...\", \"url\": \"#\", \"style\": \"primary\"}.\nFor 'html' blocks: return clean HTML.",
            'suggest_layout' => "You are a UX designer. Suggest the best Mosaic section layout for the given purpose.\nReturn ONLY a JSON object: {\"layout\": \"1/2-1/2\", \"reason\": \"...\", \"block_suggestions\": [{\"column\": 0, \"type\": \"heading\"}, ...]}",
            'improve_block'  => 'You are a content editor. Improve the block content based on the instruction. Return ONLY the improved content in the same format as the input.',
            default          => 'You are a web page builder assistant.',
        };
    }
}
