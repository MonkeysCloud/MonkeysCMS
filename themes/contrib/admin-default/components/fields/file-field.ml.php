{{-- File Field Component --}}
{{-- Usage: <x-fields.file-field :field="$field" :value="$value" :errors="$errors" /> --}}

@php
    $fieldId = ($formId ?? 'field') . '_' . $field->machine_name;
    $fieldName = ($namePrefix ?? '') ? "{$namePrefix}[{$field->machine_name}]" : $field->machine_name;
    $hasError = isset($errors[$field->machine_name]);
    
    $allowedTypes = $field->getSetting('allowed_types', []);
    $accept = implode(',', $allowedTypes);
    $maxSize = $field->getSetting('max_size', 10 * 1024 * 1024);
    $maxSizeFormatted = number_format($maxSize / 1024 / 1024, 0) . ' MB';
    
    $hasValue = !empty($value);
    $fileInfo = $fileInfo ?? null; // Passed from controller with filename, size, etc.
@endphp

<div class="field-widget field-widget--file{{ $hasError ? ' field-widget--error' : '' }}{{ $field->required ? ' field-widget--required' : '' }}" data-field="{{ $field->machine_name }}">
    @if(!($hideLabel ?? false))
    <label class="field-widget__label">
        {{ $field->name }}
        @if($field->required)<span class="field-widget__required">*</span>@endif
    </label>
    @endif
    
    <div class="field-widget__input">
        <div class="field-file" id="{{ $fieldId }}_wrapper">
            {{-- Current file display --}}
            @if($hasValue)
            <div class="field-file__info" id="{{ $fieldId }}_info">
                <span class="field-file__icon">📄</span>
                <span class="field-file__name">{{ $fileInfo['name'] ?? "File #{$value}" }}</span>
                @if($fileInfo && isset($fileInfo['size']))
                <span class="field-file__size">({{ number_format($fileInfo['size'] / 1024, 0) }} KB)</span>
                @endif
                <a href="/media/{{ $value }}/download" class="field-file__download" target="_blank">Download</a>
            </div>
            @endif
            
            <input type="hidden" name="{{ $fieldName }}" id="{{ $fieldId }}" value="{{ $value ?? '' }}">
            
            <div class="field-file__actions">
                <button type="button" 
                        class="field-file__select btn btn-sm btn-secondary" 
                        onclick="window.openMediaBrowser('{{ $fieldId }}', 'file')">
                    Select File
                </button>
                <button type="button" 
                        class="field-file__upload btn btn-sm btn-secondary" 
                        onclick="document.getElementById('{{ $fieldId }}_upload').click()">
                    Upload New
                </button>
                @if($hasValue)
                <button type="button" 
                        class="field-file__remove btn btn-sm btn-danger" 
                        onclick="window.clearMediaField('{{ $fieldId }}')">
                    Remove
                </button>
                @endif
            </div>
            
            <input type="file" 
                   id="{{ $fieldId }}_upload" 
                   accept="{{ $accept }}" 
                   style="display: none;"
                   onchange="window.uploadMediaField('{{ $fieldId }}', this.files[0])">
            
            <div class="field-file__drop-zone" id="{{ $fieldId }}_dropzone">
                <div class="field-file__drop-zone-text">
                    Drop file here or click to browse<br>
                    <small>Max file size: {{ $maxSizeFormatted }}</small>
                </div>
            </div>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize drag and drop
    const dropzone = document.getElementById('{{ $fieldId }}_dropzone');
    if (dropzone) {
        dropzone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('field-file__drop-zone--active');
        });
        
        dropzone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('field-file__drop-zone--active');
        });
        
        dropzone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('field-file__drop-zone--active');
            if (e.dataTransfer.files.length) {
                window.uploadMediaField('{{ $fieldId }}', e.dataTransfer.files[0]);
            }
        });
        
        dropzone.addEventListener('click', function() {
            document.getElementById('{{ $fieldId }}_upload').click();
        });
    }
});
</script>
@endpush
