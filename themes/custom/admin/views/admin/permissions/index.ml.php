@extends('layouts/admin')

@section('content')
<div class="page-header">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Permissions</h1>
        <div class="flex gap-2">
            <form action="/admin/permissions/sync" method="POST" onsubmit="return confirm('This will scan all modules for new permissions and add them to the database. Continue?');">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded shadow flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
                    </svg>
                    Sync Permissions
                </button>
            </form>
        </div>
    </div>
</div>

@if(isset($message))
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
    <span class="block sm:inline">{{ $message }}</span>
</div>
@endif

@if(isset($error))
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
    <span class="block sm:inline">{{ $error }}</span>
</div>
@endif

<form action="/admin/permissions/save" method="POST" class="bg-white rounded-lg shadow overflow-hidden">
    
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b">
                <tr>
                    <th scope="col" class="px-6 py-3 w-1/3 min-w-[300px]">Permission</th>
                    @foreach($roles as $role)
                        <th scope="col" class="px-6 py-3 text-center min-w-[100px] border-l">
                            <div class="flex flex-col items-center">
                                <span class="font-bold" style="color: {{ $role->color }}">{{ $role->name }}</span>
                                @if($role->slug === 'super_admin')
                                    <span class="text-[10px] text-gray-400 font-normal mt-1">(All Access)</span>
                                @endif
                            </div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($groupedPermissions as $group => $permissions)
                    <tr class="bg-gray-100 border-b">
                        <td class="px-6 py-2 font-bold text-gray-700" colspan="{{ count($roles) + 1 }}">
                            {{ $group }}
                        </td>
                    </tr>
                    
                    @foreach($permissions as $permission)
                        <tr class="bg-white border-b hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-3 border-r">
                                <div class="font-medium text-gray-900">{{ $permission->name }}</div>
                                <div class="text-xs text-gray-500 mt-0.5">{{ $permission->description }}</div>
                                <div class="text-[10px] text-gray-400 font-mono mt-0.5">{{ $permission->slug }}</div>
                            </td>
                            
                            @foreach($roles as $role)
                                <td class="px-6 py-3 text-center border-l">
                                    <div class="flex justify-center">
                                        @php
                                            $hasPerm = in_array($permission->slug, $rolePermissions[$role->id] ?? []);
                                            $disabled = $role->slug === 'super_admin'; 
                                            // Super admin gets verified check but disabled input
                                            $checked = $disabled ? true : $hasPerm;
                                        @endphp
                                        
                                        <input type="checkbox" 
                                               name="permissions[{{ $role->id }}][]" 
                                               value="{{ $permission->id }}"
                                               id="perm-{{ $role->id }}-{{ $permission->id }}"
                                               @if($checked) checked @endif
                                               @if($disabled) disabled @endif
                                               class="w-5 h-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
    
    <div class="bg-gray-50 px-6 py-4 border-t flex justify-end sticky bottom-0 z-10">
        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded shadow flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
            Save Changes
        </button>
    </div>
</form>
@endsection
