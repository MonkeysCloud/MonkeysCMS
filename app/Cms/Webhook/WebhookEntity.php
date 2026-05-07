<?php

declare(strict_types=1);

namespace App\Cms\Webhook;

/**
 * WebhookEntity — Represents a registered webhook endpoint.
 *
 * Uses PHP 8.4 property hooks for URL validation and event normalization.
 * Hydrated from the `webhooks` table via WebhookService.
 */
final class WebhookEntity
{
    public ?int $id = null;

    public string $name = '';

    public string $url = '' {
        set(string $value) {
            $value = trim($value);
            if ($value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException("Invalid webhook URL: {$value}");
            }
            $this->url = $value;
        }
    }

    /** @var list<string> Subscribed event names */
    public array $events = [] {
        set(array $value) {
            $this->events = array_values(array_unique(array_filter(
                array_map('trim', $value),
                fn(string $e) => $e !== '',
            )));
        }
    }

    public string $secret = '';

    public bool $is_active = true;

    public ?\DateTimeImmutable $last_triggered_at = null;

    public int $failure_count = 0;

    public ?int $created_by = null;

    public ?\DateTimeImmutable $created_at = null;

    public ?\DateTimeImmutable $updated_at = null;

    // ── Aggregate (populated by service queries, not stored) ───────────

    /** Total delivery log entries */
    public int $_logCount = 0;

    /** Failed delivery count */
    public int $_failedCount = 0;

    // ── Computed Properties ────────────────────────────────────────────

    /** Whether this webhook has been auto-disabled due to failures */
    public bool $isAutoDisabled {
        get => !$this->is_active && $this->failure_count >= 10;
    }

    /** Human-readable event list */
    public string $eventsSummary {
        get => implode(', ', $this->events);
    }

    // ── Hydration ──────────────────────────────────────────────────────

    /**
     * Hydrate entity from a database row.
     */
    public function hydrate(array $row): self
    {
        $this->id                = isset($row['id']) ? (int) $row['id'] : null;
        $this->name              = (string) ($row['name'] ?? '');
        $this->url               = (string) ($row['url'] ?? '');
        $this->events            = is_string($row['events'] ?? null)
            ? (json_decode($row['events'], true) ?: [])
            : ($row['events'] ?? []);
        $this->secret            = (string) ($row['secret'] ?? '');
        $this->is_active         = (bool) ($row['is_active'] ?? true);
        $this->last_triggered_at = !empty($row['last_triggered_at'])
            ? new \DateTimeImmutable($row['last_triggered_at'])
            : null;
        $this->failure_count     = (int) ($row['failure_count'] ?? 0);
        $this->created_by        = isset($row['created_by']) ? (int) $row['created_by'] : null;
        $this->created_at        = !empty($row['created_at'])
            ? new \DateTimeImmutable($row['created_at'])
            : null;
        $this->updated_at        = !empty($row['updated_at'])
            ? new \DateTimeImmutable($row['updated_at'])
            : null;

        return $this;
    }

    /**
     * Check if this webhook is subscribed to a given event.
     */
    public function subscribedTo(string $event): bool
    {
        // Support wildcard: "node.*" matches "node.created"
        foreach ($this->events as $e) {
            if ($e === $event) {
                return true;
            }
            if (str_ends_with($e, '.*')) {
                $prefix = substr($e, 0, -1); // "node."
                if (str_starts_with($event, $prefix)) {
                    return true;
                }
            }
        }
        return false;
    }
}
