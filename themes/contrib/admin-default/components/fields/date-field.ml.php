{{-- Date Field Component --}}
{{-- Usage: <x-fields.date-field :field="$field" :value="$value" :errors="$errors" /> --}}

@php
    $fieldId = ($formId ?? 'field') . '_' . $field->machine_name;
    $fieldName = ($namePrefix ?? '') ? "{$namePrefix}[{$field->machine_name}]" : $field->machine_name;
    $hasError = isset($errors[$field->machine_name]);
    
    // Format value for date input
    $formattedValue = '';
    if ($value) {
        if ($value instanceof \DateTimeInterface) {
            $formattedValue = $value->format('Y-m-d');
        } else {
            try {
                $formattedValue = (new \DateTime($value))->format('Y-m-d');
            } catch (\Exception $e) {
                $formattedValue = $value;
            }
        }
    }
@endphp

<div class="field-widget field-widget--date{{ $hasError ? ' field-widget--error' : '' }}{{ $field->required ? ' field-widget--required' : '' }}" data-field="{{ $field->machine_name }}">
    @if(!($hideLabel ?? false))
    <label class="field-widget__label" for="{{ $fieldId }}">
        {{ $field->name }}
        @if($field->required)<span class="field-widget__required">*</span>@endif
    </label>
    @endif
    
    <div class="field-widget__input">
        <input type="date" 
               id="{{ $fieldId }}" 
               name="{{ $fieldName }}" 
               value="{{ $formattedValue }}"
               class="field-widget__control"
               @if($field->required) required @endif
               @if($field->getSetting('min_date')) min="{{ $field->getSetting('min_date') }}" @endif
               @if($field->getSetting('max_date')) max="{{ $field->getSetting('max_date') }}" @endif
               @if($disabled ?? false) disabled @endif
               @if($readonly ?? false) readonly @endif>
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
