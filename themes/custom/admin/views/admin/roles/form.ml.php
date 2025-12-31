@extends('layouts/admin')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="/admin/roles" class="text-gray-500 hover:text-gray-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
        </a>
        <h1 class="text-xl sm:text-2xl font-bold text-gray-800">{{ $role ? 'Edit Role: ' . $role->name : 'Create New Role' }}</h1>
    </div>

    @if(isset($error))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm" role="alert">
        {{ $error }}
    </div>
    @endif

    <form method="POST" class="bg-white rounded-lg shadow p-4 sm:p-6 space-y-6">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Name -->
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Role Name</label>
                <input type="text" 
                       name="name" 
                       id="name" 
                       value="{{ $role ? $role->name : ($old['name'] ?? '') }}" 
                       required
                       {{ ($role && $role->is_system) ? 'readonly' : '' }}
                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-3 py-2 {{ ($role && $role->is_system) ? 'bg-gray-100 cursor-not-allowed text-gray-500' : '' }}"
                       placeholder="e.g. Editor">
                @if($role && $role->is_system)
                    <p class="mt-1 text-xs text-gray-500">System role names cannot be changed.</p>
                @endif
            </div>

            <!-- Slug -->
            <div>
                <label for="slug" class="block text-sm font-medium text-gray-700 mb-1">Slug (Identifier)</label>
                <input type="text" 
                       name="slug" 
                       id="slug" 
                       value="{{ $role ? $role->slug : ($old['slug'] ?? '') }}" 
                       {{ ($role && $role->is_system) ? 'readonly' : '' }}
                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-3 py-2 font-mono {{ ($role && $role->is_system) ? 'bg-gray-100 cursor-not-allowed text-gray-500' : '' }}"
                       placeholder="e.g. editor">
                 @if(!$role)
                    <p class="mt-1 text-xs text-gray-500">Leave blank to auto-generate from name.</p>
                @endif
            </div>
        </div>

        <!-- Description -->
        <div>
            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" 
                      id="description" 
                      rows="3" 
                      class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-3 py-2">{{ $role ? $role->description : ($old['description'] ?? '') }}</textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
            <!-- Color -->
            <div>
                <label for="color" class="block text-sm font-medium text-gray-700 mb-1">Color</label>
                <div class="flex gap-2">
                    <input type="color" 
                           id="color_picker" 
                           value="{{ $role ? $role->color : ($old['color'] ?? '#6b7280') }}"
                           class="h-10 w-10 p-1 rounded-lg cursor-pointer border border-gray-300 flex-shrink-0">
                    <input type="text" 
                           name="color" 
                           id="color" 
                           value="{{ $role ? $role->color : ($old['color'] ?? '#6b7280') }}" 
                           class="flex-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-3 py-2 uppercase font-mono"
                           pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$">
                </div>
            </div>

            <!-- Weight -->
            <div>
                <label for="weight" class="block text-sm font-medium text-gray-700 mb-1">Weight</label>
                <input type="number" 
                       name="weight" 
                       id="weight" 
                       value="{{ $role ? $role->weight : ($old['weight'] ?? 0) }}" 
                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm px-3 py-2">
                <p class="mt-1 text-xs text-gray-500">Higher weights appear first.</p>
            </div>

            <!-- Is Default -->
            <div class="flex items-center h-full pt-6">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" 
                           name="is_default" 
                           value="1" 
                           {{ ($role && $role->is_default) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 h-5 w-5">
                    <span class="text-sm font-medium text-gray-700">Default Role</span>
                </label>
            </div>
        </div>

        <div class="pt-4 border-t flex flex-col sm:flex-row justify-end gap-3">
            <a href="/admin/roles" class="px-4 py-2 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 text-center">
                Cancel
            </a>
            <button type="submit" class="px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                {{ $role ? 'Update Role' : 'Create Role' }}
            </button>
        </div>
    </form>
</div>

<script>
    // Sync color inputs
    const colorPicker = document.getElementById('color_picker');
    const colorInput = document.getElementById('color');
    
    colorPicker.addEventListener('input', (e) => colorInput.value = e.target.value);
    colorInput.addEventListener('input', (e) => {
        if (/^#[0-9A-F]{6}$/i.test(e.target.value)) {
            colorPicker.value = e.target.value;
        }
    });

    // Auto-generate slug for new roles
    @if(!$role)
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');
    
    nameInput.addEventListener('input', (e) => {
        if (!slugInput.value || slugInput.dataset.touched !== 'true') {
            slugInput.value = e.target.value
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_|_$/g, '');
        }
    });
    
    slugInput.addEventListener('input', () => {
        slugInput.dataset.touched = 'true';
    });
    @endif
</script>
@endsection
