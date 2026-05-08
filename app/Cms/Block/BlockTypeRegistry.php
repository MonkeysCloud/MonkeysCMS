<?php

declare(strict_types=1);

namespace App\Cms\Block;

use PDO;

/**
 * BlockTypeRegistry — Central registry for all available block types.
 *
 * Manages both code-defined (PHP class implementing BlockTypeInterface)
 * and database-defined block types (from `block_types` table).
 *
 * Code-defined blocks take precedence over DB-defined blocks with the
 * same type ID.
 */
final class BlockTypeRegistry
{
    /** @var array<string, BlockTypeInterface> */
    private array $types = [];

    /** @var array<string, true> Code-defined type IDs (PHP class registered) */
    private array $codeIds = [];

    /** @var bool Whether DB blocks have been loaded */
    private bool $dbLoaded = false;

    /** @var BlockTemplateRenderer|null Theme-based template renderer */
    private ?BlockTemplateRenderer $templateRenderer = null;

    /**
     * Register a code-defined block type
     */
    public function register(BlockTypeInterface $type): void
    {
        $id = $type::getId();
        $this->types[$id] = $type;
        $this->codeIds[$id] = true;
    }

    /**
     * Register multiple block types
     */
    public function registerMany(array $types): void
    {
        foreach ($types as $type) {
            $this->register($type);
        }
    }

    /**
     * Load block types from the database.
     *
     * Only loads enabled DB blocks that don't conflict with code-defined blocks.
     * Called lazily on first access if PDO is available.
     *
     * @param PDO $pdo Database connection
     * @param \MonkeysLegion\Template\Renderer|null $renderer Template engine for full .ml.php support
     */
    public function loadFromDatabase(PDO $pdo, ?\MonkeysLegion\Template\Renderer $renderer = null): void
    {
        if ($this->dbLoaded) {
            return;
        }

        try {
            $stmt = $pdo->query(
                'SELECT * FROM block_types WHERE enabled = 1 ORDER BY weight ASC, label ASC'
            );

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $typeId = $row['type_id'];

                // Code-defined blocks always take precedence
                if (isset($this->types[$typeId])) {
                    continue;
                }

                $dynamic = DynamicBlockType::fromRow($row);

                // Inject template engine for full .ml.php directive support
                if ($renderer) {
                    $dynamic->setRenderer($renderer);
                }

                $this->types[$typeId] = $dynamic;
            }
        } catch (\Throwable) {
            // Table might not exist yet (pre-migration) — silently skip
        }

