<?php

declare(strict_types=1);

namespace App\Cms\Mosaic;

use App\Cms\Block\BlockTypeRegistry;
use App\Cms\Content\ContentRepository;
use App\Cms\Field\FieldRepository;
use MonkeysLegion\Template\Renderer;

/**
 * MosaicRenderer — Server-side HTML renderer for Mosaic layouts.
 *
 * Renders sections → regions → blocks using template-based rendering.
 * Falls back to the block's PHP `render()` method if no `.ml.php`
 * template exists for the block type.
 *
 * Supports "field" blocks that resolve EAV field values from the
 * current node context, plus core node properties (title, body, etc).
 */
final class MosaicRenderer
{
    /** @var string Base path for block templates */
    private string $templatePrefix;

    /** @var array|null Cached node context for field resolution */
    private ?array $nodeContext = null;

    /** @var array Cached field definitions for current content type */
    private array $fieldDefs = [];

    public function __construct(
        private readonly Renderer $renderer,
        private readonly BlockTypeRegistry $blockRegistry,
        private readonly MosaicManager $mosaicManager,
        private readonly ContentRepository $contentRepo,
        private readonly FieldRepository $fieldRepo,
        private string $blockTemplatePath = 'blocks',
    ) {
        $this->templatePrefix = $blockTemplatePath;
    }

    /**
     * Render a complete Mosaic layout for a content node.
     */
    public function renderForNode(int $nodeId, string $contentType): string
    {
        $mosaic = $this->mosaicManager->getForNode($nodeId, $contentType);

        if (!$mosaic || empty($mosaic->sections)) {
            return '';
        }

        // Load node context for field blocks
        $this->loadNodeContext($nodeId, $contentType);

        $html = $this->render($mosaic);

        // Clear context
        $this->nodeContext = null;
        $this->fieldDefs = [];

        return $html;
    }

    /**
     * Render a Mosaic entity to full HTML.
     */
    public function render(MosaicEntity $mosaic): string
    {
        if (empty($mosaic->sections)) {
            return '';
        }

        $html = '<div class="mosaic" data-mosaic-node="' . $mosaic->node_id . '">' . "\n";

        foreach ($mosaic->sections as $sectionData) {
            $section = Section::fromArray($sectionData);
            $html .= $this->renderSection($section);
        }

        $html .= "</div>\n";

        return $html;
    }

    /**
     * Render a single section with its layout grid.
     */
    private function renderSection(Section $section): string
    {
        $layout = htmlspecialchars($section->layout);
        $id = htmlspecialchars($section->id);

        // Section-level settings
        $style = '';
        if (!empty($section->settings['background'])) {
            $style .= 'background-color:' . htmlspecialchars($section->settings['background']) . ';';
        }
        if (!empty($section->settings['padding'])) {
            $style .= 'padding:' . htmlspecialchars($section->settings['padding']) . ';';
        }
        $styleAttr = $style ? ' style="' . $style . '"' : '';

        $html = '<section class="mosaic-section mosaic-section--' . $layout . '" data-section="' . $id . '"' . $styleAttr . '>' . "\n";
        $html .= '  <div class="mosaic-container">' . "\n";
        $html .= '    <div class="mosaic-row mosaic-row--' . $layout . '">' . "\n";

        foreach ($section->regions as $regionName => $blocks) {
            $html .= $this->renderRegion($regionName, $blocks, $section->layout);
        }

        $html .= "    </div>\n";
        $html .= "  </div>\n";
        $html .= "</section>\n";

        return $html;
    }

    /**
     * Render a region (column) with its blocks.
     */
    private function renderRegion(string $name, array $blocks, string $layout): string
    {
        $regionClass = 'mosaic-col mosaic-col--' . htmlspecialchars($name);

        // Add grid-specific classes
        $regionClass .= match ($layout) {
            'sidebar_left'  => $name === 'sidebar' ? ' mosaic-col--sidebar' : ' mosaic-col--main',
            'sidebar_right' => $name === 'sidebar' ? ' mosaic-col--sidebar' : ' mosaic-col--main',
            default         => '',
        };

        $html = '      <div class="' . $regionClass . '">' . "\n";

        foreach ($blocks as $block) {
            $html .= $this->renderBlock($block);
        }

        $html .= "      </div>\n";

        return $html;
    }

