@extends('layouts/admin')

@section('content')
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Block Types</h2>
            <p class="mt-1 text-sm text-gray-500">Manage the types of content blocks available in the system.</p>
        </div>
        <x-ui.button href="/admin/structure/block-types/create">
            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Create Block Type
        </x-ui.button>
    </div>

    <x-ui.card class="bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Label</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th scope="col" class="relative px-6 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @if(empty($types))
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                <p class="mt-2">No block types found.</p>
                                <div class="mt-6">
                                    <x-ui.button href="/admin/structure/block-types/create" size="sm">
                                        Create Block Type
                                    </x-ui.button>
                                </div>
                            </td>
                        </tr>
                    @else
                        @foreach($types as $type)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ $type['label'] }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $type['id'] }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                @if($type['source'] === 'code')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Code</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Database</span>
                                @endif
                            </td>
                             <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $type['description'] ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                @if($type['source'] === 'database' && !($type['entity']->is_system ?? false))
                                    <a href="/admin/structure/block-types/{{ $type['id'] }}/fields" class="text-blue-600 hover:text-blue-900">Manage Fields</a>
                                    <a href="/admin/structure/block-types/{{ $type['id'] }}/edit" class="text-blue-600 hover:text-blue-900">Edit</a>
                                    <form action="/admin/structure/block-types/{{ $type['id'] }}/delete" method="POST" class="inline-block" onsubmit="return confirm('Are you sure? This cannot be undone.');">
                                        <button type="submit" class="text-red-600 hover:text-red-900 bg-transparent border-0 p-0 cursor-pointer">Delete</button>
                                    </form>
                                @else
                                    <span class="text-gray-400 cursor-not-allowed">Locked</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </x-ui.card>
@endsection
