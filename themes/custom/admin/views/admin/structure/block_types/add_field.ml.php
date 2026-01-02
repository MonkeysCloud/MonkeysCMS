@extends('layouts/admin')

@section('content')
<div class="md:flex md:items-center md:justify-between mb-6">
    <div class="min-w-0 flex-1">
        <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">
            Add Field: {{ $type['label'] }}
        </h2>
    </div>
</div>

<div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl md:col-span-2 max-w-2xl">
    <form action="{{ $action }}" method="POST" class="px-4 py-6 sm:px-6">
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

        <div class="space-y-6">
            <div>
                <label for="name" class="block text-sm font-medium leading-6 text-gray-900">Label</label>
                <div class="mt-2">
                    <input type="text" name="name" id="name" required
                        class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6">
                </div>
            </div>
            
            <div>
                <label for="machine_name" class="block text-sm font-medium leading-6 text-gray-900">Machine Name</label>
                <div class="mt-2">
                    <input type="text" name="machine_name" id="machine_name" placeholder="field_example"
                        class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6">
                     <p class="mt-2 text-sm text-gray-500">Optional. Only lowercase letters, numbers, and underscores.</p>
                </div>
            </div>

            <div>
                <label for="type" class="block text-sm font-medium leading-6 text-gray-900">Field Type</label>
                <div class="mt-2">
                    <select name="type" id="type" required
                        class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6">
                        <option value="">Select a field type...</option>
                        @foreach($grouped_types as $category => $types)
                            <optgroup label="{{ $category }}">
                                @foreach($types as $type_enum)
                                    <option value="{{ $type_enum->value }}">
                                        {{ $type_enum->getLabel() }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex gap-4">
                <div class="flex h-6 items-center">
                    <input id="required" name="required" type="checkbox" value="1"
                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600">
                </div>
                <div class="text-sm leading-6">
                    <label for="required" class="font-medium text-gray-900">Required field</label>
                </div>
            </div>
            
            <div class="flex gap-4">
                <div class="flex h-6 items-center">
                    <input id="multiple" name="multiple" type="checkbox" value="1"
                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600">
                </div>
                <div class="text-sm leading-6">
                    <label for="multiple" class="font-medium text-gray-900">Allow multiple values</label>
                </div>
            </div>

            <div>
                <label for="description" class="block text-sm font-medium leading-6 text-gray-900">Help Text</label>
                <div class="mt-2">
                    <textarea name="description" id="description" rows="2"
                        class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6"></textarea>
                </div>
            </div>
        </div>

        <div class="mt-6 flex items-center justify-end gap-x-6">
            <a href="{{ $cancel_url }}" class="text-sm font-semibold leading-6 text-gray-900">Cancel</a>
            <button type="submit" class="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">Save Field</button>
        </div>
    </form>
</div>
@endsection
