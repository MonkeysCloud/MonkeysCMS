<?php

declare(strict_types=1);

namespace App\Cms\Controller\Admin;

use App\Cms\Webform\WebformEntity;
use App\Cms\Webform\WebformService;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * WebformController — Admin CRUD + submission management for webforms.
 *
 * All mutating endpoints return JSON (CMS.fetch pattern).
 */
#[RoutePrefix('/admin/webforms')]
final class WebformController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly WebformService $webforms,
    ) {}

    // ═══════════════════════════════════════════════════════════════════════
    // Pages (HTML)
    // ═══════════════════════════════════════════════════════════════════════

    #[Route('GET', '/', name: 'admin::webforms.index')]
    public function index(): Response
    {
        $forms = $this->webforms->findAll();

        return Response::html($this->renderer->render('webforms.index', [
            'title' => 'Webforms',
            'forms' => $forms,
        ]));
    }

    #[Route('GET', '/create', name: 'admin::webforms.create')]
    public function create(): Response
    {
        $entity = new WebformEntity();

        return Response::html($this->renderer->render('webforms.form', [
            'title'   => 'Create Webform',
            'webform' => $entity,
            'isNew'   => true,
        ]));
    }

    #[Route('GET', '/{id:\d+}', name: 'admin::webforms.edit')]
    public function edit(ServerRequestInterface $request, string $id): Response
    {
        $entity = $this->webforms->findOrFail((int) $id);

        return Response::html($this->renderer->render('webforms.form', [
            'title'   => 'Edit: ' . $entity->label,
            'webform' => $entity,
            'isNew'   => false,
        ]));
    }

    #[Route('GET', '/{id:\d+}/submissions', name: 'admin::webforms.submissions')]
    public function submissions(ServerRequestInterface $request, string $id): Response
    {
        $entity = $this->webforms->findOrFail((int) $id);
        $page = max(1, (int) ($request->getQueryParams()['page'] ?? 1));
        $result = $this->webforms->getSubmissions((int) $id, $page);
        $stats  = $this->webforms->getStats((int) $id);

        return Response::html($this->renderer->render('webforms.submissions', [
            'title'       => 'Submissions: ' . $entity->label,
            'webform'     => $entity,
            'submissions' => $result,
            'stats'       => $stats,
        ]));
    }

    #[Route('GET', '/{id:\d+}/submissions/{sid:\d+}', name: 'admin::webforms.submission-detail')]
    public function submissionDetail(ServerRequestInterface $request, string $id, string $sid): Response
    {
        $entity = $this->webforms->findOrFail((int) $id);
        $submission = $this->webforms->getSubmission((int) $sid);

        if (!$submission || (int) $submission['webform_id'] !== (int) $id) {
            return Response::json(['error' => 'Submission not found'], 404);
        }

        // Auto-mark as read
        $this->webforms->markRead((int) $sid);

        return Response::html($this->renderer->render('webforms.submission-detail', [
            'title'      => 'Submission #' . $sid,
            'webform'    => $entity,
            'submission' => $submission,
        ]));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // JSON API — CRUD
    // ═══════════════════════════════════════════════════════════════════════

    #[Route('POST', '/', name: 'admin::webforms.store')]
    public function store(ServerRequestInterface $request): Response
    {
        $data = $this->parseBody($request);

        try {
            $entity = $this->hydrateFromRequest(new WebformEntity(), $data);

            // Auto-generate machine name if empty
            if (empty($entity->machine_name)) {
                $entity->machine_name = $this->slugify($entity->label);
            }

            // Check uniqueness
            if (!$this->webforms->isSlugAvailable($entity->machine_name)) {
                return Response::json(['success' => false, 'error' => 'Machine name already exists.'], 422);
            }

            $entity = $this->webforms->persist($entity);
            return Response::json(['success' => true, 'id' => $entity->id, 'slug' => $entity->machine_name], 201);
        } catch (\Throwable $e) {
            return Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('PUT', '/{id:\d+}', name: 'admin::webforms.update')]
    public function update(ServerRequestInterface $request, string $id): Response
    {
        $data = $this->parseBody($request);

        try {
            $entity = $this->webforms->findOrFail((int) $id);
            $entity = $this->hydrateFromRequest($entity, $data);

            // Check slug uniqueness (excluding self)
            if (!$this->webforms->isSlugAvailable($entity->machine_name, $entity->id)) {
                return Response::json(['success' => false, 'error' => 'Machine name already exists.'], 422);
            }

            $entity = $this->webforms->persist($entity);
            return Response::json(['success' => true, 'id' => $entity->id]);
        } catch (\Throwable $e) {
            return Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('DELETE', '/{id:\d+}', name: 'admin::webforms.delete')]
    public function destroy(ServerRequestInterface $request, string $id): Response
    {
        try {
            $this->webforms->delete((int) $id);
            return Response::json(['success' => true]);
        } catch (\Throwable $e) {
            return Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('POST', '/{id:\d+}/duplicate', name: 'admin::webforms.duplicate')]
    public function duplicateForm(ServerRequestInterface $request, string $id): Response
    {
        try {
            $copy = $this->webforms->duplicate((int) $id);
            if (!$copy) {
                return Response::json(['success' => false, 'error' => 'Form not found.'], 404);
            }
            return Response::json(['success' => true, 'id' => $copy->id]);
        } catch (\Throwable $e) {
            return Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // JSON API — Submissions
    // ═══════════════════════════════════════════════════════════════════════

    #[Route('POST', '/{id:\d+}/submissions/{sid:\d+}/read', name: 'admin::webforms.submission.read')]
    public function markSubmissionRead(ServerRequestInterface $request, string $id, string $sid): Response
    {
        $data = $this->parseBody($request);
        $read = filter_var($data['read'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $this->webforms->markRead((int) $sid, $read);
        return Response::json(['success' => true]);
    }

    #[Route('POST', '/{id:\d+}/submissions/{sid:\d+}/star', name: 'admin::webforms.submission.star')]
    public function toggleSubmissionStar(ServerRequestInterface $request, string $id, string $sid): Response
    {
        $this->webforms->toggleStar((int) $sid);
        return Response::json(['success' => true]);
    }

    #[Route('DELETE', '/{id:\d+}/submissions/{sid:\d+}', name: 'admin::webforms.submission.delete')]
    public function deleteSubmission(ServerRequestInterface $request, string $id, string $sid): Response
    {
        $this->webforms->deleteSubmission((int) $sid);
        return Response::json(['success' => true]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Export
    // ═══════════════════════════════════════════════════════════════════════

    #[Route('GET', '/{id:\d+}/export/{format}', name: 'admin::webforms.export')]
    public function export(ServerRequestInterface $request, string $id, string $format): Response
    {
        $entity = $this->webforms->findOrFail((int) $id);
        $submissions = $this->webforms->getAllSubmissions((int) $id);
        $fields = $entity->fields;

        if ($format === 'json') {
            return $this->exportJson($entity, $submissions);
        }

        return $this->exportCsv($entity, $fields, $submissions);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Internals
    // ═══════════════════════════════════════════════════════════════════════

    private function parseBody(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody() ?? [];
        if (empty($body)) {
            $stream = $request->getBody();
            $stream->rewind();
            $decoded = json_decode($stream->getContents(), true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }
        return $body;
    }

    private function hydrateFromRequest(WebformEntity $entity, array $data): WebformEntity
    {
        if (isset($data['label'])) $entity->label = trim($data['label']);
        if (isset($data['machine_name'])) $entity->machine_name = trim($data['machine_name']);
        if (isset($data['description'])) $entity->description = $data['description'] ?: null;
        if (isset($data['status'])) $entity->status = $data['status'];
        if (isset($data['fields'])) $entity->fields = $data['fields'];
        if (isset($data['pages'])) $entity->pages = $data['pages'];
        if (isset($data['settings'])) $entity->settings = $data['settings'];
        if (isset($data['confirmation'])) $entity->confirmation = $data['confirmation'] ?: null;
        if (isset($data['redirect_url'])) $entity->redirect_url = $data['redirect_url'] ?: null;
        if (isset($data['submit_label'])) $entity->submit_label = $data['submit_label'] ?: 'Submit';
        if (isset($data['max_submissions'])) $entity->max_submissions = $data['max_submissions'] ? (int) $data['max_submissions'] : null;
        if (isset($data['open_at'])) $entity->open_at = $data['open_at'] ? new \DateTimeImmutable($data['open_at']) : null;
        if (isset($data['close_at'])) $entity->close_at = $data['close_at'] ? new \DateTimeImmutable($data['close_at']) : null;
        if (isset($data['recaptcha_enabled'])) $entity->recaptcha_enabled = filter_var($data['recaptcha_enabled'], FILTER_VALIDATE_BOOLEAN);
        if (isset($data['notify_emails'])) $entity->notify_emails = $data['notify_emails'] ?: null;

        return $entity;
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '_', $text);
        return trim($text, '_');
    }

    private function exportJson(WebformEntity $entity, array $submissions): Response
    {
        $export = [
            'form'        => $entity->label,
            'exported_at' => (new \DateTimeImmutable())->format('c'),
            'total'       => count($submissions),
            'submissions' => array_map(fn(array $s) => [
                'id'         => $s['id'],
                'data'       => $s['data'],
                'ip'         => $s['ip_address'] ?? null,
                'created_at' => $s['created_at'] ?? null,
            ], $submissions),
        ];

        $filename = $entity->machine_name . '_export_' . date('Ymd') . '.json';
        return Response::json($export)
            ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    private function exportCsv(WebformEntity $entity, array $fields, array $submissions): Response
    {
        // Build header row from field labels
        $headers = ['#', 'Submitted At', 'IP'];
        $fieldNames = [];
        foreach ($fields as $f) {
            $headers[] = $f['label'] ?? $f['name'] ?? 'Unknown';
            $fieldNames[] = $f['name'] ?? '';
        }

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, $headers);

        foreach ($submissions as $i => $sub) {
            $row = [$sub['id'] ?? ($i + 1), $sub['created_at'] ?? '', $sub['ip_address'] ?? ''];
            foreach ($fieldNames as $fname) {
                $val = $sub['data'][$fname] ?? '';
                if (is_array($val)) {
                    $val = implode(', ', $val);
                }
                $row[] = $val;
            }
            fputcsv($csv, $row);
        }

        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        $filename = $entity->machine_name . '_export_' . date('Ymd') . '.csv';

        return new Response(
            200,
            ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"{$filename}\""],
            $content,
        );
    }
}
