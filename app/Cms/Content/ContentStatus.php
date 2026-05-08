<?php

declare(strict_types=1);

namespace App\Cms\Content;

/**
 * ContentStatus — Enum for content publication state.
 *
 * Provides labels, badges, and query helpers via PHP 8.4 backed enum.
 * Extended with editorial workflow states (needs_review, in_review).
 */
enum ContentStatus: string
{
    case DRAFT        = 'draft';
    case NEEDS_REVIEW = 'needs_review';
    case IN_REVIEW    = 'in_review';
    case PUBLISHED    = 'published';
    case ARCHIVED     = 'archived';
    case SCHEDULED    = 'scheduled';

    /**
     * Human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT        => 'Draft',
            self::NEEDS_REVIEW => 'Needs Review',
            self::IN_REVIEW    => 'In Review',
            self::PUBLISHED    => 'Published',
            self::ARCHIVED     => 'Archived',
            self::SCHEDULED    => 'Scheduled',
        };
    }

    /**
     * CSS badge class for admin UI
     */
    public function badge(): string
    {
        return match ($this) {
            self::DRAFT        => 'badge--draft',
            self::NEEDS_REVIEW => 'badge--warning',
            self::IN_REVIEW    => 'badge--info',
            self::PUBLISHED    => 'badge--success',
            self::ARCHIVED     => 'badge--muted',
            self::SCHEDULED    => 'badge--info',
        };
    }

    /**
     * Lucide icon name
     */
    public function icon(): string
    {
        return match ($this) {
            self::DRAFT        => 'pencil-line',
            self::NEEDS_REVIEW => 'send',
            self::IN_REVIEW    => 'eye',
            self::PUBLISHED    => 'check-circle',
            self::ARCHIVED     => 'archive',
            self::SCHEDULED    => 'clock',
        };
    }

    /**
     * Whether content in this status is visible to the public
     */
    public function isPublic(): bool
    {
        return $this === self::PUBLISHED;
    }

    /**
     * Whether this status is part of the editorial workflow
     */
    public function isEditorial(): bool
    {
        return in_array($this, [self::NEEDS_REVIEW, self::IN_REVIEW], true);
    }

    /**
     * Statuses available for selection in the admin form
     *
     * @return list<self>
     */
    public static function formOptions(): array
    {
        return [self::DRAFT, self::NEEDS_REVIEW, self::IN_REVIEW, self::PUBLISHED, self::ARCHIVED, self::SCHEDULED];
    }
}
