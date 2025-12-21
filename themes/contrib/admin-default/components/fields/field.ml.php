{{-- Base Field Component --}}
{{-- Usage: <x-fields.field :field="$field" :value="$value" :errors="$errors" /> --}}

@props([
    'field' => null,
    'value' => null,
    'errors' => [],
    'disabled' => false,
    'readonly' => false,
    'formId' => 'form',
    'hideLabel' => false,
    'hideHelp' => false,
])

@php
    $fieldId = $formId . '_' . $field->machine_name;
    $fieldName = $field->machine_name;
    $fieldErrors = $errors[$field->machine_name] ?? [];
    $hasError = !empty($fieldErrors);
    
    $wrapperClass = 'field-widget';
    $wrapperClass .= ' field-widget--' . ($field->widget ?? $field->field_type);
    $wrapperClass .= ' field-type--' . $field->field_type;
    if ($hasError) $wrapperClass .= ' field-widget--error';
    if ($field->required) $wrapperClass .= ' field-widget--required';
@endphp

<div class="{{ $wrapperClass }}" data-field="{{ $field->machine_name }}">
    @if (!$hideLabel)
        <label class="field-widget__label" for="{{ $fieldId }}">
            {{ $field->name }}
            @if ($field->required)
                <span class="field-widget__required">*</span>
            @endif
        </label>
    @endif
    
    <div class="field-widget__input">
        {{ $slot }}
    </div>
    
    @if ($field->help_text && !$hideHelp)
        <div class="field-widget__help" id="{{ $fieldId }}_help">
            {{ $field->help_text }}
        </div>
    @endif
    
    @if ($hasError)
        <div class="field-widget__errors">
            @foreach ($fieldErrors as $error)
                <div class="field-widget__error">{{ $error }}</div>
            @endforeach
        </div>
    @endif
</div>
