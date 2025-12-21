{{-- Repeater Field Component --}}
{{-- Repeatable field groups for complex nested data --}}
{{-- Usage: <x-fields.repeater-field :field="$field" :value="$value" :errors="$errors" /> --}}

@php
    $fieldId = ($formId ?? 'field') . '_' . $field->machine_name;
    $fieldName = ($namePrefix ?? '') ? "{$namePrefix}[{$field->machine_name}]" : $field->machine_name;
    $hasError = isset($errors[$field->machine_name]);
    
    $subfields = $field->getSetting('subfields', []);
    $minItems = $field->getSetting('min_items', 0);
    $maxItems = $field->getSetting('max_items', -1);
    $collapsed = $field->getSetting('collapsed', false);
    $sortable = $field->getSetting('sortable', true);
    $itemLabel = $field->getSetting('item_label', 'Item');
    
    $items = is_array($value) ? $value : [];
    $canAdd = $maxItems < 0 || count($items) < $maxItems;
@endphp

<div class="field-widget field-widget--repeater{{ $hasError ? ' field-widget--error' : '' }}{{ $field->required ? ' field-widget--required' : '' }}" data-field="{{ $field->machine_name }}">
    @if(!($hideLabel ?? false))
    <label class="field-widget__label">
        {{ $field->name }}
        @if($field->required)<span class="field-widget__required">*</span>@endif
    </label>
    @endif
    
    <div class="field-widget__input">
        <div class="field-repeater" 
             id="{{ $fieldId }}_wrapper" 
             data-field="{{ $field->machine_name }}"
             data-min="{{ $minItems }}" 
             data-max="{{ $maxItems }}"
             data-sortable="{{ $sortable ? 'true' : 'false' }}"
             data-collapsed="{{ $collapsed ? 'true' : 'false' }}">
            
            <div class="field-repeater__items" id="{{ $fieldId }}_items">
                @foreach($items as $index => $itemData)
                <div class="field-repeater__item{{ $collapsed ? ' field-repeater__item--collapsed' : '' }}" data-index="{{ $index }}">
                    <div class="field-repeater__item-header">
                        @if($sortable)
                        <span class="field-repeater__item-drag" title="Drag to reorder">⋮⋮</span>
                        @endif
                        <span class="field-repeater__item-label">{{ $itemLabel }} {{ $index + 1 }}</span>
                        <button type="button" class="field-repeater__item-toggle" title="Toggle">▼</button>
                        <button type="button" class="field-repeater__item-remove" title="Remove" onclick="window.removeRepeaterItem(this)">×</button>
                    </div>
                    <div class="field-repeater__item-content">
                        @foreach($subfields as $subfield)
                        @php
                            $subfieldName = $subfield['name'] ?? $subfield['machine_name'] ?? '';
                            $subfieldType = $subfield['type'] ?? 'string';
                            $subfieldLabel = $subfield['label'] ?? ucfirst(str_replace('_', ' ', $subfieldName));
                            $subfieldValue = $itemData[$subfieldName] ?? ($subfield['default'] ?? '');
                            $inputName = "{$fieldName}[{$index}][{$subfieldName}]";
                            $inputId = "{$fieldId}_{$index}_{$subfieldName}";
                        @endphp
                        <div class="field-repeater__subfield field-repeater__subfield--{{ $subfieldType }}">
                            <label for="{{ $inputId }}">{{ $subfieldLabel }}</label>
                            @switch($subfieldType)
                                @case('text')
                                @case('textarea')
                                    <textarea name="{{ $inputName }}" id="{{ $inputId }}" class="field-widget__control" rows="3">{{ $subfieldValue }}</textarea>
                                    @break
                                @case('select')
                                    <select name="{{ $inputName }}" id="{{ $inputId }}" class="field-widget__control">
                                        @foreach($subfield['options'] ?? [] as $optVal => $optLabel)
                                        <option value="{{ $optVal }}" {{ $subfieldValue == $optVal ? 'selected' : '' }}>{{ $optLabel }}</option>
                                        @endforeach
                                    </select>
                                    @break
                                @case('checkbox')
                                @case('boolean')
                                    <input type="checkbox" name="{{ $inputName }}" id="{{ $inputId }}" value="1" {{ $subfieldValue ? 'checked' : '' }}>
                                    @break
                                @case('number')
                                @case('integer')
                                    <input type="number" name="{{ $inputName }}" id="{{ $inputId }}" value="{{ $subfieldValue }}" class="field-widget__control">
                                    @break
                                @case('image')
                                    <div class="field-repeater__image-picker">
                                        <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" value="{{ $subfieldValue }}" class="field-image-input">
                                        @if($subfieldValue)
                                        <img src="/media/{{ $subfieldValue }}/thumbnail" alt="" class="field-repeater__image-preview">
                                        @endif
                                        <button type="button" class="btn btn-sm btn-secondary" onclick="window.selectImage('{{ $inputId }}')">Select Image</button>
                                    </div>
                                    @break
                                @default
                                    <input type="text" name="{{ $inputName }}" id="{{ $inputId }}" value="{{ $subfieldValue }}" class="field-widget__control">
                            @endswitch
                            @if(!empty($subfield['description']))
                            <small class="field-repeater__subfield-help">{{ $subfield['description'] }}</small>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
            
            @if($canAdd)
            <div class="field-repeater__actions">
                <button type="button" class="field-repeater__add btn btn-sm btn-secondary" onclick="window.addRepeaterItem('{{ $fieldId }}')">
                    + Add {{ $itemLabel }}
                </button>
            </div>
            @endif
            
            {{-- Template for new items --}}
            <template id="{{ $fieldId }}_template">
                <div class="field-repeater__item" data-index="__INDEX__">
                    <div class="field-repeater__item-header">
                        @if($sortable)
                        <span class="field-repeater__item-drag" title="Drag to reorder">⋮⋮</span>
                        @endif
                        <span class="field-repeater__item-label">{{ $itemLabel }}</span>
                        <button type="button" class="field-repeater__item-toggle" title="Toggle">▼</button>
                        <button type="button" class="field-repeater__item-remove" title="Remove" onclick="window.removeRepeaterItem(this)">×</button>
                    </div>
                    <div class="field-repeater__item-content">
                        @foreach($subfields as $subfield)
                        @php
                            $subfieldName = $subfield['name'] ?? $subfield['machine_name'] ?? '';
                            $subfieldType = $subfield['type'] ?? 'string';
                            $subfieldLabel = $subfield['label'] ?? ucfirst(str_replace('_', ' ', $subfieldName));
                            $inputName = "{$fieldName}[__INDEX__][{$subfieldName}]";
                            $inputId = "{$fieldId}___INDEX___{$subfieldName}";
                        @endphp
                        <div class="field-repeater__subfield field-repeater__subfield--{{ $subfieldType }}">
                            <label for="{{ $inputId }}">{{ $subfieldLabel }}</label>
                            @switch($subfieldType)
                                @case('text')
                                @case('textarea')
                                    <textarea name="{{ $inputName }}" id="{{ $inputId }}" class="field-widget__control" rows="3">{{ $subfield['default'] ?? '' }}</textarea>
                                    @break
                                @case('select')
                                    <select name="{{ $inputName }}" id="{{ $inputId }}" class="field-widget__control">
                                        @foreach($subfield['options'] ?? [] as $optVal => $optLabel)
                                        <option value="{{ $optVal }}">{{ $optLabel }}</option>
                                        @endforeach
                                    </select>
                                    @break
                                @case('checkbox')
                                @case('boolean')
                                    <input type="checkbox" name="{{ $inputName }}" id="{{ $inputId }}" value="1">
                                    @break
                                @case('number')
                                @case('integer')
                                    <input type="number" name="{{ $inputName }}" id="{{ $inputId }}" value="{{ $subfield['default'] ?? '' }}" class="field-widget__control">
                                    @break
                                @case('image')
                                    <div class="field-repeater__image-picker">
                                        <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" value="" class="field-image-input">
                                        <button type="button" class="btn btn-sm btn-secondary" onclick="window.selectImage('{{ $inputId }}')">Select Image</button>
                                    </div>
                                    @break
                                @default
                                    <input type="text" name="{{ $inputName }}" id="{{ $inputId }}" value="{{ $subfield['default'] ?? '' }}" class="field-widget__control">
                            @endswitch
                        </div>
                        @endforeach
                    </div>
                </div>
            </template>
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
<link rel="stylesheet" href="/css/fields/repeater.css">
@endpush

@push('scripts')
<script src="/js/fields/repeater.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    window.initRepeater && window.initRepeater('{{ $fieldId }}', @json($subfields), @json($items), {
        minItems: {{ $minItems }},
        maxItems: {{ $maxItems }},
        sortable: {{ $sortable ? 'true' : 'false' }},
        collapsed: {{ $collapsed ? 'true' : 'false' }},
        itemLabel: '{{ $itemLabel }}'
    });
});
</script>
@endpush
