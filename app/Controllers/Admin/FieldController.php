<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\FieldType;
use App\Cms\Fields\Widget\WidgetRegistry;
use MonkeysLegion\Router\Attributes\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * FieldController - Admin API for field types and widgets
 *
 * Field Type Endpoints:
 * - GET /admin/fields/types - List all field types
 * - GET /admin/fields/types/{type} - Get field type info
 *
 * Widget Endpoints:
 * - GET /admin/fields/widgets - List all widgets
 * - GET /admin/fields/widgets/grouped - Widgets grouped by category
 * - GET /admin/fields/widgets/{type} - Widgets for a field type
 * - GET /admin/fields/widgets/{id}/schema - Widget settings schema
 *
 * Validation Endpoints:
 * - POST /admin/fields/validate - Validate field value
 *
 * Render Endpoints:
 * - POST /admin/fields/render - Render field widget HTML
 * - POST /admin/fields/preview - Preview field display
 */
class FieldController
{
    public function __construct(
        private readonly WidgetRegistry $widgetManager,
    ) {
    }

    // =========================================================================
    // Field Types
    // =========================================================================

    /**
     * List all field types
     */
    #[Route('GET', '/admin/fields/types')]
    public function listTypes(ServerRequestInterface $request): ResponseInterface
    {
        $types = [];

        foreach (FieldType::cases() as $case) {
            $types[] = [
                'value' => $case->value,
                'label' => $case->getLabel(),
                'category' => $case->getCategory(),
                'description' => $case->getDescription(),
                'php_type' => $case->getPhpType(),
                'sql_type' => $case->getSqlType(),
                'default_widget' => $case->getDefaultWidget(),
            ];
        }

        return json([
            'success' => true,
            'data' => $types,
            'total' => count($types),
        ]);
    }

    /**
     * Get field type info
     */
    #[Route('GET', '/admin/fields/types/{type}')]
    public function getType(ServerRequestInterface $request, string $type): ResponseInterface
    {
        $fieldType = FieldType::tryFrom($type);

        if (!$fieldType) {
            return json([
                'success' => false,
                'error' => 'Field type not found',
            ], 404);
        }

        return json([
            'success' => true,
            'data' => [
                'value' => $fieldType->value,
                'label' => $fieldType->getLabel(),
                'category' => $fieldType->getCategory(),
                'description' => $fieldType->getDescription(),
                'php_type' => $fieldType->getPhpType(),
                'sql_type' => $fieldType->getSqlType(),
                'default_widget' => $fieldType->getDefaultWidget(),
                'available_widgets' => $this->widgetManager->getOptionsForType($type),
            ],
        ]);
    }

    /**
     * Get field types grouped by category
     */
    #[Route('GET', '/admin/fields/types/grouped')]
    public function getTypesGrouped(ServerRequestInterface $request): ResponseInterface
    {
        $grouped = [];

        foreach (FieldType::cases() as $case) {
            $category = $case->getCategory();
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = [
                'value' => $case->value,
                'label' => $case->getLabel(),
                'description' => $case->getDescription(),
            ];
        }

        ksort($grouped);

        return json([
            'success' => true,
            'data' => $grouped,
        ]);
    }

    // =========================================================================
    // Widgets
    // =========================================================================

    /**
     * List all widgets
     */
    #[Route('GET', '/admin/fields/widgets')]
    public function listWidgets(ServerRequestInterface $request): ResponseInterface
    {
        $widgets = [];

        foreach ($this->widgetManager->all() as $id => $widget) {
            $widgets[] = [
                'id' => $id,
                'label' => $widget->getLabel(),
                'category' => $widget->getCategory(),
                'icon' => $widget->getIcon(),
                'supported_types' => $widget->getSupportedTypes(),
                'supports_multiple' => $widget->supportsMultiple(),
                'priority' => $widget->getPriority(),
            ];
        }

        // Sort by category, then label
        usort($widgets, function ($a, $b) {
            $catCmp = strcmp($a['category'], $b['category']);
            return $catCmp !== 0 ? $catCmp : strcmp($a['label'], $b['label']);
        });

        return json([
            'success' => true,
            'data' => $widgets,
            'total' => count($widgets),
        ]);
    }

