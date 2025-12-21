{{-- Select Field Component --}}
{{-- Usage: <x-fields.select-field :field="$field" :value="$value" :errors="$errors" :options="$options" /> --}}

@php
    $fieldId = ($formId ?? 'field') . '_' . $field->machine_name;
    $fieldName = ($namePrefix ?? '') ? "{$namePrefix}[{$field->machine_name}]" : $field->machine_name;
    $hasError = isset($errors[$field->machine_name]);
    $options = $options ?? $field->getOptions();
    $selectedValues = is_array($value) ? $value : ($value !== null ? [$value] : []);
    $emptyOption = $field->getSetting('empty_option', '- Select -');
    $searchable = $field->getSetting('searchable', false);
@endphp

<div class="field-widget field-widget--select{{ $hasError ? ' field-widget--error' : '' }}{{ $field->required ? ' field-widget--required' : '' }}" data-field="{{ $field->machine_name }}">
    @if(!($hideLabel ?? false))
    <label class="field-widget__label" for="{{ $fieldId }}">
        {{ $field->name }}
        @if($field->required)<span class="field-widget__required">*</span>@endif
    </label>
    @endif
    
    <div class="field-widget__input">
        <select id="{{ $fieldId }}" 
                name="{{ $fieldName }}{{ $field->multiple ? '[]' : '' }}" 
                class="field-widget__control{{ $searchable ? ' field-select--searchable' : '' }}"
                @if($field->multiple) multiple @endif
                @if($field->required) required @endif
                @if($searchable) data-searchable="true" @endif
                @if($disabled ?? false) disabled @endif>
            
            @if($emptyOption && !$field->multiple)
            <option value="">{{ $emptyOption }}</option>
            @endif
            
            @foreach($options as $optValue => $optLabel)
                @if(is_array($optLabel))
                {{-- Option group --}}
                <optgroup label="{{ $optValue }}">
                    @foreach($optLabel as $subValue => $subLabel)
                    <option value="{{ $subValue }}" {{ in_array($subValue, $selectedValues) ? 'selected' : '' }}>
                        {{ $subLabel }}
                    </option>
                    @endforeach
                </optgroup>
                @else
                <option value="{{ $optValue }}" {{ in_array($optValue, $selectedValues) ? 'selected' : '' }}>
                    {{ $optLabel }}
                </option>
                @endif
            @endforeach
        </select>
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

@if($searchable)
@push('scripts')
<script>
// Initialize searchable select (e.g., with Choices.js or Select2)
document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('{{ $fieldId }}');
    if (select && window.initSearchableSelect) {
        window.initSearchableSelect(select);
    }
});
</script>
@endpush
@endif
