<?php

declare(strict_types=1);

namespace App\Cms\I18n;

use PDO;

/**
 * TranslationService — Entity-agnostic translation mapping API.
 *
 * Links source entities to their translations across any entity type
 * (node, term, menu_item, block, webform, or custom modules).
 *
 * Usage by custom modules:
 *   $translationService->link('product', 1, 'es', 42);
 *   $esId = $translationService->getTranslation('product', 1, 'es');
 *   $all  = $translationService->getAllTranslations('product', 1);
 */
final class TranslationService
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    // ═══════════════════════════════════════════════════════════════════════
    // Link / Unlink
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Link a translation: source entity → target entity for a given language.
     *
     * @param string $type     Entity type identifier (e.g. 'node', 'term', 'webform')
     * @param int    $sourceId Source entity ID (original)
     * @param string $lang     Target language code
     * @param int    $targetId Target entity ID (translated version)
     */
    public function link(string $type, int $sourceId, string $lang, int $targetId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO entity_translations (source_type, source_id, target_type, target_id, language)
             VALUES (:stype, :sid, :ttype, :tid, :lang)
             ON DUPLICATE KEY UPDATE target_id = VALUES(target_id), target_type = VALUES(target_type)'
        );
        $stmt->execute([
            'stype' => $type,
            'sid'   => $sourceId,
            'ttype' => $type,
            'tid'   => $targetId,
            'lang'  => $lang,
        ]);
    }

    /**
     * Remove a specific translation link.
     */
    public function unlink(string $type, int $sourceId, string $lang): void
    {
        $this->pdo->prepare(
            'DELETE FROM entity_translations WHERE source_type = :type AND source_id = :sid AND language = :lang'
        )->execute(['type' => $type, 'sid' => $sourceId, 'lang' => $lang]);
    }

    /**
     * Remove all translation links for an entity.
     */
    public function unlinkAll(string $type, int $sourceId): void
    {
        $this->pdo->prepare(
            'DELETE FROM entity_translations WHERE
             (source_type = :t1 AND source_id = :s1) OR (target_type = :t2 AND target_id = :s2)'
        )->execute(['t1' => $type, 's1' => $sourceId, 't2' => $type, 's2' => $sourceId]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Resolve
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Get the translated entity ID for a specific language.
     *
     * @return int|null  Target entity ID, or null if no translation exists
     */
    public function getTranslation(string $type, int $sourceId, string $lang): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT target_id FROM entity_translations
             WHERE source_type = :type AND source_id = :sid AND language = :lang'
        );
        $stmt->execute(['type' => $type, 'sid' => $sourceId, 'lang' => $lang]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (int) $val : null;
    }

    /**
     * Get all translations for a source entity.
     *
     * @return array<string, int>  [language_code => target_entity_id, ...]
     */
    public function getAllTranslations(string $type, int $sourceId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT language, target_id FROM entity_translations
             WHERE source_type = :type AND source_id = :sid
             ORDER BY language ASC'
        );
        $stmt->execute(['type' => $type, 'sid' => $sourceId]);

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[$row['language']] = (int) $row['target_id'];
        }
        return $result;
    }

    /**
     * Given a target entity, find the source it's a translation of.
     *
     * @return array{type: string, id: int}|null
     */
    public function getSourceOf(string $type, int $targetId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT source_type, source_id FROM entity_translations
             WHERE target_type = :type AND target_id = :tid LIMIT 1'
        );
        $stmt->execute(['type' => $type, 'tid' => $targetId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? ['type' => $row['source_type'], 'id' => (int) $row['source_id']] : null;
    }

    /**
     * Get translation siblings: all translations of the same source (including source itself).
     *
     * @return array<string, int>  [language_code => entity_id, ...]
     */
    public function getSiblings(string $type, int $entityId, string $entityLang): array
    {
        // First check if this entity IS a source
        $translations = $this->getAllTranslations($type, $entityId);
        if (!empty($translations)) {
            return [$entityLang => $entityId] + $translations;
        }

        // Otherwise, find the source
        $source = $this->getSourceOf($type, $entityId);
        if ($source === null) {
            return [$entityLang => $entityId]; // No translations exist
        }

        $sourceTranslations = $this->getAllTranslations($source['type'], $source['id']);
        // We need the source's language — query it
        return $sourceTranslations + [$entityLang => $entityId];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Batch Operations
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Get translations for multiple source IDs at once.
     *
     * @param list<int> $sourceIds
     * @return array<int, array<string, int>>  [sourceId => [lang => targetId, ...], ...]
     */
    public function getTranslationsForMany(string $type, array $sourceIds, ?string $lang = null): array
    {
        if (empty($sourceIds)) return [];

        $placeholders = implode(',', array_fill(0, count($sourceIds), '?'));
        $params = array_merge([$type], $sourceIds);
        $sql = "SELECT source_id, language, target_id FROM entity_translations
                WHERE source_type = ? AND source_id IN ({$placeholders})";

        if ($lang !== null) {
            $sql .= ' AND language = ?';
            $params[] = $lang;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $sid = (int) $row['source_id'];
            $result[$sid][$row['language']] = (int) $row['target_id'];
        }
        return $result;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Coverage / Stats
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Get translation coverage for an entity type in a specific language.
     *
     * @return array{total: int, translated: int, percent: float}
     */
    public function getCoverage(string $type, string $lang, ?string $countTable = null): array
    {
        // Count total source entities
        $table = $countTable ?? match ($type) {
            'node'      => 'nodes',
            'term'      => 'terms',
            'menu_item' => 'menu_items',
            'webform'   => 'webforms',
            default     => null,
        };

        if ($table === null) {
            return ['total' => 0, 'translated' => 0, 'percent' => 0.0];
        }

        $total = (int) $this->pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(DISTINCT source_id) FROM entity_translations
             WHERE source_type = :type AND language = :lang'
        );
        $stmt->execute(['type' => $type, 'lang' => $lang]);
        $translated = (int) $stmt->fetchColumn();

        $percent = $total > 0 ? round(($translated / $total) * 100, 1) : 0.0;

        return ['total' => $total, 'translated' => $translated, 'percent' => $percent];
    }

    /**
     * Get source IDs that are missing a translation for a given language.
     *
     * @return list<int>
     */
    public function getMissing(string $type, string $lang, int $limit = 50, ?string $table = null): array
    {
        $table = $table ?? match ($type) {
            'node'      => 'nodes',
            'term'      => 'terms',
            'menu_item' => 'menu_items',
            'webform'   => 'webforms',
            default     => null,
        };

        if ($table === null) return [];

        $stmt = $this->pdo->prepare(
            "SELECT t.id FROM {$table} t
             LEFT JOIN entity_translations et ON et.source_type = :type AND et.source_id = t.id AND et.language = :lang
             WHERE et.id IS NULL
             ORDER BY t.id DESC
             LIMIT :limit"
        );
        $stmt->bindValue('type', $type);
        $stmt->bindValue('lang', $lang);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
    }
}
