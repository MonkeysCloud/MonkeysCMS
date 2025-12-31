@extends('layouts.admin')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <div class="flex items-center gap-2">
                <a href="/admin/taxonomies/{{ $vocabulary->id }}/terms" class="text-gray-500 hover:text-gray-700">
                    {{ $vocabulary->name }}
                </a>
                <span class="text-gray-300">/</span>
                <h2 class="text-2xl font-bold text-gray-900">
                    {{ $isNew ? 'New Term' : 'Edit Term' }}
                </h2>
            </div>
        </div>
        <a href="/admin/taxonomies/{{ $vocabulary->id }}/terms" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
            Cancel
        </a>
    </div>

    <x-ui.card class="bg-white shadow overflow-hidden sm:rounded-lg">
        <?php
            $termName = $term->name;
            $termSlug = $term->slug;
            $termDescription = $term->description;
            $termWeight = $term->weight ?? 0;
            $termParentId = $term->parent_id;
            
            $formUrl = '/admin/taxonomies/' . $vocabulary->id . '/terms';
            if (!$isNew) {
                $formUrl .= '/' . $term->id;
            }
        ?>
        <form action="{{ $formUrl }}" method="POST" class="p-6 space-y-6">
            
            <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                <!-- Name -->
                <div class="sm:col-span-4">
                    <x-form.input 
                        name="name" 
                        label="Name" 
                        :value="$termName" 
                        required 
                    />
                </div>

                <!-- Slug -->
                <div class="sm:col-span-4">
                    <x-form.input 
                        name="slug" 
                        label="Slug" 
                        :value="$termSlug" 
                        placeholder="auto-generated"
                        help="URL-friendly identifier." 
                        class="font-mono text-sm"
                    />
                </div>

                <!-- Parent (if hierarchical) -->
                @if($vocabulary->hierarchical)
                <div class="sm:col-span-4">
                    <x-form.select 
                        name="parent_id" 
                        label="Parent Term" 
                        :options="$termOptions"
                        :selected="$termParentId"
                        placeholder="<Root>"
                        help="Select a parent term for hierarchy."
                    />
                </div>
                @endif

                <!-- Description -->
                <div class="sm:col-span-6">
                    <x-form.textarea 
                        name="description" 
                        label="Description" 
                        :value="$termDescription" 
                        rows="3"
                    />
                </div>

                <!-- Weight -->
                <div class="sm:col-span-2">
                    <x-form.input 
                        name="weight" 
                        type="number"
                        label="Weight" 
                        :value="$termWeight" 
                        help="Order in lists (lower numbers first)."
                    />
                </div>
                
                <!-- Published -->
                <div class="sm:col-span-6 pt-2">
                     <div class="flex items-start">
                        <div class="flex items-center h-5">
                            <input id="is_published" name="is_published" type="checkbox" value="1" {{ ($isNew || $term->is_published) ? 'checked' : '' }} class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="is_published" class="font-medium text-gray-700">Published</label>
                            <p class="text-gray-500">Uncheck to hide this term from public views.</p>
                        </div>
                    </div>
                </div>

            </div>

            <div class="pt-5 border-t border-gray-200 flex justify-end">
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    {{ $isNew ? 'Create Term' : 'Save Changes' }}
                </button>
            </div>
        </form>
    </x-ui.card>
</div>
@endsection
