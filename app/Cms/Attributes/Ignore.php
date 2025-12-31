<?php

declare(strict_types=1);

namespace App\Cms\Attributes;

use Attribute;

/**
 * Attribute to mark a property as ignored by the persistence layer
 * 
 * Properties marked with this attribute will not be:
 * - Hydrated from the database
 * - Saved to the database (skipped in toArray/getDirtyFields)
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Ignore
{
}
