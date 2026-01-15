@extends('layouts/admin')

@section('content')
<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $title ?? 'Content' }}</h1>
            <p class="mt-1 text-sm text-gray-600">Create and manage content across your site</p>
        </div>
    </div>

    @if(!empty($types))
        {{-- Content Types Grid --}}
        <div class="mb-8">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Add Content</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                @foreach($types as $type)
                    <a href="/admin/content/{{ $type['id'] }}/add" 
                       class="group flex flex-col items-center p-6 bg-white border border-gray-200 rounded-lg hover:border-blue-500 hover:shadow-md transition-all">
                        <span class="text-4xl mb-3">{{ $type['icon'] ?? '📄' }}</span>
                        <span class="text-sm font-medium text-gray-900 group-hover:text-blue-600">{{ $type['label'] }}</span>
                        @if(!empty($type['description']))
                            <span class="text-xs text-gray-500 text-center mt-1 line-clamp-2">{{ $type['description'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Recent Content Section --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-800">Recent Content</h2>
                <div class="flex items-center gap-4">
                    {{-- Search --}}
                    <div class="relative">
                        <input type="text" 
                               placeholder="Search content..." 
                               class="pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-64"
                               id="search-content">
                        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    {{-- Filter by type --}}
                    <select class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" id="filter-type">
                        <option value="">All Types</option>
                        @foreach($types as $type)
                            <option value="{{ $type['id'] }}">{{ $type['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            
            <div class="p-4">
                @if(!empty($recentContent))
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4 text-xs font-semibold text-gray-600 uppercase">Title</th>
                                <th class="text-left py-3 px-4 text-xs font-semibold text-gray-600 uppercase">Type</th>
                                <th class="text-left py-3 px-4 text-xs font-semibold text-gray-600 uppercase">Status</th>
                                <th class="text-left py-3 px-4 text-xs font-semibold text-gray-600 uppercase">Updated</th>
                                <th class="text-right py-3 px-4 text-xs font-semibold text-gray-600 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentContent as $content)
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-3 px-4">
                                        <a href="/admin/content/{{ $content['type_id'] }}/{{ $content['id'] }}/edit" class="text-blue-600 hover:text-blue-800 font-medium">
                                            {{ $content['title'] }}
                                        </a>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            {{ $content['type_label'] ?? $content['type_id'] }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        @if(($content['status'] ?? 'draft') === 'published')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Published</span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Draft</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-sm text-gray-500">
                                        {{ $content['updated_at'] ?? '-' }}
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <a href="/admin/content/{{ $content['type_id'] }}/{{ $content['id'] }}/edit" class="text-blue-600 hover:text-blue-800 text-sm">Edit</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No content yet</h3>
                        <p class="mt-1 text-sm text-gray-500">Get started by creating your first piece of content.</p>
                        <div class="mt-6">
                            @if(!empty($types))
                                <a href="/admin/content/{{ array_key_first($types) }}/add" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Create Content
                                </a>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @else
        {{-- No content types defined --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-900">No content types defined</h3>
            <p class="mt-2 text-sm text-gray-500 max-w-md mx-auto">Before you can create content, you need to define at least one content type. Content types define the structure and fields for your content.</p>
            <div class="mt-6">
                <a href="/admin/structure/content-types/create" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Create Content Type
                </a>
            </div>
        </div>
    @endif
</div>
@endsection