    /**
     * Get widgets grouped by category
     */
    #[Route('GET', '/admin/fields/widgets/grouped')]
    public function getWidgetsGrouped(ServerRequestInterface $request): ResponseInterface
    {
        return json([
            'success' => true,
            'data' => $this->widgetManager->getGroupedByCategory(),
        ]);
    }

    /**
     * Get widgets for a field type
     */
    #[Route('GET', '/admin/fields/widgets/for-type/{type}')]
    public function getWidgetsForType(ServerRequestInterface $request, string $type): ResponseInterface
    {
        $widgets = $this->widgetManager->getOptionsForType($type);

        return json([
            'success' => true,
            'data' => $widgets,
            'field_type' => $type,
        ]);
    }

    /**
     * Get widget settings schema
     */
    #[Route('GET', '/admin/fields/widgets/{id}/schema')]
    public function getWidgetSchema(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $widget = $this->widgetManager->get($id);

        if (!$widget) {
            return json([
                'success' => false,
                'error' => 'Widget not found',
            ], 404);
        }

        $schema = [];
        if ($widget instanceof \App\Cms\Fields\Widget\ConfigurableWidgetInterface) {
            $schema = $widget->getSettingsSchema();
        } elseif (method_exists($widget, 'getSettingsSchema')) {
            /** @phpstan-ignore-next-line */
            $schema = $widget->getSettingsSchema();
        }

        $assets = ['css' => [], 'js' => []];
        if (method_exists($widget, 'getAssets')) {
            /** @phpstan-ignore-next-line */
            $widgetAssets = $widget->getAssets();
            $assets['css'] = $widgetAssets->getCssFiles();
            $assets['js'] = $widgetAssets->getJsFiles();
        }

        return json([
            'success' => true,
            'data' => [
                'id' => $id,
                'label' => $widget->getLabel(),
                'settings_schema' => $schema,
                'css_assets' => $assets['css'],
                'js_assets' => $assets['js'],
            ],
        ]);
    }

    // =========================================================================
    // Validation
    // =========================================================================