    /**
     * Render a single block.
     *
     * Priority:
     * 1. For 'field' blocks: resolve the EAV value and render
     * 2. Try template: `blocks/{blockType}.ml.php`
     * 3. Fall back to PHP `BlockTypeInterface::render()`
     */
    private function renderBlock(array $block): string
    {
        $blockType = $block['blockType'] ?? 'text';
        $data      = $block['data'] ?? [];
        $settings  = $block['settings'] ?? [];
        $blockId   = $block['id'] ?? '';

        // For field blocks, resolve the actual value
        if ($blockType === 'field') {
            $data = $this->resolveFieldData($data);
        }

        // Wrap in semantic container
        $html = '        <div class="mosaic-block mosaic-block--' . htmlspecialchars($blockType) . '"'
              . ' data-block="' . htmlspecialchars($blockId) . '">' . "\n";

        // Try template-based rendering first
        $templateName = $this->templatePrefix . '.' . $blockType;

        try {
            $html .= $this->renderer->render($templateName, [
                'data'     => $data,
                'settings' => $settings,
                'blockId'  => $blockId,
            ]);
        } catch (\Throwable) {
            // Fall back to PHP block render
            $html .= $this->blockRegistry->render($blockType, $data, $settings);
        }

        $html .= "\n        </div>\n";

        return $html;
    }

    // ── Field Resolution ────────────────────────────────────────────

    /**
     * Load node data and field definitions for field block resolution.
     */
    private function loadNodeContext(int $nodeId, string $contentType): void
    {
        // Load the node with its EAV fields
        $node = $this->contentRepo->findWithFields($nodeId);

        if ($node) {
            $this->nodeContext = [
                // Core node properties
                'title'      => $node->title ?? '',
                'body'       => $node->body ?? '',
                'slug'       => $node->slug ?? '',
                'status'     => $node->status ?? '',
                'author_id'  => $node->author_id ?? 0,
                'created_at' => $node->created_at ?? '',
                'updated_at' => $node->updated_at ?? '',
            ];

            // Add EAV field values
            if (isset($node->fields) && is_array($node->fields)) {
                foreach ($node->fields as $fieldName => $fieldValue) {
                    $this->nodeContext[$fieldName] = $fieldValue;
                }
            }
        }

        // Load field definitions for labels
        try {
            $fields = $this->fieldRepo->findByTypeId($contentType);
            foreach ($fields as $field) {
                $this->fieldDefs[$field->machine_name] = [
                    'label'      => $field->name,
                    'field_type' => $field->field_type,
                    'widget'     => $field->widget ?? 'text_input',
                ];
            }
        } catch (\Throwable) {
            // Pre-migration or no fields defined
        }

        // Add core field definitions
        $this->fieldDefs['title'] = ['label' => 'Title', 'field_type' => 'string', 'widget' => 'text_input'];
        $this->fieldDefs['body'] = ['label' => 'Body', 'field_type' => 'text_long', 'widget' => 'wysiwyg'];
        $this->fieldDefs['slug'] = ['label' => 'URL Slug', 'field_type' => 'string', 'widget' => 'text_input'];
    }

    /**
     * Resolve field data by injecting the actual node value.
     */
    private function resolveFieldData(array $data): array
    {
        $fieldName = $data['field_name'] ?? 'title';

        // Inject the resolved value
        $data['_resolved_value'] = $this->nodeContext[$fieldName] ?? null;

        // Inject the field label
        $data['_field_label'] = $this->fieldDefs[$fieldName]['label']
            ?? ucfirst(str_replace('_', ' ', $fieldName));

        return $data;
    }
}
