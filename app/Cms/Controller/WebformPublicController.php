<?php

declare(strict_types=1);

namespace App\Cms\Controller;

use App\Cms\Webform\WebformService;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Template\Renderer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * WebformPublicController — Public-facing form rendering and submission.
 */
#[RoutePrefix('/form')]
final class WebformPublicController
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly WebformService $webforms,
    ) {}

    #[Route('GET', '/{slug}', name: 'webform.show')]
    public function show(ServerRequestInterface $request, string $slug): Response
    {
        $entity = $this->webforms->findBySlug($slug);
        if (!$entity) {
            return Response::html('<h1>Form not found</h1>', 404);
        }

        if (!$entity->isOpen) {
            return Response::html($this->renderer->render('webforms.public.closed', [
                'title'   => $entity->label,
                'webform' => $entity,
            ]));
        }

        // Check max submissions
        if ($entity->max_submissions !== null) {
            $stats = $this->webforms->getStats($entity->id);
            if ($stats['total'] >= $entity->max_submissions) {
                return Response::html($this->renderer->render('webforms.public.closed', [
                    'title'   => $entity->label,
                    'webform' => $entity,
                    'reason'  => 'max_reached',
                ]));
            }
        }

        $page = max(0, (int) ($request->getQueryParams()['page'] ?? 0));
        $formHtml = $this->webforms->renderForm($entity, $page);

        return Response::html($this->renderer->render('webforms.public.form', [
            'title'       => $entity->label,
            'webform'     => $entity,
            'formHtml'    => $formHtml,
            'currentPage' => $page,
        ]));
    }

    #[Route('POST', '/{slug}/submit', name: 'webform.submit')]
    public function submit(ServerRequestInterface $request, string $slug): Response
    {
        $entity = $this->webforms->findBySlug($slug);
        if (!$entity) {
            return Response::json(['success' => false, 'error' => 'Form not found.'], 404);
        }

        if (!$entity->isOpen) {
            return Response::json(['success' => false, 'error' => 'This form is currently closed.'], 403);
        }

        $body = $this->parseBody($request);

        // Honeypot check
        if (!empty($body['_hp_field'])) {
            // Bot detected — silently "succeed"
            return Response::json(['success' => true, 'message' => 'Thank you!']);
        }

        // Validate
        $result = $this->webforms->validateSubmission($entity, $body);
        if (!$result->isValid) {
            return Response::json([
                'success' => false,
                'errors'  => $result->getErrors(),
            ], 422);
        }

        // Strip internal fields
        $data = array_filter($body, fn(string $k) => !str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY);

        // Handle file uploads
        $uploadedFiles = $request->getUploadedFiles();
        $files = null;
        if (!empty($uploadedFiles)) {
            $files = [];
            foreach ($uploadedFiles as $name => $file) {
                if ($file->getError() === UPLOAD_ERR_OK) {
                    $filename = uniqid() . '_' . $file->getClientFilename();
                    $dest = '/var/www/storage/webform-uploads/' . $filename;
                    @mkdir(dirname($dest), 0755, true);
                    $file->moveTo($dest);
                    $files[$name] = $filename;
                }
            }
        }

        // Get user info
        $serverParams = $request->getServerParams();
        $ip = $serverParams['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $request->getHeaderLine('User-Agent') ?: null;

        // Store submission
        $submissionId = $this->webforms->submit(
            $entity->id,
            $data,
            $files,
            null, // user_id — could be extracted from session
            $ip,
            $ua,
        );

        // Send notification emails
        $this->sendNotifications($entity, $data, $submissionId);

        $response = ['success' => true];
        if ($entity->redirect_url) {
            $response['redirect'] = $entity->redirect_url;
        } else {
            $response['message'] = $entity->confirmation ?: 'Thank you for your submission!';
        }

        return Response::json($response);
    }

    // ── Internals ────────────────────────────────────────────────────────

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

    private function sendNotifications(\App\Cms\Webform\WebformEntity $entity, array $data, int $submissionId): void
    {
        $addresses = $entity->getNotifyAddresses();
        if (empty($addresses)) {
            return;
        }

        try {
            // Build email body
            $lines = ["New submission for: {$entity->label}", "Submission #{$submissionId}", ""];
            foreach ($entity->fields as $field) {
                $name = $field['name'] ?? '';
                $label = $field['label'] ?? $name;
                $val = $data[$name] ?? '(empty)';
                if (is_array($val)) {
                    $val = implode(', ', $val);
                }
                $lines[] = "{$label}: {$val}";
            }
            $body = implode("\n", $lines);
            $subject = "New submission: {$entity->label}";

            // Use MonkeysLegion Mail if available, fallback to mail()
            foreach ($addresses as $to) {
                @mail($to, $subject, $body, "From: noreply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            }
        } catch (\Throwable) {
            // Notification failure should not block submission
        }
    }
}
