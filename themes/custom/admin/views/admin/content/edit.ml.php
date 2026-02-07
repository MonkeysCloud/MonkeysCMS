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
                <p class="text-sm text-gray-500">Edit {{ $type['label'] }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-3xl">{{ $type['icon'] ?? '📄' }}</span>
            @if(!empty($item['slug']))
                <a href="/{{ $item['slug'] }}" target="_blank" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    View
                </a>
            @endif
        </div>
    </div>

    @if(!empty($error))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <p class="text-sm text-red-600">{{ $error }}</p>
        </div>
    @endif

    {{-- Tab Navigation (only when composer is enabled) --}}
    @if($type['composer_enabled'] ?? false)
        <div class="border-b border-gray-200 mb-6">
            <nav class="-mb-px flex gap-8" aria-label="Tabs">
                <a href="/admin/content/{{ $type_id }}/{{ $item['id'] }}/edit"
                   class="border-blue-500 text-blue-600 group inline-flex items-center border-b-2 py-4 px-1 text-sm font-medium" aria-current="page">
                   <svg class="-ml-0.5 mr-2 h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                       <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                   </svg>
                   <span>Content</span>
                </a>
                <a href="/admin/composer/node/{{ $item['id'] }}"
                   class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 group inline-flex items-center border-b-2 py-4 px-1 text-sm font-medium">
                   <svg class="-ml-0.5 mr-2 h-5 w-5 text-gray-400 group-hover:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                       <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                   </svg>
                   <span>Layout</span>
                </a>
            </nav>
        </div>
    @endif

    <form method="POST" action="{{ $action }}" class="space-y-6">
        {{-- Core Fields --}}
        <x-ui.card>
            <div class="p-6 space-y-4">
                <h2 class="text-lg font-semibold text-gray-900 border-b pb-2">Basic Information</h2>
                
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                    <input type="text" name="title" id="title" required
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2"
                        value="{{ $item['title'] ?? '' }}"
                        placeholder="Enter a title...">
                </div>

                <div>
                    <label for="slug" class="block text-sm font-medium text-gray-700 mb-1">URL Slug</label>
                    <input type="text" name="slug" id="slug"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2"
                        value="{{ $item['slug'] ?? '' }}"
                        placeholder="url-slug">
                    <p id="url-preview" class="mt-2 text-sm text-gray-600">
                        URL: <span id="url-preview-full" class="font-mono text-blue-600">/{{ $item['slug'] ?? '' }}</span>
                    </p>
                </div>
            </div>
        </x-ui.card>

        {{-- Dynamic Fields --}}
        @if(!empty($renderedFields))
            <x-ui.card>
                <div class="p-6 space-y-6">
                    <h2 class="text-lg font-semibold text-gray-900 border-b pb-2">Content Fields</h2>
                    
                    @foreach($renderedFields as $machineName => $field)
                        <div class="form-group field-{{ str_replace('_', '-', $machineName) }}">
                            {!! $field['html'] !!}
                        </div>
                    @endforeach
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
                            <option value="draft" {{ ($item['status'] ?? 'draft') === 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="published" {{ ($item['status'] ?? '') === 'published' ? 'selected' : '' }}>Published</option>
                        </select>
                    </div>

                    <div>
                        <label for="published_at" class="block text-sm font-medium text-gray-700 mb-1">Publish Date</label>
                        <input type="datetime-local" name="published_at" id="published_at"
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2"
                            value="{{ !empty($item['published_at']) ? date('Y-m-d\TH:i', strtotime($item['published_at'])) : '' }}">
                        <p class="mt-1 text-xs text-gray-500">Leave blank to publish immediately</p>
                    </div>
                </div>
            </x-ui.card>
        @endif

        {{-- Meta Information --}}
        <x-ui.card>
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900 border-b pb-2 mb-4">Meta Information</h2>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">Created:</span>
                        <span class="text-gray-900">{{ $item['created_at'] ?? 'N/A' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Updated:</span>
                        <span class="text-gray-900">{{ $item['updated_at'] ?? 'N/A' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">ID:</span>
                        <span class="text-gray-900 font-mono">{{ $item['id'] ?? 'N/A' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">UUID:</span>
                        <span class="text-gray-900 font-mono text-xs">{{ $item['uuid'] ?? 'N/A' }}</span>
                    </div>
                </div>
            </div>
        </x-ui.card>

        {{-- Actions --}}
        <div class="flex justify-between items-center">
            <div>
                <a href="/admin/content/{{ $type_id }}/{{ $item['id'] }}/delete" 
                   class="text-red-600 hover:text-red-800 text-sm"
                   onclick="return confirm('Are you sure you want to delete this content?')">
                    Delete
                </a>
            </div>
            <div class="flex gap-3">
                <a href="{{ $cancel_url }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                    Save Changes
                </button>
            </div>
        </div>
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const slugInput = document.getElementById('slug');
        const urlPreviewFull = document.getElementById('url-preview-full');
        
        function updateUrlPreview() {
            const slug = slugInput.value || 'your-slug';
            urlPreviewFull.textContent = '/' + slug;
        }
        
        slugInput?.addEventListener('input', updateUrlPreview);
    });
    </script>
</div>
@endsection
