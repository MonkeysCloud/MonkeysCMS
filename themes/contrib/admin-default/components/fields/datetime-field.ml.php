{{-- DateTime Field Component --}}
{{-- Usage: <x-fields.datetime-field :field="$field" :value="$value" :errors="$errors" /> --}}

@php
    $fieldId = ($formId ?? 'field') . '_' . $field->machine_name;
    $fieldName = ($namePrefix ?? '') ? "{$namePrefix}[{$field->machine_name}]" : $field->machine_name;
    $hasError = isset($errors[$field->machine_name]);
    
    // Format value for datetime-local input
    $formattedValue = '';
    if ($value) {
        if ($value instanceof \DateTimeInterface) {
            $formattedValue = $value->format('Y-m-d\TH:i');
        } else {
            try {
                $formattedValue = (new \DateTime($value))->format('Y-m-d\TH:i');
            } catch (\Exception $e) {
                $formattedValue = $value;
            }
        }
    }
    
    $step = $field->getSetting('step', 60);
@endphp

<div class="field-widget field-widget--datetime{{ $hasError ? ' field-widget--error' : '' }}{{ $field->required ? ' field-widget--required' : '' }}" data-field="{{ $field->machine_name }}">
    @if(!($hideLabel ?? false))
    <label class="field-widget__label" for="{{ $fieldId }}">
        {{ $field->name }}
        @if($field->required)<span class="field-widget__required">*</span>@endif
    </label>
    @endif
    
    <div class="field-widget__input">
        <input type="datetime-local" 
               id="{{ $fieldId }}" 
               name="{{ $fieldName }}" 
               value="{{ $formattedValue }}"
               class="field-widget__control"
               step="{{ $step }}"
               @if($field->required) required @endif
               @if($field->getSetting('min_datetime')) min="{{ $field->getSetting('min_datetime') }}" @endif
               @if($field->getSetting('max_datetime')) max="{{ $field->getSetting('max_datetime') }}" @endif
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
