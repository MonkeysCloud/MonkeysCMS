<?php

declare(strict_types=1);

namespace App\Cms\Composer;

use App\Cms\Blocks\BlockManager;
use App\Cms\Composer\Layout\LayoutRegistry;

/**
 * ComposerManager - Central service for Content Composer
 * 
 * Handles:
 * - Rendering composer data to HTML
 * - Validating composer data structures
 * - Providing available blocks and layouts
 */
class ComposerManager
{
    public function __construct(
        private ?BlockManager $blockManager = null,
        private ?LayoutRegistry $layoutRegistry = null,
    ) {
    }

    /**
     * Parse composer data from various sources
     */
    public function parse(array|string|null $data): ComposerData
    {
        if ($data === null) {
            return ComposerData::empty();
        }

        if (is_string($data)) {
            return ComposerData::fromJson($data);
        }

        return ComposerData::fromArray($data);
    }

    /**
     * Validate composer data structure
     * 
     * @return array{valid: bool, errors: array}
     */
    public function validate(ComposerData $data): array
    {
        $errors = [];

        // Validate version
        if (empty($data->getVersion())) {
            $errors[] = 'Missing version';
        }

        // Validate sections
        foreach ($data->getSections() as $sectionIndex => $section) {
            if (empty($section->getId())) {
                $errors[] = "Section {$sectionIndex} missing ID";
            }

            foreach ($section->getRows() as $rowIndex => $row) {
                if (empty($row->getId())) {
                    $errors[] = "Section {$sectionIndex}, Row {$rowIndex} missing ID";
                }

                foreach ($row->getColumns() as $colIndex => $column) {
                    if (empty($column->getId())) {
                        $errors[] = "Section {$sectionIndex}, Row {$rowIndex}, Column {$colIndex} missing ID";
                    }

                    foreach ($column->getBlocks() as $blockIndex => $block) {
                        if (empty($block->getId())) {
                            $errors[] = "Block at [{$sectionIndex}][{$rowIndex}][{$colIndex}][{$blockIndex}] missing ID";
                        }
                        if (empty($block->getType())) {
                            $errors[] = "Block {$block->getId()} missing type";
                        }
                    }
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get all available blocks for the composer sidebar
     * 
     * @return array Block definitions grouped by category
     */
    public function getAvailableBlocks(): array
    {
        $blocks = [];

        // Built-in blocks
        $blocks['Layout'] = [
            ['type' => 'text', 'label' => 'Text', 'icon' => '📝', 'description' => 'Rich text content'],
            ['type' => 'heading', 'label' => 'Heading', 'icon' => 'H', 'description' => 'H1-H6 heading'],
            ['type' => 'spacer', 'label' => 'Spacer', 'icon' => '↕️', 'description' => 'Vertical space'],
            ['type' => 'divider', 'label' => 'Divider', 'icon' => '—', 'description' => 'Horizontal line'],
        ];

        $blocks['Media'] = [
            ['type' => 'image', 'label' => 'Image', 'icon' => '🖼️', 'description' => 'Single image'],
            ['type' => 'video', 'label' => 'Video', 'icon' => '🎬', 'description' => 'Video embed'],
            ['type' => 'gallery', 'label' => 'Gallery', 'icon' => '📷', 'description' => 'Image gallery'],
        ];

        $blocks['Content'] = [
            ['type' => 'button', 'label' => 'Button', 'icon' => '🔘', 'description' => 'Call to action button'],
            ['type' => 'icon_box', 'label' => 'Icon Box', 'icon' => '📦', 'description' => 'Icon with text'],
            ['type' => 'testimonial', 'label' => 'Testimonial', 'icon' => '💬', 'description' => 'Quote/review'],
        ];

        $blocks['Interactive'] = [
            ['type' => 'accordion', 'label' => 'Accordion', 'icon' => '📋', 'description' => 'Collapsible content'],
            ['type' => 'tabs', 'label' => 'Tabs', 'icon' => '📑', 'description' => 'Tabbed content'],
        ];

        $blocks['Advanced'] = [
            ['type' => 'html', 'label' => 'HTML', 'icon' => '</>', 'description' => 'Raw HTML code'],
            ['type' => 'code', 'label' => 'Code', 'icon' => '💻', 'description' => 'Code snippet'],
        ];

        // Add blocks from BlockManager (existing block types)
        if ($this->blockManager) {
            $blocks['Custom Blocks'] = $this->getBlockTypesFromManager();
        }

        return $blocks;
    }

    /**
     * Get existing block types from BlockManager
     */
    private function getBlockTypesFromManager(): array
    {
        if (!$this->blockManager) {
            return [];
        }

        $blockTypes = [];
        foreach ($this->blockManager->getTypes() as $type) {
            $blockTypes[] = [
                'type' => '_block_type:' . ($type['type_id'] ?? $type['id'] ?? 'unknown'),
                'label' => $type['label'] ?? 'Unknown',
                'icon' => $type['icon'] ?? '📦',
                'description' => $type['description'] ?? '',
            ];
        }

        return $blockTypes;
    }

    /**
     * Get available row layouts
     */
    public function getAvailableLayouts(): array
    {
        $layouts = [];

        // Built-in layouts
        foreach (Row::LAYOUTS as $id => $widths) {
            $layoutId = (string) $id;
            $layouts[] = [
                'id' => $layoutId,
                'label' => $this->getLayoutLabel($layoutId),
                'columns' => count($widths),
                'widths' => $widths,
            ];
        }

        // Add layouts from registry (module-provided)
        if ($this->layoutRegistry) {
            foreach ($this->layoutRegistry->getLayouts() as $layout) {
                $layouts[] = $layout;
            }
        }

        return $layouts;
    }

    private function getLayoutLabel(string $id): string
    {
        return match ($id) {
            '1' => 'Full Width',
            '1-1' => '50% / 50%',
            '1-2' => '33% / 67%',
            '2-1' => '67% / 33%',
            '1-1-1' => '33% / 33% / 33%',
            '1-1-1-1' => '25% / 25% / 25% / 25%',
            '1-2-1' => '25% / 50% / 25%',
            '1-3' => '25% / 75%',
            '3-1' => '75% / 25%',
            default => $id,
        };
    }

    /**
     * Create initial composer data for a content type
     * 
     * @param array $fields Content type fields to include as placeholders
     */
    public function createDefaultLayout(array $fields = []): ComposerData
    {
        $data = ComposerData::empty();

        // Create a section with single-column row for content
        $section = Section::create(['padding' => ['top' => 0, 'bottom' => 0]]);
        $row = Row::create('1');

        // Add field placeholders for each field
        foreach ($fields as $field) {
            $block = ComposerBlock::fieldPlaceholder(
                $field['machine_name'] ?? $field['name'] ?? 'unknown',
                ['hide_label' => false]
            );
            $row->getColumns()[0]->addBlock($block);
        }

        $section->addRow($row);
        $data->addSection($section);

        return $data;
    }

    /**
     * Set the block manager for accessing existing block types
     */
    public function setBlockManager(BlockManager $blockManager): void
    {
        $this->blockManager = $blockManager;
    }

    /**
     * Set the layout registry for extensible layouts
     */
    public function setLayoutRegistry(LayoutRegistry $registry): void
    {
        $this->layoutRegistry = $registry;
    }
}
