<?php

declare(strict_types=1);

namespace App\Cms\Plugin;

use MonkeysLegion\DI\Attributes\Singleton;

/**
 * HookManager — Event & filter dispatch system for plugins.
 *
 * Plugins register listeners via:
 *   $hooks->on('content.after_save', callable $listener, int $priority = 0);
 *   $hooks->filter('admin.menu', callable $filter, int $priority = 0);
 *
 * Core dispatches hooks via:
 *   $hooks->dispatch('content.after_save', $node);
 *   $items = $hooks->applyFilters('admin.menu', $items);
 */
#[Singleton]
final class HookManager
{
    /**
     * @var array<string, list<array{callable: callable, priority: int}>>
     */
    private array $listeners = [];

    /**
     * @var array<string, list<array{callable: callable, priority: int}>>
     */
    private array $filters = [];

    // ── Events ─────────────────────────────────────────────────────────

    /**
     * Register an event listener.
     *
     * @param string   $hook     Hook name (e.g. "content.after_save")
     * @param callable $listener Callback to invoke
     * @param int      $priority Lower = earlier (default 0)
     */
    public function on(string $hook, callable $listener, int $priority = 0): void
    {
        $this->listeners[$hook][] = [
            'callable' => $listener,
            'priority' => $priority,
        ];
    }

    /**
     * Dispatch an event to all registered listeners.
     *
     * @param string $hook Hook name
     * @param mixed  ...$args Arguments passed to each listener
     */
    public function dispatch(string $hook, mixed ...$args): void
    {
        if (!isset($this->listeners[$hook])) {
            return;
        }

        $sorted = $this->listeners[$hook];
        usort($sorted, static fn(array $a, array $b): int => $a['priority'] <=> $b['priority']);

        foreach ($sorted as $entry) {
            ($entry['callable'])(...$args);
        }
    }

    /**
     * Check if any listeners are registered for a hook.
     */
    public function hasListeners(string $hook): bool
    {
        return !empty($this->listeners[$hook]);
    }

    // ── Filters ────────────────────────────────────────────────────────

    /**
     * Register a filter callback.
     *
     * Filters receive a value, may modify it, and MUST return it.
     *
     * @param string   $hook   Filter name (e.g. "admin.menu")
     * @param callable $filter fn(mixed $value, ...$args): mixed
     * @param int      $priority Lower = earlier
     */
    public function filter(string $hook, callable $filter, int $priority = 0): void
    {
        $this->filters[$hook][] = [
            'callable' => $filter,
            'priority' => $priority,
        ];
    }

    /**
     * Apply all registered filters to a value.
     *
     * @param string $hook  Filter name
     * @param mixed  $value The value to filter
     * @param mixed  ...$args Extra arguments passed to each filter
     * @return mixed The filtered value
     */
    public function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        if (!isset($this->filters[$hook])) {
            return $value;
        }

        $sorted = $this->filters[$hook];
        usort($sorted, static fn(array $a, array $b): int => $a['priority'] <=> $b['priority']);

        foreach ($sorted as $entry) {
            $value = ($entry['callable'])($value, ...$args);
        }

        return $value;
    }

    /**
     * Check if any filters are registered for a hook.
     */
    public function hasFilters(string $hook): bool
    {
        return !empty($this->filters[$hook]);
    }

    // ── Utility ────────────────────────────────────────────────────────

    /**
     * Remove all listeners and filters (useful for testing).
     */
    public function reset(): void
    {
        $this->listeners = [];
        $this->filters   = [];
    }

    /**
     * Get all registered hook names (events + filters).
     *
     * @return list<string>
     */
    public function getRegisteredHooks(): array
    {
        return array_unique([
            ...array_keys($this->listeners),
            ...array_keys($this->filters),
        ]);
    }
}
