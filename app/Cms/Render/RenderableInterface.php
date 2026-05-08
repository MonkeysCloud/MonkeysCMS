<?php

declare(strict_types=1);

namespace App\Cms\Render;

use MonkeysLegion\Template\Renderer;

interface RenderableInterface
{
    /**
     * Render the component using the provided renderer.
     */
    public function render(Renderer $renderer): string;
}
