<?php

declare(strict_types=1);

namespace App\Cms\Webform;

use App\Cms\Content\PaginatedResult;
use App\Cms\Form\Form;
use App\Cms\Form\FormBuilder;
use App\Cms\Form\FormRenderer;
use App\Cms\Form\ValidationResult;
use PDO;

/**
 * WebformService — CRUD, submission handling, export, and Form API bridge.
 *
 * Manages webform definitions (stored as JSON configs) and their submissions.
 * Converts field configs into FormBuilder chains for rendering.
 */
final class WebformService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly WebformValidator $validator,
    ) {}

    // ═══════════════════════════════════════════════════════════════════════
    // Form CRUD
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Get all webforms ordered by creation date.
     *
     * @return list<WebformEntity>
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT w.*, (SELECT COUNT(*) FROM webform_submissions ws WHERE ws.webform_id = w.id) as submission_count,
             (SELECT COUNT(*) FROM webform_submissions ws WHERE ws.webform_id = w.id AND ws.is_read = 0) as unread_count
             FROM webforms w ORDER BY w.created_at DESC'
        );

        $forms = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $entity = (new WebformEntity())->hydrate($row);
            $entity->_submissionCount = (int) ($row['submission_count'] ?? 0);
            $entity->_unreadCount = (int) ($row['unread_count'] ?? 0);
            $forms[] = $entity;
        }

        return $forms;
    }

    /**
     * Find a webform by ID.
     */
    public function find(int $id): ?WebformEntity
    {
        $stmt = $this->pdo->prepare('SELECT * FROM webforms WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (new WebformEntity())->hydrate($row) : null;
    }

    /**
     * Find a webform by ID or throw.
     */
    public function findOrFail(int $id): WebformEntity
    {
        return $this->find($id) ?? throw new \RuntimeException("Webform #{$id} not found.");
    }

    /**
     * Find a webform by machine name (slug).
     */
    public function findBySlug(string $slug): ?WebformEntity
    {
        $stmt = $this->pdo->prepare('SELECT * FROM webforms WHERE machine_name = :slug');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (new WebformEntity())->hydrate($row) : null;
    }

    /**
     * Persist (create or update) a webform.
     */
    public function persist(WebformEntity $entity): WebformEntity
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        if ($entity->id !== null) {
            return $this->update($entity, $now);
        }

        return $this->insert($entity, $now);
    }

    /**
     * Delete a webform and all its submissions.
     */
    public function delete(int $id): bool
    {
        // CASCADE will handle submissions
        $stmt = $this->pdo->prepare('DELETE FROM webforms WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Duplicate a webform.
     */
    public function duplicate(int $id): ?WebformEntity
    {
        $original = $this->find($id);
        if ($original === null) {
            return null;
        }

        $copy = new WebformEntity();
        $copy->machine_name = $original->machine_name . '_copy_' . time();
        $copy->label = $original->label . ' (Copy)';
        $copy->description = $original->description;
        $copy->status = 'closed';
        $copy->fields = $original->fields;
        $copy->pages = $original->pages;
        $copy->settings = $original->settings;
        $copy->confirmation = $original->confirmation;
        $copy->redirect_url = $original->redirect_url;
        $copy->submit_label = $original->submit_label;
        $copy->max_submissions = $original->max_submissions;
        $copy->recaptcha_enabled = $original->recaptcha_enabled;
        $copy->notify_emails = $original->notify_emails;
        $copy->created_by = $original->created_by;

        return $this->persist($copy);
    }

    /**
     * Check if a machine name is available.
     */
    public function isSlugAvailable(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM webforms WHERE machine_name = :slug';
        $params = ['slug' => $slug];

        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() === 0;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Submissions
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Store a form submission.
     *
     * @return int Submission ID
     */
    public function submit(
        int $webformId,
        array $data,
        ?array $files = null,
        ?int $userId = null,
        string $ip = '0.0.0.0',
        ?string $userAgent = null,
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO webform_submissions (webform_id, data, files, user_id, ip_address, user_agent)
             VALUES (:wid, :data, :files, :uid, :ip, :ua)'
        );
        $stmt->execute([
            'wid'   => $webformId,
            'data'  => json_encode($data),
            'files' => $files !== null ? json_encode($files) : null,
            'uid'   => $userId,
            'ip'    => $ip,
            'ua'    => $userAgent,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Get paginated submissions for a webform.
     */
    public function getSubmissions(int $webformId, int $page = 1, int $perPage = 25): PaginatedResult
    {
        $offset = ($page - 1) * $perPage;

        // Total count
        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM webform_submissions WHERE webform_id = :wid');
        $countStmt->execute(['wid' => $webformId]);
        $total = (int) $countStmt->fetchColumn();

        // Items
        $stmt = $this->pdo->prepare(
            'SELECT * FROM webform_submissions WHERE webform_id = :wid
             ORDER BY created_at DESC LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue('wid', $webformId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = array_map(function (array $row) {
            $row['data'] = json_decode($row['data'] ?? '{}', true) ?: [];
            $row['files'] = json_decode($row['files'] ?? 'null', true);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));

        return new PaginatedResult($items, $total, $page, $perPage);
    }

    /**
     * Get a single submission.
     */
    public function getSubmission(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM webform_submissions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['data'] = json_decode($row['data'] ?? '{}', true) ?: [];
        $row['files'] = json_decode($row['files'] ?? 'null', true);
        return $row;
    }

    /**
     * Mark a submission as read/unread.
     */
    public function markRead(int $id, bool $read = true): void
    {
        $this->pdo->prepare('UPDATE webform_submissions SET is_read = :read WHERE id = :id')
            ->execute(['id' => $id, 'read' => (int) $read]);
    }

    /**
     * Toggle starred status.
     */
    public function toggleStar(int $id): void
    {
        $this->pdo->prepare('UPDATE webform_submissions SET is_starred = NOT is_starred WHERE id = :id')
            ->execute(['id' => $id]);
    }

    /**
     * Delete a submission.
     */
    public function deleteSubmission(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM webform_submissions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get stats for a webform.
     *
     * @return array{total: int, unread: int, starred: int, today: int}
     */
    public function getStats(int $webformId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                COUNT(*) as total,
                SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
                SUM(CASE WHEN is_starred = 1 THEN 1 ELSE 0 END) as starred,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
             FROM webform_submissions WHERE webform_id = :wid'
        );
        $stmt->execute(['wid' => $webformId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total'   => (int) ($row['total'] ?? 0),
            'unread'  => (int) ($row['unread'] ?? 0),
            'starred' => (int) ($row['starred'] ?? 0),
            'today'   => (int) ($row['today'] ?? 0),
        ];
    }

    /**
     * Get all submissions for export (no pagination).
     *
     * @return list<array>
     */
    public function getAllSubmissions(int $webformId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM webform_submissions WHERE webform_id = :wid ORDER BY created_at DESC'
        );
        $stmt->execute(['wid' => $webformId]);

        return array_map(function (array $row) {
            $row['data'] = json_decode($row['data'] ?? '{}', true) ?: [];
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Validation Bridge
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Validate a submission against form field definitions.
     */
    public function validateSubmission(WebformEntity $form, array $data, array $files = []): ValidationResult
    {
        return $this->validator->validate($form->fields, $data, $files);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Form API Bridge — converts JSON config → FormBuilder → Form
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Build a renderable Form object from a WebformEntity.
     *
     * @param int $pageIndex  For multi-page: which page to render (0-indexed)
     */
    public function buildForm(WebformEntity $entity, int $pageIndex = 0): Form
    {
        $action = '/form/' . urlencode($entity->machine_name) . '/submit';
        $builder = FormBuilder::create($action, 'POST');

        // Get fields for the current page
        $fields = $entity->isMultiPage
            ? $entity->getFieldsForPage($pageIndex)
            : $entity->fields;

        // Sort by weight
        usort($fields, fn(array $a, array $b) => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));

        foreach ($fields as $field) {
            $this->addFieldToBuilder($builder, $field);
        }

        // Hidden webform identifier
        $builder->hidden('_webform_id', (string) $entity->id);

        // Multi-page hidden field
        if ($entity->isMultiPage) {
            $builder->hidden('_page', (string) $pageIndex);
        }

        // Honeypot
        $builder->hidden('_hp_field', '');

        // Submit button
        $submitLabel = $entity->submit_label ?: 'Submit';
        if ($entity->isMultiPage && $pageIndex < $entity->pageCount - 1) {
            $submitLabel = 'Next →';
        }
        $builder->submit($submitLabel, 'send');

        return $builder->build();
    }

    /**
     * Render a webform to HTML using the FormRenderer.
     */
    public function renderForm(WebformEntity $entity, int $pageIndex = 0): string
    {
        $form = $this->buildForm($entity, $pageIndex);
        $renderer = new FormRenderer();
        return $renderer->render($form);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Internals
    // ═══════════════════════════════════════════════════════════════════════

    private function insert(WebformEntity $entity, string $now): WebformEntity
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO webforms
                (machine_name, label, description, status, fields, pages, settings,
                 confirmation, redirect_url, submit_label, max_submissions,
                 open_at, close_at, recaptcha_enabled, notify_emails, created_by, created_at, updated_at)
             VALUES
                (:machine_name, :label, :description, :status, :fields, :pages, :settings,
                 :confirmation, :redirect_url, :submit_label, :max_submissions,
                 :open_at, :close_at, :recaptcha_enabled, :notify_emails, :created_by, :created_at, :updated_at)'
        );

        $stmt->execute($this->toParams($entity, $now, false));
        $entity->id = (int) $this->pdo->lastInsertId();
        $entity->created_at = new \DateTimeImmutable($now);
        $entity->updated_at = new \DateTimeImmutable($now);

        return $entity;
    }

    private function update(WebformEntity $entity, string $now): WebformEntity
    {
        $stmt = $this->pdo->prepare(
            'UPDATE webforms SET
                machine_name = :machine_name, label = :label, description = :description,
                status = :status, fields = :fields, pages = :pages, settings = :settings,
                confirmation = :confirmation, redirect_url = :redirect_url,
                submit_label = :submit_label, max_submissions = :max_submissions,
                open_at = :open_at, close_at = :close_at,
                recaptcha_enabled = :recaptcha_enabled, notify_emails = :notify_emails,
                updated_at = :updated_at
             WHERE id = :id'
        );

        $params = $this->toParams($entity, $now, true);
        $stmt->execute($params);
        $entity->updated_at = new \DateTimeImmutable($now);

        return $entity;
    }

    /**
     * @return array<string, mixed>
     */
    private function toParams(WebformEntity $entity, string $now, bool $isUpdate): array
    {
        $params = [
            'machine_name'      => $entity->machine_name,
            'label'             => $entity->label,
            'description'       => $entity->description,
            'status'            => $entity->status,
            'fields'            => json_encode($entity->fields),
            'pages'             => json_encode($entity->pages),
            'settings'          => json_encode($entity->settings),
            'confirmation'      => $entity->confirmation,
            'redirect_url'      => $entity->redirect_url,
            'submit_label'      => $entity->submit_label ?: 'Submit',
            'max_submissions'   => $entity->max_submissions,
            'open_at'           => $entity->open_at?->format('Y-m-d H:i:s'),
            'close_at'          => $entity->close_at?->format('Y-m-d H:i:s'),
            'recaptcha_enabled' => (int) $entity->recaptcha_enabled,
            'notify_emails'     => $entity->notify_emails,
            'updated_at'        => $now,
        ];

        if ($isUpdate) {
            $params['id'] = $entity->id;
        } else {
            $params['created_by'] = $entity->created_by;
            $params['created_at'] = $now;
        }

        return $params;
    }

    /**
     * Map a single JSON field definition to a FormBuilder call.
     */
    private function addFieldToBuilder(FormBuilder $builder, array $field): void
    {
        $name  = $field['name'] ?? '';
        $label = $field['label'] ?? '';
        $type  = $field['type'] ?? 'text';

        match ($type) {
            'text', 'phone' => $builder->text($name, $label),
            'email'         => $builder->email($name, $label),
            'textarea'      => $builder->textarea($name, $label),
            'number'        => $builder->number($name, $label),
            'url'           => $builder->url($name, $label),
            'date'          => $builder->date($name, $label),
            'datetime'      => $builder->datetime($name, $label),
            'color'         => $builder->color($name, $label),
            'checkbox'      => $builder->checkbox($name, $label),
            'file'          => $builder->file($name, $label),
            'hidden'        => $builder->hidden($name, $field['default_value'] ?? ''),
            'select'        => $builder->select($name, $label, $field['options'] ?? []),
            'radio'         => $builder->radio($name, $label, $field['options'] ?? []),
            default         => $builder->text($name, $label),
        };

        // Apply modifiers
        if (!empty($field['placeholder'])) {
            $builder->placeholder($field['placeholder']);
        }
        if (!empty($field['help'])) {
            $builder->help($field['help']);
        }
        if (!empty($field['required'])) {
            $builder->required();
        }
        if (isset($field['default_value']) && $type !== 'hidden') {
            $builder->value($field['default_value']);
        }
        $rules = $field['rules'] ?? [];
        if (isset($rules['min'])) {
            $builder->min((int) $rules['min']);
        }
        if (isset($rules['max'])) {
            $builder->max((int) $rules['max']);
        }
    }
}
