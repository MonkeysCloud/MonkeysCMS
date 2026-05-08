<?php

declare(strict_types=1);

namespace App\Cms\I18n;

/**
 * LanguageEntity — Represents a configured language.
 *
 * Uses PHP 8.4 property hooks for computed display properties.
 */
final class LanguageEntity
{
    public string $code = '';
    public string $label = '';
    public string $native = '';
    public string $direction = 'ltr';
    public bool $enabled = false;
    public bool $is_default = false;
    public int $weight = 0;
    public ?\DateTimeImmutable $created_at = null;

    // ── Computed properties ──────────────────────────────────────────

    public bool $isRtl {
        get => $this->direction === 'rtl';
    }

    public string $flagEmoji {
        get {
            $code = strtoupper($this->code);
            if (strlen($code) < 2) return '🌐';
            // Map language codes to country codes for flags
            $map = [
                'EN' => 'US', 'ES' => 'ES', 'FR' => 'FR', 'DE' => 'DE',
                'PT' => 'BR', 'IT' => 'IT', 'JA' => 'JP', 'ZH' => 'CN',
                'KO' => 'KR', 'AR' => 'SA', 'RU' => 'RU', 'NL' => 'NL',
                'PL' => 'PL', 'SV' => 'SE', 'TR' => 'TR', 'HI' => 'IN',
                'HE' => 'IL', 'TH' => 'TH', 'VI' => 'VN', 'UK' => 'UA',
            ];
            $cc = $map[$code] ?? $code;
            return mb_chr(0x1F1E6 + ord($cc[0]) - ord('A'))
                 . mb_chr(0x1F1E6 + ord($cc[1]) - ord('A'));
        }
    }

    public string $displayName {
        get => $this->flagEmoji . ' ' . $this->native . ' (' . $this->code . ')';
    }

    public string $statusBadge {
        get {
            if ($this->is_default) return 'badge--primary';
            return $this->enabled ? 'badge--success' : 'badge--muted';
        }
    }

    public string $statusLabel {
        get {
            if ($this->is_default) return 'Default';
            return $this->enabled ? 'Enabled' : 'Disabled';
        }
    }

    // ── Hydration ────────────────────────────────────────────────────

    public function hydrate(array $row): static
    {
        $this->code = $row['code'] ?? '';
        $this->label = $row['label'] ?? '';
        $this->native = $row['native'] ?? '';
        $this->direction = $row['direction'] ?? 'ltr';
        $this->enabled = !empty($row['enabled']);
        $this->is_default = !empty($row['is_default']);
        $this->weight = (int) ($row['weight'] ?? 0);
        $this->created_at = !empty($row['created_at']) ? new \DateTimeImmutable($row['created_at']) : null;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'code'       => $this->code,
            'label'      => $this->label,
            'native'     => $this->native,
            'direction'  => $this->direction,
            'enabled'    => $this->enabled,
            'is_default' => $this->is_default,
            'weight'     => $this->weight,
            'flag'       => $this->flagEmoji,
            'is_rtl'     => $this->isRtl,
        ];
    }
}
