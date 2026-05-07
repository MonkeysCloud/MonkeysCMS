<?php

declare(strict_types=1);

namespace App\Cms\Webform;

use App\Cms\I18n\TranslatableInterface;

/**
 * WebformEntity — Represents a webform definition.
 *
 * Uses PHP 8.4 property hooks for JSON field serialization.
 */
final class WebformEntity implements TranslatableInterface
{
    public ?int $id = null;
    public string $machine_name = '';
    public string $label = '';
    public ?string $description = null;
    public string $status = 'open'; // open | closed | scheduled
    public string $submit_label = 'Submit';
    public ?string $confirmation = null;
    public ?string $redirect_url = null;
    public ?int $max_submissions = null;
    public ?\DateTimeImmutable $open_at = null;
    public ?\DateTimeImmutable $close_at = null;
    public bool $recaptcha_enabled = false;
    public ?string $notify_emails = null;
    public string $language = 'en';
    public ?int $created_by = null;
    public ?\DateTimeImmutable $created_at = null;
    public ?\DateTimeImmutable $updated_at = null;

    /** @var int Runtime-only: populated by findAll() query */
    public int $_submissionCount = 0;
    /** @var int Runtime-only: populated by findAll() query */
    public int $_unreadCount = 0;

    /** @var list<array{name:string,type:string,label:string,...}> */
    private array $fieldsData = [];

    /** @var list<array{title:string,weight:int}> */
    private array $pagesData = [['title' => 'Page 1', 'weight' => 0]];

    /** @var array<string, mixed> */
    private array $settingsData = [];

    // ── Fields (JSON ↔ array) ───────────────────────────────────────────

    /** @return list<array> */
    public array $fields {
        get => $this->fieldsData;
        set(array $value) {
            $this->fieldsData = $value;
        }
    }

    /** @return list<array> */
    public array $pages {
        get => $this->pagesData;
        set(array $value) {
            $this->pagesData = $value;
        }
    }

    /** @return array<string, mixed> */
    public array $settings {
        get => $this->settingsData;
        set(array $value) {
            $this->settingsData = $value;
        }
    }

    // ── Computed Properties ──────────────────────────────────────────────

    public bool $isOpen {
        get {
            if ($this->status !== 'open' && $this->status !== 'scheduled') {
                return false;
            }
            $now = new \DateTimeImmutable();
            if ($this->open_at !== null && $now < $this->open_at) {
                return false;
            }
            if ($this->close_at !== null && $now > $this->close_at) {
                return false;
            }
            return true;
        }
    }

    public bool $isMultiPage {
        get => count($this->pagesData) > 1;
    }

    public int $pageCount {
        get => count($this->pagesData);
    }

    public int $fieldCount {
        get => count($this->fieldsData);
    }

    public string $statusBadge {
        get => match ($this->status) {
            'open' => '🟢',
            'closed' => '🔴',
            'scheduled' => '🟡',
            default => '⚪',
        };
    }

    /**
     * Get fields for a specific page (0-indexed).
     *
     * @return list<array>
     */
    public function getFieldsForPage(int $pageIndex): array
    {
        return array_values(array_filter(
            $this->fieldsData,
            fn(array $f) => ($f['page'] ?? 0) === $pageIndex,
        ));
    }

    /**
     * Get notify email addresses as an array.
     *
     * @return list<string>
     */
    public function getNotifyAddresses(): array
    {
        if (empty($this->notify_emails)) {
            return [];
        }
        return array_filter(array_map('trim', explode(',', $this->notify_emails)));
    }

    // ── Hydration ───────────────────────────────────────────────────────

    /**
     * Hydrate from a database row.
     *
     * @param array<string, mixed> $row
     */
    public function hydrate(array $row): static
    {
        $this->id = isset($row['id']) ? (int) $row['id'] : null;
        $this->machine_name = $row['machine_name'] ?? '';
        $this->label = $row['label'] ?? '';
        $this->description = $row['description'] ?? null;
        $this->status = $row['status'] ?? 'open';
        $this->submit_label = $row['submit_label'] ?? 'Submit';
        $this->confirmation = $row['confirmation'] ?? null;
        $this->redirect_url = $row['redirect_url'] ?? null;
        $this->max_submissions = isset($row['max_submissions']) ? (int) $row['max_submissions'] : null;
        $this->recaptcha_enabled = !empty($row['recaptcha_enabled']);
        $this->notify_emails = $row['notify_emails'] ?? null;
        $this->language = $row['language'] ?? 'en';
        $this->created_by = isset($row['created_by']) ? (int) $row['created_by'] : null;

        // JSON columns
        $this->fieldsData = is_string($row['fields'] ?? null)
            ? (json_decode($row['fields'], true) ?: [])
            : ($row['fields'] ?? []);

        $this->pagesData = is_string($row['pages'] ?? null)
            ? (json_decode($row['pages'], true) ?: [['title' => 'Page 1', 'weight' => 0]])
            : ($row['pages'] ?? [['title' => 'Page 1', 'weight' => 0]]);

        $this->settingsData = is_string($row['settings'] ?? null)
            ? (json_decode($row['settings'], true) ?: [])
            : ($row['settings'] ?? []);

        // Dates
        $this->open_at = !empty($row['open_at']) ? new \DateTimeImmutable($row['open_at']) : null;
        $this->close_at = !empty($row['close_at']) ? new \DateTimeImmutable($row['close_at']) : null;
        $this->created_at = !empty($row['created_at']) ? new \DateTimeImmutable($row['created_at']) : null;
        $this->updated_at = !empty($row['updated_at']) ? new \DateTimeImmutable($row['updated_at']) : null;

        return $this;
    }

    /**
     * Export to a JSON-serializable array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'machine_name' => $this->machine_name,
            'label' => $this->label,
            'description' => $this->description,
            'status' => $this->status,
            'fields' => $this->fieldsData,
            'pages' => $this->pagesData,
            'settings' => $this->settingsData,
            'confirmation' => $this->confirmation,
            'redirect_url' => $this->redirect_url,
            'submit_label' => $this->submit_label,
            'max_submissions' => $this->max_submissions,
            'open_at' => $this->open_at?->format('Y-m-d\TH:i'),
            'close_at' => $this->close_at?->format('Y-m-d\TH:i'),
            'recaptcha_enabled' => $this->recaptcha_enabled,
            'notify_emails' => $this->notify_emails,
            'field_count' => $this->fieldCount,
            'page_count' => $this->pageCount,
            'is_open' => $this->isOpen,
        ];
    }

    // ── TranslatableInterface ─────────────────────────────────────────

    public function getTranslatableType(): string { return 'webform'; }
    public function getTranslatableId(): int { return $this->id ?? 0; }
    public function getLanguage(): string { return $this->language; }
}
