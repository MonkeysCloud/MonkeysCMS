{{-- Entity Reference Field Component --}}
{{-- Autocomplete selector for referencing other entities --}}
{{-- Usage: <x-fields.entity-reference :field="$field" :value="$value" :errors="$errors" /> --}}

@php
    $fieldId = ($formId ?? 'field') . '_' . $field->machine_name;
    $fieldName = ($namePrefix ?? '') ? "{$namePrefix}[{$field->machine_name}]" : $field->machine_name;
    $hasError = isset($errors[$field->machine_name]);
    
    $targetType = $field->getSetting('target_type', 'content');
    $targetBundle = $field->getSetting('target_bundle', '');
    $allowCreate = $field->getSetting('allow_create', false);
    
    $selectedValues = is_array($value) ? $value : ($value ? [$value] : []);
    $selectedEntities = $selectedEntities ?? []; // Passed from controller
@endphp

<div class="field-widget field-widget--entity-reference{{ $hasError ? ' field-widget--error' : '' }}{{ $field->required ? ' field-widget--required' : '' }}" data-field="{{ $field->machine_name }}">
    @if(!($hideLabel ?? false))
    <label class="field-widget__label">
        {{ $field->name }}
        @if($field->required)<span class="field-widget__required">*</span>@endif
    </label>
    @endif
    
    <div class="field-widget__input">
        <div class="field-entity-reference" 
             id="{{ $fieldId }}_wrapper"
             data-target-type="{{ $targetType }}"
             data-target-bundle="{{ $targetBundle }}"
             data-allow-create="{{ $allowCreate ? 'true' : 'false' }}"
             data-multiple="{{ $field->multiple ? 'true' : 'false' }}">
            
            {{-- Selected items display --}}
            <div class="field-entity-reference__selected" id="{{ $fieldId }}_selected">
                @foreach($selectedEntities as $entity)
                <div class="field-entity-reference__item" data-id="{{ $entity['id'] }}">
                    <span class="field-entity-reference__item-label">{{ $entity['label'] ?? "Item #{$entity['id']}" }}</span>
                    <button type="button" class="field-entity-reference__item-remove" onclick="window.removeEntityReference('{{ $fieldId }}', {{ $entity['id'] }})">&times;</button>
                </div>
                @endforeach
            </div>
            
            {{-- Search input --}}
            <input type="text" 
                   class="field-entity-reference__search field-widget__control" 
                   id="{{ $fieldId }}_search"
                   placeholder="Search {{ $targetType }}..."
                   autocomplete="off"
                   @if($disabled ?? false) disabled @endif>
            
            {{-- Search results dropdown --}}
            <div class="field-entity-reference__results" id="{{ $fieldId }}_results" style="display: none;"></div>
            
            {{-- Hidden input for actual values --}}
            @if($field->multiple)
                @foreach($selectedValues as $selectedValue)
                <input type="hidden" name="{{ $fieldName }}[]" value="{{ $selectedValue }}" class="field-entity-reference__value">
                @endforeach
            @else
            <input type="hidden" name="{{ $fieldName }}" id="{{ $fieldId }}" value="{{ $selectedValues[0] ?? '' }}">
            @endif
        </div>
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

@push('styles')
<link rel="stylesheet" href="/css/fields/reference.css">
@endpush

@push('scripts')
<script src="/js/fields/entity-reference.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    window.initEntityReference && window.initEntityReference('{{ $fieldId }}', @json($selectedValues), {
        targetType: '{{ $targetType }}',
        targetBundle: '{{ $targetBundle }}',
        multiple: {{ $field->multiple ? 'true' : 'false' }},
        allowCreate: {{ $allowCreate ? 'true' : 'false' }}
    });
});
</script>
@endpush
