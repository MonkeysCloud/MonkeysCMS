<?php

declare(strict_types=1);

namespace App\Cms\Mosaic;

use PDO;

/**
 * MosaicManager — CRUD operations for Mosaic page layouts.
 *
 * Handles loading, saving, versioning, and server-side rendering
 * of the visual page builder data attached to content nodes.
 * Snapshots every save to `node_revisions` for history.
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
     * Get mosaic by node_id only (no content_type filter).
     */
    public function getForNodeById(int $nodeId): ?MosaicEntity
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM node_mosaic WHERE node_id = :node_id ORDER BY revision DESC LIMIT 1'
        );
        $stmt->execute(['node_id' => $nodeId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return (new MosaicEntity())->hydrate($row);
    }

    /**
     * Save (create or update) a Mosaic layout for a node.
     * Snapshots the previous state to `node_revisions` before updating.
     */
    public function save(int $nodeId, string $contentType, array $sections): MosaicEntity
    {
        $existing = $this->getForNode($nodeId, $contentType);
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        if ($existing) {
            // Snapshot current state before overwriting
            $this->snapshotRevision($existing);

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
     * Revert a mosaic layout to a specific revision.
     */
    public function revertToRevision(int $nodeId, int $revision): ?MosaicEntity
    {
        $stmt = $this->pdo->prepare(
            'SELECT data FROM node_revisions WHERE node_id = :node_id AND revision = :revision AND type = :type LIMIT 1'
        );
        $stmt->execute(['node_id' => $nodeId, 'revision' => $revision, 'type' => 'mosaic']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $data = json_decode($row['data'], true);
        $sections = $data['sections'] ?? [];
        $contentType = $data['content_type'] ?? 'page';

        return $this->save($nodeId, $contentType, $sections);
    }

    /**
     * Get revision history for a node.
     *
     * @return list<array{revision: int, created_at: string}>
     */
    public function getRevisions(int $nodeId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT revision, created_at FROM node_revisions WHERE node_id = :node_id AND type = :type ORDER BY revision DESC LIMIT :limit'
        );
        $stmt->bindValue('node_id', $nodeId, PDO::PARAM_INT);
        $stmt->bindValue('type', 'mosaic');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    // ── Private ─────────────────────────────────────────────────────────

    /**
     * Snapshot current mosaic state into node_revisions.
     */
    private function snapshotRevision(MosaicEntity $mosaic): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO node_revisions (node_id, type, revision, data, created_at) VALUES (:node_id, :type, :revision, :data, :created_at)'
            );
            $stmt->execute([
                'node_id'    => $mosaic->node_id,
                'type'       => 'mosaic',
                'revision'   => $mosaic->revision,
                'data'       => json_encode([
                    'content_type' => $mosaic->content_type,
                    'sections'     => $mosaic->sections,
                ]),
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Silently ignore if node_revisions table doesn't exist yet
        }
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
