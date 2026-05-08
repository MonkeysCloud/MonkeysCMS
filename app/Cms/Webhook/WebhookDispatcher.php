<?php

declare(strict_types=1);

namespace App\Cms\Webhook;

use MonkeysLegion\DI\Attributes\Singleton;

/**
 * WebhookDispatcher — Bridge between CMS operations and the webhook system.
 *
 * Called from controllers/services after mutating actions to fire
 * the corresponding webhook events. Designed as a thin facade so
 * dispatch calls remain one-liners in existing code.
 *
 * Usage:
 *   $this->webhooks->fireNodeCreated($nodeId, $title, $contentType);
 *   $this->webhooks->fireMediaUploaded($mediaId, $filename);
 */
#[Singleton]
final class WebhookDispatcher
{
    public function __construct(
        private readonly WebhookService $service,
    ) {}

    // ── Content Events ─────────────────────────────────────────────────

    public function fireNodeCreated(int $nodeId, string $title, string $contentType): void
    {
        $this->fire('node.created', [
            'node_id'      => $nodeId,
            'title'        => $title,
            'content_type' => $contentType,
        ]);
    }

    public function fireNodeUpdated(int $nodeId, string $title, string $contentType, array $changedFields = []): void
    {
        $this->fire('node.updated', [
            'node_id'        => $nodeId,
            'title'          => $title,
            'content_type'   => $contentType,
            'changed_fields' => $changedFields,
        ]);
    }

    public function fireNodePublished(int $nodeId, string $title, string $contentType): void
    {
        $this->fire('node.published', [
            'node_id'      => $nodeId,
            'title'        => $title,
            'content_type' => $contentType,
        ]);
    }

    public function fireNodeUnpublished(int $nodeId, string $title, string $contentType): void
    {
        $this->fire('node.unpublished', [
            'node_id'      => $nodeId,
            'title'        => $title,
            'content_type' => $contentType,
        ]);
    }

    public function fireNodeDeleted(int $nodeId, string $title, string $contentType): void
    {
        $this->fire('node.deleted', [
            'node_id'      => $nodeId,
            'title'        => $title,
            'content_type' => $contentType,
        ]);
    }

    // ── Media Events ───────────────────────────────────────────────────

    public function fireMediaUploaded(int $mediaId, string $filename, string $mimeType): void
    {
        $this->fire('media.uploaded', [
            'media_id'  => $mediaId,
            'filename'  => $filename,
            'mime_type' => $mimeType,
        ]);
    }

    public function fireMediaDeleted(int $mediaId, string $filename): void
    {
        $this->fire('media.deleted', [
            'media_id' => $mediaId,
            'filename' => $filename,
        ]);
    }

    // ── User Events ────────────────────────────────────────────────────

    public function fireUserCreated(int $userId, string $name, string $email): void
    {
        $this->fire('user.created', [
            'user_id' => $userId,
            'name'    => $name,
            'email'   => $email,
        ]);
    }

    public function fireUserUpdated(int $userId, string $name, array $changedFields = []): void
    {
        $this->fire('user.updated', [
            'user_id'        => $userId,
            'name'           => $name,
            'changed_fields' => $changedFields,
        ]);
    }

    // ── Comment Events ─────────────────────────────────────────────────

    public function fireCommentCreated(int $commentId, int $nodeId, ?string $author): void
    {
        $this->fire('comment.created', [
            'comment_id' => $commentId,
            'node_id'    => $nodeId,
            'author'     => $author,
        ]);
    }

    public function fireCommentApproved(int $commentId, int $nodeId): void
    {
        $this->fire('comment.approved', [
            'comment_id' => $commentId,
            'node_id'    => $nodeId,
        ]);
    }

    // ── Form Events ────────────────────────────────────────────────────

    public function fireFormSubmitted(int $webformId, string $formName, int $submissionId): void
    {
        $this->fire('form.submitted', [
            'webform_id'    => $webformId,
            'form_name'     => $formName,
            'submission_id' => $submissionId,
        ]);
    }

    // ── Core ───────────────────────────────────────────────────────────

    /**
     * Fire an event — delegates to WebhookService::dispatch().
     * Silently catches exceptions so webhook failures never break the main operation.
     */
    private function fire(string $event, array $data): void
    {
        try {
            $this->service->dispatch($event, $data);
        } catch (\Throwable) {
            // Never let webhook delivery break the main request
        }
    }
}
