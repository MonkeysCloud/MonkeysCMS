<?php

declare(strict_types=1);

namespace App\Cms\Field;

/**
 * RenderMode — Determines the rendering context for field widgets.
 *
 * Widgets use this to output the appropriate HTML:
 *   - ADMIN_FORM: Standard admin content/taxonomy form (POST-based)
 *   - MOSAIC_INSPECTOR: Mosaic visual editor sidebar (JS callback-based)
 *   - API: JSON API output (serialization, no HTML)
 */
enum RenderMode: string
{
    case ADMIN_FORM = 'admin_form';
    case MOSAIC_INSPECTOR = 'mosaic_inspector';
    case API = 'api';
}
