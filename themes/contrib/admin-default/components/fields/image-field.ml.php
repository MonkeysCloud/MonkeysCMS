{{-- Image Field Component with Media Browser --}}
{{-- Usage: <x-fields.image-field :field="$field" :value="$value" :errors="$errors" /> --}}

@php
    $fieldId = ($formId ?? 'field') . '_' . $field->machine_name;
    $fieldName = ($namePrefix ?? '') ? "{$namePrefix}[{$field->machine_name}]" : $field->machine_name;
    $hasError = isset($errors[$field->machine_name]);
    $previewSize = $field->getSetting('preview_size', 'thumbnail');
    $previewUrl = $value ? "/media/{$value}/{$previewSize}" : '';
    $hasValue = !empty($value);
@endphp

<div class="field-widget field-widget--image{{ $hasError ? ' field-widget--error' : '' }}{{ $field->required ? ' field-widget--required' : '' }}" data-field="{{ $field->machine_name }}">
    @if(!($hideLabel ?? false))
    <label class="field-widget__label">
        {{ $field->name }}
        @if($field->required)<span class="field-widget__required">*</span>@endif
    </label>
    @endif
    
    <div class="field-widget__input">
        <div class="field-image" id="{{ $fieldId }}_wrapper">
            <div class="field-image__preview{{ $hasValue ? '' : ' field-image__preview--empty' }}" id="{{ $fieldId }}_preview">
                <img src="{{ $previewUrl }}" alt="" id="{{ $fieldId }}_img" style="{{ $hasValue ? '' : 'display: none;' }}">
                <div class="field-image__placeholder" style="{{ $hasValue ? 'display: none;' : '' }}">
                    <span class="field-image__icon">📷</span>
                    <span class="field-image__text">Click to select image</span>
                </div>
            </div>
            
            <input type="hidden" name="{{ $fieldName }}" id="{{ $fieldId }}" value="{{ $value ?? '' }}">
            
            <div class="field-image__actions">
                <button type="button" class="field-image__select btn btn-sm btn-secondary" onclick="window.openMediaBrowser('{{ $fieldId }}', 'image')">
                    Select Image
                </button>
                <button type="button" class="field-image__upload btn btn-sm btn-secondary" onclick="document.getElementById('{{ $fieldId }}_upload').click()">
                    Upload New
                </button>
                <button type="button" class="field-image__remove btn btn-sm btn-danger" onclick="window.clearMediaField('{{ $fieldId }}')" style="{{ $hasValue ? '' : 'display: none;' }}">
                    Remove
                </button>
            </div>
            
            <input type="file" 
                   id="{{ $fieldId }}_upload" 
                   accept="image/*" 
                   style="display: none;"
                   onchange="window.uploadMediaField('{{ $fieldId }}', this.files[0])">
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
<link rel="stylesheet" href="/css/fields/media.css">
@endpush

@push('scripts')
<script src="/js/fields/media.js" defer></script>
@endpush
