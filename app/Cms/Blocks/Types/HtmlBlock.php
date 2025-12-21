<?php

declare(strict_types=1);

namespace App\Cms\Blocks\Types;

use App\Modules\Core\Entities\Block;

/**
 * HtmlBlock - Raw HTML content block
 */
class HtmlBlock extends AbstractBlockType
{
    protected const ID = 'html';
    protected const LABEL = 'HTML Block';
    protected const DESCRIPTION = 'Display raw HTML content';
    protected const ICON = '📝';
    protected const CATEGORY = 'Basic';

    public static function getFields(): array
    {
        return [
            'content' => [
                'type' => 'html',
                'label' => 'HTML Content',
                'required' => true,
                'description' => 'Enter raw HTML content',
                'widget' => 'code_editor',
                'settings' => [
                    'mode' => 'html',
                    'height' => 400,
                ],
            ],
        ];
    }

    public function render(Block $block, array $context = []): string
    {
        $content = $this->getFieldValue($block, 'content', '');

        // If block has body, use that instead (for backward compatibility)
        if (!empty($block->body)) {
            $content = $block->body;
        }

        return $content;
    }

    public function validate(array $data): array
    {
        $errors = parent::validate($data);

        // Could add HTML validation/sanitization here

        return $errors;
    }
}
