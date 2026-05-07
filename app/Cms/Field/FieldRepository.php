<?php

declare(strict_types=1);

namespace App\Cms\Field;

use PDO;

/**
 * FieldRepository — CRUD for field definitions attached to content types.
 */
final class FieldRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * Get all field definitions for a content type, ordered by weight.
     *
     * @return list<FieldDefinition>
     */
    public function findByContentType(int $contentTypeId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM field_definitions WHERE content_type_id = :ct_id ORDER BY weight ASC'
        );
        $stmt->execute(['ct_id' => $contentTypeId]);

        return array_map(
            fn(array $row) => (new FieldDefinition())->hydrate($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    /**
     * Get all field definitions for a content type by its string type_id.
     *
     * @return list<FieldDefinition>
     */
    public function findByTypeId(string $typeId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT fd.* FROM field_definitions fd
             JOIN content_types ct ON ct.id = fd.content_type_id
             WHERE ct.type_id = :type_id
             ORDER BY fd.weight ASC'
        );
        $stmt->execute(['type_id' => $typeId]);

        return array_map(
            fn(array $row) => (new FieldDefinition())->hydrate($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    /**
     * Find a single field definition.
     */
    public function find(int $id): ?FieldDefinition
    {
        $stmt = $this->pdo->prepare('SELECT * FROM field_definitions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (new FieldDefinition())->hydrate($row) : null;
    }

    /**
     * Find a field by machine name within a content type.
     */
    public function findByMachineName(int $contentTypeId, string $machineName): ?FieldDefinition
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM field_definitions WHERE content_type_id = :ct_id AND machine_name = :name'
        );
        $stmt->execute(['ct_id' => $contentTypeId, 'name' => $machineName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (new FieldDefinition())->hydrate($row) : null;
    }

    /**
     * Persist (insert or update) a field definition.
     */
    public function persist(int $contentTypeId, FieldDefinition $field): FieldDefinition
    {
        $field->prePersist();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        if ($field->id !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE field_definitions SET
                    name = :name, machine_name = :machine_name, field_type = :field_type,
                    description = :description, help_text = :help_text, widget = :widget,
                    required = :required, multiple = :multiple, cardinality = :cardinality,
                    default_value = :default_value, settings = :settings, validation = :validation,
                    widget_settings = :widget_settings, weight = :weight, searchable = :searchable,
                    translatable = :translatable, updated_at = :updated_at
                WHERE id = :id'
            );
            $stmt->execute([
                'id'              => $field->id,
                'name'            => $field->name,
                'machine_name'    => $field->machine_name,
                'field_type'      => $field->field_type,
                'description'     => $field->description,
                'help_text'       => $field->help_text,
                'widget'          => $field->widget,
                'required'        => (int) $field->required,
                'multiple'        => (int) $field->multiple,
                'cardinality'     => $field->cardinality,
                'default_value'   => $field->default_value,
                'settings'        => json_encode($field->settings),
                'validation'      => json_encode($field->validation),
                'widget_settings' => json_encode($field->widget_settings),
                'weight'          => $field->weight,
                'searchable'      => (int) $field->searchable,
                'translatable'    => (int) $field->translatable,
                'updated_at'      => $now,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO field_definitions
                    (content_type_id, name, machine_name, field_type, description, help_text,
                     widget, required, multiple, cardinality, default_value, settings,
                     validation, widget_settings, weight, searchable, translatable,
                     created_at, updated_at)
                VALUES
                    (:content_type_id, :name, :machine_name, :field_type, :description, :help_text,
                     :widget, :required, :multiple, :cardinality, :default_value, :settings,
                     :validation, :widget_settings, :weight, :searchable, :translatable,
                     :created_at, :updated_at)'
            );
            $stmt->execute([
                'content_type_id' => $contentTypeId,
                'name'            => $field->name,
                'machine_name'    => $field->machine_name,
                'field_type'      => $field->field_type,
                'description'     => $field->description,
                'help_text'       => $field->help_text,
                'widget'          => $field->widget,
                'required'        => (int) $field->required,
                'multiple'        => (int) $field->multiple,
                'cardinality'     => $field->cardinality,
                'default_value'   => $field->default_value,
                'settings'        => json_encode($field->settings),
                'validation'      => json_encode($field->validation),
                'widget_settings' => json_encode($field->widget_settings),
                'weight'          => $field->weight,
                'searchable'      => (int) $field->searchable,
                'translatable'    => (int) $field->translatable,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
            $field->id = (int) $this->pdo->lastInsertId();
        }

        return $field;
    }

    /**
     * Delete a field definition.
     */
    public function delete(int $fieldId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM field_definitions WHERE id = :id');
        $stmt->execute(['id' => $fieldId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Reorder fields within a content type.
     *
     * @param array<int, int> $weights Map of fieldId => weight
     */
    public function reorder(int $contentTypeId, array $weights): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE field_definitions SET weight = :weight WHERE id = :id AND content_type_id = :ct_id'
        );

        foreach ($weights as $fieldId => $weight) {
            $stmt->execute([
                'id'    => $fieldId,
                'weight' => $weight,
                'ct_id'  => $contentTypeId,
            ]);
        }
    }

    /**
     * Sync field definitions from an MLC content type into the database.
     *
     * @param list<FieldDefinition> $fields
     */
    public function syncFromDefinitions(int $contentTypeId, array $fields): void
    {
        foreach ($fields as $field) {
            $existing = $this->findByMachineName($contentTypeId, $field->machine_name);
            if ($existing === null) {
                $this->persist($contentTypeId, $field);
            }
        }
    }
}
