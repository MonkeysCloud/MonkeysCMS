<?php

declare(strict_types=1);

namespace App\Cms\Fields\Value;

/**
 * JsonTransformer - Handles JSON values
 */
final class JsonTransformer implements ValueTransformerInterface
{
    public function toForm(FieldValue $value): FieldValue
    {
        if ($value->isEmpty()) {
            return FieldValue::string('');
        }
        
        $data = $value->get();
        if (is_array($data)) {
            return FieldValue::string(json_encode($data, JSON_PRETTY_PRINT));
        }
        
        return $value;
    }

    public function toStorage(FieldValue $value): FieldValue
    {
        if ($value->isEmpty()) {
            return FieldValue::null('json');
        }
        
        $string = $value->asString();
        $decoded = json_decode($string, true);
        
        return FieldValue::array($decoded ?? []);
    }

    public function toDisplay(FieldValue $value): string
    {
        if ($value->isEmpty()) {
            return '';
        }
        
        $data = $value->get();
        if (is_array($data)) {
            return json_encode($data, JSON_PRETTY_PRINT);
        }
        
        return $value->asString();
    }
}
