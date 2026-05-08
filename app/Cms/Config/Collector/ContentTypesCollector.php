<?php

declare(strict_types=1);

namespace App\Cms\Config\Collector;

use App\Cms\Config\ConfigCollectorInterface;
use App\Cms\Config\ImportResult;
use PDO;

/**
 * ContentTypesCollector — Exports/imports content type definitions + field definitions.
 *
 * Files: content_type.article.mlc, content_type.page.mlc, etc.
 */
final class ContentTypesCollector implements ConfigCollectorInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    public function getKey(): string { return 'content_type'; }
    public function getLabel(): string { return 'Content Types'; }
    public function getDependencies(): array { return []; }

    public function export(): array
    {
        $types = $this->pdo->query('SELECT * FROM content_types ORDER BY weight ASC')->fetchAll(PDO::FETCH_ASSOC);
        $result = [];

        foreach ($types as $ct) {
            $typeId = $ct['type_id'];
            $data = [
                'label'               => $ct['label'],
                'label_plural'        => $ct['label_plural'],
                'description'         => $ct['description'] ?? '',
                'icon'                => $ct['icon'] ?? 'file-text',
                'is_system'           => (bool) $ct['is_system'],
                'enabled'             => (bool) $ct['enabled'],
                'publishable'         => (bool) $ct['publishable'],
                'revisionable'        => (bool) $ct['revisionable'],
                'translatable'        => (bool) $ct['translatable'],
                'has_author'          => (bool) $ct['has_author'],
                'has_taxonomy'        => (bool) $ct['has_taxonomy'],
                'has_media'           => (bool) $ct['has_media'],
                'mosaic_enabled'      => (bool) $ct['mosaic_enabled'],
                'mosaic_default'      => (bool) $ct['mosaic_default'],
                'comments_enabled'    => (bool) $ct['comments_enabled'],
                'title_field'         => $ct['title_field'] ?? 'title',
                'slug_field'          => $ct['slug_field'] ?? 'slug',
                'url_pattern'         => $ct['url_pattern'] ?? '',
                'weight'              => (int) $ct['weight'],
            ];

            // Export field definitions
            $fields = $this->pdo->prepare(
                'SELECT * FROM field_definitions WHERE content_type_id = :ctid ORDER BY weight ASC'
            );
            $fields->execute(['ctid' => (int) $ct['id']]);

            foreach ($fields->fetchAll(PDO::FETCH_ASSOC) as $field) {
                $fieldExport = [
                    'label'       => $field['name'] ?? '',
                    'type'        => $field['field_type'] ?? 'string',
                    'widget'      => $field['widget'] ?? null,
                    'required'    => (bool) ($field['required'] ?? false),
                    'weight'      => (int) ($field['weight'] ?? 0),
                    'description' => $field['description'] ?? '',
                    'searchable'  => (bool) ($field['searchable'] ?? false),
                ];

                // Add settings if present
                if (!empty($field['settings'])) {
                    $settings = is_string($field['settings'])
                        ? json_decode($field['settings'], true) ?? []
                        : $field['settings'];
                    if (!empty($settings)) {
                        $fieldExport['settings'] = $settings;
                    }
                }

                $data['field'][$field['machine_name']] = $fieldExport;
            }

            $result[$typeId] = $data;
        }

        return $result;
    }

    public function import(array $data, bool $overwrite = false): ImportResult
    {
        $result = new ImportResult();

        foreach ($data as $typeId => $values) {
            $stmt = $this->pdo->prepare('SELECT id FROM content_types WHERE type_id = :tid');
            $stmt->execute(['tid' => $typeId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing && !$overwrite) {
                $result->addSkipped("content_type.{$typeId}");
                continue;
            }

            $fields = $values['field'] ?? [];
            unset($values['field']);

            $now = date('Y-m-d H:i:s');

            if ($existing) {
                // Update
                $this->pdo->prepare(
                    'UPDATE content_types SET
                        label = :label, label_plural = :label_plural, description = :description,
                        icon = :icon, is_system = :is_system, enabled = :enabled,
                        publishable = :publishable, revisionable = :revisionable, translatable = :translatable,
                        has_author = :has_author, has_taxonomy = :has_taxonomy, has_media = :has_media,
                        mosaic_enabled = :mosaic_enabled, mosaic_default = :mosaic_default,
                        comments_enabled = :comments_enabled,
                        title_field = :title_field, slug_field = :slug_field, url_pattern = :url_pattern,
                        weight = :weight, updated_at = :now
                     WHERE type_id = :tid'
                )->execute([
                    'tid'              => $typeId,
                    'label'            => $values['label'] ?? ucfirst($typeId),
                    'label_plural'     => $values['label_plural'] ?? '',
                    'description'      => $values['description'] ?? '',
                    'icon'             => $values['icon'] ?? 'file-text',
                    'is_system'        => (int) ($values['is_system'] ?? false),
                    'enabled'          => (int) ($values['enabled'] ?? true),
                    'publishable'      => (int) ($values['publishable'] ?? true),
                    'revisionable'     => (int) ($values['revisionable'] ?? false),
                    'translatable'     => (int) ($values['translatable'] ?? false),
                    'has_author'       => (int) ($values['has_author'] ?? true),
                    'has_taxonomy'     => (int) ($values['has_taxonomy'] ?? true),
                    'has_media'        => (int) ($values['has_media'] ?? true),
                    'mosaic_enabled'   => (int) ($values['mosaic_enabled'] ?? false),
                    'mosaic_default'   => (int) ($values['mosaic_default'] ?? false),
                    'comments_enabled' => (int) ($values['comments_enabled'] ?? false),
                    'title_field'      => $values['title_field'] ?? 'title',
                    'slug_field'       => $values['slug_field'] ?? 'slug',
                    'url_pattern'      => $values['url_pattern'] ?? '',
                    'weight'           => (int) ($values['weight'] ?? 0),
                    'now'              => $now,
                ]);
                $ctId = (int) $existing['id'];
                $result->addUpdated("content_type.{$typeId}");
            } else {
                // Insert
                $this->pdo->prepare(
                    'INSERT INTO content_types
                        (type_id, label, label_plural, description, icon, is_system, enabled,
                         publishable, revisionable, translatable, has_author, has_taxonomy, has_media,
                         mosaic_enabled, mosaic_default, comments_enabled,
                         title_field, slug_field, url_pattern, weight, created_at, updated_at)
                     VALUES
                        (:tid, :label, :label_plural, :description, :icon, :is_system, :enabled,
                         :publishable, :revisionable, :translatable, :has_author, :has_taxonomy, :has_media,
                         :mosaic_enabled, :mosaic_default, :comments_enabled,
                         :title_field, :slug_field, :url_pattern, :weight, :now, :now2)'
                )->execute([
                    'tid'              => $typeId,
                    'label'            => $values['label'] ?? ucfirst($typeId),
                    'label_plural'     => $values['label_plural'] ?? '',
                    'description'      => $values['description'] ?? '',
                    'icon'             => $values['icon'] ?? 'file-text',
                    'is_system'        => (int) ($values['is_system'] ?? false),
                    'enabled'          => (int) ($values['enabled'] ?? true),
                    'publishable'      => (int) ($values['publishable'] ?? true),
                    'revisionable'     => (int) ($values['revisionable'] ?? false),
                    'translatable'     => (int) ($values['translatable'] ?? false),
                    'has_author'       => (int) ($values['has_author'] ?? true),
                    'has_taxonomy'     => (int) ($values['has_taxonomy'] ?? true),
                    'has_media'        => (int) ($values['has_media'] ?? true),
                    'mosaic_enabled'   => (int) ($values['mosaic_enabled'] ?? false),
                    'mosaic_default'   => (int) ($values['mosaic_default'] ?? false),
                    'comments_enabled' => (int) ($values['comments_enabled'] ?? false),
                    'title_field'      => $values['title_field'] ?? 'title',
                    'slug_field'       => $values['slug_field'] ?? 'slug',
                    'url_pattern'      => $values['url_pattern'] ?? '',
                    'weight'           => (int) ($values['weight'] ?? 0),
                    'now'              => $now,
                    'now2'             => $now,
                ]);
                $ctId = (int) $this->pdo->lastInsertId();
                $result->addCreated("content_type.{$typeId}");
            }

            // Import field definitions
            foreach ($fields as $machineName => $fieldData) {
                $fStmt = $this->pdo->prepare(
                    'SELECT id FROM field_definitions WHERE content_type_id = :ctid AND machine_name = :mn'
                );
                $fStmt->execute(['ctid' => $ctId, 'mn' => $machineName]);
                $existingField = $fStmt->fetch(PDO::FETCH_ASSOC);

                $settings = isset($fieldData['settings']) ? json_encode($fieldData['settings']) : '{}';
                unset($fieldData['settings']);

                if ($existingField) {
                    if ($overwrite) {
                        $this->pdo->prepare(
                            'UPDATE field_definitions SET name = :name, field_type = :field_type, widget = :widget,
                             required = :required, weight = :weight, description = :desc,
                             searchable = :searchable, settings = :settings WHERE id = :id'
                        )->execute([
                            'id'         => (int) $existingField['id'],
                            'name'       => $fieldData['label'] ?? '',
                            'field_type' => $fieldData['type'] ?? 'string',
                            'widget'     => $fieldData['widget'] ?? null,
                            'required'   => (int) ($fieldData['required'] ?? false),
                            'weight'     => (int) ($fieldData['weight'] ?? 0),
                            'desc'       => $fieldData['description'] ?? '',
                            'searchable' => (int) ($fieldData['searchable'] ?? false),
                            'settings'   => $settings,
                        ]);
                    }
                } else {
                    $this->pdo->prepare(
                        'INSERT INTO field_definitions (content_type_id, machine_name, name, field_type, widget, required, weight, description, searchable, settings)
                         VALUES (:ctid, :mn, :name, :field_type, :widget, :required, :weight, :desc, :searchable, :settings)'
                    )->execute([
                        'ctid'       => $ctId,
                        'mn'         => $machineName,
                        'name'       => $fieldData['label'] ?? '',
                        'field_type' => $fieldData['type'] ?? 'string',
                        'widget'     => $fieldData['widget'] ?? null,
                        'required'   => (int) ($fieldData['required'] ?? false),
                        'weight'     => (int) ($fieldData['weight'] ?? 0),
                        'desc'       => $fieldData['description'] ?? '',
                        'searchable' => (int) ($fieldData['searchable'] ?? false),
                        'settings'   => $settings,
                    ]);
                }
            }
        }

        return $result;
    }
}
