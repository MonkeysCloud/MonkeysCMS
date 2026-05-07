<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Apex\ApexConfig;
use App\Cms\Apex\ApexService;
use App\Cms\Content\ContentTypeEntity;
use App\Cms\Content\ContentTypeManager;
use App\Cms\Field\FieldDefinition;
use App\Cms\Field\FieldRepository;
use App\Cms\Field\FieldType;
use App\Cms\Field\Widget\WidgetRegistry;
use App\Cms\Form\FormBuilder;
use App\Cms\Form\FormRenderer;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Session\SessionManager;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * ContentTypeController — Admin UI for managing content types and their fields.
 */
#[RoutePrefix('/admin/content-types')]
final class ContentTypeController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly ContentTypeManager $typeManager,
        private readonly FieldRepository $fieldRepo,
        private readonly WidgetRegistry $widgetRegistry,
        private readonly ApexService $apex,
        private readonly FormRenderer $formRenderer,
        private readonly SessionManager $session,
    ) {}

    // ── List ────────────────────────────────────────────────────────────

    #[Route('GET', '/', name: 'admin::content-types.index')]
    public function index(): Response
    {
        $types = $this->typeManager->loadAll();

        return Response::html($this->renderer->render('admin::content-types.index', [
            'title'        => 'Content Types',
            'contentTypes' => $types,
        ]));
    }

    // ── Create ──────────────────────────────────────────────────────────

    #[Route('GET', '/create', name: 'admin::content-types.create')]
    public function create(): Response
    {
        return Response::html($this->renderer->render('admin::content-types.form', [
            'title'       => 'Create Content Type',
            'contentType' => null,
            'isNew'       => true,
        ]));
    }

    #[Route('POST', '/', name: 'admin::content-types.store')]
    public function store(ServerRequestInterface $request): Response
    {
        $body = $this->parseBody($request);

        $entity = new ContentTypeEntity();
        $this->hydrateFromRequest($entity, $body);

        $this->typeManager->persist($entity);

        // Sync MLC fields if this type has them
        if (!empty($entity->fieldDefinitions)) {
            $this->fieldRepo->syncFromDefinitions($entity->id, $entity->fieldDefinitions);
        }

        return Response::redirect('/admin/content-types/' . $entity->type_id . '/fields');
    }

    // ── Edit ────────────────────────────────────────────────────────────

    #[Route('GET', '/{typeId}/edit', name: 'admin::content-types.edit')]
    public function edit(ServerRequestInterface $request, string $typeId): Response
    {
        $ct = $this->typeManager->getOrFail($typeId);

        return Response::html($this->renderer->render('admin::content-types.form', [
            'title'       => 'Edit: ' . $ct->label,
            'contentType' => $ct,
            'isNew'       => false,
        ]));
    }

    #[Route('POST', '/{typeId}', name: 'admin::content-types.update')]
    public function update(ServerRequestInterface $request, string $typeId): Response
    {
        $ct = $this->typeManager->getOrFail($typeId);
        $body = $this->parseBody($request);

        $this->hydrateFromRequest($ct, $body);
        $this->typeManager->persist($ct);

        return Response::redirect('/admin/content-types');
    }

    // ── Delete ──────────────────────────────────────────────────────────

    #[Route('POST', '/{typeId}/delete', name: 'admin::content-types.delete')]
    public function delete(ServerRequestInterface $request, string $typeId): Response
    {
        $this->typeManager->delete($typeId);
        return Response::redirect('/admin/content-types');
    }

    // ── Fields Management ───────────────────────────────────────────────

    #[Route('GET', '/{typeId}/fields', name: 'admin::content-types.fields')]
    public function fields(ServerRequestInterface $request, string $typeId): Response
    {
        $ct = $this->typeManager->getOrFail($typeId);
        $fields = $this->typeManager->getFieldsFor($typeId);

        return Response::html($this->renderer->render('admin::content-types.fields', [
            'title'       => $ct->label . ' — Fields',
            'contentType' => $ct,
            'fields'      => $fields,
            'fieldTypes'  => FieldType::cases(),
        ]));
    }

    #[Route('POST', '/{typeId}/fields', name: 'admin::content-types.fields.store')]
    public function addField(ServerRequestInterface $request, string $typeId): Response
    {
        $ct = $this->typeManager->getOrFail($typeId);
        $body = $this->parseBody($request);

        $field = FieldDefinition::create(
            name: $body['name'] ?? '',
            machineName: $body['machine_name'] ?? '',
            fieldType: $body['field_type'] ?? 'string',
        );

        if (!empty($body['required'])) $field->required();
        if (!empty($body['searchable'])) $field->searchable();
        if (!empty($body['help_text'])) $field->withHelpText($body['help_text']);
        if (!empty($body['widget'])) $field->withWidget($body['widget']);
        if (isset($body['weight'])) $field->withWeight((int) $body['weight']);

        $this->fieldRepo->persist($ct->id, $field);

        return Response::redirect('/admin/content-types/' . $typeId . '/fields');
    }

    #[Route('POST', '/{typeId}/fields/{fieldId}/delete', name: 'admin::content-types.fields.delete')]
    public function deleteField(ServerRequestInterface $request, string $typeId, string $fieldId): Response
    {
        $this->fieldRepo->delete((int) $fieldId);
        return Response::redirect('/admin/content-types/' . $typeId . '/fields');
    }

    #[Route('POST', '/{typeId}/fields/reorder', name: 'admin::content-types.fields.reorder')]
    public function reorderFields(ServerRequestInterface $request, string $typeId): Response
    {
        $ct = $this->typeManager->getOrFail($typeId);
        $body = $this->parseBody($request);
        $weights = $body['weights'] ?? [];

        if (is_array($weights)) {
            $mapped = [];
            foreach ($weights as $fieldId => $weight) {
                $mapped[(int) $fieldId] = (int) $weight;
            }
            $this->fieldRepo->reorder($ct->id, $mapped);
        }

        return Response::json(['success' => true]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function parseBody(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody() ?? [];

        if (empty($body)) {
            $stream = $request->getBody();
            $stream->rewind();
            $raw = $stream->getContents();
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        return $body;
    }

    private function hydrateFromRequest(ContentTypeEntity $entity, array $body): void
    {
        $entity->type_id       = $body['type_id'] ?? $entity->type_id;
        $entity->label         = $body['label'] ?? $entity->label;
        $entity->label_plural  = $body['label_plural'] ?? $entity->label_plural;
        $entity->description   = $body['description'] ?? $entity->description;
        $entity->icon          = $body['icon'] ?? $entity->icon;
        $entity->publishable   = !empty($body['publishable']);
        $entity->revisionable  = !empty($body['revisionable']);
        $entity->translatable  = !empty($body['translatable']);
        $entity->has_author    = !empty($body['has_author']);
        $entity->has_taxonomy  = !empty($body['has_taxonomy']);
        $entity->has_media     = !empty($body['has_media']);
        $entity->mosaic_enabled = !empty($body['mosaic_enabled']);
        $entity->mosaic_default = !empty($body['mosaic_default']);
        $entity->comments_enabled = !empty($body['comments_enabled']);
        $entity->url_pattern   = !empty($body['url_pattern']) ? $body['url_pattern'] : null;
    }

    // ── AI Field Configuration ──────────────────────────────────────────

    #[Route('GET', '/{typeId}/ai', name: 'admin::content-types.ai')]
    public function aiConfig(ServerRequestInterface $request, string $typeId): Response
    {
        $ct = $this->typeManager->getOrFail($typeId);
        $fields = $this->typeManager->getFieldsFor($typeId);
        $apexConfig = $this->apex->config();

        // Current AI field overrides for this content type
        $ctOverrides = $apexConfig->contentTypeOverrides[$typeId] ?? [];
        $ctEnabled = (bool) ($ctOverrides['enabled'] ?? true);

        // Available actions per field type
        $actionsMap = [
            'string'    => ['generate', 'rewrite'],
            'text'      => ['generate', 'rewrite', 'summarize', 'expand', 'translate', 'grammar'],
            'html'      => ['generate', 'rewrite', 'summarize', 'expand', 'translate', 'grammar'],
            'markdown'  => ['generate', 'rewrite', 'summarize', 'expand', 'translate', 'grammar'],
            'image'     => ['generate_image', 'alt_text'],
            'gallery'   => ['generate_image'],
            'select'    => ['generate_options'],
            'taxonomy'  => ['suggest_tags'],
            'slug'      => ['generate_slug'],
            'email'     => ['generate'],
        ];

        // Action labels
        $actionLabels = [
            'generate'        => 'Generate',
            'rewrite'         => 'Rewrite',
            'summarize'       => 'Summarize',
            'expand'          => 'Expand',
            'translate'       => 'Translate',
            'grammar'         => 'Grammar Check',
            'generate_image'  => 'Generate Image',
            'alt_text'        => 'Generate Alt Text',
            'generate_options' => 'Generate Options',
            'suggest_tags'    => 'Suggest Tags',
            'generate_slug'   => 'Generate Slug',
        ];

        // ── Build form via FormBuilder API ──────────────────────────────
        $checkedAttr = $ctEnabled ? ' checked' : '';
        $masterToggleHtml = '<div class="card mb-4">'
            . '<div class="card__body" style="display:flex;align-items:center;justify-content:space-between;">'
            . '<div style="display:flex;align-items:center;gap:1rem;">'
            . '<div style="width:2.5rem;height:2.5rem;border-radius:0.5rem;background:rgba(99,102,241,0.15);display:flex;align-items:center;justify-content:center;">'
            . '<i data-lucide="' . htmlspecialchars($ct->icon) . '" class="w-5 h-5" style="color:#818cf8;"></i>'
            . '</div>'
            . '<div>'
            . '<h3 style="margin:0;font-size:1rem;color:#e2e8f0;">Enable AI for ' . htmlspecialchars($ct->label) . '</h3>'
            . '<p style="margin:0;font-size:0.8rem;color:#94a3b8;">AI tools will appear in the content editor for this type.</p>'
            . '</div>'
            . '</div>'
            . '<label class="ct-toggle">'
            . '<input type="hidden" name="ai_enabled" value="0">'
            . '<input type="checkbox" name="ai_enabled" value="1"' . $checkedAttr . '>'
            . '<span class="toggle-switch"></span>'
            . '</label>'
            . '</div>'
            . '</div>';

        $form = FormBuilder::create('/admin/content-types/' . $typeId . '/ai', 'POST')
            ->id('ai-config-form')
            ->layout('default')

            // Styled master toggle card
            ->html($masterToggleHtml)

            // Field mapping table (custom HTML)
            ->html($this->buildFieldMappingHtml($fields, $ctOverrides, $actionsMap, $actionLabels))

            ->submit('Save AI Configuration', 'save')
            ->cancel('/admin/content-types/' . $typeId . '/fields')
            ->build($this->session);

        $formHtml = $this->formRenderer->render($form);

        return Response::html($this->renderer->render('admin::content-types.ai', [
            'title'        => $ct->label . ' — AI Configuration',
            'contentType'  => $ct,
            'fields'       => $fields,
            'ctOverrides'  => $ctOverrides,
            'actionsMap'   => $actionsMap,
            'actionLabels' => $actionLabels,
            'apexEnabled'  => $apexConfig->enabled,
            'formHtml'     => $formHtml,
        ]));
    }

    /**
     * Build the per-field AI configuration table as raw HTML.
     */
    private function buildFieldMappingHtml(
        array $fields,
        array $ctOverrides,
        array $actionsMap,
        array $actionLabels,
    ): string {
        $builtInFields = [
            ['name' => 'Title', 'machine_name' => 'title', 'field_type' => 'string'],
            ['name' => 'Body', 'machine_name' => 'body', 'field_type' => 'html'],
            ['name' => 'Slug', 'machine_name' => 'slug', 'field_type' => 'slug'],
            ['name' => 'Meta Title', 'machine_name' => 'meta_title', 'field_type' => 'string'],
            ['name' => 'Meta Description', 'machine_name' => 'meta_description', 'field_type' => 'text'],
            ['name' => 'Summary', 'machine_name' => 'summary', 'field_type' => 'text'],
        ];

        $html = '<div class="card" id="ai-fields-card">';
        $html .= '<div class="card__header flex-between">';
        $html .= '<h3 class="card__title"><i data-lucide="wand-2" class="w-4 h-4 card__title-icon"></i> Field AI Actions</h3>';
        $html .= '<span class="badge badge--muted text-xs">Configure which fields get AI tools</span>';
        $html .= '</div>';
        $html .= '<div class="card__body p-0">';
        $html .= '<table class="table table--hover" id="ai-fields-table">';
        $html .= '<thead><tr><th style="width:44px">AI</th><th>Field</th><th>Type</th><th>Available Actions</th></tr></thead>';
        $html .= '<tbody>';

        // Built-in fields
        foreach ($builtInFields as $bf) {
            $html .= $this->buildFieldRow($bf['machine_name'], $bf['name'], $bf['field_type'], $ctOverrides, $actionsMap, $actionLabels);
        }

        // Dynamic fields
        if (!empty($fields)) {
            $html .= '<tr class="table__divider"><td colspan="4"><span class="text-xs text-muted">Custom Fields</span></td></tr>';
            foreach ($fields as $field) {
                $ft = $field->field_type;
                $label = $field->name;
                if (method_exists($field, 'getFieldTypeEnum')) {
                    $label = $field->name;
                    $ft = $field->field_type;
                }
                $html .= $this->buildFieldRow($field->machine_name, $label, $ft, $ctOverrides, $actionsMap, $actionLabels);
            }
        }

        $html .= '</tbody></table></div></div>';
        return $html;
    }

    /**
     * Build a single field row for the AI configuration table.
     */
    private function buildFieldRow(
        string $machineName,
        string $displayName,
        string $fieldType,
        array $ctOverrides,
        array $actionsMap,
        array $actionLabels,
    ): string {
        $fieldOverride = $ctOverrides['fields'][$machineName] ?? ['enabled' => false, 'actions' => []];
        $isOn = (bool) ($fieldOverride['enabled'] ?? false);
        $selectedActions = $fieldOverride['actions'] ?? [];
        $availActions = $actionsMap[$fieldType] ?? [];
        $rowClass = $isOn ? '' : ' ai-field-row--off';
        $checked = $isOn ? ' checked' : '';

        $mn = htmlspecialchars($machineName);
        $dn = htmlspecialchars($displayName);
        $ft = htmlspecialchars($fieldType);

        $html = '<tr class="ai-field-row' . $rowClass . '" data-field="' . $mn . '">';
        $html .= '<td class="text-center"><label class="ct-toggle ct-toggle--sm">';
        $html .= '<input type="checkbox" name="ai_field_' . $mn . '" value="1"' . $checked;
        $html .= ' onchange="toggleFieldRow(\'' . $mn . '\', this.checked)">';
        $html .= '<span class="toggle-switch toggle-switch--sm"></span></label></td>';
        $html .= '<td><span class="font-medium">' . $dn . '</span><div class="text-xs text-muted">' . $mn . '</div></td>';
        $html .= '<td><span class="badge badge--outline badge--sm">' . $ft . '</span></td>';
        $html .= '<td class="ai-actions-cell" id="actions-' . $mn . '">';

        if (!empty($availActions)) {
            foreach ($availActions as $act) {
                $actChecked = in_array($act, $selectedActions) ? ' checked' : '';
                $chipClass = $actChecked ? ' ai-action-chip--on' : '';
                $actLabel = htmlspecialchars($actionLabels[$act] ?? $act);
                $html .= '<label class="ai-action-chip' . $chipClass . '">';
                $html .= '<input type="checkbox" name="ai_actions_' . $mn . '[]" value="' . htmlspecialchars($act) . '"' . $actChecked . '>';
                $html .= ' ' . $actLabel . '</label>';
            }
        } else {
            $html .= '<span class="text-xs text-muted">No AI actions for this field type</span>';
        }

        $html .= '</td></tr>';
        return $html;
    }

    #[Route('POST', '/{typeId}/ai', name: 'admin::content-types.ai.save')]
    public function saveAiConfig(ServerRequestInterface $request, string $typeId): Response
    {
        $ct = $this->typeManager->getOrFail($typeId);
        $body = $this->parseBody($request);
        $fields = $this->typeManager->getFieldsFor($typeId);

        // Build per-field config from POST data
        $fieldConfig = [];
        foreach ($fields as $field) {
            $mn = $field->machine_name;
            $enabled = !empty($body["ai_field_{$mn}"]);
            $fieldActions = [];

            if ($enabled && !empty($body["ai_actions_{$mn}"])) {
                $fieldActions = is_array($body["ai_actions_{$mn}"])
                    ? $body["ai_actions_{$mn}"]
                    : [$body["ai_actions_{$mn}"]];
            }

            $fieldConfig[$mn] = [
                'enabled' => $enabled,
                'actions' => $fieldActions,
            ];
        }

        // Also include built-in fields (title, body, slug, etc.)
        foreach (['title', 'body', 'slug', 'meta_title', 'meta_description', 'summary'] as $builtIn) {
            $enabled = !empty($body["ai_field_{$builtIn}"]);
            $fieldActions = [];
            if ($enabled && !empty($body["ai_actions_{$builtIn}"])) {
                $fieldActions = is_array($body["ai_actions_{$builtIn}"])
                    ? $body["ai_actions_{$builtIn}"]
                    : [$body["ai_actions_{$builtIn}"]];
            }
            $fieldConfig[$builtIn] = [
                'enabled' => $enabled,
                'actions' => $fieldActions,
            ];
        }

        // Update content type overrides in Apex config
        $apexConfig = $this->apex->config();
        $overrides = $apexConfig->contentTypeOverrides;
        $overrides[$typeId] = [
            'enabled' => !empty($body['ai_enabled']),
            'fields'  => $fieldConfig,
        ];

        // Save by creating a new config with updated overrides
        $newConfig = new ApexConfig(
            enabled: $apexConfig->enabled,
            provider: $apexConfig->provider,
            apiKey: $apexConfig->apiKey,
            model: $apexConfig->model,
            baseUrl: $apexConfig->baseUrl,
            temperature: $apexConfig->temperature,
            maxTokens: $apexConfig->maxTokens,
            systemPrompt: $apexConfig->systemPrompt,
            enabledFeatures: $apexConfig->enabledFeatures,
            budgetLimit: $apexConfig->budgetLimit,
            alertThreshold: $apexConfig->alertThreshold,
            guardrails: $apexConfig->guardrails,
            contentTypeOverrides: $overrides,
            imageProvider: $apexConfig->imageProvider,
            imageApiKey: $apexConfig->imageApiKey,
            imageModel: $apexConfig->imageModel,
            imageSettings: $apexConfig->imageSettings,
        );

        $this->apex->saveConfig($newConfig);

        return Response::redirect('/admin/content-types/' . $typeId . '/ai?saved=1');
    }
}
