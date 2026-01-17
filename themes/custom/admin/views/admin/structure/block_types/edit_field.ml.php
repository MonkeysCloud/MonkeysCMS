@extends('layouts/admin')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-4">
                <a href="{{ $cancel_url }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <h2 class="text-2xl font-bold text-gray-900">Edit Field: {{ $field['label'] }}</h2>
            </div>
        </div>
    </div>

    <x-ui.card class="max-w-3xl">
        @if(isset($error))
            <div class="rounded-md bg-red-50 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Error</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <p>{{ $error }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <form method="post" action="{{ $action }}">
            
            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Label</label>
                <input type="text" name="name" id="name" required value="{{ $field['label'] }}"
                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
            </div>

            <div class="mb-4">
                <label for="machine_name" class="block text-sm font-medium text-gray-700 mb-1">Machine Name</label>
                <input type="text" name="machine_name" id="machine_name" value="{{ $machine_name }}" disabled
                    class="block w-full rounded-lg border-gray-300 bg-gray-50 text-gray-500 shadow-sm sm:text-sm px-3 py-2 cursor-not-allowed">
                <p class="mt-1 text-sm text-gray-500">Machine name cannot be changed.</p>
            </div>

            <div class="mb-4">
                <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Field Type</label>
                <select name="type" id="type" disabled
                    class="block w-full rounded-lg border-gray-300 bg-gray-50 text-gray-500 shadow-sm sm:text-sm px-3 py-2 cursor-not-allowed">
                    <option value="">Select a field type...</option>
                    @foreach($grouped_types as $category => $types)
                        <optgroup label="{{ $category }}">
                            @foreach($types as $type_enum)
                                <option value="{{ $type_enum->value }}" {{ $type_enum->value === ($field['type']->value ?? $field['type']) ? 'selected' : '' }}>
                                    {{ $type_enum->getLabel() }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <!-- Hidden input to submit the type value if needed, but usually we don't update type -->
                <p class="mt-1 text-sm text-gray-500">Field type cannot be changed.</p>
            </div>

            <div class="mb-4">
                <label for="widget" class="block text-sm font-medium text-gray-700 mb-1">Form Widget</label>
                <select name="widget" id="widget"
                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
                    @foreach($widget_options as $widget_id => $widget_label)
                        <option value="{{ $widget_id }}" {{ $widget_id === ($field['widget'] ?? '') ? 'selected' : '' }}>
                            {{ $widget_label }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-sm text-gray-500">Controls how this field is entered in forms.</p>
            </div>

            @if(!empty($widget_settings_schema))
                <div class="mb-6 bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <h3 class="text-sm font-medium text-gray-900 mb-4">Widget Settings</h3>
                    
                    @foreach($widget_settings_schema as $key => $schema)
                        <div class="mb-4 last:mb-0">
                            <label for="settings_{{ $key }}" class="block text-sm font-medium text-gray-700 mb-1">
                                {{ $schema['label'] }}
                            </label>

                            @if($schema['type'] === 'boolean')
                                <div class="relative flex items-start">
                                    <div class="flex h-5 items-center">
                                        <input type="hidden" name="settings[{{ $key }}]" value="0">
                                        <input id="settings_{{ $key }}" name="settings[{{ $key }}]" type="checkbox" value="1"
                                            {{ ($widget_settings[$key] ?? $schema['default'] ?? false) ? 'checked' : '' }}
                                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="settings_{{ $key }}" class="font-medium text-gray-700">Enable</label>
                                    </div>
                                </div>

                            @elseif($schema['type'] === 'select')
                                <select name="settings[{{ $key }}]" id="settings_{{ $key }}"
                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
                                    @foreach($schema['options'] as $optValue => $optLabel)
                                        <option value="{{ $optValue }}" 
                                            {{ (string)($widget_settings[$key] ?? $schema['default'] ?? '') === (string)$optValue ? 'selected' : '' }}>
                                            {{ $optLabel }}
                                        </option>
                                    @endforeach
                                </select>

                            @elseif($schema['type'] === 'multiselect')
                                <select name="settings[{{ $key }}][]" id="settings_{{ $key }}" multiple
                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 h-32">
                                    @php
                                        $selectedValues = $widget_settings[$key] ?? $schema['default'] ?? [];
                                        if (!is_array($selectedValues)) $selectedValues = [];
                                    @endphp
                                    @foreach($schema['options'] as $optValue => $optLabel)
                                        <option value="{{ $optValue }}"
                                            {{ in_array((string)$optValue, $selectedValues) ? 'selected' : '' }}>
                                            {{ $optLabel }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500">Hold Ctrl/Cmd to select multiple.</p>

                            @elseif($schema['type'] === 'key_value')
                                <div x-data="{
                                    rows: [],
                                    init() {
                                        try {
                                            const raw = document.getElementById('settings_{{ $key }}').value;
                                            const data = raw ? JSON.parse(raw) : {};
                                            this.rows = Object.entries(data).map(([k, v]) => ({ key: k, value: v }));
                                        } catch(e) {
                                            this.rows = [];
                                        }
                                        if (this.rows.length === 0) this.addRow();
                                        this.$watch('rows', () => this.updateJson());
                                    },
                                    addRow() {
                                        this.rows.push({ key: '', value: '' });
                                        this.updateJson();
                                    },
                                    removeRow(index) {
                                        this.rows.splice(index, 1);
                                        this.updateJson();
                                    },
                                    updateJson() {
                                        const obj = {};
                                        this.rows.forEach(r => {
                                            if(r.key) obj[r.key] = r.value;
                                        });
                                        document.getElementById('settings_{{ $key }}').value = JSON.stringify(obj);
                                    }
                                }">
                                    <div class="space-y-2 mb-2">
                                        <template x-for="(row, index) in rows" :key="index">
                                            <div class="flex gap-2 items-center">
                                                <input type="text" x-model="row.key" placeholder="Value (Key)" 
                                                    class="block w-1/3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
                                                <input type="text" x-model="row.value" placeholder="Label (Text)" 
                                                    class="block w-2/3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
                                                <button type="button" @click="removeRow(index)" class="text-red-500 hover:text-red-700 p-2">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                    <button type="button" @click="addRow()" class="text-sm text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        Add Option
                                    </button>
                                    <!-- Hidden input stores the actual JSON string -->
                                    <input type="hidden" name="settings[{{ $key }}]" id="settings_{{ $key }}"
                                        value="{{ $widget_settings[$key] ?? json_encode($schema['default'] ?? []) }}">
                                </div>

                            @else
                                <input type="text" name="settings[{{ $key }}]" id="settings_{{ $key }}"
                                    value="{{ $widget_settings[$key] ?? $schema['default'] ?? '' }}"
                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="mb-4">
                <div class="relative flex items-start">
                    <div class="flex h-5 items-center">
                        <input type="hidden" name="required" value="0">
                        <input id="required" name="required" type="checkbox" value="1" {{ !empty($field['required']) ? 'checked' : '' }}
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="required" class="font-medium text-gray-700">Required field</label>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <div class="relative flex items-start">
                    <div class="flex h-5 items-center">
                        <input type="hidden" name="multiple" value="0">
                        <input id="multiple" name="multiple" type="checkbox" value="1" {{ !empty($field['multiple']) ? 'checked' : '' }}
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="multiple" class="font-medium text-gray-700">Allow multiple values</label>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Help Text</label>
                <textarea name="description" id="description" rows="2"
                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">{{ $field['description'] ?? '' }}</textarea>
            </div>

            <div class="pt-4 border-t border-gray-100 flex justify-end">
                <x-ui.button type="submit">
                    Save Changes
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
