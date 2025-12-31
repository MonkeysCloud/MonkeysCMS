@extends('layouts.admin')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">
                {{ $isNew ? 'Create Vocabulary' : 'Edit Vocabulary' }}
            </h2>
            <p class="mt-1 text-sm text-gray-500">
                Define the properties and settings for this vocabulary.
            </p>
        </div>
        <a href="/admin/taxonomies" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            <svg class="-ml-1 mr-2 h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to List
        </a>
    </div>

    <x-ui.card class="bg-white shadow overflow-hidden sm:rounded-lg">
        @php
            $vocabularyName = $vocabulary->name;
            $vocabularyMachineName = $vocabulary->machine_name;
            $vocabularyDescription = $vocabulary->description;
            $formAction = $isNew ? '/admin/taxonomies' : '/admin/taxonomies/' . $vocabulary->id;
        @endphp
        <form action="{{ $formAction }}" method="POST" class="p-6 space-y-6">
            
            <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                <!-- Name -->
                <div class="sm:col-span-4">
                    <x-form.input 
                        name="name" 
                        label="Name" 
                        :value="$vocabularyName" 
                        required 
                        placeholder="e.g. Categories, Tags"
                    />
                </div>

                <!-- Machine Name -->
                <div class="sm:col-span-4">
                    <x-form.input 
                        name="machine_name" 
                        label="Machine Name" 
                        :value="$vocabularyMachineName" 
                        help="Unique identifier (slug). Leave blank to auto-generate from name." 
                        class="font-mono text-sm"
                    />
                </div>

                <!-- Description -->
                <div class="sm:col-span-6">
                    <x-form.textarea 
                        name="description" 
                        label="Description" 
                        :value="$vocabularyDescription" 
                        rows="3"
                        help="Brief description of what this vocabulary is used for."
                    />
                </div>
            </div>

            <div class="border-t border-gray-200 pt-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Settings</h3>
                
                <div class="space-y-4">
                    <div class="flex items-start">
                        <div class="flex items-center h-5">
                            <input id="hierarchical" name="hierarchical" type="checkbox" value="1" {{ $vocabulary->hierarchical ? 'checked' : '' }} class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="hierarchical" class="font-medium text-gray-700">Hierarchical</label>
                            <p class="text-gray-500">Allows terms to have parents/children (like Categories). Uncheck for flat lists (like Tags).</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex items-center h-5">
                            <input id="multiple" name="multiple" type="checkbox" value="1" {{ $vocabulary->multiple ? 'checked' : '' }} class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="multiple" class="font-medium text-gray-700">Allow Multiple</label>
                            <p class="text-gray-500">Can content items select more than one term from this vocabulary?</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex items-center h-5">
                            <input id="required" name="required" type="checkbox" value="1" {{ $vocabulary->required ? 'checked' : '' }} class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="required" class="font-medium text-gray-700">Required</label>
                            <p class="text-gray-500">Is selecting a term mandatory when editing content?</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pt-5 border-t border-gray-200 flex justify-end">
                <a href="/admin/taxonomies" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-3">Cancel</a>
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    {{ $isNew ? 'Create Vocabulary' : 'Save Changes' }}
                </button>
            </div>
        </form>
    </x-ui.card>
</div>
@endsection
