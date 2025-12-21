<?php

declare(strict_types=1);

namespace App\Cms\Fields\Http;

use App\Cms\Fields\FieldDefinition;
use App\Cms\Fields\FieldRepository;
use App\Cms\Fields\Form\FormBuilder;
use App\Cms\Fields\Rendering\RenderContext;
use App\Cms\Fields\Widget\WidgetRegistry;

/**
 * FieldController - HTTP API for field operations
 * 
 * Provides RESTful endpoints for field management,
 * widget rendering, and value handling.
 */
final class FieldController
{
    public function __construct(
        private readonly WidgetRegistry $widgets,
        private readonly FieldRepository $repository,
        private readonly FormBuilder $formBuilder,
    ) {}

    // =========================================================================
    // Field CRUD
    // =========================================================================

    /**
     * GET /api/fields
     * List all fields, optionally filtered by entity type
     */
    public function index(Request $request): Response
    {
        $entityType = $request->get('entity_type');
        $bundleId = $request->get('bundle_id');
        
        $fields = $entityType
            ? $this->repository->findByEntityType($entityType, $bundleId)
            : $this->repository->findAll();

        return Response::json([
            'data' => array_map(fn($f) => $f->toArray(), $fields),
            'meta' => [
                'total' => count($fields),
            ],
        ]);
    }

    /**
     * GET /api/fields/{id}
     * Get a single field by ID
     */
    public function show(int $id): Response
    {
        $field = $this->repository->find($id);
        
        if (!$field) {
            return Response::json(['error' => 'Field not found'], 404);
        }

        return Response::json([
            'data' => $field->toArray(),
            'widget' => $this->widgets->resolve($field)->getMetadata()->toArray(),
        ]);
    }

    /**
     * POST /api/fields
     * Create a new field definition
     */
    public function store(Request $request): Response
    {
        $data = $request->json();
        
        // Validate required fields
        $errors = $this->validateFieldData($data);
        if (!empty($errors)) {
            return Response::json(['errors' => $errors], 422);
        }

        // Check for duplicate machine name
        if ($this->repository->findByMachineName($data['machine_name'])) {
            return Response::json([
                'errors' => ['machine_name' => 'A field with this machine name already exists'],
            ], 422);
        }

        $field = FieldDefinition::fromArray($data);
        $this->repository->save($field);

        return Response::json([
            'data' => $field->toArray(),
            'message' => 'Field created successfully',
        ], 201);
    }

    /**
     * PUT /api/fields/{id}
     * Update a field definition
     */
    public function update(int $id, Request $request): Response
    {
        $field = $this->repository->find($id);
        
        if (!$field) {
            return Response::json(['error' => 'Field not found'], 404);
        }

        $data = $request->json();
        $field->hydrate($data);
        $this->repository->save($field);

        return Response::json([
            'data' => $field->toArray(),
            'message' => 'Field updated successfully',
        ]);
    }

    /**
     * DELETE /api/fields/{id}
     * Delete a field definition
     */
    public function destroy(int $id): Response
    {
        $field = $this->repository->find($id);
        
        if (!$field) {
            return Response::json(['error' => 'Field not found'], 404);
        }

        $this->repository->delete($field);

        return Response::json([
            'message' => 'Field deleted successfully',
        ]);
    }

    // =========================================================================
    // Widget Operations
    // =========================================================================

    /**
     * GET /api/fields/widgets
     * List all available widgets
     */
    public function listWidgets(): Response
    {
        $grouped = $this->widgets->getGroupedByCategory();
        
        $data = [];
        foreach ($grouped as $category => $widgets) {
            $data[$category] = array_map(fn($w) => $w->toArray(), $widgets);
        }

        return Response::json(['data' => $data]);
    }

    /**
     * GET /api/fields/widgets/{id}
     * Get widget details and settings schema
     */
    public function showWidget(string $widgetId): Response
    {
        $widget = $this->widgets->get($widgetId);
        
        if (!$widget) {
            return Response::json(['error' => 'Widget not found'], 404);
        }

        return Response::json([
            'data' => $widget->getMetadata()->toArray(),
            'settings_schema' => $widget->getSettingsSchema(),
        ]);
    }

