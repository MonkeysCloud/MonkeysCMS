<?php

declare(strict_types=1);

namespace App\Cms\Fields\Value;

/**
 * TransformerChain - Chains multiple transformers
 */
final class TransformerChain implements ValueTransformerInterface
{
    /** @var ValueTransformerInterface[] */
    private array $transformers;

    public function __construct(ValueTransformerInterface ...$transformers)
    {
        $this->transformers = $transformers;
    }

    public function toForm(FieldValue $value): FieldValue
    {
        foreach ($this->transformers as $transformer) {
            $value = $transformer->toForm($value);
        }
        return $value;
    }

    public function toStorage(FieldValue $value): FieldValue
    {
        foreach (array_reverse($this->transformers) as $transformer) {
            $value = $transformer->toStorage($value);
        }
        return $value;
    }

    public function toDisplay(FieldValue $value): string
    {
        $lastTransformer = end($this->transformers);
        return $lastTransformer ? $lastTransformer->toDisplay($value) : $value->asString();
    }
}
