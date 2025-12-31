@extends('layouts/admin')

@section('content')
<div class="page-header mb-4 sm:mb-6">
    {{-- Responsive header --}}
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 sm:gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-gray-800">Roles</h1>
            <p class="text-gray-500 text-sm mt-1 flex flex-wrap items-center gap-2">
                Manage user roles and access levels.
                <span class="text-xs bg-gray-100 px-2 py-0.5 rounded">Drag to reorder</span>
                <span class="sortable-status font-medium text-sm"></span>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="/admin/permissions" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-lg shadow-sm flex items-center gap-2 transition text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                </svg>
                Permissions
            </a>
            <a href="/admin/roles/create" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg shadow-sm flex items-center gap-2 transition text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                </svg>
                New Role
            </a>
        </div>
    </div>
</div>

@if(isset($message))
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm" role="alert">
    {{ $message }}
</div>
@endif

@if(isset($error))
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm" role="alert">
    {{ $error }}
</div>
@endif

{{-- Mobile: Card Layout --}}
<div class="block sm:hidden space-y-3" id="roles-mobile" data-sortable-url="/admin/roles/reorder">
    @foreach($roles as $role)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4" data-id="{{ $role->id }}">
            <div class="flex items-start gap-3">
                {{-- Drag Handle --}}
                <div class="text-gray-400 cursor-move mt-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                    </svg>
                </div>
                
                {{-- Role Info --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="w-4 h-4 rounded-full border shadow-sm flex-shrink-0" style="background-color: {{ $role->color }}"></span>
                        <h3 class="font-medium text-gray-900">{{ $role->name }}</h3>
                        @if($role->is_system)
                            <span class="bg-gray-100 text-gray-600 text-xs px-1.5 py-0.5 rounded">System</span>
                        @endif
                        @if($role->is_default)
                            <span class="bg-blue-100 text-blue-600 text-xs px-1.5 py-0.5 rounded">Default</span>
                        @endif
                    </div>
                    <p class="text-xs text-gray-400 font-mono mt-1">{{ $role->slug }}</p>
                    @if($role->description)
                        <p class="text-sm text-gray-500 mt-2 line-clamp-2">{{ $role->description }}</p>
                    @endif
                    <div class="flex items-center gap-1 mt-2 text-xs text-gray-400">
                        <span>Weight: </span>
                        <span class="weight-cell font-medium">{{ $role->weight }}</span>
                    </div>
                </div>
                
                {{-- Actions --}}
                <div class="flex flex-col gap-2">
                    <a href="/admin/roles/{{ $role->id }}/edit" class="text-blue-600 hover:text-blue-900 p-2 -m-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </a>
                    @if(!$role->is_system)
                        <form action="/admin/roles/{{ $role->id }}/delete" method="POST" onsubmit="return confirm('Delete this role?');">
                            <button type="submit" class="text-red-600 hover:text-red-900 p-2 -m-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
    
    @if(empty($roles))
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center text-gray-500">
            No roles found.
        </div>
    @endif
</div>

{{-- Desktop: Table Layout --}}
<div class="hidden sm:block bg-white rounded-lg shadow overflow-x-auto">
    <table class="min-w-full text-sm text-left">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b">
            <tr>
                <th scope="col" class="px-4 py-3 w-10"></th>
                <th scope="col" class="px-4 py-3 w-16">Weight</th>
                <th scope="col" class="px-4 py-3">Name</th>
                <th scope="col" class="px-4 py-3 hidden md:table-cell">Slug</th>
                <th scope="col" class="px-4 py-3 hidden lg:table-cell">Color</th>
                <th scope="col" class="px-4 py-3 hidden xl:table-cell">Description</th>
                <th scope="col" class="px-4 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody data-sortable-url="/admin/roles/reorder">
            @foreach($roles as $role)
                <tr class="bg-white border-b hover:bg-gray-50 cursor-move group" data-id="{{ $role->id }}">
                    <td class="px-4 py-3 text-gray-400">
                        <svg class="w-4 h-4 cursor-move" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                        </svg>
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-center weight-cell">{{ $role->weight }}</td>
                    <td class="px-4 py-3 font-medium text-gray-900">
                        <div class="flex items-center gap-2 flex-wrap">
                            {{ $role->name }}
                            @if($role->is_system)
                                <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-full">System</span>
                            @endif
                            @if($role->is_default)
                                <span class="bg-blue-100 text-blue-600 text-xs px-2 py-0.5 rounded-full">Default</span>
                            @endif
                        </div>
                        <span class="text-xs text-gray-400 font-mono md:hidden">{{ $role->slug }}</span>
                    </td>
                    <td class="px-4 py-3 font-mono text-gray-500 hidden md:table-cell">{{ $role->slug }}</td>
                    <td class="px-4 py-3 hidden lg:table-cell">
                        <div class="flex items-center gap-2">
                             <span class="w-5 h-5 rounded border shadow-sm flex-shrink-0" style="background-color: {{ $role->color }}"></span>
                             <span class="text-xs text-gray-500">{{ $role->color }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-gray-500 truncate max-w-xs hidden xl:table-cell">{{ $role->description }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <div class="flex justify-end gap-3">
                            <a href="/admin/roles/{{ $role->id }}/edit" class="text-blue-600 hover:text-blue-900 text-sm">Edit</a>
                            @if(!$role->is_system)
                                <form action="/admin/roles/{{ $role->id }}/delete" method="POST" onsubmit="return confirm('Delete this role?');" class="inline">
                                    <button type="submit" class="text-red-600 hover:text-red-900 text-sm">Delete</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
            
            @if(empty($roles))
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                        No roles found.
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle sortable for both mobile cards and desktop table
    const sortableContainers = document.querySelectorAll('[data-sortable-url]');
    sortableContainers.forEach(container => {
        container.addEventListener('sortable:saved', function() {
            const items = container.querySelectorAll('[data-id]');
            items.forEach((item, index) => {
                const weightCell = item.querySelector('.weight-cell');
                if (weightCell) {
                    weightCell.textContent = index;
                    weightCell.classList.add('text-blue-600', 'font-bold');
                    setTimeout(() => weightCell.classList.remove('text-blue-600', 'font-bold'), 2000);
                }
            });
        });
    });
});
</script>
@endsection
