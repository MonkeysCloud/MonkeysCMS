@extends('layouts/admin')

@section('content')
<div class="page-header mb-4 sm:mb-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 sm:gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-gray-800">Users</h1>
            <p class="text-gray-500 text-sm mt-1">
                Manage user accounts and access.
                <span class="text-xs bg-gray-100 px-2 py-0.5 rounded">{{ $total }} total</span>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="/admin/roles" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-lg shadow-sm flex items-center gap-2 transition text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" />
                </svg>
                Roles
            </a>
            <a href="/admin/users/create" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg shadow-sm flex items-center gap-2 transition text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                </svg>
                New User
            </a>
        </div>
    </div>
</div>

@php
    $successMsg = $_GET['success'] ?? null;
    $errorMsg = $_GET['error'] ?? null;
@endphp

@if($successMsg === 'created')
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm" role="alert">
    User created successfully.
</div>
@endif

@if($successMsg === 'deleted')
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm" role="alert">
    User deleted successfully.
</div>
@endif

@if($errorMsg === 'self-delete')
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm" role="alert">
    You cannot delete your own account.
</div>
@endif

<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-4">
    <form method="GET" action="/admin/users" class="flex flex-col sm:flex-row gap-3">
        <div class="flex-1">
            <input type="text" 
                   name="q" 
                   value="{{ $search ?? '' }}"
                   placeholder="Search by name, email, or username..."
                   class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
        </div>
        <div class="flex gap-2">
            @php $currentStatus = $status ?? ''; @endphp
            <select name="status" class="rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
                <option value="">All Status</option>
                <option value="active" <?= $currentStatus === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $currentStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="blocked" <?= $currentStatus === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                <option value="pending" <?= $currentStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
            </select>
            <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg shadow-sm text-sm">
                Search
            </button>
            @if($search || $status)
            <a href="/admin/users" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg shadow-sm text-sm flex items-center">
                Clear
            </a>
            @endif
        </div>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="min-w-full text-sm text-left">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b">
            <tr>
                <th scope="col" class="px-4 py-3">User</th>
                <th scope="col" class="px-4 py-3 hidden md:table-cell">Username</th>
                <th scope="col" class="px-4 py-3">Status</th>
                <th scope="col" class="px-4 py-3 hidden lg:table-cell">Roles</th>
                <th scope="col" class="px-4 py-3 hidden xl:table-cell">Last Login</th>
                <th scope="col" class="px-4 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
            @php
                $statusColors = [
                    'active' => 'bg-green-100 text-green-800',
                    'inactive' => 'bg-gray-100 text-gray-800',
                    'blocked' => 'bg-red-100 text-red-800',
                    'pending' => 'bg-yellow-100 text-yellow-800',
                ];
                $statusClass = $statusColors[$user->status] ?? 'bg-gray-100 text-gray-800';
                $initial = strtoupper(substr($user->display_name ?: $user->username, 0, 1));
                $displayName = $user->display_name ?: $user->username;
            @endphp
            <tr class="bg-white border-b hover:bg-gray-50">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                        @if($user->avatar)
                        <img src="{{ $user->avatar }}" alt="{{ $displayName }}" class="w-10 h-10 rounded-full object-cover">
                        @else
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold">
                            {{ $initial }}
                        </div>
                        @endif
                        <div>
                            <div class="font-medium text-gray-900">{{ $displayName }}</div>
                            <div class="text-gray-500 text-xs">{{ $user->email }}</div>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3 font-mono text-gray-500 hidden md:table-cell">
                    {{ $user->username }}
                </td>
                <td class="px-4 py-3">
                    <span class="text-xs px-2 py-1 rounded-full {{ $statusClass }}">
                        {{ ucfirst($user->status) }}
                    </span>
                </td>
                <td class="px-4 py-3 hidden lg:table-cell">
                    <div class="flex flex-wrap gap-1">
                        @foreach($user->roles as $role)
                        <span class="text-xs px-2 py-0.5 rounded-full text-white" style="background-color: {{ $role->color }}">
                            {{ $role->name }}
                        </span>
                        @endforeach
                        @if(empty($user->roles))
                        <span class="text-gray-400 text-xs">No roles</span>
                        @endif
                    </div>
                </td>
                <td class="px-4 py-3 text-gray-500 hidden xl:table-cell">
                    @if($user->last_login_at)
                    {{ $user->last_login_at->format('M j, Y g:i A') }}
                    @else
                    <span class="text-gray-400">Never</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    <div class="flex justify-end gap-3">
                        <a href="/admin/users/{{ $user->id }}/view" class="text-gray-600 hover:text-gray-900 text-sm">View</a>
                        <a href="/admin/users/{{ $user->id }}/edit" class="text-blue-600 hover:text-blue-900 text-sm">Edit</a>
                        <a href="/admin/users/{{ $user->id }}/delete" 
                           onclick="return confirm('Are you sure you want to delete this user?');"
                           class="text-red-600 hover:text-red-900 text-sm">Delete</a>
                    </div>
                </td>
            </tr>
            @endforeach
            
            @if(empty($users))
            <tr>
                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                    No users found.
                </td>
            </tr>
            @endif
        </tbody>
    </table>
</div>

@if($totalPages > 1)
<div class="mt-4 flex justify-center">
    <nav class="flex items-center gap-1">
        @if($page > 1)
        @php $prevPage = $page - 1; @endphp
        <a href="/admin/users?page={{ $prevPage }}{{ $search ? '&q=' . urlencode($search) : '' }}{{ $status ? '&status=' . urlencode($status) : '' }}" 
           class="px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">
            Previous
        </a>
        @endif
        
        @php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            $pageRange = range($startPage, $endPage);
        @endphp
        @foreach($pageRange as $i)
        @php $isCurrentPage = ($i === $page); @endphp
        <a href="/admin/users?page={{ $i }}{{ $search ? '&q=' . urlencode($search) : '' }}{{ $status ? '&status=' . urlencode($status) : '' }}" 
           class="px-3 py-2 text-sm rounded-lg <?= $isCurrentPage ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
            {{ $i }}
        </a>
        @endforeach
        
        @if($page < $totalPages)
        @php $nextPage = $page + 1; @endphp
        <a href="/admin/users?page={{ $nextPage }}{{ $search ? '&q=' . urlencode($search) : '' }}{{ $status ? '&status=' . urlencode($status) : '' }}" 
           class="px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">
            Next
        </a>
        @endif
    </nav>
</div>
@endif
@endsection
