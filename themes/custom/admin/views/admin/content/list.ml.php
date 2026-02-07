@extends('layouts/admin')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="/admin/content" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>
                <p class="text-sm text-gray-500">Manage your {{ $type['label'] }} content</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-3xl">{{ $type['icon'] ?? '📄' }}</span>
            <a href="{{ $add_url }}" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                + Add {{ $type['label'] }}
            </a>
        </div>
    </div>

    <x-ui.card>
        @if(empty($items))
            <div class="p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h3 class="mt-4 text-lg font-medium text-gray-900">No content yet</h3>
                <p class="mt-2 text-sm text-gray-500">Get started by creating a new {{ $type['label'] }}.</p>
                <a href="{{ $add_url }}" class="mt-4 inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                    + Create {{ $type['label'] }}
                </a>
            </div>
        @else
            <div class="overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slug</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                            <th scope="col" class="relative px-6 py-3">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($items as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $item['title'] ?? 'Untitled' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-500 font-mono">/{{ $item['slug'] ?? '' }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if(($item['status'] ?? 'draft') === 'published')
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Published</span>
                                    @else
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Draft</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $item['created_at'] ?? '' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="/admin/content/{{ $type_id }}/{{ $item['id'] }}/edit" class="text-blue-600 hover:text-blue-900">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>
</div>
@endsection
