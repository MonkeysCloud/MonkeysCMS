{{-- blocks/field.ml.php — Renders a content type field value --}}
@php
    $fieldName = $data['field_name'] ?? 'title';
    $value = $data['_resolved_value'] ?? null;
    $label = $data['_field_label'] ?? ucfirst(str_replace('_', ' ', $fieldName));
    $tag = $data['wrapper_tag'] ?? 'div';
    $showLabel = ($data['display_label'] ?? 'false') === 'true';
@endphp

@if($value !== null)
<div class="block-field block-field--{{ $fieldName }}">
    @if($showLabel)
    <div class="block-field__label">{{ $label }}</div>
    @endif

    @if($tag === 'none')
        {!! $value !!}
    @else
        <{{ $tag }} class="block-field__value">{!! $value !!}</{{ $tag }}>
    @endif
</div>
@endif