    /**
     * GET /api/fields/widgets/for-type/{type}
     * Get widgets compatible with a field type
     */
    public function widgetsForType(string $type): Response
    {
        $options = $this->widgets->getOptionsForType($type);

        return Response::json([
            'data' => $options,
            'default' => array_key_first($options),
        ]);
    }

    // =========================================================================
    // Rendering
    // =========================================================================

    /**
     * POST /api/fields/{id}/render
     * Render a field widget
     */
    public function render(int $id, Request $request): Response
    {
        $field = $this->repository->find($id);
        
        if (!$field) {
            return Response::json(['error' => 'Field not found'], 404);
        }

        $value = $request->json('value');
        $contextData = $request->json('context', []);
        
        $context = RenderContext::create($contextData);
        $result = $this->widgets->renderField($field, $value, $context);

        return Response::json([
            'html' => $result->getHtml(),
            'assets' => [
                'css' => $result->getAssets()->getCssFiles(),
                'js' => $result->getAssets()->getJsFiles(),
            ],
        ]);
    }

    /**
     * POST /api/fields/{id}/render-display
     * Render a field for display (read-only)
     */
    public function renderDisplay(int $id, Request $request): Response
    {
        $field = $this->repository->find($id);
        
        if (!$field) {
            return Response::json(['error' => 'Field not found'], 404);
        }

        $value = $request->json('value');
        $context = RenderContext::forDisplay();
        $result = $this->widgets->renderFieldDisplay($field, $value, $context);

        return Response::json([
            'html' => $result->getHtml(),
        ]);
    }

    /**
     * POST /api/fields/render-form
     * Render multiple fields as a form
     */
    public function renderForm(Request $request): Response
    {
        $fieldIds = $request->json('field_ids', []);
        $values = $request->json('values', []);
        $formConfig = $request->json('form', []);
        
        $fields = $this->repository->findByIds($fieldIds);
        
        // Configure form builder
        $builder = $this->formBuilder
            ->id($formConfig['id'] ?? 'form')
            ->action($formConfig['action'] ?? '')
            ->submitLabel($formConfig['submit_label'] ?? 'Save');

        if (isset($formConfig['cancel_url'])) {
            $builder = $builder->cancelUrl($formConfig['cancel_url']);
        }

        $result = $builder->build($fields, $values);

        return Response::json([
            'html' => $result->html,
            'assets' => [
                'css' => $result->assets->getCssFiles(),
                'js' => $result->assets->getJsFiles(),
            ],
        ]);
    }

    // =========================================================================
    // Value Operations
    // =========================================================================

    /**
     * POST /api/fields/{id}/validate
     * Validate a field value
     */
    public function validate(int $id, Request $request): Response
    {
        $field = $this->repository->find($id);
        
        if (!$field) {
            return Response::json(['error' => 'Field not found'], 404);
        }

        $value = $request->json('value');
        $errors = $this->widgets->validateField($field, $value);

        return Response::json([
            'valid' => empty($errors),
            'errors' => $errors,
        ]);
    }

    /**
     * POST /api/fields/validate-multiple
     * Validate multiple field values
     */
    public function validateMultiple(Request $request): Response
    {
        $fieldIds = $request->json('field_ids', []);
        $values = $request->json('values', []);
        
        $fields = $this->repository->findByIds($fieldIds);
        $errors = $this->widgets->validateFields($fields, $values);

        return Response::json([
            'valid' => empty($errors),
            'errors' => $errors,
        ]);
    }

    /**
     * POST /api/fields/{id}/prepare
     * Prepare a field value for storage
     */
    public function prepare(int $id, Request $request): Response
    {
        $field = $this->repository->find($id);
        
        if (!$field) {
            return Response::json(['error' => 'Field not found'], 404);
        }

        $value = $request->json('value');
        $prepared = $this->widgets->prepareValue($field, $value);

        return Response::json([
            'value' => $prepared,
        ]);
    }

