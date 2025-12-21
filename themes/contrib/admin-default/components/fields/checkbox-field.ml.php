{{-- Checkbox Field Component --}}
{{-- Supports single checkbox (boolean) and multiple checkboxes --}}
{{-- Usage: <x-fields.checkbox-field :field="$field" :value="$value" :errors="$errors" /> --}}

@php
    $fieldId = ($formId ?? 'field') . '_' . $field->machine_name;
    $fieldName = ($namePrefix ?? '') ? "{$namePrefix}[{$field->machine_name}]" : $field->machine_name;
    $hasError = isset($errors[$field->machine_name]);
    $isSingle = $field->field_type === 'boolean' || !$field->getSetting('options');
    $options = $field->getOptions();
    $selectedValues = is_array($value) ? $value : ($value ? [$value] : []);
    $layout = $field->getSetting('layout', 'vertical');
@endphp

<div class="field-widget field-widget--checkbox{{ $hasError ? ' field-widget--error' : '' }}{{ $field->required ? ' field-widget--required' : '' }}" data-field="{{ $field->machine_name }}">
    @if(!($hideLabel ?? false) && !$isSingle)
    <label class="field-widget__label">
        {{ $field->name }}
        @if($field->required)<span class="field-widget__required">*</span>@endif
    </label>
    @endif
    
    <div class="field-widget__input">
        @if($isSingle)
        {{-- Single checkbox (boolean) --}}
        <input type="hidden" name="{{ $fieldName }}" value="0">
        <label class="field-checkbox" for="{{ $fieldId }}">
            <input type="checkbox" 
                   id="{{ $fieldId }}" 
                   name="{{ $fieldName }}" 
                   value="1"
                   {{ $value ? 'checked' : '' }}
                   @if($disabled ?? false) disabled @endif>
            <span class="field-checkbox__mark"></span>
            <span class="field-checkbox__label">{{ $field->getSetting('checkbox_label', $field->name) }}</span>
        </label>
        @else
        {{-- Multiple checkboxes --}}
        <div class="field-checkboxes field-checkboxes--{{ $layout }}">
            @foreach($options as $optValue => $optLabel)
            @php $optId = $fieldId . '_' . $loop->index; @endphp
            <label class="field-checkbox" for="{{ $optId }}">
                <input type="checkbox" 
                       id="{{ $optId }}" 
                       name="{{ $fieldName }}[]" 
                       value="{{ $optValue }}"
                       {{ in_array($optValue, $selectedValues) ? 'checked' : '' }}
                       @if($disabled ?? false) disabled @endif>
                <span class="field-checkbox__mark"></span>
                <span class="field-checkbox__label">{{ $optLabel }}</span>
            </label>
            @endforeach
        </div>
        @endif
    </div>
    
    @if($field->help_text && !($hideHelp ?? false))
    <div class="field-widget__help">{{ $field->help_text }}</div>
    @endif
    
    @if($hasError)
    <div class="field-widget__errors">
        @foreach($errors[$field->machine_name] as $error)
        <div class="field-widget__error">{{ $error }}</div>
        @endforeach
    </div>
    @endif
</div>