        $this->dbLoaded = true;
    }

    /**
     * Load block types from the active theme's blocks.php file.
     * Theme-defined blocks are registered as DynamicBlockTypes without a hardcoded template,
     * relying on the BlockTemplateRenderer to find the .ml.php file.
     */
    public function loadFromTheme(\App\Cms\Theme\ThemeManager $themeManager, \MonkeysLegion\Mlc\Contracts\ParserInterface $mlcParser): void
    {
        $theme = $themeManager->getActiveTheme();
        if (!$theme) {
            return;
        }

        $chain = $themeManager->getInheritanceChain($theme);
        
        // Process parent themes first, so child themes can override
        foreach (array_reverse($chain) as $t) {
            $blocksDir = $t->basePath . '/blocks';
            if (!is_dir($blocksDir)) {
                continue;
            }

            foreach (glob($blocksDir . '/*.mlc') as $file) {
                try {
                    $def = $mlcParser->parseFile($file);
                    $typeId = basename($file, '.mlc');

                    // Code-defined blocks take precedence over theme blocks
                    if (isset($this->codeIds[$typeId])) {
                        continue;
                    }

                    $dynamic = new DynamicBlockType(
                        id: $typeId,
                        label: $def['label'] ?? ucfirst($typeId),
                        description: $def['description'] ?? '',
                        icon: $def['icon'] ?? 'puzzle',
                        category: $def['category'] ?? 'Theme',
                        fields: $def['fields'] ?? [],
                        template: null // Let BlockTemplateRenderer handle it
                    );

                    $this->types[$typeId] = $dynamic;
                } catch (\Throwable $e) {
                    // Log error or ignore broken block config
                }
            }
        }
    }

    /**
     * Get a block type by ID
     */
    public function get(string $id): ?BlockTypeInterface
    {
        return $this->types[$id] ?? null;
    }

    /**
     * Check if a block type exists
     */
    public function has(string $id): bool
    {
        return isset($this->types[$id]);
    }

    /**
     * Check if a block type is code-defined (PHP class).
     */
    public function isCodeDefined(string $id): bool
    {
        return isset($this->codeIds[$id]);
    }

    /**
     * Get the source of a block type: 'code' or 'database'.
     */
    public function getSource(string $id): string
    {
        return $this->isCodeDefined($id) ? 'code' : 'database';
    }

    /**
     * Invalidate DB cache so next access reloads from database.
     */
    public function invalidate(): void
    {
        // Remove all DB-defined types, keeping code-defined
        foreach ($this->types as $id => $type) {
            if (!isset($this->codeIds[$id])) {
                unset($this->types[$id]);
            }
        }
        $this->dbLoaded = false;
    }

    /**
     * Get all registered block types as metadata arrays.
     *
     * @return array<string, array{id: string, label: string, description: string, icon: string, category: string, fields: array}>
     */
    public function all(): array
    {
        $result = [];

        foreach ($this->types as $id => $type) {
            if ($type instanceof DynamicBlockType) {
                $result[$id] = [
                    'id'          => $type->typeId,
                    'label'       => $type->typeLabel,
                    'description' => $type->typeDescription,
                    'icon'        => $type->typeIcon,
                    'category'    => $type->typeCategory,
                    'fields'      => $type->typeFields,
                    'dynamic'     => true,
                ];
            } else {
                $result[$id] = [
                    'id'          => $id,
                    'label'       => $type::getLabel(),
                    'description' => $type::getDescription(),
                    'icon'        => $type::getIcon(),
                    'category'    => $type::getCategory(),
                    'fields'      => $type::getFields(),
                    'dynamic'     => false,
                ];
            }
        }

        // Sort by category then label
        uasort($result, function ($a, $b) {
            $cat = strcmp($a['category'], $b['category']);
            return $cat !== 0 ? $cat : strcmp($a['label'], $b['label']);
        });

        return $result;
    }

    /**
     * Get block types grouped by category
     */
    public function grouped(): array
    {
        $grouped = [];

        foreach ($this->all() as $type) {
            $grouped[$type['category']][] = $type;
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * Set the theme template renderer.
     *
     * When set, render() will try theme templates (atoms/molecules)
     * before falling back to the PHP block class render().
     */
    public function setTemplateRenderer(BlockTemplateRenderer $renderer): void
    {
        $this->templateRenderer = $renderer;
    }

    /**
     * Render a block.
     *
     * Priority: theme template (atoms/molecules) → PHP render() fallback.
     * This ensures both Mosaic editor preview and frontend use the same
     * themed block components.
     */
    public function render(string $blockType, array $data, array $settings = []): string
    {
        // 1. Try theme template first (atoms/molecules/organisms)
        if ($this->templateRenderer) {
            $html = $this->templateRenderer->render($blockType, $data, $settings);
            if ($html !== null) {
                return $html;
            }
        }

        // 2. Fall back to PHP block class render()
        $type = $this->get($blockType);

        if (!$type) {
            return '<!-- Unknown block type: ' . htmlspecialchars($blockType) . ' -->';
        }

        return $type->render($data, $settings);
    }

    /**
     * Serialize all block types to JSON for frontend consumption.
     */
    public function toJson(): string
    {
        return json_encode([
            'blockTypes' => array_values($this->all()),
            'grouped'    => $this->grouped(),
        ]);
    }
}
