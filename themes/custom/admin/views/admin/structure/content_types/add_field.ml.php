@extends('layouts/admin')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-4">
                <a href="{{ $cancel_url }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <h2 class="text-2xl font-bold text-gray-900">Add Field: {{ $type['label'] }}</h2>
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
                <input type="text" name="name" id="name" required
                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
            </div>

            <div class="mb-4">
                <label for="machine_name" class="block text-sm font-medium text-gray-700 mb-1">Machine Name</label>
                <input type="text" name="machine_name" id="machine_name" placeholder="field_example"
                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
                <p class="mt-1 text-sm text-gray-500">Optional. Only lowercase letters, numbers, and underscores.</p>
            </div>

            <div class="mb-4">
                <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Field Type</label>
                <select name="type" id="type" required
                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
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

            <div class="mb-4">
                <div class="relative flex items-start">
                    <div class="flex h-5 items-center">
                        <input type="hidden" name="required" value="0">
                        <input id="required" name="required" type="checkbox" value="1"
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
                        <input id="multiple" name="multiple" type="checkbox" value="1"
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
                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2"></textarea>
            </div>

            <div class="pt-4 border-t border-gray-100 flex justify-end">
                <x-ui.button type="submit">
                    Save Field
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
