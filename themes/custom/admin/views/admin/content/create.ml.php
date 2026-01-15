@extends('layouts/admin')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ $cancel_url }}" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>
                <p class="text-sm text-gray-500">Create a new {{ $type['label'] }}</p>
            </div>
        </div>
        <span class="text-3xl">{{ $type['icon'] ?? '📄' }}</span>
    </div>

    <form method="POST" action="{{ $action }}" class="space-y-6">
        {{-- Core Fields --}}
        <x-ui.card>
            <div class="p-6 space-y-4">
                <h2 class="text-lg font-semibold text-gray-900 border-b pb-2">Basic Information</h2>
                
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                    <input type="text" name="title" id="title" required
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2"
                        placeholder="Enter a title...">
                </div>

                <div>
                    <label for="slug" class="block text-sm font-medium text-gray-700 mb-1">URL Slug</label>
                    <div class="flex items-center">
                        <span class="text-sm text-gray-500 mr-2">/{{ $type_id }}/</span>
                        <input type="text" name="slug" id="slug"
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2"
                            placeholder="url-slug">
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Leave blank to auto-generate from title</p>
                </div>
            </div>
        </x-ui.card>

        {{-- Body / Content Field --}}
        <x-ui.card>
            <div class="p-6 space-y-4">
                <div class="flex items-center justify-between border-b pb-2">
                    <h2 class="text-lg font-semibold text-gray-900">Content</h2>
                    <div class="flex items-center gap-2">
                        <label for="body_format" class="text-sm text-gray-600">Format:</label>
                        <select name="body_format" id="body_format" 
                            class="rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-2 py-1">
                            <option value="html" {{ ($body_format ?? 'html') === 'html' ? 'selected' : '' }}>HTML (WYSIWYG)</option>
                            <option value="markdown" {{ ($body_format ?? '') === 'markdown' ? 'selected' : '' }}>Markdown</option>
                            <option value="plain" {{ ($body_format ?? '') === 'plain' ? 'selected' : '' }}>Plain Text</option>
                        </select>
                    </div>
                </div>
                
                {{-- Dynamically rendered body field --}}
                @if(isset($renderedBodyField))
                    {!! $renderedBodyField['html'] !!}
                @else
                    <textarea name="body" id="body" rows="10"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2"
                        placeholder="Enter your content..."></textarea>
                @endif
            </div>
        </x-ui.card>

        {{-- Dynamic Fields --}}
        @if(!empty($fields))
            <x-ui.card>
                <div class="p-6 space-y-4">
                    <h2 class="text-lg font-semibold text-gray-900 border-b pb-2">Content Fields</h2>
                    
                    @foreach($fields as $field)
                        <div>
                            <label for="field_{{ $field['machine_name'] ?? $field->machine_name }}" class="block text-sm font-medium text-gray-700 mb-1">
                                {{ $field['label'] ?? $field['name'] ?? $field->name }}
                                @if($field['required'] ?? ($field->required ?? false))
                                    <span class="text-red-500">*</span>
                                @endif
                            </label>
                            @php
                                $fieldType = $field['type'] ?? ($field->field_type ?? 'string');
                                $machineName = $field['machine_name'] ?? $field->machine_name;
                            @endphp
                            
                            @if($fieldType === 'text' || $fieldType === 'textarea')
                                <textarea name="field_{{ $machineName }}" id="field_{{ $machineName }}" rows="4"
                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2"></textarea>
                            @elseif($fieldType === 'boolean')
                                <input type="checkbox" name="field_{{ $machineName }}" id="field_{{ $machineName }}" value="1"
                                    class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600">
                            @elseif($fieldType === 'integer' || $fieldType === 'float' || $fieldType === 'decimal')
                                <input type="number" name="field_{{ $machineName }}" id="field_{{ $machineName }}"
                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2"
                                    @if($fieldType !== 'integer') step="0.01" @endif>
                            @elseif($fieldType === 'date')
                                <input type="date" name="field_{{ $machineName }}" id="field_{{ $machineName }}"
                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
                            @elseif($fieldType === 'datetime')
                                <input type="datetime-local" name="field_{{ $machineName }}" id="field_{{ $machineName }}"
                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
                            @elseif($fieldType === 'email')
                                <input type="email" name="field_{{ $machineName }}" id="field_{{ $machineName }}"
                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
                            @elseif($fieldType === 'url')
                                <input type="url" name="field_{{ $machineName }}" id="field_{{ $machineName }}"
                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
                            @else
                                <input type="text" name="field_{{ $machineName }}" id="field_{{ $machineName }}"
                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
                            @endif
                            
                            @if(!empty($field['description'] ?? ($field->description ?? null)))
                                <p class="mt-1 text-xs text-gray-500">{{ $field['description'] ?? $field->description }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        @else
            <x-ui.card>
                <div class="p-6 text-center text-gray-500">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="mt-2">No custom fields defined for this content type.</p>
                    <a href="/admin/structure/content-types/{{ $type_id }}/fields/add" class="text-blue-600 hover:text-blue-800 text-sm">Add fields</a>
                </div>
            </x-ui.card>
        @endif

        {{-- Publishing Options --}}
        @if($type['publishable'] ?? false)
            <x-ui.card>
                <div class="p-6 space-y-4">
                    <h2 class="text-lg font-semibold text-gray-900 border-b pb-2">Publishing</h2>
                    
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" id="status"
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                        </select>
                    </div>

                    <div>
                        <label for="published_at" class="block text-sm font-medium text-gray-700 mb-1">Publish Date</label>
                        <input type="datetime-local" name="published_at" id="published_at"
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
                        <p class="mt-1 text-xs text-gray-500">Leave blank to publish immediately</p>
                    </div>
                </div>
            </x-ui.card>
        @endif

        {{-- Actions --}}
        <div class="flex justify-end gap-3">
            <a href="{{ $cancel_url }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                Cancel
            </a>
            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                Create {{ $type['label'] }}
            </button>
        </div>
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const formatSelect = document.getElementById('body_format');
        if (formatSelect) {
            formatSelect.addEventListener('change', function() {
                const newFormat = this.value;
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('body_format', newFormat);
                window.location.href = currentUrl.toString();
            });
        }
    });
    </script>
</div>
@endsection
