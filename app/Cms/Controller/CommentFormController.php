<?php

declare(strict_types=1);

namespace App\Cms\Controller;

use App\Cms\Comment\CommentEntity;
use App\Cms\Comment\CommentService;
use App\Cms\Service\SettingsService;
use MonkeysLegion\Http\Message\Response;
use MonkeysLegion\Router\Attributes\Route;
use Psr\Http\Message\ServerRequestInterface;

/**
 * CommentFormController — Handles public comment submissions.
 */
final class CommentFormController
{
    public function __construct(
        private readonly CommentService $commentService,
        private readonly SettingsService $settingsService,
    ) {}

    #[Route('POST', '/comments', name: 'front.comments.store')]
    public function store(ServerRequestInterface $request): Response
    {
        $body = $request->getParsedBody() ?? [];

        // Check global comments enabled
        if (!(bool) $this->settingsService->get('enable_comments', '0')) {
            return Response::json(['success' => false, 'error' => 'Comments are disabled.'], 403);
        }

        // Honeypot check
        if (!empty($body['website_url'])) {
            // Silently accept but don't save (looks successful to bots)
            return Response::json(['success' => true, 'message' => 'Comment submitted.']);
        }

        // Check if login is required
        $session = $request->getAttribute('session');
        $cmsUserId = $session ? $session->get('cms_user_id') : null;
        if ((bool) $this->settingsService->get('comments_require_login', '0') && !$cmsUserId) {
            return Response::json(['success' => false, 'error' => 'You must be logged in to comment.'], 403);
        }

        // Validate required fields
        $nodeId = (int) ($body['node_id'] ?? 0);
        $authorName = trim($body['author_name'] ?? '');
        $authorEmail = trim($body['author_email'] ?? '');
        $commentBody = trim($body['body'] ?? '');
        $parentId = !empty($body['parent_id']) ? (int) $body['parent_id'] : null;

        if ($nodeId === 0) {
            return Response::json(['success' => false, 'error' => 'Invalid content.'], 400);
        }

        if ($authorName === '' || mb_strlen($authorName) > 100) {
            return Response::json(['success' => false, 'error' => 'Please enter a valid name.'], 422);
        }

        if ($authorEmail === '' || !filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) {
            return Response::json(['success' => false, 'error' => 'Please enter a valid email.'], 422);
        }

        if ($commentBody === '' || mb_strlen($commentBody) < 3) {
            return Response::json(['success' => false, 'error' => 'Comment is too short.'], 422);
        }

        if (mb_strlen($commentBody) > 5000) {
            return Response::json(['success' => false, 'error' => 'Comment is too long (5000 chars max).'], 422);
        }

        // Rate limiting (skip for authenticated CMS users)
        $ip = $this->getClientIp($request);
        if (!$cmsUserId && $this->commentService->isRateLimited($ip, 60)) {
            return Response::json(['success' => false, 'error' => 'Please wait before posting another comment.'], 429);
        }

        // Check threaded replies enabled
        if ($parentId && !(bool) $this->settingsService->get('comments_threaded', '1')) {
            $parentId = null;
        }

        // Verify parent comment exists and belongs to same node
        if ($parentId) {
            $parentComment = $this->commentService->find($parentId);
            if (!$parentComment || $parentComment->node_id !== $nodeId) {
                $parentId = null;
            }
        }

        // Build comment entity
        $comment = new CommentEntity();
        $comment->node_id = $nodeId;
        $comment->parent_id = $parentId;
        $comment->author_name = $authorName;
        $comment->author_email = strtolower($authorEmail);
        $comment->author_url = null;
        $comment->body = $commentBody;
        $comment->ip_address = $ip;
        $comment->user_agent = $request->getHeaderLine('User-Agent');

        // Check if authenticated CMS user
        if ($cmsUserId) {
            $comment->author_id = (int) $cmsUserId;
        }

        // Determine initial status
        $requireModeration = (bool) $this->settingsService->get('comments_moderation', '1');
        if ($this->commentService->isLikelySpam($comment)) {
            $comment->status = 'spam';
        } elseif ($requireModeration) {
            $comment->status = 'pending';
        } else {
            $comment->status = 'approved';
        }

        // Save
        $this->commentService->create($comment);

        $message = match ($comment->status) {
            'approved' => 'Comment posted successfully!',
            'pending'  => 'Comment submitted! It will appear after moderation.',
            'spam'     => 'Comment submitted! It will appear after moderation.',
            default    => 'Comment submitted.',
        };

        return Response::json(['success' => true, 'message' => $message]);
    }

    private function getClientIp(ServerRequestInterface $request): string
    {
        $headers = ['X-Forwarded-For', 'X-Real-IP', 'CF-Connecting-IP'];
        foreach ($headers as $header) {
            $val = $request->getHeaderLine($header);
            if ($val !== '') {
                return explode(',', $val)[0];
            }
        }

        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
