<?php

declare(strict_types=1);

namespace App\Cms\Provider;

use App\Cms\Form\FormBuilder;
use App\Cms\Form\FormRenderer;
use App\Cms\Form\FormSecurityMiddleware;
use MonkeysLegion\Session\SessionManager;
use Psr\Container\ContainerInterface;

/**
 * FormProvider — Registers Form Engine services in the DI container.
 */
final class FormProvider
{
    /**
     * @return array<string, callable>
     */
    public static function getDefinitions(): array
    {
        return [
            FormRenderer::class => fn(ContainerInterface $c): FormRenderer
                => new FormRenderer(),

            FormSecurityMiddleware::class => fn(ContainerInterface $c): FormSecurityMiddleware
                => new FormSecurityMiddleware(
                    $c->get(SessionManager::class),
                ),
        ];
    }
}
