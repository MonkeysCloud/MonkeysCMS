<?php

declare(strict_types=1);

namespace App\Cms\Fields;

use App\Cms\Fields\Form\FormBuilder;
use App\Cms\Fields\Validation\FieldValidator;
use App\Cms\Fields\Widget\WidgetFactory;
use App\Cms\Fields\Widget\WidgetRegistry;
use Psr\Container\ContainerInterface;

/**
 * FieldServiceProvider - Registers field-related services in the container
 *
 * This provider sets up the field widget system with all its dependencies:
 * - FieldValidator - For validating field values
 * - WidgetRegistry - For managing widgets
 * - FormBuilder - For building forms
 */
final class FieldServiceProvider
{
    /**
     * Get service definitions for the container
     *
     * @return array<string, callable>
     */
    public static function getDefinitions(): array
    {
        return [
            // Field Validator
            FieldValidator::class => function (): FieldValidator {
                return new FieldValidator();
            },

            // Widget Registry (with all core widgets registered)
            WidgetRegistry::class => function (ContainerInterface $c): WidgetRegistry {
                $validator = $c->get(FieldValidator::class);
                return WidgetFactory::create($validator);
            },

            // Form Builder
            FormBuilder::class => function (ContainerInterface $c): FormBuilder {
                $registry = $c->get(WidgetRegistry::class);
                return new FormBuilder($registry);
            },
        ];
    }

    /**
     * Register services with a container builder callback
     */
    public static function register(callable $addDefinition): void
    {
        foreach (self::getDefinitions() as $id => $factory) {
            $addDefinition($id, $factory);
        }
    }

    /**
     * Get a configured WidgetRegistry (for use without DI container)
     */
    public static function createWidgetRegistry(): WidgetRegistry
    {
        return WidgetFactory::create();
    }

    /**
     * Get a configured FormBuilder (for use without DI container)
     */
    public static function createFormBuilder(?WidgetRegistry $registry = null): FormBuilder
    {
        return new FormBuilder($registry ?? self::createWidgetRegistry());
    }
}
