@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Menus</h2>
            <p class="mt-1 text-sm text-gray-500">Manage your site's navigation menus.</p>
        </div>
        <x-ui.button href="/admin/menus/create">
            Create Menu
        </x-ui.button>
    </div>

    <x-ui.card class="bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Machine Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($menus as $menu)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex flex-col">
                                <a href="/admin/menus/{{ $menu->id }}/edit" class="text-sm font-semibold text-blue-600 hover:text-blue-900">
                                    {{ $menu->name }}
                                </a>
                                @if($menu->description)
                                    <span class="text-xs text-gray-500 mt-1">{{ $menu->description }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 font-mono">
                                {{ $menu->machine_name }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $menu->location }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                            <a href="/admin/menus/{{ $menu->id }}/edit" class="text-blue-600 hover:text-blue-900">Edit</a>
                            <a href="/admin/menus/{{ $menu->id }}/delete" onclick="return confirm('Are you sure?')" class="text-red-600 hover:text-red-900">Delete</a>
                        </td>
                    </tr>
                    @endforeach
                    
                    @if(empty($menus))
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No menus defined</h3>
                            <p class="mt-1 text-sm text-gray-500">Get started by creating a new menu.</p>
                            <div class="mt-6">
                                <x-ui.button href="/admin/menus/create" size="sm">
                                    Create Menu
                                </x-ui.button>
                            </div>
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </x-ui.card>
@endsection
