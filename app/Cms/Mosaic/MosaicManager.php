<?php

declare(strict_types=1);

namespace App\Cms\Mosaic;

use PDO;

/**
 * MosaicManager — CRUD operations for Mosaic page layouts.
 *
 * Handles loading, saving, and versioning of the visual page builder
 * data attached to content nodes.
 */
final class MosaicManager
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * Get the Mosaic layout for a node
     */
    public function getForNode(int $nodeId, string $contentType): ?MosaicEntity
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM node_mosaic WHERE node_id = :node_id AND content_type = :content_type ORDER BY revision DESC LIMIT 1'
        );
        $stmt->execute(['node_id' => $nodeId, 'content_type' => $contentType]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return (new MosaicEntity())->hydrate($row);
    }

    /**
     * Save (create or update) a Mosaic layout for a node
     */
    public function save(int $nodeId, string $contentType, array $sections): MosaicEntity
    {
        $existing = $this->getForNode($nodeId, $contentType);
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        if ($existing) {
            // Update existing
            $stmt = $this->pdo->prepare(
                'UPDATE node_mosaic SET sections = :sections, revision = revision + 1, updated_at = :updated_at WHERE id = :id'
            );
            $stmt->execute([
                'id' => $existing->id,
                'sections' => json_encode($sections),
                'updated_at' => $now,
            ]);

            $existing->sections = $sections;
            $existing->revision++;
            $existing->updated_at = new \DateTimeImmutable($now);

            return $existing;
        }

        // Create new
        $stmt = $this->pdo->prepare(
            'INSERT INTO node_mosaic (node_id, content_type, sections, revision, created_at, updated_at) VALUES (:node_id, :content_type, :sections, 1, :created_at, :updated_at)'
        );
        $stmt->execute([
            'node_id' => $nodeId,
            'content_type' => $contentType,
            'sections' => json_encode($sections),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $entity = new MosaicEntity();
        $entity->id = (int) $this->pdo->lastInsertId();
        $entity->node_id = $nodeId;
        $entity->content_type = $contentType;
        $entity->sections = $sections;
        $entity->revision = 1;
        $entity->created_at = new \DateTimeImmutable($now);
        $entity->updated_at = new \DateTimeImmutable($now);

        return $entity;
    }

    /**
     * Delete the Mosaic layout for a node
     */
    public function deleteForNode(int $nodeId, string $contentType): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM node_mosaic WHERE node_id = :node_id AND content_type = :content_type'
        );
        $stmt->execute(['node_id' => $nodeId, 'content_type' => $contentType]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Render a Mosaic layout to HTML (server-side rendering)
     *
     * @param MosaicEntity $mosaic   The layout to render
     * @param callable     $blockRenderer  Function that renders a single block: fn(string $blockType, array $data): string
     */
    public function render(MosaicEntity $mosaic, callable $blockRenderer): string
    {
        $html = '<div class="mosaic">';

        foreach ($mosaic->sections as $sectionData) {
            $section = Section::fromArray($sectionData);
            $html .= $this->renderSection($section, $blockRenderer);
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render a single section
     */
    private function renderSection(Section $section, callable $blockRenderer): string
    {
        $html = '<div class="mosaic-section mosaic-section--' . htmlspecialchars($section->layout) . '">';

        $html .= '<div class="mosaic-regions layout--' . htmlspecialchars($section->layout) . '">';

        foreach ($section->regions as $regionName => $blocks) {
            $html .= '<div class="mosaic-region mosaic-region--' . htmlspecialchars($regionName) . '">';

            foreach ($blocks as $block) {
                $blockType = $block['blockType'] ?? 'text';
                $blockData = $block['data'] ?? [];
                $blockSettings = $block['settings'] ?? [];

                $html .= '<div class="mosaic-block mosaic-block--' . htmlspecialchars($blockType) . '">';
                $html .= $blockRenderer($blockType, $blockData, $blockSettings);
                $html .= '</div>';
            }

            $html .= '</div>';
        }

        $html .= '</div></div>';

        return $html;
    }
}
