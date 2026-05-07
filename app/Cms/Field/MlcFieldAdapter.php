<?php

declare(strict_types=1);

namespace App\Cms\Field;

/**
 * MlcFieldAdapter — Converts MLC block/theme field definitions into FieldDefinition objects.
 *
 * This bridges the MLC config format (used in theme blocks/*.mlc files)
 * to the global FieldDefinition entity, enabling the same WidgetRegistry
 * to render fields for content types, taxonomy, AND Mosaic blocks.
 *
 * Usage:
 *   $definition = MlcFieldAdapter::fromArray('title', [
 *       'type' => 'string',
 *       'label' => 'Title',
 *       'required' => true,
 *       'translatable' => true,
 *   ]);
 */
final class MlcFieldAdapter
{
    /**
     * Convert an MLC field definition array into a FieldDefinition.
     *
     * @param string $machineName  Field machine name (key in the MLC fields block)
     * @param array  $mlcDef       Field definition from MLC parser
     */
    public static function fromArray(string $machineName, array $mlcDef): FieldDefinition
    {
        $def = FieldDefinition::create(
            name: $mlcDef['label'] ?? ucfirst(str_replace('_', ' ', $machineName)),
            machineName: $machineName,
            fieldType: self::normalizeType($mlcDef['type'] ?? 'string'),
        );

        $def->required(!empty($mlcDef['required']));
        $def->translatable(!empty($mlcDef['translatable']));

        if (!empty($mlcDef['description'])) {
            $def->withDescription($mlcDef['description']);
        }
        if (!empty($mlcDef['help_text'])) {
            $def->withHelpText($mlcDef['help_text']);
        }
        if (array_key_exists('default', $mlcDef)) {
            $def->withDefault($mlcDef['default']);
        }
        if (!empty($mlcDef['widget'])) {
            $def->withWidget($mlcDef['widget']);
        }
        if (isset($mlcDef['cardinality'])) {
            $def->withCardinality((int) $mlcDef['cardinality']);
        }
        if (isset($mlcDef['searchable'])) {
            $def->searchable((bool) $mlcDef['searchable']);
        }

        // Pack all extra config into settings (options, entity_type, sub_fields, etc.)
        $settings = [];
        $settingsKeys = [
            'options', 'entity_type', 'vocabulary', 'allowed_types',
            'sub_fields', 'max_items', 'min_items', 'placeholder',
            'rows', 'min', 'max', 'step', 'target_type',
        ];
        foreach ($settingsKeys as $key) {
            if (isset($mlcDef[$key])) {
                $settings[$key] = $mlcDef[$key];
            }
        }
        if ($settings) {
            $def->withSettings($settings);
        }

        // Widget settings (visual config)
        if (!empty($mlcDef['widget_settings'])) {
            $def->withWidgetSettings($mlcDef['widget_settings']);
        }

        return $def;
    }

    /**
     * Convert a full MLC fields block into an array of FieldDefinitions.
     *
     * @param array<string, array> $mlcFields
     * @return array<string, FieldDefinition>
     */
    public static function fromFieldsArray(array $mlcFields): array
    {
        $definitions = [];
        $weight = 0;
        foreach ($mlcFields as $machineName => $mlcDef) {
            $def = self::fromArray($machineName, $mlcDef);
            $def->withWeight($weight++);
            $definitions[$machineName] = $def;
        }
        return $definitions;
    }

    /**
     * Normalize MLC type names to FieldType enum values.
     */
    private static function normalizeType(string $type): string
    {
        return match ($type) {
            'media', 'image' => 'image',
            'number' => 'integer',
            'textarea' => 'text',
            'wysiwyg' => 'html',
            default => $type,
        };
    }
}
