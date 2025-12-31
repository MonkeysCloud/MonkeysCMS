@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex justify-between items-center">
        <div>
            <div class="flex items-center gap-2">
                <a href="/admin/taxonomies" class="text-gray-500 hover:text-gray-700">
                    Vocabularies
                </a>
                <span class="text-gray-300">/</span>
                <h2 class="text-2xl font-bold text-gray-900">{{ $vocabulary->name }} Terms</h2>
            </div>
            <p class="mt-1 text-sm text-gray-500 flex items-center gap-2">
                Drag and drop rows to reorder terms.
                <span class="sortable-status font-medium ml-2"></span>
            </p>
        </div>
        @php
            $createUrl = '/admin/taxonomies/' . $vocabulary->id . '/terms/create';
        @endphp
        <div class="flex gap-2">
            <x-ui.button :href="$createUrl">
                Add Term
            </x-ui.button>
        </div>
    </div>

    <x-ui.card class="bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-10"></th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slug</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" 
                       data-sortable-url="/admin/taxonomies/{{ $vocabulary->id }}/terms/reorder">
                    @foreach($terms as $term)
                    <tr class="hover:bg-gray-50 transition-colors group cursor-move" data-id="{{ $term->id }}">
                        <td class="px-6 py-4 text-gray-400">
                            <!-- Drag Handle -->
                            <svg class="w-4 h-4 cursor-move" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                            </svg>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <!-- Indentation -->
                                @if($term->depth > 0)
                                    <div class="flex mr-2">
                                        @for($i = 0; $i < $term->depth; $i++)
                                            <div class="w-6 border-r border-gray-200 h-full mr-2"></div>
                                        @endfor
                                    </div>
                                @endif
                                
                                <div class="flex items-center">
                                    <span class="font-medium text-gray-900 group-hover:text-blue-700 transition-colors">{{ $term->name }}</span>
                                    @if(!$term->is_published)
                                        <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-500 border border-gray-200">Draft</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                            {{ $term->slug }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $term->weight }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                            <a href="/admin/taxonomies/{{$vocabulary->id}}/terms/{{ $term->id }}/edit" class="text-blue-600 hover:text-blue-900">Edit</a>
                            <a href="/admin/taxonomies/{{$vocabulary->id}}/terms/{{ $term->id }}/delete" onclick="return confirm('Are you sure?')" class="text-red-600 hover:text-red-900">Delete</a>
                        </td>
                    </tr>
                    @endforeach
                    
                    @if(empty($terms))
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            <p class="text-sm">No terms found.</p>
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </x-ui.card>
@endsection
