<?php

declare(strict_types=1);

namespace App\Cms\Content;

/**
 * LockResult — Outcome of a lock acquisition attempt.
 */
enum LockResult: string
{
    case ACQUIRED       = 'acquired';
    case RENEWED        = 'renewed';
    case LOCKED_BY_OTHER = 'locked';
}
