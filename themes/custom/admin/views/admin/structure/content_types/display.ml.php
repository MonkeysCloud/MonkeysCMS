@extends('layouts/admin')

@section('content')
<div class="md:flex md:items-center md:justify-between mb-6">
    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-4">
            <a href="/admin/structure/content-types" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">
                Content Display: {{ $type['label'] }}
            </h2>
        </div>
    </div>
</div>

<div class="border-b border-gray-200 mb-6">
    <nav class="-mb-px flex gap-8" aria-label="Tabs">
        <a href="/admin/structure/content-types/{{ $type['id'] }}/edit"
           class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 group inline-flex items-center border-b-2 py-4 px-1 text-sm font-medium">
           <svg class="-ml-0.5 mr-2 h-5 w-5 text-gray-400 group-hover:text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
               <path fill-rule="evenodd" d="M11.5 2a.75.75 0 01.75.75L15 6h-2.25a.75.75 0 01-.75-.75V2.5zm-2.25 0a.75.75 0 00-.75.75V6a2.25 2.25 0 002.25 2.25h3.75a.75.75 0 00.75-.75V2.5a.75.75 0 00-.75-.75H9.25zM5 2.25A2.25 2.25 0 002.75 4.5v11A2.25 2.25 0 005 17.75h9.5A2.25 2.25 0 0016.75 15.5V9.5a.75.75 0 00-.75-.75H12a3.75 3.75 0 01-3.75-3.75V2.75a.75.75 0 00-.75-.75H5z" clip-rule="evenodd" />
           </svg>
           <span>Settings</span>
        </a>
        <a href="/admin/structure/content-types/{{ $type['id'] }}/fields"
           class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 group inline-flex items-center border-b-2 py-4 px-1 text-sm font-medium">
           <svg class="-ml-0.5 mr-2 h-5 w-5 text-gray-400 group-hover:text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
               <path fill-rule="evenodd" d="M2 3.75A.75.75 0 012.75 3h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 3.75zm0 4.167a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75a.75.75 0 01-.75-.75zm0 4.166a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75a.75.75 0 01-.75-.75zm0 4.167a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75a.75.75 0 01-.75-.75z" clip-rule="evenodd" />
           </svg>
           <span>Manage Fields</span>
        </a>
        <a href="/admin/structure/content-types/{{ $type['id'] }}/form-display"
           class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 group inline-flex items-center border-b-2 py-4 px-1 text-sm font-medium">
           <svg class="-ml-0.5 mr-2 h-5 w-5 text-gray-400 group-hover:text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
               <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />
           </svg>
           <span>Form Display</span>
        </a>
        <a href="/admin/structure/content-types/{{ $type['id'] }}/display"
           class="border-blue-500 text-blue-600 group inline-flex items-center border-b-2 py-4 px-1 text-sm font-medium" aria-current="page">
           <svg class="-ml-0.5 mr-2 h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
               <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
               <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
           </svg>
           <span>Content Display</span>
        </a>
    </nav>
</div>

<form action="/admin/structure/content-types/{{ $type['id'] }}/display" method="POST">
    <input type="hidden" name="{{ $csrf_token_name ?? 'csrf_token' }}" value="{{ $csrf_token ?? '' }}">
    
    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl md:col-span-2 mb-6 field-sortable-container"
         data-reorder-url="/admin/structure/content-types/{{ $type['id'] }}/display"
         data-context="display">
        <div class="px-4 py-6 sm:px-6">
            <div class="text-sm text-gray-500 mb-4 flex items-center gap-2">
                Drag and drop rows to reorder how fields are displayed on the frontend.
                <span class="sortable-status font-medium ml-2"></span>
            </div>
            
            <table class="min-w-full divide-y divide-gray-300">
                <thead>
                    <tr>
                        <th scope="col" class="w-10 pl-4 sm:pl-0"></th>
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-0">Field</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Weight</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Format</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 field-sortable-list">
                    @if(empty($fields))
                        <tr>
                            <td colspan="4" class="py-10 text-center text-gray-500 text-sm">No fields found.</td>
                        </tr>
                    @else
                        @foreach($fields as $machineName => $field)
                        <tr class="bg-white" data-field="{{ $field['machine_name'] }}">
                            <td class="pl-4 sm:pl-0 py-4 cursor-move handle">
                                <svg class="w-5 h-5 text-gray-400 hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                                </svg>
                            </td>
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-0">
                                {{ $field['label'] }} <span class="text-gray-400 text-xs">({{ $field['machine_name'] }})</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                <input type="number" name="weights[{{ $field['machine_name'] }}]" value="{{ $loop->index }}" class="block w-20 rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6 weight-input">
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                Default
                            </td>
                        </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="flex justify-end">
        <x-ui.button type="submit">Save Order</x-ui.button>
    </div>
</form>

@push('scripts')
<script src="/js/sortable.min.js"></script>
<script src="/themes/custom/admin/assets/js/field-sortable.js"></script>
@endpush
@endsection
