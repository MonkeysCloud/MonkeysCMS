@extends('layouts.admin')

@section('content')
<div class="page-header mb-4 sm:mb-6">
    {{-- Responsive header --}}
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 sm:gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-gray-800">Taxonomy</h1>
            <p class="text-gray-500 text-sm mt-1">Manage vocabularies and terms.</p>
        </div>
        <a href="/admin/taxonomies/create" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg shadow-sm flex items-center gap-2 transition text-sm w-fit">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
            </svg>
            Add Vocabulary
        </a>
    </div>
</div>

{{-- Mobile: Card Layout --}}
<div class="block sm:hidden space-y-3">
    @foreach($vocabularies as $vocabulary)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="flex items-start justify-between gap-3">
                {{-- Vocabulary Info --}}
                <div class="flex-1 min-w-0">
                    <a href="/admin/taxonomies/{{ $vocabulary->id }}/terms" class="font-medium text-blue-600 hover:text-blue-800">
                        {{ $vocabulary->name }}
                    </a>
                    <p class="text-xs text-gray-400 font-mono mt-1">{{ $vocabulary->machine_name }}</p>
                    @if(!empty($vocabulary->description))
                        <p class="text-sm text-gray-500 mt-2 line-clamp-2">{{ $vocabulary->description }}</p>
                    @endif
                    <div class="flex flex-wrap items-center gap-2 mt-3">
                        @if($vocabulary->hierarchical)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                Hierarchical
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                Flat
                            </span>
                        @endif
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                            {{ $vocabulary->term_count ?? 0 }} terms
                        </span>
                    </div>
                </div>
                
                {{-- Actions --}}
                <div class="flex flex-col gap-2">
                    <a href="/admin/taxonomies/{{ $vocabulary->id }}/terms" class="text-indigo-600 hover:text-indigo-900 p-2 -m-2" title="Manage Terms">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                        </svg>
                    </a>
                    <a href="/admin/taxonomies/{{ $vocabulary->id }}/edit" class="text-blue-600 hover:text-blue-900 p-2 -m-2" title="Edit">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </a>
                    <a href="/admin/taxonomies/{{ $vocabulary->id }}/delete" onclick="return confirm('Delete this vocabulary? This will delete all terms as well.')" class="text-red-600 hover:text-red-900 p-2 -m-2" title="Delete">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    @endforeach
    
    @if(empty($vocabularies))
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No vocabularies found</h3>
            <p class="mt-1 text-sm text-gray-500">Get started by creating a new vocabulary.</p>
            <div class="mt-4">
                <a href="/admin/taxonomies/create" class="inline-flex items-center px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">
                    Add Vocabulary
                </a>
            </div>
        </div>
    @endif
</div>

{{-- Desktop: Table Layout --}}
<div class="hidden sm:block bg-white rounded-lg shadow overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th scope="col" class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                <th scope="col" class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Machine Name</th>
                <th scope="col" class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Structure</th>
                <th scope="col" class="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Terms</th>
                <th scope="col" class="px-4 lg:px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            @foreach($vocabularies as $vocabulary)
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-4 lg:px-6 py-4">
                    <div class="flex flex-col">
                        <a href="/admin/taxonomies/{{ $vocabulary->id }}/terms" class="text-sm font-semibold text-blue-600 hover:text-blue-900">
                            {{ $vocabulary->name }}
                        </a>
                        @if(!empty($vocabulary->description))
                            <span class="text-xs text-gray-500 mt-1 hidden lg:block">{{ $vocabulary->description }}</span>
                        @endif
                        <span class="text-xs text-gray-400 font-mono mt-1 md:hidden">{{ $vocabulary->machine_name }}</span>
                    </div>
                </td>
                <td class="px-4 lg:px-6 py-4 whitespace-nowrap hidden md:table-cell">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 font-mono">
                        {{ $vocabulary->machine_name }}
                    </span>
                </td>
                <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    @if($vocabulary->hierarchical)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                            Hierarchical
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                            Flat
                        </span>
                    @endif
                </td>
                <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        {{ $vocabulary->term_count ?? 0 }}
                    </span>
                </td>
                <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div class="flex justify-end gap-3">
                        <a href="/admin/taxonomies/{{ $vocabulary->id }}/terms" class="text-indigo-600 hover:text-indigo-900">Terms</a>
                        <a href="/admin/taxonomies/{{ $vocabulary->id }}/edit" class="text-blue-600 hover:text-blue-900">Edit</a>
                        <a href="/admin/taxonomies/{{ $vocabulary->id }}/delete" onclick="return confirm('Delete this vocabulary? This will delete all terms as well.')" class="text-red-600 hover:text-red-900">Delete</a>
                    </div>
                </td>
            </tr>
            @endforeach
            
            @if(empty($vocabularies))
            <tr>
                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No vocabularies found</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by creating a new vocabulary.</p>
                    <div class="mt-6">
                        <a href="/admin/taxonomies/create" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">
                            Add Vocabulary
                        </a>
                    </div>
                </td>
            </tr>
            @endif
        </tbody>
    </table>
</div>
@endsection
