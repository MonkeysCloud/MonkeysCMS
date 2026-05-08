{{-- Molecule: Field — Renders a content type field value --}}
@php
    $fieldName = $data['field_name'] ?? 'title';
    $value = $data['_resolved_value'] ?? null;
    $label = $data['_field_label'] ?? ucfirst(str_replace('_', ' ', $fieldName));
    $tag = $data['wrapper_tag'] ?? 'div';
    $showLabel = ($data['display_label'] ?? 'false') === 'true';
    $cls = 'block-field block-field--' . htmlspecialchars($fieldName)
         . (!empty($settings['css_class']) ? ' ' . htmlspecialchars($settings['css_class']) : '');

    $formatValue = function ($val) use (&$formatValue) {
        if ($val === null) return '';
        if (is_scalar($val)) return (string) $val;
        if (is_array($val)) {
            // Check if it's an associative array
            if (array_keys($val) !== range(0, count($val) - 1)) {
                return json_encode($val, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            $mapped = array_map($formatValue, $val);
            return implode(', ', array_filter($mapped, fn($v) => $v !== ''));
        }
        if (is_object($val) && method_exists($val, '__toString')) return (string) $val;
        if ($val instanceof \DateTimeInterface) return $val->format('Y-m-d H:i:s');
        return json_encode($val, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    };

    $formattedValue = $formatValue($value);
@endphp

@if($formattedValue !== '')
<div class="{{ $cls }}">
    @if($showLabel)
    <div class="block-field__label">{{ $label }}</div>
    @endif

    @if($tag === 'none')
        {!! $formattedValue !!}
    @else
        <{{ $tag }} class="block-field__value">{!! $formattedValue !!}</{{ $tag }}>
    @endif
</div>
@endif
