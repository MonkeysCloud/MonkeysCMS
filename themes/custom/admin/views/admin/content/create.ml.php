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