    /**
     * Validate a field value
     */
    #[Route('POST', '/admin/fields/validate')]
    public function validateField(ServerRequestInterface $request): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true) ?? [];

        $fieldDef = $data['field'] ?? null;
        $value = $data['value'] ?? null;

        if (!$fieldDef) {
            return json([
                'success' => false,
                'error' => 'Field definition required',
            ], 400);
        }

        // Create field definition
        $field = new FieldDefinition();
        $field->hydrate($fieldDef);

        // Get widget and validate
        $widget = $this->widgetManager->resolve($field);

        // Validate
        $errors = $this->widgetManager->validateField($field, $value);

        return json([
            'success' => empty($errors),
            'valid' => empty($errors),
            'errors' => $errors,
        ]);
    }

    /**
     * Validate multiple field values
     */
    #[Route('POST', '/admin/fields/validate-many')]
    public function validateFields(ServerRequestInterface $request): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true) ?? [];

        $fieldDefs = $data['fields'] ?? [];
        $values = $data['values'] ?? [];

        if (empty($fieldDefs)) {
            return json([
                'success' => false,
                'error' => 'Field definitions required',
            ], 400);
        }

        $fields = [];
        foreach ($fieldDefs as $def) {
            $field = new FieldDefinition();
            $field->hydrate($def);
            $fields[] = $field;
        }

        $errors = $this->widgetManager->validateFields($fields, $values);

        return json([
            'success' => empty($errors),
            'valid' => empty($errors),
            'errors' => $errors,
        ]);
    }

    // =========================================================================
    // Rendering
    // =========================================================================

    /**
     * Render a field widget
     */
    #[Route('POST', '/admin/fields/render')]
    public function renderField(ServerRequestInterface $request): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true) ?? [];

        $fieldDef = $data['field'] ?? null;
        $value = $data['value'] ?? null;
        $context = $data['context'] ?? [];

        if (!$fieldDef) {
            return json([
                'success' => false,
                'error' => 'Field definition required',
            ], 400);
        }

        // Create field definition
        $field = new FieldDefinition();
        $field->hydrate($fieldDef);

        // Render
        $this->widgetManager->clearAssets();
        $html = $this->widgetManager->renderField($field, $value, $context);

        return json([
            'success' => true,
            'data' => [
                'html' => $html->getHtml(),
                'css' => $this->widgetManager->getCollectedAssets()->getCssFiles(),
                'js' => $this->widgetManager->getCollectedAssets()->getJsFiles(),
                'init_script' => $this->widgetManager->getCollectedAssets()->getInitScripts(),
            ],
        ]);
    }

    /**
     * Render field for display (view mode)
     */
    #[Route('POST', '/admin/fields/render-display')]
    public function renderDisplay(ServerRequestInterface $request): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true) ?? [];

        $fieldDef = $data['field'] ?? null;
        $value = $data['value'] ?? null;
        $context = $data['context'] ?? [];

        if (!$fieldDef) {
            return json([
                'success' => false,
                'error' => 'Field definition required',
            ], 400);
        }

        // Create field definition
        $field = new FieldDefinition();
        $field->hydrate($fieldDef);

        // Render display
        $result = $this->widgetManager->renderFieldDisplay($field, $value, $context);

        return json([
            'success' => true,
            'data' => [
                'html' => $result->getHtml(),
            ],
        ]);
    }

    /**
     * Prepare values for storage
     */
    #[Route('POST', '/admin/fields/prepare')]
    public function prepareValues(ServerRequestInterface $request): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true) ?? [];

        $fieldDefs = $data['fields'] ?? [];
        $values = $data['values'] ?? [];

        if (empty($fieldDefs)) {
            return json([
                'success' => false,
                'error' => 'Field definitions required',
            ], 400);
        }

        $fields = [];
        foreach ($fieldDefs as $def) {
            $field = new FieldDefinition();
            $field->hydrate($def);
            $fields[] = $field;
        }

        $prepared = $this->widgetManager->prepareValues($fields, $values);

        return json([
            'success' => true,
            'data' => $prepared,
        ]);
    }

    // =========================================================================
    // Field Definition Builder
    // =========================================================================

    /**
     * Get field definition template
     */
    #[Route('GET', '/admin/fields/template')]
    public function getTemplate(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $type = $params['type'] ?? 'string';

        $fieldType = FieldType::tryFrom($type) ?? FieldType::STRING;

        return json([
            'success' => true,
            'data' => [
                'name' => '',
                'machine_name' => '',
                'field_type' => $fieldType->value,
                'description' => '',
                'help_text' => '',
                'required' => false,
                'multiple' => false,
                'cardinality' => 1,
                'default_value' => null,
                'widget' => $fieldType->getDefaultWidget(),
                'settings' => [],
                'validation' => [],
                'widget_settings' => [],
            ],
        ]);
    }

    /**
     * Generate machine name from label
     */
    #[Route('POST', '/admin/fields/machine-name')]
    public function generateMachineName(ServerRequestInterface $request): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true) ?? [];
        $label = $data['label'] ?? '';
        $prefix = $data['prefix'] ?? 'field_';

        $machineName = $prefix . strtolower(preg_replace('/[^a-z0-9]+/i', '_', $label));
        $machineName = trim($machineName, '_');

        return json([
            'success' => true,
            'data' => [
                'machine_name' => $machineName,
            ],
        ]);
    }
}
