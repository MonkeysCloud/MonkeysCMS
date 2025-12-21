<?php

declare(strict_types=1);

namespace App\Cms\Blocks\Types;

use App\Modules\Core\Entities\Block;

/**
 * TextBlock - Formatted text content block with WYSIWYG editor
 */
class TextBlock extends AbstractBlockType
{
    protected const ID = 'text';
    protected const LABEL = 'Text Block';
    protected const DESCRIPTION = 'Rich text content with formatting';
    protected const ICON = '📄';
    protected const CATEGORY = 'Basic';

    public static function getFields(): array
    {
        return [
            'content' => [
                'type' => 'html',
                'label' => 'Content',
                'required' => true,
                'description' => 'Enter formatted text content',
                'widget' => 'wysiwyg',
                'settings' => [
                    'toolbar' => 'full',
                    'height' => 300,
                ],
            ],
            'format' => [
                'type' => 'select',
                'label' => 'Text Format',
                'required' => false,
                'default' => 'html',
                'settings' => [
                    'options' => [
                        'html' => 'HTML',
                        'markdown' => 'Markdown',
                        'plain' => 'Plain Text',
                    ],
                ],
            ],
            'text_align' => [
                'type' => 'select',
                'label' => 'Text Alignment',
                'required' => false,
                'default' => 'left',
                'settings' => [
                    'options' => [
                        'left' => 'Left',
                        'center' => 'Center',
                        'right' => 'Right',
                        'justify' => 'Justify',
                    ],
                ],
            ],
        ];
    }

    public function render(Block $block, array $context = []): string
    {
        $content = $this->getFieldValue($block, 'content', '');
        $format = $this->getFieldValue($block, 'format', 'html');
        $textAlign = $this->getFieldValue($block, 'text_align', 'left');
        
        // Use block body if available
        if (!empty($block->body)) {
            $content = $block->body;
            $format = $block->body_format ?? 'html';
        }

        // Process content based on format
        $processedContent = match ($format) {
            'markdown' => $this->parseMarkdown($content),
            'plain' => nl2br($this->escape($content)),
            default => $content,
        };

        $style = $textAlign !== 'left' ? " style=\"text-align: {$textAlign};\"" : '';

        return "<div class=\"text-block\"{$style}>{$processedContent}</div>";
    }

    private function parseMarkdown(string $text): string
    {
        // Basic markdown parsing
        $text = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $text);
        $text = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $text);
        $text = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $text);
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $text);
        $text = preg_replace('/^- (.+)$/m', '<li>$1</li>', $text);
        $text = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $text);
        
        return nl2br($text);
    }
}
