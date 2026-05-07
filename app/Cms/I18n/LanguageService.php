<?php

declare(strict_types=1);

namespace App\Cms\I18n;

use App\Cms\Service\SettingsService;
use PDO;

/**
 * LanguageService — Manage CMS languages.
 *
 * The multilingual module is toggleable via the settings table
 * (multilingual_enabled). When disabled, all methods return
 * sensible defaults (only the default language).
 */
final class LanguageService
{
    /** @var list<LanguageEntity>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly SettingsService $settings,
    ) {}

    // ═══════════════════════════════════════════════════════════════════════
    // Module Toggle
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Check if the multilingual module is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->settings->get('multilingual_enabled', '0') === '1';
    }

    /**
     * Enable or disable the multilingual module.
     */
    public function setEnabled(bool $enabled): void
    {
        $this->settings->set('multilingual_enabled', $enabled ? '1' : '0', 'modules');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Language CRUD
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Get all languages (enabled + disabled), ordered by weight.
     *
     * @return list<LanguageEntity>
     */
    public function getAll(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $stmt = $this->pdo->query(
            'SELECT * FROM languages ORDER BY is_default DESC, weight ASC, code ASC'
        );

        $this->cache = array_map(
            fn(array $row) => (new LanguageEntity())->hydrate($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC),
        );

        return $this->cache;
    }

    /**
     * Get only enabled languages.
     *
     * @return list<LanguageEntity>
     */
    public function getEnabled(): array
    {
        return array_values(array_filter(
            $this->getAll(),
            fn(LanguageEntity $l) => $l->enabled,
        ));
    }

    /**
     * Get the default language.
     */
    public function getDefault(): LanguageEntity
    {
        foreach ($this->getAll() as $lang) {
            if ($lang->is_default) {
                return $lang;
            }
        }

        // Fallback: first enabled or first overall
        $enabled = $this->getEnabled();
        return $enabled[0] ?? $this->getAll()[0];
    }

    /**
     * Get the default language code.
     */
    public function getDefaultCode(): string
    {
        return $this->getDefault()->code;
    }

    /**
     * Find a language by code.
     */
    public function find(string $code): ?LanguageEntity
    {
        foreach ($this->getAll() as $lang) {
            if ($lang->code === $code) {
                return $lang;
            }
        }
        return null;
    }

    /**
     * Enable a language.
     */
    public function enable(string $code): void
    {
        $this->pdo->prepare('UPDATE languages SET enabled = 1 WHERE code = :code')
            ->execute(['code' => $code]);
        $this->clearCache();
    }

    /**
     * Disable a language (cannot disable the default).
     */
    public function disable(string $code): void
    {
        $lang = $this->find($code);
        if ($lang?->is_default) {
            throw new \RuntimeException('Cannot disable the default language.');
        }

        $this->pdo->prepare('UPDATE languages SET enabled = 0 WHERE code = :code')
            ->execute(['code' => $code]);
        $this->clearCache();
    }

    /**
     * Set a language as default (auto-enables it).
     */
    public function setDefault(string $code): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('UPDATE languages SET is_default = 0');
            $this->pdo->prepare('UPDATE languages SET is_default = 1, enabled = 1 WHERE code = :code')
                ->execute(['code' => $code]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        $this->clearCache();
    }

    /**
     * Update weight (sort order).
     */
    public function updateWeight(string $code, int $weight): void
    {
        $this->pdo->prepare('UPDATE languages SET weight = :w WHERE code = :code')
            ->execute(['code' => $code, 'w' => $weight]);
        $this->clearCache();
    }

    /**
     * Batch update weights.
     *
     * @param array<string, int> $weights  [code => weight, ...]
     */
    public function reorder(array $weights): void
    {
        $stmt = $this->pdo->prepare('UPDATE languages SET weight = :w WHERE code = :code');
        foreach ($weights as $code => $weight) {
            $stmt->execute(['code' => $code, 'w' => (int) $weight]);
        }
        $this->clearCache();
    }

    /**
     * Add a custom language.
     */
    public function add(string $code, string $label, string $native, string $direction = 'ltr'): void
    {
        $maxWeight = (int) $this->pdo->query('SELECT MAX(weight) FROM languages')->fetchColumn();
        $this->pdo->prepare(
            'INSERT INTO languages (code, label, native, direction, weight) VALUES (:code, :label, :native, :dir, :w)'
        )->execute([
            'code'   => $code,
            'label'  => $label,
            'native' => $native,
            'dir'    => $direction,
            'w'      => $maxWeight + 1,
        ]);
        $this->clearCache();
    }

    /**
     * Get enabled language codes as a simple array.
     *
     * @return list<string>
     */
    public function getEnabledCodes(): array
    {
        return array_map(fn(LanguageEntity $l) => $l->code, $this->getEnabled());
    }

    private function clearCache(): void
    {
        $this->cache = null;
    }
}
