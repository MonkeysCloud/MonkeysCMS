<?php

declare(strict_types=1);

namespace App\Cms\Fields\Value;

/**
 * TransformerFactory - Creates transformers for field types
 */
final class TransformerFactory
{
    /** @var array<string, ValueTransformerInterface> */
    private array $cache = [];

    public function create(string $fieldType, array $settings = []): ValueTransformerInterface
    {
        $key = $fieldType . ':' . md5(serialize($settings));

        if (!isset($this->cache[$key])) {
            $this->cache[$key] = $this->buildTransformer($fieldType, $settings);
        }

        return $this->cache[$key];
    }

    private function buildTransformer(string $fieldType, array $settings): ValueTransformerInterface
    {
        return match ($fieldType) {
            'string', 'text', 'textarea', 'email', 'url', 'phone', 'slug' => new StringTransformer(),
            'integer' => new IntegerTransformer(),
            'float', 'decimal' => new FloatTransformer(
                decimals: $settings['decimals'] ?? 2
            ),
            'boolean' => new BooleanTransformer(
                trueLabel: $settings['on_label'] ?? 'Yes',
                falseLabel: $settings['off_label'] ?? 'No'
            ),
            'date' => new DateTransformer(
                displayFormat: $settings['display_format'] ?? 'F j, Y'
            ),
            'datetime' => new DateTimeTransformer(
                displayFormat: $settings['display_format'] ?? 'F j, Y g:i A'
            ),
            'json' => new JsonTransformer(),
            'checkbox', 'multiselect', 'gallery' => new ArrayTransformer(),
            default => new IdentityTransformer(),
        };
    }
}
