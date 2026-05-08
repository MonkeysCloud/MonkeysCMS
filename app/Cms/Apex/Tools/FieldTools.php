<?php

declare(strict_types=1);

namespace App\Cms\Apex\Tools;

use MonkeysLegion\Apex\Tool\Attribute\Tool;
use MonkeysLegion\Apex\Tool\Attribute\ToolParam;

/**
 * FieldTools — AI-powered field assistance for CMS form fields.
 */
final class FieldTools
{
    #[Tool(name: 'fill_field', description: 'Generate content for a specific field type')]
    public function fillField(
        #[ToolParam(description: 'Field label/name', required: true)]
        string $fieldLabel,
        #[ToolParam(description: 'Field type', required: true, enum: ['text', 'textarea', 'number', 'email', 'url', 'date', 'select', 'wysiwyg'])]
        string $fieldType,
        #[ToolParam(description: 'Context about the content (title, type, etc.)')]
        string $context = '',
        #[ToolParam(description: 'Field constraints (max length, min/max values, etc.)')]
        string $constraints = '',
    ): array {
        return [
            'field_label' => $fieldLabel,
            'field_type'  => $fieldType,
            'context'     => $context,
            'constraints' => $constraints,
            'action'      => 'fill_field',
        ];
    }

    #[Tool(name: 'extract_fields', description: 'Extract structured field values from unstructured text')]
    public function extractFields(
        #[ToolParam(description: 'Unstructured text to extract from', required: true)]
        string $text,
        #[ToolParam(description: 'Field definitions as JSON: [{name, type, label}]', required: true)]
        string $fieldDefinitions,
    ): array {
        return [
            'text'              => $text,
            'field_definitions' => $fieldDefinitions,
            'action'            => 'extract_fields',
        ];
    }

    #[Tool(name: 'generate_field_options', description: 'Generate select/checkbox options for a field')]
    public function generateFieldOptions(
        #[ToolParam(description: 'Field label/context', required: true)]
        string $fieldLabel,
        #[ToolParam(description: 'Number of options to generate')]
        int $count = 8,
        #[ToolParam(description: 'Context for the options (content type, purpose)')]
        string $context = '',
    ): array {
        return [
            'field_label' => $fieldLabel,
            'count'       => $count,
            'context'     => $context,
            'action'      => 'field_options',
        ];
    }

    #[Tool(name: 'validate_field_content', description: 'AI-powered content validation beyond regex')]
    public function validateFieldContent(
        #[ToolParam(description: 'Field value to validate', required: true)]
        string $value,
        #[ToolParam(description: 'Field label for context', required: true)]
        string $fieldLabel,
        #[ToolParam(description: 'Validation rules description')]
        string $rules = '',
    ): array {
        return [
            'value'       => $value,
            'field_label' => $fieldLabel,
            'rules'       => $rules,
            'action'      => 'validate_field',
        ];
    }

    /**
     * Build system prompt for field operations.
     */
    public static function buildSystemPrompt(string $action): string
    {
        return match ($action) {
            'fill_field'      => "You are a content assistant. Generate appropriate content for the specified form field.\nConsider the field type, label, and any constraints.\nReturn ONLY the field value — no explanations.",
            'extract_fields'  => "You are a data extraction expert. Extract structured values from the text to fill the specified fields.\nReturn ONLY a JSON object with field names as keys and extracted values as values.\nUse null for fields you can't confidently extract.",
            'field_options'   => "You are a UX expert. Generate sensible options for a form select/checkbox field.\nReturn ONLY a JSON array of objects: [{\"value\": \"option-key\", \"label\": \"Display Label\"}]",
            'validate_field'  => "You are a content validator. Check if the field value is appropriate for the given context.\nReturn ONLY a JSON object: {\"valid\": true/false, \"message\": \"explanation if invalid\", \"suggestion\": \"corrected value or null\"}",
            default           => 'You are a form field assistant.',
        };
    }
}
