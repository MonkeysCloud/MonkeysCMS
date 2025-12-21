{{-- Taxonomy Field Component --}}
{{-- Term selector with multiple display modes (select, checkboxes, tree, autocomplete) --}}
{{-- Usage: <x-fields.taxonomy-field :field="$field" :value="$value" :errors="$errors" :terms="$terms" /> --}}

@php
    $fieldId = ($formId ?? 'field') . '_' . $field->machine_name;
    $fieldName = ($namePrefix ?? '') ? "{$namePrefix}[{$field->machine_name}]" : $field->machine_name;
    $hasError = isset($errors[$field->machine_name]);
    
    $vocabulary = $field->getSetting('vocabulary', '');
    $displayMode = $field->getSetting('display_mode', 'select');
    $allowCreate = $field->getSetting('allow_create', false);
    
    $selectedValues = is_array($value) ? $value : ($value ? [$value] : []);
    $terms = $terms ?? []; // Passed from controller
@endphp

<div class="field-widget field-widget--taxonomy{{ $hasError ? ' field-widget--error' : '' }}{{ $field->required ? ' field-widget--required' : '' }}" data-field="{{ $field->machine_name }}">
    @if(!($hideLabel ?? false))
    <label class="field-widget__label"{{ $displayMode === 'select' ? ' for="'.$fieldId.'"' : '' }}>
        {{ $field->name }}
        @if($field->required)<span class="field-widget__required">*</span>@endif
    </label>
    @endif
    
    <div class="field-widget__input">
        @switch($displayMode)
            @case('select')
                {{-- Dropdown select --}}
                <select id="{{ $fieldId }}" 
                        name="{{ $fieldName }}{{ $field->multiple ? '[]' : '' }}" 
                        class="field-widget__control"
                        @if($field->multiple) multiple @endif
                        @if($field->required) required @endif
                        @if($disabled ?? false) disabled @endif>
                    @if(!$field->required && !$field->multiple)
                    <option value="">- None -</option>
                    @endif
                    @foreach($terms as $term)
                    <option value="{{ $term['id'] }}" {{ in_array($term['id'], $selectedValues) ? 'selected' : '' }}>
                        {{ str_repeat('— ', $term['depth'] ?? 0) }}{{ $term['name'] }}
                    </option>
                    @endforeach
                </select>
                @break
            
            @case('checkboxes')
                {{-- Checkboxes --}}
                <div class="field-taxonomy-checkboxes">
                    @foreach($terms as $term)
                    @php $termId = $fieldId . '_' . $loop->index; @endphp
                    <label class="field-checkbox" style="margin-left: {{ ($term['depth'] ?? 0) * 20 }}px;">
                        <input type="checkbox" 
                               id="{{ $termId }}" 
                               name="{{ $fieldName }}[]" 
                               value="{{ $term['id'] }}"
                               {{ in_array($term['id'], $selectedValues) ? 'checked' : '' }}
                               @if($disabled ?? false) disabled @endif>
                        <span class="field-checkbox__mark"></span>
                        <span class="field-checkbox__label">{{ $term['name'] }}</span>
                    </label>
                    @endforeach
                </div>
                @break
            
            @case('tree')
                {{-- Tree view --}}
                <div class="field-taxonomy-tree" 
                     id="{{ $fieldId }}_tree" 
                     data-vocabulary="{{ $vocabulary }}"
                     data-multiple="{{ $field->multiple ? 'true' : 'false' }}">
                    <div class="field-taxonomy-tree__loading">Loading...</div>
                </div>
                <input type="hidden" name="{{ $fieldName }}" id="{{ $fieldId }}" value="{{ implode(',', $selectedValues) }}">
                @break
            
            @case('autocomplete')
                {{-- Autocomplete --}}
                <div class="field-taxonomy-autocomplete" id="{{ $fieldId }}_wrapper">
                    <div class="field-taxonomy-autocomplete__selected" id="{{ $fieldId }}_selected">
                        @foreach($terms as $term)
                            @if(in_array($term['id'], $selectedValues))
                            <span class="field-taxonomy-autocomplete__tag" data-id="{{ $term['id'] }}">
                                {{ $term['name'] }}
                                <button type="button" class="field-taxonomy-autocomplete__remove" onclick="window.removeTaxonomyTag('{{ $fieldId }}', {{ $term['id'] }})">&times;</button>
                            </span>
                            @endif
                        @endforeach
                    </div>
                    <input type="text" 
                           class="field-taxonomy-autocomplete__search field-widget__control"
                           id="{{ $fieldId }}_search"
                           placeholder="Search or create terms..."
                           autocomplete="off"
                           @if($disabled ?? false) disabled @endif>
                    <div class="field-taxonomy-autocomplete__results" id="{{ $fieldId }}_results" style="display: none;"></div>
                    <input type="hidden" name="{{ $fieldName }}" id="{{ $fieldId }}" value="{{ implode(',', $selectedValues) }}">
                </div>
                @break
        @endswitch
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

@if(in_array($displayMode, ['tree', 'autocomplete']))
@push('styles')
<link rel="stylesheet" href="/css/fields/taxonomy.css">
@endpush

@push('scripts')
<script src="/js/fields/taxonomy.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    @if($displayMode === 'tree')
    window.initTaxonomyTree && window.initTaxonomyTree('{{ $fieldId }}', '{{ $vocabulary }}', @json($selectedValues), {{ $field->multiple ? 'true' : 'false' }});
    @elseif($displayMode === 'autocomplete')
    window.initTaxonomyAutocomplete && window.initTaxonomyAutocomplete('{{ $fieldId }}', '{{ $vocabulary }}', @json($selectedValues), {
        multiple: {{ $field->multiple ? 'true' : 'false' }},
        allowCreate: {{ $allowCreate ? 'true' : 'false' }}
    });
    @endif
});
</script>
@endpush
@endif
