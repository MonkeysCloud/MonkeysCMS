{{-- Textarea Field Component --}}
{{-- Usage: <x-fields.textarea-field :field="$field" :value="$value" :errors="$errors" /> --}}

@php
    $fieldId = ($formId ?? 'field') . '_' . $field->machine_name;
    $fieldName = ($namePrefix ?? '') ? "{$namePrefix}[{$field->machine_name}]" : $field->machine_name;
    $hasError = isset($errors[$field->machine_name]);
    $rows = $field->getSetting('rows', 5);
    $maxLength = $field->getSetting('max_length');
    $showCounter = $field->getSetting('show_counter', true) && $maxLength;
@endphp

<div class="field-widget field-widget--textarea{{ $hasError ? ' field-widget--error' : '' }}{{ $field->required ? ' field-widget--required' : '' }}" data-field="{{ $field->machine_name }}">
    @if(!($hideLabel ?? false))
    <label class="field-widget__label" for="{{ $fieldId }}">
        {{ $field->name }}
        @if($field->required)<span class="field-widget__required">*</span>@endif
    </label>
    @endif
    
    <div class="field-widget__input">
        <textarea id="{{ $fieldId }}" 
                  name="{{ $fieldName }}" 
                  class="field-widget__control"
                  rows="{{ $rows }}"
                  @if($field->getSetting('cols')) cols="{{ $field->getSetting('cols') }}" @endif
                  @if($maxLength) maxlength="{{ $maxLength }}" @endif
                  @if($field->getSetting('placeholder')) placeholder="{{ $field->getSetting('placeholder') }}" @endif
                  @if($field->required) required @endif
                  @if($disabled ?? false) disabled @endif
                  @if($readonly ?? false) readonly @endif
                  style="resize: {{ $field->getSetting('resize', 'vertical') }};">{{ $value ?? '' }}</textarea>
        
        @if($showCounter)
        <div class="field-textarea__counter">
            <span class="field-textarea__count">{{ strlen($value ?? '') }}</span> / {{ $maxLength }}
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

@if($showCounter)
@push('scripts')
<script>
document.getElementById('{{ $fieldId }}')?.addEventListener('input', function() {
    const counter = this.parentElement.querySelector('.field-textarea__count');
    if (counter) counter.textContent = this.value.length;
});
</script>
@endpush
@endif
