<?php

declare(strict_types=1);

namespace App\Modules\Core\Entities;

use App\Cms\Attributes\ContentType;
use App\Cms\Attributes\Field;
use App\Cms\Attributes\Id;
use App\Cms\Core\BaseEntity;

/**
 * Block Entity - Reusable content blocks/widgets
 */
#[ContentType(
    tableName: 'blocks',
    label: 'Block',
    description: 'Reusable content blocks and widgets',
    icon: '🧱',
    revisionable: true,
    publishable: true
)]
class Block extends BaseEntity
{
    #[Id(strategy: 'auto')]
    public ?int $id = null;

    #[Field(type: 'string', label: 'Admin Title', required: true, length: 255, searchable: true)]
    public string $admin_title = '';

    #[Field(type: 'string', label: 'Machine Name', required: true, length: 100, unique: true)]
    public string $machine_name = '';

    #[Field(type: 'string', label: 'Display Title', required: false, length: 255)]
    public ?string $title = null;

    #[Field(type: 'boolean', label: 'Show Title', default: true)]
    public bool $show_title = true;

    #[Field(
        type: 'string',
        label: 'Block Type',
        required: true,
        length: 50,
        widget: 'select',
        options: [
            'content' => 'Content Block',
            'html' => 'HTML Block',
            'view' => 'View/Template Block',
            'menu' => 'Menu Block',
            'form' => 'Form Block',
            'custom' => 'Custom Block'
        ]
    )]
    public string $block_type = 'content';

    #[Field(type: 'text', label: 'Body', required: false, widget: 'wysiwyg')]
    public ?string $body = null;

    #[Field(
        type: 'string',
        label: 'Body Format',
        required: false,
        length: 50,
        default: 'html',
        widget: 'select',
        options: ['html' => 'HTML', 'markdown' => 'Markdown', 'plain' => 'Plain Text']
    )]
    public string $body_format = 'html';

    #[Field(type: 'string', label: 'View Template', required: false, length: 255)]
    public ?string $view_template = null;

    #[Field(type: 'string', label: 'Region', required: false, length: 100, indexed: true)]
    public ?string $region = null;

    #[Field(type: 'string', label: 'Theme', required: false, length: 100)]
    public ?string $theme = null;

    #[Field(type: 'int', label: 'Weight', default: 0, indexed: true)]
    public int $weight = 0;

    #[Field(type: 'boolean', label: 'Published', default: true)]
    public bool $is_published = true;

    #[Field(type: 'json', label: 'Visibility Pages', default: [])]
    public array $visibility_pages = [];

    #[Field(
        type: 'string',
        label: 'Visibility Mode',
        default: 'show',
        widget: 'select',
        options: ['show' => 'Show on listed pages', 'hide' => 'Hide on listed pages', 'all' => 'All pages']
    )]
    public string $visibility_mode = 'all';

    #[Field(type: 'json', label: 'Visibility Roles', default: [])]
    public array $visibility_roles = [];

    #[Field(type: 'json', label: 'Settings', default: [])]
    public array $settings = [];

    #[Field(type: 'string', label: 'CSS Classes', required: false, length: 255)]
    public ?string $css_class = null;

    #[Field(type: 'string', label: 'CSS ID', required: false, length: 100)]
    public ?string $css_id = null;

    #[Field(type: 'int', label: 'Author ID', required: false, indexed: true)]
    public ?int $author_id = null;

    #[Field(type: 'datetime', label: 'Created At')]
    public ?\DateTimeImmutable $created_at = null;

    #[Field(type: 'datetime', label: 'Updated At')]
    public ?\DateTimeImmutable $updated_at = null;

    public function prePersist(): void
    {
        parent::prePersist();
        if (empty($this->machine_name)) {
            // Generate UUID v4
            $data = random_bytes(16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
            $this->machine_name = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        }
    }

    /**
     * Check if block should be visible on a given path
     */
    public function isVisibleOnPath(string $path): bool
    {
        if (!$this->is_published) {
            return false;
        }

        if ($this->visibility_mode === 'all' || empty($this->visibility_pages)) {
            return true;
        }

        $matches = false;
        foreach ($this->visibility_pages as $pattern) {
            if ($this->pathMatches($path, $pattern)) {
                $matches = true;
                break;
            }
        }

        return $this->visibility_mode === 'show' ? $matches : !$matches;
    }

    /**
     * Check if block is visible for a user
     */
    public function isVisibleForUser(?User $user = null): bool
    {
        if (empty($this->visibility_roles)) {
            return true;
        }

        if ($user === null) {
            return in_array('anonymous', $this->visibility_roles, true);
        }

        foreach ($this->visibility_roles as $roleSlug) {
            if ($user->hasRole($roleSlug)) {
                return true;
            }
        }

        return false;
    }

    private function pathMatches(string $path, string $pattern): bool
    {
        $path = '/' . ltrim($path, '/');
        $pattern = '/' . ltrim($pattern, '/');

        if ($pattern === $path) {
            return true;
        }

        // Support wildcards
        if (str_contains($pattern, '*')) {
            $regex = '/^' . str_replace(['*', '/'], ['.*', '\/'], $pattern) . '$/';
            return (bool) preg_match($regex, $path);
        }

        return false;
    }

    /**
     * Get rendered content
     */
    public function getRenderedBody(): string
    {
        if ($this->body === null) {
            return '';
        }

        return match ($this->body_format) {
            'markdown' => $this->parseMarkdown($this->body),
            'plain' => nl2br(htmlspecialchars($this->body)),
            default => $this->body,
        };
    }

    private function parseMarkdown(string $text): string
    {
        // Basic markdown parsing - in production, use a library like Parsedown
        $text = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $text);
        $text = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $text);
        $text = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $text);
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $text);
        return nl2br($text);
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function setSetting(string $key, mixed $value): void
    {
        $this->settings[$key] = $value;
    }
}
