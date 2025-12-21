{{-- Text Field Component --}}
{{-- Usage: <x-fields.text-field :field="$field" :value="$value" :errors="$errors" /> --}}

@php
    $fieldId = ($formId ?? 'field') . '_' . $field->machine_name;
    $fieldName = ($namePrefix ?? '') ? "{$namePrefix}[{$field->machine_name}]" : $field->machine_name;
    $hasError = isset($errors[$field->machine_name]);
    $errorClass = $hasError ? ' field-widget--error' : '';
    $requiredClass = $field->required ? ' field-widget--required' : '';
@endphp

<div class="field-widget field-widget--text{{ $errorClass }}{{ $requiredClass }}" data-field="{{ $field->machine_name }}">
    @if(!($hideLabel ?? false))
    <label class="field-widget__label" for="{{ $fieldId }}">
        {{ $field->name }}
        @if($field->required)
        <span class="field-widget__required">*</span>
        @endif
    </label>
    @endif
    
    <div class="field-widget__input">
        @if($field->getSetting('prefix') || $field->getSetting('suffix'))
        <div class="field-input-group">
            @if($prefix = $field->getSetting('prefix'))
            <span class="field-input-group__prefix">{{ $prefix }}</span>
            @endif
            
            <input type="text" 
                   id="{{ $fieldId }}" 
                   name="{{ $fieldName }}" 
                   value="{{ $value ?? '' }}"
                   class="field-widget__control"
                   @if($field->required) required @endif
                   @if($field->getSetting('max_length')) maxlength="{{ $field->getSetting('max_length') }}" @endif
                   @if($field->getSetting('min_length')) minlength="{{ $field->getSetting('min_length') }}" @endif
                   @if($field->getSetting('placeholder')) placeholder="{{ $field->getSetting('placeholder') }}" @endif
                   @if($field->getSetting('pattern')) pattern="{{ $field->getSetting('pattern') }}" @endif
                   @if($disabled ?? false) disabled @endif
                   @if($readonly ?? false) readonly @endif>
            
            @if($suffix = $field->getSetting('suffix'))
            <span class="field-input-group__suffix">{{ $suffix }}</span>
            @endif
        </div>
        @else
        <input type="text" 
               id="{{ $fieldId }}" 
               name="{{ $fieldName }}" 
               value="{{ $value ?? '' }}"
               class="field-widget__control"
               @if($field->required) required @endif
               @if($field->getSetting('max_length')) maxlength="{{ $field->getSetting('max_length') }}" @endif
               @if($field->getSetting('min_length')) minlength="{{ $field->getSetting('min_length') }}" @endif
               @if($field->getSetting('placeholder')) placeholder="{{ $field->getSetting('placeholder') }}" @endif
               @if($field->getSetting('pattern')) pattern="{{ $field->getSetting('pattern') }}" @endif
               @if($disabled ?? false) disabled @endif
               @if($readonly ?? false) readonly @endif>
        @endif
    </div>
    
    @if($field->help_text && !($hideHelp ?? false))
    <div class="field-widget__help" id="{{ $fieldId }}_help">{{ $field->help_text }}</div>
    @endif
    
    @if($hasError)
    <div class="field-widget__errors">
        @foreach($errors[$field->machine_name] as $error)
        <div class="field-widget__error">{{ $error }}</div>
        @endforeach
    </div>
    @endif
</div>
