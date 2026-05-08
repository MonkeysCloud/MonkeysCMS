<?php

declare(strict_types=1);

namespace App\Cms\Apex\Tools;

use MonkeysLegion\Apex\Tool\Attribute\Tool;
use MonkeysLegion\Apex\Tool\Attribute\ToolParam;

/**
 * ImageTools — AI-powered image analysis for CMS media.
 *
 * Requires a vision-capable model (GPT-4 Vision, Claude 3+, Gemini).
 */
final class ImageTools
{
    #[Tool(name: 'generate_alt_text', description: 'Generate descriptive alt text for an image')]
    public function generateAltText(
        #[ToolParam(description: 'Image URL or description', required: true)]
        string $imageContext,
        #[ToolParam(description: 'Page context (title, content type)')]
        string $pageContext = '',
        #[ToolParam(description: 'Maximum alt text length')]
        int $maxLength = 125,
    ): array {
        return [
            'image_context' => $imageContext,
            'page_context'  => $pageContext,
            'max_length'    => $maxLength,
            'action'        => 'alt_text',
        ];
    }

    #[Tool(name: 'generate_image_caption', description: 'Generate a caption for an image')]
    public function generateImageCaption(
        #[ToolParam(description: 'Image URL or description', required: true)]
        string $imageContext,
        #[ToolParam(description: 'Caption style', enum: ['descriptive', 'creative', 'informative', 'minimal'])]
        string $style = 'descriptive',
    ): array {
        return [
            'image_context' => $imageContext,
            'style'         => $style,
            'action'        => 'caption',
        ];
    }

    #[Tool(name: 'describe_image', description: 'Analyze and describe image content in detail')]
    public function describeImage(
        #[ToolParam(description: 'Image URL', required: true)]
        string $imageUrl,
    ): array {
        return [
            'image_url' => $imageUrl,
            'action'    => 'describe',
        ];
    }

    /**
     * Build system prompt for image operations.
     */
    public static function buildSystemPrompt(string $action): string
    {
        return match ($action) {
            'alt_text' => 'You are an accessibility expert. Generate concise, descriptive alt text for the image. Be specific about what is shown. Max 125 characters. Return ONLY the alt text.',
            'caption'  => 'You are a content writer. Generate an engaging image caption. Return ONLY the caption text.',
            'describe' => 'You are an image analyst. Provide a detailed description of what the image shows including subjects, setting, colors, mood, and any text visible.',
            default    => 'You are an image analysis assistant.',
        };
    }
}
