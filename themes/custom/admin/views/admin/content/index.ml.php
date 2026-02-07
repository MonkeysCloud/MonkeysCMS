@extends('layouts/admin')

@section('content')
<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $title ?? 'Content' }}</h1>
            <p class="mt-1 text-sm text-gray-600">Create and manage content across your site</p>
        </div>
    </div>

    @if(!empty($types))
        {{-- Content Types Grid --}}
        <div class="mb-8">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Add Content</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                @foreach($types as $type)
                    <a href="/admin/content/{{ $type['id'] }}/add" 
                       class="group flex flex-col items-center p-6 bg-white border border-gray-200 rounded-lg hover:border-blue-500 hover:shadow-md transition-all">
                        <span class="text-4xl mb-3">{{ $type['icon'] ?? '📄' }}</span>
                        <span class="text-sm font-medium text-gray-900 group-hover:text-blue-600">{{ $type['label'] }}</span>
                        @if(!empty($type['description']))
                            <span class="text-xs text-gray-500 text-center mt-1 line-clamp-2">{{ $type['description'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Filter Card - matches Users page pattern --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-4">
            <form method="GET" action="/admin/content" class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <input type="text" 
                           name="search" 
                           value="{{ $filters['search'] ?? '' }}"
                           placeholder="Search by title or slug..."
                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
                </div>
                <div class="flex flex-wrap gap-2">
                    <select name="type" class="rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
                        <option value="">All Types</option>
                        @foreach($types as $type)
                            <option value="{{ $type['id'] }}" {{ ($filters['type'] ?? '') === $type['id'] ? 'selected' : '' }}>{{ $type['label'] }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
                        <option value="">All Status</option>
                        <option value="published" {{ ($filters['status'] ?? '') === 'published' ? 'selected' : '' }}>Published</option>
                        <option value="draft" {{ ($filters['status'] ?? '') === 'draft' ? 'selected' : '' }}>Draft</option>
                    </select>
                    <select name="sort" class="rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
                        <option value="created_at" {{ ($filters['sort'] ?? 'created_at') === 'created_at' ? 'selected' : '' }}>Newest</option>
                        <option value="updated_at" {{ ($filters['sort'] ?? '') === 'updated_at' ? 'selected' : '' }}>Updated</option>
                        <option value="title" {{ ($filters['sort'] ?? '') === 'title' ? 'selected' : '' }}>Title</option>
                    </select>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow-sm text-sm">
                        Search
                    </button>
                    @if(!empty($filters['search']) || !empty($filters['type']) || !empty($filters['status']))
                    <a href="/admin/content" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg shadow-sm text-sm flex items-center">
                        Clear
                    </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Content List Table --}}
        <div class="bg-white rounded-lg shadow overflow-hidden">
            {{-- Bulk Actions Bar --}}
            <div id="bulkActionsBar" class="hidden bg-blue-50 border-b border-blue-200 px-4 py-3">
                <form id="bulkActionForm" method="POST" action="/admin/content/bulk" class="flex items-center gap-4">
                    <span class="text-sm text-blue-800 font-medium">
                        <span id="selectedCount">0</span> item(s) selected
                    </span>
                    <select name="action" id="bulkAction" class="rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm px-3 py-1.5">
                        <option value="">Choose action...</option>
                        <option value="publish">Publish</option>
                        <option value="unpublish">Unpublish (Draft)</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg shadow-sm text-sm">
                        Apply
                    </button>
                    <button type="button" onclick="clearSelection()" class="text-blue-600 hover:text-blue-800 text-sm">
                        Clear selection
                    </button>
                </form>
            </div>

            @if(!empty($recentContent))
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-left">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b">
                            <tr>
                                <th scope="col" class="px-4 py-3 w-10">
                                    <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" onchange="toggleSelectAll(this)">
                                </th>
                                <th scope="col" class="px-4 py-3">Title</th>
                                <th scope="col" class="px-4 py-3">Type</th>
                                <th scope="col" class="px-4 py-3 hidden md:table-cell">Author</th>
                                <th scope="col" class="px-4 py-3">Status</th>
                                <th scope="col" class="px-4 py-3 hidden lg:table-cell">Created</th>
                                <th scope="col" class="px-4 py-3 hidden xl:table-cell">Updated</th>
                                <th scope="col" class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                                @foreach($recentContent as $content)
                                    <tr class="bg-white border-b hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <input type="checkbox" 
                                                   name="items[]" 
                                                   value="{{ $content['type'] }}:{{ $content['id'] }}" 
                                                   class="item-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                   onchange="updateBulkActions()">
                                        </td>
                                        <td class="px-4 py-3">
                                            <a href="/admin/content/{{ $content['type'] }}/{{ $content['id'] }}/edit" class="text-blue-600 hover:text-blue-800 font-medium">
                                                {{ $content['title'] }}
                                            </a>
                                            <div class="text-xs text-gray-400 mt-0.5">/{{ $content['slug'] }}</div>
                                        </td>
                                        <td class="py-3 px-4">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                {{ $content['type_label'] ?? $content['type'] }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-sm text-gray-600">
                                            {{ $content['user_name'] ?? 'Unknown' }}
                                        </td>
                                        <td class="py-3 px-4">
                                            @if(($content['status'] ?? 'draft') === 'published')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Published</span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Draft</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4 text-sm text-gray-500">
                                            {{ $content['created_at'] ? date('M j, Y', strtotime($content['created_at'])) : '-' }}
                                        </td>
                                        <td class="py-3 px-4 text-sm text-gray-500">
                                            {{ $content['updated_at'] ? date('M j, Y g:i A', strtotime($content['updated_at'])) : '-' }}
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <!-- View -->
                                                <a href="/{{ $content['slug'] ?? $content['type'] . '/' . $content['id'] }}" 
                                                   target="_blank"
                                                   class="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-600 bg-gray-100 rounded hover:bg-gray-200 transition-colors"
                                                   title="View">
                                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                    </svg>
                                                    View
                                                </a>
                                                <!-- Edit -->
                                                <a href="/admin/content/{{ $content['type'] }}/{{ $content['id'] }}/edit" 
                                                   class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-600 bg-blue-50 rounded hover:bg-blue-100 transition-colors"
                                                   title="Edit">
                                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                    </svg>
                                                    Edit
                                                </a>
                                                <!-- Delete -->
                                                <button type="button"
                                                        onclick="confirmDelete('{{ $content['type'] }}', {{ $content['id'] }}, '{{ addslashes($content['title']) }}')"
                                                        class="inline-flex items-center px-2 py-1 text-xs font-medium text-red-600 bg-red-50 rounded hover:bg-red-100 transition-colors"
                                                        title="Delete">
                                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No content found</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            @if(!empty($filters['search']) || !empty($filters['type']) || !empty($filters['status']))
                                Try adjusting your filters or <a href="/admin/content" class="text-blue-600 hover:underline">clear all filters</a>.
                            @else
                                Get started by creating your first piece of content.
                            @endif
                        </p>
                        <div class="mt-6">
                            @if(!empty($types))
                                <a href="/admin/content/{{ array_key_first($types) }}/add" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Create Content
                                </a>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @else
        {{-- No content types defined --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-900">No content types defined</h3>
            <p class="mt-2 text-sm text-gray-500 max-w-md mx-auto">Before you can create content, you need to define at least one content type. Content types define the structure and fields for your content.</p>
            <div class="mt-6">
                <a href="/admin/structure/content-types/create" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Create Content Type
                </a>
            </div>
        </div>
    @endif
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeDeleteModal()"></div>
        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-red-100">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 text-center mb-2">Delete Content</h3>
            <p class="text-sm text-gray-500 text-center mb-6">
                Are you sure you want to delete "<span id="deleteTitle" class="font-medium text-gray-900"></span>"? 
                This action cannot be undone.
            </p>
            <div class="flex gap-3">
                <button type="button" onclick="closeDeleteModal()" 
                        class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                    Cancel
                </button>
                <form id="deleteForm" method="POST" class="flex-1">
                    <button type="submit" 
                            class="w-full px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="/js/content-bulk.js"></script>
<script>
function confirmDelete(type, id, title) {
    document.getElementById('deleteTitle').textContent = title;
    document.getElementById('deleteForm').action = '/admin/content/' + type + '/' + id + '/delete';
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDeleteModal();
});
</script>
@endsection
