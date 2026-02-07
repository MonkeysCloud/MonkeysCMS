<?php

declare(strict_types=1);

namespace App\Cms\Composer;

use App\Cms\Composer\Integration\BlockAdapter;
use App\Cms\Composer\Integration\FieldPlaceholder;

/**
 * ComposerRenderer - Renders composer data to HTML with Tailwind CSS
 * 
 * This class takes a ComposerData structure and generates
 * the final HTML output for frontend display using Tailwind classes.
 */
class ComposerRenderer
{
    public function __construct(
        private ?BlockAdapter $blockAdapter = null,
        private ?FieldPlaceholder $fieldPlaceholder = null,
    ) {
        $this->fieldPlaceholder ??= new FieldPlaceholder();
    }

    /**
     * Render composer data to HTML
     */
    public function render(ComposerData $data, array $context = []): string
    {
        if ($data->isEmpty()) {
            return '';
        }

        $html = '<div class="w-full">';

        foreach ($data->getSections() as $section) {
            $html .= $this->renderSection($section, $context);
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render a single section
     */
    public function renderSection(Section $section, array $context = []): string
    {
        $settings = $section->getSettings();
        $classes = ['relative'];
        $styles = [];

        // Full width
        if ($settings['fullWidth'] ?? false) {
            $classes[] = 'w-screen -ml-[50vw] left-1/2 relative';
        }

        // Background image handling
        $bg = $settings['background'] ?? [];
        if (($bg['type'] ?? 'none') !== 'none') {
            if ($bg['type'] === 'color' && !empty($bg['value'])) {
                $styles[] = 'background-color: ' . $bg['value'];
            } elseif ($bg['type'] === 'image' && !empty($bg['value'])) {
                $styles[] = 'background-image: url(' . $bg['value'] . ')';
                $classes[] = 'bg-cover bg-center bg-no-repeat';
            }
        }

        // Padding
        $padding = $settings['padding'] ?? [];
        if (!empty($padding['top'])) {
            $styles[] = 'padding-top: ' . $padding['top'] . 'px';
        }
        if (!empty($padding['bottom'])) {
            $styles[] = 'padding-bottom: ' . $padding['bottom'] . 'px';
        }

        // Custom CSS class
        if (!empty($settings['cssClass'])) {
            $classes[] = $settings['cssClass'];
        }

        $classStr = implode(' ', $classes);
        $styleStr = !empty($styles) ? ' style="' . implode('; ', $styles) . '"' : '';
        $idAttr = !empty($settings['cssId']) ? ' id="' . htmlspecialchars($settings['cssId']) . '"' : '';

        $innerClasses = ($settings['fullWidth'] ?? false) 
            ? 'px-10' 
            : 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8';

        $html = '<section class="' . $classStr . '"' . $styleStr . $idAttr . '>';
        $html .= '<div class="' . $innerClasses . '">';

        foreach ($section->getRows() as $row) {
            $html .= $this->renderRow($row, $context);
        }

        $html .= '</div>';
        $html .= '</section>';

        return $html;
    }

    /**
     * Render a row with Tailwind flex
     */
    public function renderRow(Row $row, array $context = []): string
    {
        $settings = $row->getSettings();
        $classes = ['flex', 'flex-wrap'];

        // Gap using Tailwind
        $gap = $settings['gap'] ?? 20;
        $gapClass = $this->getGapClass($gap);
        $classes[] = $gapClass;

        // Vertical alignment
        $vAlign = $settings['verticalAlign'] ?? 'top';
        $classes[] = match ($vAlign) {
            'center' => 'items-center',
            'bottom' => 'items-end',
            'stretch' => 'items-stretch',
            default => 'items-start',
        };

        // Reverse on mobile
        if ($settings['reverseOnMobile'] ?? false) {
            $classes[] = 'flex-col-reverse md:flex-row';
        } else {
            $classes[] = 'flex-col md:flex-row';
        }

        // Custom CSS class
        if (!empty($settings['cssClass'])) {
            $classes[] = $settings['cssClass'];
        }

        $classStr = implode(' ', $classes);

        $html = '<div class="' . $classStr . '">';

        foreach ($row->getColumns() as $column) {
            $html .= $this->renderColumn($column, $context);
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render a column
     */
    public function renderColumn(Column $column, array $context = []): string
    {
        $settings = $column->getSettings();
        $width = $column->getWidth();
        $classes = ['flex', 'flex-col', 'w-full'];

        // Width classes for desktop
        $widthClass = $this->getWidthClass($width);
        $classes[] = $widthClass;

        // Vertical alignment
        $vAlign = $settings['verticalAlign'] ?? 'top';
        $classes[] = match ($vAlign) {
            'center' => 'justify-center',
            'bottom' => 'justify-end',
            default => 'justify-start',
        };

        // Custom CSS class
        if (!empty($settings['cssClass'])) {
            $classes[] = $settings['cssClass'];
        }

        // Padding using style for custom values
        $styles = [];
        $padding = $settings['padding'] ?? [];
        if (!empty(array_filter($padding))) {
            $p = ($padding['top'] ?? 0) . 'px ' . 
                 ($padding['right'] ?? 0) . 'px ' . 
                 ($padding['bottom'] ?? 0) . 'px ' . 
                 ($padding['left'] ?? 0) . 'px';
            $styles[] = 'padding: ' . $p;
        }

        $classStr = implode(' ', $classes);
        $styleStr = !empty($styles) ? ' style="' . implode('; ', $styles) . '"' : '';

        $html = '<div class="' . $classStr . '"' . $styleStr . '>';

        foreach ($column->getBlocks() as $block) {
            $html .= $this->renderBlock($block, $context);
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render a block
     */
    public function renderBlock(ComposerBlock $block, array $context = []): string
    {
        $type = $block->getType();
        $settings = $block->getSettings();

        $classes = ['relative'];

        // Hide on devices
        if ($settings['hideOnMobile'] ?? false) {
            $classes[] = 'hidden md:block';
        }
        if ($settings['hideOnDesktop'] ?? false) {
            $classes[] = 'block md:hidden';
        }

        // Custom CSS class
        if (!empty($settings['cssClass'])) {
            $classes[] = $settings['cssClass'];
        }

        // Margin using style for custom values
        $styles = [];
        $margin = $settings['margin'] ?? [];
        if (!empty(array_filter($margin))) {
            $m = ($margin['top'] ?? 0) . 'px ' . 
                 ($margin['right'] ?? 0) . 'px ' . 
                 ($margin['bottom'] ?? 0) . 'px ' . 
                 ($margin['left'] ?? 0) . 'px';
            $styles[] = 'margin: ' . $m;
        }

        // Animation (data attribute for JS)
        $dataAttrs = ' data-block-id="' . $block->getId() . '"';
        if (!empty($settings['animation']) && $settings['animation'] !== 'none') {
            $dataAttrs .= ' data-animation="' . $settings['animation'] . '"';
        }

        $classStr = implode(' ', $classes);
        $styleStr = !empty($styles) ? ' style="' . implode('; ', $styles) . '"' : '';

        $html = '<div class="' . $classStr . '"' . $styleStr . $dataAttrs . '>';
        $html .= $this->renderBlockContent($block, $context);
        $html .= '</div>';

        return $html;
    }

    /**
     * Render block content based on type
     */
    private function renderBlockContent(ComposerBlock $block, array $context): string
    {
        $type = $block->getType();
        $data = $block->getData();

        // Field placeholder
        if ($block->isFieldPlaceholder()) {
            return $this->renderFieldPlaceholder($block, $context);
        }

        // Saved block reference
        if ($block->isSavedBlock()) {
            return $this->renderSavedBlock($block, $context);
        }

        // Block type reference (from BlockManager)
        if (BlockAdapter::isBlockTypeReference($type)) {
            return $this->renderBlockType($block, $context);
        }

        // Built-in blocks
        return match ($type) {
            'text' => $this->renderText($data),
            'heading' => $this->renderHeading($data),
            'image' => $this->renderImage($data),
            'button' => $this->renderButton($data),
            'spacer' => $this->renderSpacer($data),
            'divider' => $this->renderDivider($data),
            'video' => $this->renderVideo($data),
            'html' => $this->renderHtml($data),
            default => $this->renderUnknown($type, $data),
        };
    }

    private function renderFieldPlaceholder(ComposerBlock $block, array $context): string
    {
        $fieldName = $block->get('field_name');
        $fields = $context['fields'] ?? [];
        $fieldConfigs = $context['field_configs'] ?? [];

        $fieldValue = $fields[$fieldName] ?? null;
        $fieldConfig = $fieldConfigs[$fieldName] ?? [];

        return $this->fieldPlaceholder->render($fieldName, $fieldValue, $fieldConfig, [
            'view_mode' => $block->get('view_mode', 'default'),
            'hide_label' => $block->get('hide_label', false),
        ]);
    }

    private function renderSavedBlock(ComposerBlock $block, array $context): string
    {
        $blockId = $block->get('block_id');
        return "<!-- Saved Block: {$blockId} -->";
    }

    private function renderBlockType(ComposerBlock $block, array $context): string
    {
        if (!$this->blockAdapter) {
            return '<!-- Block adapter not available -->';
        }

        $typeId = BlockAdapter::extractBlockTypeId($block->getType());
        return $this->blockAdapter->render($typeId, $block->getData());
    }

    // Built-in block renderers with Tailwind

    private function renderText(array $data): string
    {
        $content = $data['content'] ?? '';
        return '<div class="prose prose-lg max-w-none">' . $content . '</div>';
    }

    private function renderHeading(array $data): string
    {
        $text = htmlspecialchars($data['text'] ?? '');
        $level = $data['level'] ?? 'h2';
        $align = $data['align'] ?? 'left';
        
        $validLevels = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
        if (!in_array($level, $validLevels)) {
            $level = 'h2';
        }

        $sizeClass = match ($level) {
            'h1' => 'text-4xl md:text-5xl font-bold',
            'h2' => 'text-3xl md:text-4xl font-bold',
            'h3' => 'text-2xl md:text-3xl font-semibold',
            'h4' => 'text-xl md:text-2xl font-semibold',
            'h5' => 'text-lg md:text-xl font-medium',
            'h6' => 'text-base md:text-lg font-medium',
            default => 'text-3xl font-bold',
        };

        $alignClass = match ($align) {
            'center' => 'text-center',
            'right' => 'text-right',
            default => 'text-left',
        };

        return "<{$level} class=\"{$sizeClass} {$alignClass} leading-tight\">{$text}</{$level}>";
    }

    private function renderImage(array $data): string
    {
        $src = htmlspecialchars($data['src'] ?? '');
        $alt = htmlspecialchars($data['alt'] ?? '');
        $caption = $data['caption'] ?? '';

        if (empty($src)) {
            return '<div class="bg-gray-100 p-10 text-center text-gray-400 border-2 border-dashed border-gray-300 rounded-lg">No image selected</div>';
        }

        $html = '<figure class="m-0">';
        $html .= '<img src="' . $src . '" alt="' . $alt . '" class="w-full h-auto rounded-lg" loading="lazy">';
        
        if (!empty($caption)) {
            $html .= '<figcaption class="mt-2 text-sm text-gray-600 text-center">' . htmlspecialchars($caption) . '</figcaption>';
        }
        
        $html .= '</figure>';

        return $html;
    }

    private function renderButton(array $data): string
    {
        $text = htmlspecialchars($data['text'] ?? 'Click Here');
        $url = htmlspecialchars($data['url'] ?? '#');
        $style = $data['style'] ?? 'primary';
        $target = ($data['newTab'] ?? false) ? ' target="_blank" rel="noopener"' : '';

        $baseClasses = 'inline-block px-6 py-3 rounded-lg font-medium transition-all duration-200';
        
        $styleClasses = match ($style) {
            'secondary' => 'bg-gray-600 text-white hover:bg-gray-700',
            'outline' => 'border-2 border-blue-500 text-blue-500 hover:bg-blue-500 hover:text-white',
            'ghost' => 'text-blue-500 hover:bg-blue-50',
            default => 'bg-blue-500 text-white hover:bg-blue-600',
        };

        return '<a href="' . $url . '" class="' . $baseClasses . ' ' . $styleClasses . '"' . $target . '>' . $text . '</a>';
    }

    private function renderSpacer(array $data): string
    {
        $height = (int) ($data['height'] ?? 40);
        return '<div class="w-full" style="height: ' . $height . 'px;"></div>';
    }

    private function renderDivider(array $data): string
    {
        $style = $data['style'] ?? 'solid';
        $width = $data['width'] ?? '100%';
        
        $borderStyle = match ($style) {
            'dashed' => 'border-dashed',
            'dotted' => 'border-dotted',
            default => 'border-solid',
        };

        return '<hr class="border-t border-gray-300 ' . $borderStyle . ' my-0" style="width: ' . $width . ';">';
    }

    private function renderVideo(array $data): string
    {
        $url = $data['url'] ?? '';
        $embedUrl = $this->getVideoEmbedUrl($url);

        if (empty($embedUrl)) {
            return '<div class="bg-gray-100 p-10 text-center text-gray-400 border-2 border-dashed border-gray-300 rounded-lg">Invalid video URL</div>';
        }

        return '<div class="relative pb-[56.25%] h-0 overflow-hidden rounded-lg">' .
            '<iframe src="' . htmlspecialchars($embedUrl) . '" class="absolute top-0 left-0 w-full h-full" frameborder="0" allowfullscreen loading="lazy"></iframe>' .
            '</div>';
    }

    private function getVideoEmbedUrl(string $url): string
    {
        // YouTube
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return 'https://www.youtube.com/embed/' . $matches[1];
        }

        // Vimeo
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
            return 'https://player.vimeo.com/video/' . $matches[1];
        }

        return '';
    }

    private function renderHtml(array $data): string
    {
        return '<div class="overflow-hidden">' . ($data['content'] ?? '') . '</div>';
    }

    private function renderUnknown(string $type, array $data): string
    {
        return '<!-- Unknown block type: ' . htmlspecialchars($type) . ' -->';
    }

    // Helper methods

    private function getGapClass(int $gap): string
    {
        return match (true) {
            $gap <= 4 => 'gap-1',
            $gap <= 8 => 'gap-2',
            $gap <= 12 => 'gap-3',
            $gap <= 16 => 'gap-4',
            $gap <= 20 => 'gap-5',
            $gap <= 24 => 'gap-6',
            $gap <= 32 => 'gap-8',
            $gap <= 40 => 'gap-10',
            $gap <= 48 => 'gap-12',
            default => 'gap-16',
        };
    }

    private function getWidthClass(float $width): string
    {
        return match (true) {
            $width <= 25 => 'md:w-1/4',
            $width <= 33.4 => 'md:w-1/3',
            $width <= 50 => 'md:w-1/2',
            $width <= 66.7 => 'md:w-2/3',
            $width <= 75 => 'md:w-3/4',
            default => 'md:w-full',
        };
    }

    public function setBlockAdapter(BlockAdapter $adapter): void
    {
        $this->blockAdapter = $adapter;
    }

    public function setFieldPlaceholder(FieldPlaceholder $placeholder): void
    {
        $this->fieldPlaceholder = $placeholder;
    }
}
