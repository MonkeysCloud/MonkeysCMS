{{-- WYSIWYG Rich Text Editor Component --}}
{{-- Usage: <x-fields.wysiwyg-field :field="$field" :value="$value" :errors="$errors" /> --}}

@php
    $fieldId = ($formId ?? 'field') . '_' . $field->machine_name;
    $fieldName = ($namePrefix ?? '') ? "{$namePrefix}[{$field->machine_name}]" : $field->machine_name;
    $hasError = isset($errors[$field->machine_name]);
    $minHeight = $field->getSetting('min_height', '200px');
    $maxHeight = $field->getSetting('max_height', '500px');
    $placeholder = $field->getSetting('placeholder', 'Start writing...');
    $toolbar = $field->getSetting('toolbar', ['bold', 'italic', 'underline', '|', 'heading', '|', 'bulletList', 'orderedList', '|', 'link', 'image']);
@endphp

<div class="field-widget field-widget--wysiwyg{{ $hasError ? ' field-widget--error' : '' }}{{ $field->required ? ' field-widget--required' : '' }}" data-field="{{ $field->machine_name }}">
    @if(!($hideLabel ?? false))
    <label class="field-widget__label" for="{{ $fieldId }}">
        {{ $field->name }}
        @if($field->required)<span class="field-widget__required">*</span>@endif
    </label>
    @endif
    
    <div class="field-widget__input">
        <div class="wysiwyg-editor" id="{{ $fieldId }}_wrapper" data-field="{{ $field->machine_name }}">
            <div class="wysiwyg-toolbar" id="{{ $fieldId }}_toolbar">
                {{-- Toolbar will be populated by JS, but here's a fallback --}}
                <button type="button" data-cmd="bold" title="Bold"><b>B</b></button>
                <button type="button" data-cmd="italic" title="Italic"><i>I</i></button>
                <button type="button" data-cmd="underline" title="Underline"><u>U</u></button>
                <span class="wysiwyg-separator"></span>
                <button type="button" data-cmd="insertUnorderedList" title="Bullet List">•</button>
                <button type="button" data-cmd="insertOrderedList" title="Numbered List">1.</button>
                <span class="wysiwyg-separator"></span>
                <button type="button" data-cmd="createLink" title="Insert Link">🔗</button>
                <button type="button" data-cmd="insertImage" title="Insert Image">🖼️</button>
            </div>
            <div class="wysiwyg-content" 
                 id="{{ $fieldId }}_content" 
                 contenteditable="true"
                 style="min-height: {{ $minHeight }}; max-height: {{ $maxHeight }};"
                 data-placeholder="{{ $placeholder }}">{!! $value ?? '' !!}</div>
            <input type="hidden" name="{{ $fieldName }}" id="{{ $fieldId }}" value="{{ htmlspecialchars($value ?? '', ENT_QUOTES) }}">
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
<link rel="stylesheet" href="/css/fields/wysiwyg.css">
@endpush

@push('scripts')
<script src="/js/fields/wysiwyg.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const wrapper = document.getElementById('{{ $fieldId }}_wrapper');
    const content = document.getElementById('{{ $fieldId }}_content');
    const hidden = document.getElementById('{{ $fieldId }}');
    
    // Sync content to hidden input
    content.addEventListener('input', function() {
        hidden.value = this.innerHTML;
    });
    
    // Toolbar commands
    wrapper.querySelectorAll('[data-cmd]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const cmd = this.dataset.cmd;
            
            if (cmd === 'createLink') {
                const url = prompt('Enter URL:');
                if (url) document.execCommand(cmd, false, url);
            } else if (cmd === 'insertImage') {
                // Open media browser
                if (window.openMediaBrowser) {
                    window.openMediaBrowser('{{ $fieldId }}', 'image');
                } else {
                    const url = prompt('Enter image URL:');
                    if (url) document.execCommand(cmd, false, url);
                }
            } else {
                document.execCommand(cmd, false, null);
            }
            
            content.focus();
        });
    });
    
    // Init with TipTap if available
    if (typeof window.initWysiwyg === 'function') {
        window.initWysiwyg(wrapper, {
            toolbar: @json($toolbar),
            content: hidden.value,
            placeholder: '{{ $placeholder }}',
            onChange: (html) => { hidden.value = html; }
        });
    }
});
</script>
@endpush