    /**
     * POST /api/fields/{id}/format
     * Format a stored value for form display
     */
    public function format(int $id, Request $request): Response
    {
        $field = $this->repository->find($id);
        
        if (!$field) {
            return Response::json(['error' => 'Field not found'], 404);
        }

        $value = $request->json('value');
        $formatted = $this->widgets->formatValue($field, $value);

        return Response::json([
            'value' => $formatted,
        ]);
    }

    // =========================================================================
    // Field Types
    // =========================================================================

    /**
     * GET /api/fields/types
     * List all supported field types
     */
    public function listTypes(): Response
    {
        // Use FieldType enum if available, otherwise return common types
        $types = [
            'string' => ['label' => 'Text', 'category' => 'Text'],
            'text' => ['label' => 'Long Text', 'category' => 'Text'],
            'email' => ['label' => 'Email', 'category' => 'Text'],
            'url' => ['label' => 'URL', 'category' => 'Text'],
            'phone' => ['label' => 'Phone', 'category' => 'Text'],
            'integer' => ['label' => 'Integer', 'category' => 'Number'],
            'float' => ['label' => 'Decimal', 'category' => 'Number'],
            'boolean' => ['label' => 'Boolean', 'category' => 'Selection'],
            'select' => ['label' => 'Select', 'category' => 'Selection'],
            'date' => ['label' => 'Date', 'category' => 'Date'],
            'datetime' => ['label' => 'Date & Time', 'category' => 'Date'],
            'time' => ['label' => 'Time', 'category' => 'Date'],
            'image' => ['label' => 'Image', 'category' => 'Media'],
            'file' => ['label' => 'File', 'category' => 'Media'],
            'gallery' => ['label' => 'Gallery', 'category' => 'Media'],
            'video' => ['label' => 'Video', 'category' => 'Media'],
            'entity_reference' => ['label' => 'Entity Reference', 'category' => 'Reference'],
            'taxonomy_reference' => ['label' => 'Taxonomy', 'category' => 'Reference'],
            'user_reference' => ['label' => 'User Reference', 'category' => 'Reference'],
            'json' => ['label' => 'JSON', 'category' => 'Special'],
            'color' => ['label' => 'Color', 'category' => 'Special'],
            'repeater' => ['label' => 'Repeater', 'category' => 'Composite'],
        ];

        return Response::json(['data' => $types]);
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function validateFieldData(array $data): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        }

        if (empty($data['machine_name'])) {
            $errors['machine_name'] = 'Machine name is required';
        } elseif (!preg_match('/^[a-z_][a-z0-9_]*$/', $data['machine_name'])) {
            $errors['machine_name'] = 'Machine name must start with a letter or underscore and contain only lowercase letters, numbers, and underscores';
        }

        if (empty($data['field_type'])) {
            $errors['field_type'] = 'Field type is required';
        }

        return $errors;
    }
}

/**
 * Simple Request wrapper for demonstration
 */
class Request
{
    private array $query;
    private array $body;

    public function __construct(array $query = [], array $body = [])
    {
        $this->query = $query;
        $this->body = $body;
    }

    public static function fromGlobals(): self
    {
        $body = [];
        $input = file_get_contents('php://input');
        if ($input) {
            $body = json_decode($input, true) ?? [];
        }
        
        return new self($_GET, $body);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function json(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }
}

/**
 * Simple Response wrapper for demonstration
 */
class Response
{
    private function __construct(
        private readonly mixed $body,
        private readonly int $status,
        private readonly array $headers,
    ) {}

    public static function json(mixed $data, int $status = 200): self
    {
        return new self($data, $status, ['Content-Type' => 'application/json']);
    }

    public function send(): void
    {
        http_response_code($this->status);
        
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        
        echo json_encode($this->body, JSON_THROW_ON_ERROR);
    }

    public function getBody(): mixed
    {
        return $this->body;
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}
