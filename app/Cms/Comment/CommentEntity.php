<?php

declare(strict_types=1);

namespace App\Cms\Comment;

/**
 * CommentEntity — Represents a single comment on a content node.
 */
final class CommentEntity
{
    public ?int $id = null;
    public int $node_id = 0;
    public ?int $parent_id = null;
    public string $author_name = '';
    public string $author_email = '';
    public ?string $author_url = null;
    public ?int $author_id = null;
    public string $body = '';
    public string $status = 'pending';
    public ?string $ip_address = null;
    public ?string $user_agent = null;
    public ?\DateTimeImmutable $created_at = null;
    public ?\DateTimeImmutable $updated_at = null;

    /** @var CommentEntity[] */
    public array $children = [];

    // ── Computed Properties ────────────────────────────────────────────

    /**
     * Gravatar URL based on author email.
     */
    public string $gravatar {
        get {
            $hash = md5(strtolower(trim($this->author_email)));
            return "https://www.gravatar.com/avatar/{$hash}?s=48&d=mp";
        }
    }

    /**
     * Human-readable relative time.
     */
    public string $timeAgo {
        get {
            if (!$this->created_at) {
                return '';
            }
            $diff = time() - $this->created_at->getTimestamp();
            return match (true) {
                $diff < 60       => $diff . 's ago',
                $diff < 3600     => floor($diff / 60) . 'm ago',
                $diff < 86400    => floor($diff / 3600) . 'h ago',
                $diff < 604800   => floor($diff / 86400) . 'd ago',
                $diff < 2592000  => floor($diff / 604800) . 'w ago',
                default          => $this->created_at->format('M j, Y'),
            };
        }
    }

    /**
     * Formatted body (basic sanitization + nl2br).
     */
    public string $formattedBody {
        get => nl2br(htmlspecialchars($this->body, ENT_QUOTES, 'UTF-8'));
    }

    /**
     * Status badge CSS class.
     */
    public string $statusBadge {
        get => match ($this->status) {
            'approved' => 'badge--success',
            'pending'  => 'badge--warning',
            'spam'     => 'badge--danger',
            'trashed'  => 'badge--ghost',
            default    => 'badge--ghost',
        };
    }

    // ── Hydration ──────────────────────────────────────────────────────

    public function hydrate(array $row): self
    {
        $this->id = isset($row['id']) ? (int) $row['id'] : null;
        $this->node_id = (int) ($row['node_id'] ?? 0);
        $this->parent_id = isset($row['parent_id']) ? (int) $row['parent_id'] : null;
        $this->author_name = $row['author_name'] ?? '';
        $this->author_email = $row['author_email'] ?? '';
        $this->author_url = $row['author_url'] ?? null;
        $this->author_id = isset($row['author_id']) ? (int) $row['author_id'] : null;
        $this->body = $row['body'] ?? '';
        $this->status = $row['status'] ?? 'pending';
        $this->ip_address = $row['ip_address'] ?? null;
        $this->user_agent = $row['user_agent'] ?? null;
        $this->created_at = !empty($row['created_at'])
            ? new \DateTimeImmutable($row['created_at'])
            : null;
        $this->updated_at = !empty($row['updated_at'])
            ? new \DateTimeImmutable($row['updated_at'])
            : null;

        // From JOINs
        if (isset($row['node_title'])) {
            $this->nodeTitle = $row['node_title'];
        }
        if (isset($row['node_slug'])) {
            $this->nodeSlug = $row['node_slug'];
        }
        if (isset($row['content_type'])) {
            $this->contentType = $row['content_type'];
        }

        return $this;
    }

    // Joined fields (not stored in entity)
    public string $nodeTitle = '';
    public string $nodeSlug = '';
    public string $contentType = '';
}
