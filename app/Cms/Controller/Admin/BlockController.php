<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Block\BlockService;
use App\Cms\Block\BlockInstanceService;
use App\Cms\Block\BlockTypeRegistry;
use App\Cms\Field\FieldType;
use App\Cms\Form\FormBuilder;
use App\Cms\Form\FormRenderer;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Session\SessionManager;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * BlockController — Admin UI for block type and instance management.
 *
 * Provides full CRUD for database-defined block types (with FormBuilder),
 * field management, duplication, import/export, revision history,
 * and reusable block instance management.
 *
 * Code-defined blocks (PHP classes) are shown as read-only.
 */
#[RoutePrefix('/admin/blocks')]
final class BlockController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly BlockService $blockService,
        private readonly BlockInstanceService $instanceService,
        private readonly BlockTypeRegistry $registry,
        private readonly FormRenderer $formRenderer,
        private readonly SessionManager $session,
        private readonly \App\Cms\Theme\ThemeManager $themeManager,
    ) {}

    // ═══ Block Type Listing ════════════════════════════════════════════

    #[Route('GET', '/', name: 'admin::blocks.index')]
    public function index(ServerRequestInterface $request): Response
    {
        $tab = $request->getQueryParams()['tab'] ?? 'types';
        $grouped = $this->blockService->getGrouped();
        $instances = [];
        $instanceTypes = [];

        if ($tab === 'instances') {
            $filterType = $request->getQueryParams()['type'] ?? null;
            $instances = $this->instanceService->getAll($filterType);
            $instanceTypes = $this->instanceService->getTypeBreakdown();
        }

        $html = $this->renderer->render('admin::blocks.index', [
            'title'         => 'Blocks',
            'tab'           => $tab,
            'grouped'       => $grouped,
            'allTypes'      => $this->blockService->getAll(),
            'instances'     => $instances,
            'instanceTypes' => $instanceTypes,
            'totalTypes'    => count($this->blockService->getAll()),
            'totalInstances'=> $this->instanceService->count(),
            'flashSuccess'  => $this->session->getFlash('block_success'),
            'flashError'    => $this->session->getFlash('block_error'),
        ]);

        return Response::html($html);
    }

    // ═══ Block Type CRUD ═══════════════════════════════════════════════

    #[Route('GET', '/create', name: 'admin::blocks.create')]
    public function create(ServerRequestInterface $request): Response
    {
        $form = $this->buildBlockTypeForm('/admin/blocks', null);

        $html = $this->renderer->render('admin::blocks.form', [
            'title'        => 'Create Block Type',
            'isNew'        => true,
            'blockType'    => null,
            'form'         => $form,
            'formHtml'     => $this->formRenderer->render($form),
            'categories'   => $this->blockService->getCategories(),
            'fieldTypes'   => FieldType::cases(),
            'flashSuccess' => $this->session->getFlash('block_success'),
            'flashError'   => $this->session->getFlash('block_error'),
        ]);

        return Response::html($html);
    }

    #[Route('POST', '/', name: 'admin::blocks.store')]
    public function store(ServerRequestInterface $request): Response
    {
        $body = (array) $request->getParsedBody();

        try {
            $this->blockService->create([
                'type_id'     => $this->sanitizeMachineName($body['type_id'] ?? ''),
                'label'       => trim($body['label'] ?? ''),
                'description' => trim($body['description'] ?? ''),
                'icon'        => trim($body['icon'] ?? 'puzzle'),
                'category'    => trim($body['category'] ?? $body['category_custom'] ?? 'Custom'),
                'template'    => $body['template'] ?? null,
                'settings'    => $this->parseJsonField($body['settings'] ?? '{}'),
                'enabled'     => isset($body['enabled']),
                'weight'      => (int) ($body['weight'] ?? 0),
            ]);

            $this->session->flash('block_success', 'Block type created successfully.');
            return Response::redirect('/admin/blocks');
        } catch (\Throwable $e) {
            $this->session->flash('block_error', $e->getMessage());
            return Response::redirect('/admin/blocks/create');
        }
    }

    #[Route('GET', '/theme/create', name: 'admin::blocks.theme.create')]
    public function createThemeBlock(ServerRequestInterface $request): Response
    {
        $form = $this->buildThemeBlockTypeForm('/admin/blocks/theme');

        $html = $this->renderer->render('admin::blocks.form', [
            'title'        => 'Create Theme Component',
            'isNew'        => true,
            'blockType'    => null,
            'form'         => $form,
            'formHtml'     => $this->formRenderer->render($form),
            'categories'   => $this->blockService->getCategories(),
            'fieldTypes'   => FieldType::cases(),
            'flashSuccess' => $this->session->getFlash('block_success'),
            'flashError'   => $this->session->getFlash('block_error'),
        ]);

        return Response::html($html);
    }

    #[Route('POST', '/theme', name: 'admin::blocks.theme.store')]
    public function storeThemeBlock(ServerRequestInterface $request): Response
    {
        $body = (array) $request->getParsedBody();
        $theme = $this->themeManager->getActiveTheme();

        if (!$theme) {
            $this->session->flash('block_error', 'No active theme found.');
            return Response::redirect('/admin/blocks/theme/create');
        }

        try {
            $typeId = $this->sanitizeMachineName($body['type_id'] ?? '');
            if (!$typeId) throw new \InvalidArgumentException('Machine name is required.');

            $blocksDir = $theme->basePath . '/blocks';
            if (!is_dir($blocksDir)) {
                if (!mkdir($blocksDir, 0777, true) && !is_dir($blocksDir)) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $blocksDir));
                }
            }

            $viewsDir = $theme->basePath . '/views/blocks';
            if (!is_dir($viewsDir)) {
                if (!mkdir($viewsDir, 0777, true) && !is_dir($viewsDir)) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $viewsDir));
                }
            }

            $mlcFile = $blocksDir . '/' . $typeId . '.mlc';
            $templateFile = $viewsDir . '/' . $typeId . '.ml.php';

            if (file_exists($mlcFile)) {
                throw new \InvalidArgumentException("Theme block '{$typeId}' already exists.");
            }

            $data = [
                'label'       => trim($body['label'] ?? ucfirst($typeId)),
                'description' => trim($body['description'] ?? ''),
                'icon'        => trim($body['icon'] ?? 'puzzle'),
                'category'    => trim($body['category'] ?? $body['category_custom'] ?? 'Theme'),
            ];

            // Start MLC content
            $mlcContent = "# Block: {$data['label']}\n";
            $mlcContent .= "label = \"{$data['label']}\"\n";
            $mlcContent .= "description = \"{$data['description']}\"\n";
            $mlcContent .= "icon = \"{$data['icon']}\"\n";
            $mlcContent .= "category = \"{$data['category']}\"\n\n";
            $mlcContent .= "fields {\n  # Define your fields here (e.g. title { type=\"string\" })\n}\n";

            file_put_contents($mlcFile, $mlcContent);

            $templateContent = "{{-- Dynamic Block: {$data['label']} --}}\n";
            $templateContent .= "@php\n  // \$text = htmlspecialchars(\$data['text'] ?? '');\n@endphp\n\n";
            $templateContent .= "<div class=\"block-dynamic block-{$typeId}\">\n";
            $templateContent .= "  <p>Hello from {$data['label']} component!</p>\n";
            $templateContent .= "</div>\n";

            file_put_contents($templateFile, $templateContent);

            $this->session->flash('block_success', "Theme block '{$typeId}' generated in {$theme->name} theme.");
            return Response::redirect('/admin/blocks');
        } catch (\Throwable $e) {
            $this->session->flash('block_error', $e->getMessage());
            return Response::redirect('/admin/blocks/theme/create');
        }
    }

    #[Route('GET', '/{typeId}/edit', name: 'admin::blocks.edit')]
    public function edit(ServerRequestInterface $request, string $typeId): Response
    {
        $blockType = $this->blockService->get($typeId);
        if (!$blockType) {
            $this->session->flash('block_error', "Block type '{$typeId}' not found.");
            return Response::redirect('/admin/blocks');
        }

        $form = $this->buildBlockTypeForm("/admin/blocks/{$typeId}", $blockType);

        $html = $this->renderer->render('admin::blocks.form', [
            'title'        => 'Edit: ' . $blockType['label'],
            'isNew'        => false,
            'blockType'    => $blockType,
            'form'         => $form,
            'formHtml'     => $this->formRenderer->render($form),
            'categories'   => $this->blockService->getCategories(),
            'fieldTypes'   => FieldType::cases(),
            'flashSuccess' => $this->session->getFlash('block_success'),
            'flashError'   => $this->session->getFlash('block_error'),
        ]);

        return Response::html($html);
    }

    #[Route('POST', '/{typeId}', name: 'admin::blocks.update')]
    public function update(ServerRequestInterface $request, string $typeId): Response
    {
        $body = (array) $request->getParsedBody();

        try {
            $this->blockService->update($typeId, [
                'label'       => trim($body['label'] ?? ''),
                'description' => trim($body['description'] ?? ''),
                'icon'        => trim($body['icon'] ?? 'puzzle'),
                'category'    => trim($body['category'] ?? $body['category_custom'] ?? 'Custom'),
                'template'    => $body['template'] ?? null,
                'settings'    => $this->parseJsonField($body['settings'] ?? '{}'),
                'enabled'     => isset($body['enabled']),
                'weight'      => (int) ($body['weight'] ?? 0),
            ]);

            $this->session->flash('block_success', 'Block type updated.');
            return Response::redirect('/admin/blocks/' . $typeId . '/edit');
        } catch (\Throwable $e) {
            $this->session->flash('block_error', $e->getMessage());
            return Response::redirect('/admin/blocks/' . $typeId . '/edit');
        }
    }

    #[Route('POST', '/{typeId}/delete', name: 'admin::blocks.delete')]
    public function delete(ServerRequestInterface $request, string $typeId): Response
    {
        try {
            $this->blockService->delete($typeId);
            $this->session->flash('block_success', 'Block type deleted.');
        } catch (\Throwable $e) {
            $this->session->flash('block_error', $e->getMessage());
        }

        return Response::redirect('/admin/blocks');
    }

    // ═══ Field Management ══════════════════════════════════════════════

    #[Route('GET', '/{typeId}/fields', name: 'admin::blocks.fields')]
    public function fields(ServerRequestInterface $request, string $typeId): Response
    {
        $blockType = $this->blockService->getOrFail($typeId);

        $html = $this->renderer->render('admin::blocks.fields', [
            'title'        => $blockType['label'] . ' — Fields',
            'blockType'    => $blockType,
            'fields'       => $blockType['fields'] ?? [],
            'fieldTypes'   => FieldType::cases(),
            'flashSuccess' => $this->session->getFlash('block_success'),
            'flashError'   => $this->session->getFlash('block_error'),
        ]);

        return Response::html($html);
    }

    #[Route('POST', '/{typeId}/fields', name: 'admin::blocks.fields.add')]
    public function addField(ServerRequestInterface $request, string $typeId): Response
    {
        $body = (array) $request->getParsedBody();

        try {
            $fieldName = $this->sanitizeMachineName($body['machine_name'] ?? '');
            $fieldDef = [
                'type'     => $body['field_type'] ?? 'string',
                'label'    => trim($body['label'] ?? ucfirst($fieldName)),
                'required' => isset($body['required']),
                'default'  => $body['default'] ?? null,
            ];

            if (!empty($body['help_text'])) {
                $fieldDef['help_text'] = trim($body['help_text']);
            }

            if (!empty($body['options'])) {
                $fieldDef['options'] = $this->parseOptionsField($body['options']);
            }

            $this->blockService->addField($typeId, $fieldName, $fieldDef);
            $this->session->flash('block_success', "Field '{$fieldName}' added.");
        } catch (\Throwable $e) {
            $this->session->flash('block_error', $e->getMessage());
        }

        return Response::redirect("/admin/blocks/{$typeId}/fields");
    }

    #[Route('POST', '/{typeId}/fields/{fieldName}/delete', name: 'admin::blocks.fields.delete')]
    public function removeField(ServerRequestInterface $request, string $typeId, string $fieldName): Response
    {
        try {
            $this->blockService->removeField($typeId, $fieldName);
            $this->session->flash('block_success', "Field '{$fieldName}' removed.");
        } catch (\Throwable $e) {
            $this->session->flash('block_error', $e->getMessage());
        }

        return Response::redirect("/admin/blocks/{$typeId}/fields");
    }

    #[Route('POST', '/{typeId}/fields/reorder', name: 'admin::blocks.fields.reorder')]
    public function reorderFields(ServerRequestInterface $request, string $typeId): Response
    {
        $body = (array) $request->getParsedBody();
        $order = $body['order'] ?? [];

        if (is_string($order)) {
            $order = json_decode($order, true) ?: [];
        }

        if (empty($order)) {
            $this->session->flash('block_error', 'No field order received.');
            return Response::redirect("/admin/blocks/{$typeId}/fields");
        }

        try {
            $this->blockService->reorderFields($typeId, $order);
            $this->session->flash('block_success', 'Fields reordered.');
        } catch (\Throwable $e) {
            $this->session->flash('block_error', $e->getMessage());
        }

        return Response::redirect("/admin/blocks/{$typeId}/fields");
    }

    #[Route('POST', '/{typeId}/fields/{fieldName}/update', name: 'admin::blocks.fields.update')]
    public function updateField(ServerRequestInterface $request, string $typeId, string $fieldName): Response
    {
        $body = (array) $request->getParsedBody();

        try {
            $this->blockService->updateField($typeId, $fieldName, [
                'label'     => trim($body['label'] ?? ''),
                'required'  => !empty($body['required']),
                'help_text' => trim($body['help_text'] ?? ''),
                'default'   => $body['default'] ?? null,
            ]);
            $this->session->flash('block_success', "Field '{$fieldName}' updated.");
        } catch (\Throwable $e) {
            $this->session->flash('block_error', $e->getMessage());
        }

        return Response::redirect("/admin/blocks/{$typeId}/fields");
    }

    // ═══ Duplicate / Export / Import ════════════════════════════════════

    #[Route('POST', '/{typeId}/duplicate', name: 'admin::blocks.duplicate')]
    public function duplicateBlock(ServerRequestInterface $request, string $typeId): Response
    {
        $body = (array) $request->getParsedBody();

        try {
            // Generate a unique copy ID
            $baseId = $this->sanitizeMachineName($body['new_type_id'] ?? $typeId . '_copy');
            $newId = $baseId;
            $counter = 1;

            while ($this->blockService->get($newId)) {
                $counter++;
                $newId = $baseId . '_' . $counter;
            }

            $result = $this->blockService->duplicate($typeId, $newId);
            $this->session->flash('block_success', "Block type duplicated as '{$newId}'.");
            return Response::redirect('/admin/blocks/' . $newId . '/edit');
        } catch (\Throwable $e) {
            $this->session->flash('block_error', $e->getMessage());
            return Response::redirect('/admin/blocks');
        }
    }

    #[Route('GET', '/{typeId}/export', name: 'admin::blocks.export')]
    public function exportBlock(ServerRequestInterface $request, string $typeId): Response
    {
        $data = $this->blockService->export($typeId);

        return Response::json($data)
            ->withHeader('Content-Disposition', "attachment; filename=\"block-{$typeId}.json\"");
    }

    #[Route('POST', '/import', name: 'admin::blocks.import')]
    public function importBlock(ServerRequestInterface $request): Response
    {
        $body = (array) $request->getParsedBody();

        try {
            $json = $body['import_json'] ?? '';
            $data = json_decode($json, true);

            if (!$data || !isset($data['type_id'])) {
                throw new \InvalidArgumentException('Invalid block type JSON. Must include "type_id".');
            }

            $this->blockService->import($data);
            $this->session->flash('block_success', "Block type '{$data['type_id']}' imported.");
        } catch (\Throwable $e) {
            $this->session->flash('block_error', $e->getMessage());
        }

        return Response::redirect('/admin/blocks');
    }

    // ═══ Revisions ═════════════════════════════════════════════════════

    #[Route('GET', '/{typeId}/revisions', name: 'admin::blocks.revisions')]
    public function revisions(ServerRequestInterface $request, string $typeId): Response
    {
        $blockType = $this->blockService->getOrFail($typeId);
        $revisions = $this->blockService->getRevisions($typeId);

        $html = $this->renderer->render('admin::blocks.revisions', [
            'title'     => $blockType['label'] . ' — Revisions',
            'blockType' => $blockType,
            'revisions' => $revisions,
        ]);

        return Response::html($html);
    }

    // ═══ Block Instances ═══════════════════════════════════════════════

    #[Route('GET', '/instances/create', name: 'admin::blocks.instances.create')]
    public function createInstance(ServerRequestInterface $request): Response
    {
        $allTypes = $this->blockService->getAll();

        $html = $this->renderer->render('admin::blocks.instance-form', [
            'title'      => 'Create Block Instance',
            'isNew'      => true,
            'instance'   => null,
            'allTypes'   => $allTypes,
        ]);

        return Response::html($html);
    }

    #[Route('POST', '/instances', name: 'admin::blocks.instances.store')]
    public function storeInstance(ServerRequestInterface $request): Response
    {
        $body = (array) $request->getParsedBody();

        try {
            // Extract field data from form (fields are prefixed with "field_")
            $fieldData = [];
            foreach ($body as $key => $value) {
                if (str_starts_with($key, 'field_')) {
                    $fieldData[substr($key, 6)] = $value;
                }
            }

            $this->instanceService->create([
                'block_type'  => $body['block_type'] ?? '',
                'label'       => trim($body['label'] ?? ''),
                'description' => trim($body['description'] ?? ''),
                'data'        => $fieldData,
                'status'      => $body['status'] ?? 'published',
            ]);

            $this->session->flash('block_success', 'Block instance created.');
            return Response::redirect('/admin/blocks?tab=instances');
        } catch (\Throwable $e) {
            $this->session->flash('block_error', $e->getMessage());
            return Response::redirect('/admin/blocks/instances/create');
        }
    }

    #[Route('GET', '/instances/{id:\\d+}/edit', name: 'admin::blocks.instances.edit')]
    public function editInstance(ServerRequestInterface $request, string $id): Response
    {
        $instance = $this->instanceService->getOrFail((int) $id);
        $blockType = $this->blockService->get($instance['block_type']);
        $allTypes = $this->blockService->getAll();

        $html = $this->renderer->render('admin::blocks.instance-form', [
            'title'      => 'Edit Instance: ' . $instance['label'],
            'isNew'      => false,
            'instance'   => $instance,
            'blockType'  => $blockType,
            'allTypes'   => $allTypes,
        ]);

        return Response::html($html);
    }

    #[Route('POST', '/instances/{id:\\d+}', name: 'admin::blocks.instances.update')]
    public function updateInstance(ServerRequestInterface $request, string $id): Response
    {
        $body = (array) $request->getParsedBody();

        try {
            $fieldData = [];
            foreach ($body as $key => $value) {
                if (str_starts_with($key, 'field_')) {
                    $fieldData[substr($key, 6)] = $value;
                }
            }

            $this->instanceService->update((int) $id, [
                'label'       => trim($body['label'] ?? ''),
                'description' => trim($body['description'] ?? ''),
                'data'        => $fieldData,
                'status'      => $body['status'] ?? 'published',
            ]);

            $this->session->flash('block_success', 'Block instance updated.');
            return Response::redirect('/admin/blocks/instances/' . $id . '/edit');
        } catch (\Throwable $e) {
            $this->session->flash('block_error', $e->getMessage());
            return Response::redirect('/admin/blocks/instances/' . $id . '/edit');
        }
    }

    #[Route('POST', '/instances/{id:\\d+}/delete', name: 'admin::blocks.instances.delete')]
    public function deleteInstance(ServerRequestInterface $request, string $id): Response
    {
        try {
            $this->instanceService->delete((int) $id);
            $this->session->flash('block_success', 'Block instance deleted.');
        } catch (\Throwable $e) {
            $this->session->flash('block_error', $e->getMessage());
        }

        return Response::redirect('/admin/blocks?tab=instances');
    }

    // ═══ Private Helpers ═══════════════════════════════════════════════

    /**
     * Build the FormBuilder form for block type create/edit.
     */
    private function buildBlockTypeForm(string $action, ?array $blockType): \App\Cms\Form\Form
    {
        $isNew = $blockType === null;
        $categories = $this->blockService->getCategories();
        if (!in_array('Custom', $categories)) {
            $categories[] = 'Custom';
        }
        $categoryOptions = array_combine($categories, $categories);

        $builder = FormBuilder::create($action, 'POST')
            ->id('block-type-form')
            ->layout('two-column')
            ->cancel('/admin/blocks')

            // ── General group
            ->group('general', 'General', 'puzzle', 'Basic block type information')
            ->text('label', 'Label')
                ->value($blockType['label'] ?? '')
                ->placeholder('e.g. Call to Action')
                ->required()
                ->inGroup('general')
            ->text('type_id', 'Machine Name')
                ->value($blockType['id'] ?? '')
                ->placeholder('e.g. call_to_action')
                ->required()
                ->help('Unique identifier. Lowercase, underscores only.')
                ->inGroup('general');

        if (!$isNew) {
            $builder->disabled();
        }

        $builder
            ->textarea('description', 'Description')
                ->value($blockType['description'] ?? '')
                ->placeholder('What this block does...')
                ->inGroup('general')
            ->text('icon', 'Icon')
                ->value($blockType['icon'] ?? 'puzzle')
                ->placeholder('Lucide icon name')
                ->help('Lucide icon name (e.g., puzzle, image, text, layout)')
                ->inGroup('general')
            ->select('category', 'Category', $categoryOptions)
                ->value($blockType['category'] ?? 'Custom')
                ->inGroup('general')

            // ── Template group
            ->group('template', 'Template', 'code', 'Block rendering template using .ml.php syntax')
            ->code('template', 'Template')
                ->value($blockType['template'] ?? '')
                ->placeholder('<div class="block-{{ $blockType }}">' . "\n" . '  <h2>{{ $heading }}</h2>' . "\n" . '  {!! $body !!}' . "\n" . '</div>')
                ->help('Uses .ml.php syntax: {{ $var }}, {!! $html !!}, @if, @foreach, @include.')
                ->inGroup('template')

            // ── Settings group
            ->group('settings', 'Settings', 'settings', 'Block configuration')
            ->toggle('enabled', 'Enabled')
                ->value($blockType['enabled'] ?? true)
                ->inGroup('settings')
            ->number('weight', 'Weight')
                ->value($blockType['weight'] ?? 0)
                ->help('Lower values appear first in the block picker.')
                ->inGroup('settings')
            ->code('settings', 'Settings JSON')
                ->value(json_encode($blockType['settings'] ?? new \stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                ->help('Advanced settings as JSON. Available in templates as $settings.')
                ->inGroup('settings')

            ->submit($isNew ? 'Create Block Type' : 'Save Changes', 'save');

        return $builder->build($this->session);
    }

    /**
     * Build the FormBuilder form for theme block creation.
     */
    private function buildThemeBlockTypeForm(string $action): \App\Cms\Form\Form
    {
        $categories = $this->blockService->getCategories();
        if (!in_array('Theme', $categories)) {
            $categories[] = 'Theme';
        }
        $categoryOptions = array_combine($categories, $categories);

        $builder = FormBuilder::create($action, 'POST')
            ->id('theme-block-type-form')
            ->layout('two-column')
            ->cancel('/admin/blocks')

            ->group('general', 'General', 'puzzle', 'Basic theme component information')
            ->text('label', 'Label')
                ->placeholder('e.g. Hero Banner')
                ->required()
                ->inGroup('general')
            ->text('type_id', 'Machine Name')
                ->placeholder('e.g. hero_banner')
                ->required()
                ->help('Unique identifier. Lowercase, underscores only. Used for filenames.')
                ->inGroup('general')
            ->textarea('description', 'Description')
                ->placeholder('What this component does...')
                ->inGroup('general')
            ->text('icon', 'Icon')
                ->value('layout-template')
                ->placeholder('Lucide icon name')
                ->help('Lucide icon name (e.g., puzzle, layout, image)')
                ->inGroup('general')
            ->select('category', 'Category', $categoryOptions)
                ->value('Theme')
                ->inGroup('general')
            ->submit('Generate Component Boilerplate', 'save');

        return $builder->build($this->session);
    }

    /**
     * Sanitize a machine name: lowercase, underscores.
     */
    private function sanitizeMachineName(string $input): string
    {
        $name = strtolower(trim($input));
        $name = preg_replace('/[^a-z0-9_]/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        return trim($name, '_');
    }

    /**
     * Parse a JSON string field, returning empty array on failure.
     */
    private function parseJsonField(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Parse options field (one per line: value|label or just value).
     */
    private function parseOptionsField(string $input): array
    {
        $options = [];
        foreach (explode("\n", $input) as $line) {
            $line = trim($line);
            if ($line === '') continue;

            if (str_contains($line, '|')) {
                [$value, $label] = explode('|', $line, 2);
                $options[trim($value)] = trim($label);
            } else {
                $options[$line] = $line;
            }
        }
        return $options;
    }
}
