@extends('layouts/admin')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ $user ? '/admin/users/' . $user->id . '/view' : '/admin/users' }}" class="text-gray-500 hover:text-gray-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
        </a>
        <h1 class="text-xl sm:text-2xl font-bold text-gray-800">
            {{ $user ? 'Edit User: ' . ($user->display_name ?: $user->username) : 'Create New User' }}
        </h1>
    </div>

    {{-- Error Messages --}}
    @if(!empty($errors))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm" role="alert">
            <ul class="list-disc list-inside">
                @foreach($errors as $field => $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" 
          action="{{ $user ? '/admin/users/' . $user->id . '/update' : '/admin/users/store' }}" 
          class="bg-white rounded-lg shadow p-4 sm:p-6 space-y-6">
        
        {{-- Account Info Section --}}
        <div>
            <h3 class="text-lg font-medium text-gray-900 mb-4">Account Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Email --}}
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                        Email <span class="text-red-500">*</span>
                    </label>
                    <input type="email" 
                           name="email" 
                           id="email" 
                           value="{{ $old['email'] ?? ($user ? $user->email : '') }}" 
                           required
                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 {{ isset($errors['email']) ? 'border-red-300' : '' }}"
                           placeholder="user@example.com">
                    @if(isset($errors['email']))
                        <p class="mt-1 text-xs text-red-600">{{ $errors['email'] }}</p>
                    @endif
                </div>

                {{-- Username --}}
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">
                        Username <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">@</span>
                        <input type="text" 
                               name="username" 
                               id="username" 
                               value="{{ $old['username'] ?? ($user ? $user->username : '') }}" 
                               required
                               pattern="[a-zA-Z0-9_]{3,50}"
                               class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm pl-8 pr-3 py-2 {{ isset($errors['username']) ? 'border-red-300' : '' }}"
                               placeholder="username">
                    </div>
                    <p class="mt-1 text-xs text-gray-500">3-50 characters, letters, numbers, and underscores only.</p>
                    @if(isset($errors['username']))
                        <p class="mt-1 text-xs text-red-600">{{ $errors['username'] }}</p>
                    @endif
                </div>

                {{-- Password --}}
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                        Password {!! $user ? '' : '<span class="text-red-500">*</span>' !!}
                    </label>
                    <input type="password" 
                           name="password" 
                           id="password" 
                           {{ $user ? '' : 'required' }}
                           minlength="8"
                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 {{ isset($errors['password']) ? 'border-red-300' : '' }}"
                           placeholder="{{ $user ? 'Leave blank to keep current' : '••••••••' }}">
                    <p class="mt-1 text-xs text-gray-500">Minimum 8 characters.</p>
                    @if(isset($errors['password']))
                        <p class="mt-1 text-xs text-red-600">{{ $errors['password'] }}</p>
                    @endif
                </div>

                {{-- Status --}}
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" 
                            id="status" 
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
                        @php 
                            $currentStatus = $old['status'] ?? ($user ? $user->status : 'active');
                        @endphp
                        <option value="active" {{ $currentStatus === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ $currentStatus === 'inactive' ? 'selected' : '' }}>Inactive</option>
                        <option value="pending" {{ $currentStatus === 'pending' ? 'selected' : '' }}>Pending Verification</option>
                        <option value="blocked" {{ $currentStatus === 'blocked' ? 'selected' : '' }}>Blocked</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Profile Section --}}
        <div class="border-t pt-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Profile Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Display Name --}}
                <div class="md:col-span-2">
                    <label for="display_name" class="block text-sm font-medium text-gray-700 mb-1">Display Name</label>
                    <input type="text" 
                           name="display_name" 
                           id="display_name" 
                           value="{{ $old['display_name'] ?? ($user ? $user->display_name : '') }}" 
                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2"
                           placeholder="John Doe">
                    <p class="mt-1 text-xs text-gray-500">Leave blank to use username.</p>
                </div>

                {{-- Avatar --}}
                <div class="md:col-span-2">
                    <span class="block text-sm font-medium text-gray-700 mb-1">Avatar</span>
                    <div class="field-image" data-field-id="avatar">
                        <input type="hidden" 
                               name="avatar" 
                               id="avatar" 
                               value="{{ $old['avatar'] ?? ($user ? $user->avatar : '') }}" 
                               class="field-image__value">
                        
                        <div class="field-image__preview field-image__preview--medium {{ empty($user->avatar) ? 'field-image__preview--empty' : '' }}">
                            @if(!empty($user->avatar))
                                <img src="{{ $user->avatar }}" alt="Avatar" class="field-image__img">
                            @else
                                <img src="" alt="Avatar" class="field-image__img" style="display: none;">
                                <div class="field-image__placeholder">No image selected</div>
                            @endif
                        </div>

                        <div class="field-image__actions">
                            <label class="field-image__upload">
                                <input type="file" class="field-image__file" accept="image/jpeg,image/png,image/gif,image/webp" data-max-size="5242880">
                                <span>Upload</span>
                            </label>
                            <button type="button" class="field-image__browse" data-action="browse">Browse Library</button>
                            <button type="button" class="field-image__remove" data-action="remove" style="{{ empty($user->avatar) ? 'display: none;' : '' }}">Remove</button>
                        </div>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Upload an avatar or select from library.</p>
                </div>

                @push('styles')
                <link rel="stylesheet" href="/css/fields/media.css">
                @endpush

                @push('scripts')
                <script src="/js/fields/media.js"></script>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        if (typeof CmsMedia !== 'undefined') {
                            CmsMedia.initImage('avatar');
                        }
                    });
                </script>
                @endpush

                {{-- First Name --}}
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                    <input type="text" 
                           name="first_name" 
                           id="first_name" 
                           value="{{ $old['first_name'] ?? ($user ? $user->first_name : '') }}" 
                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2"
                           placeholder="John">
                </div>

                {{-- Last Name --}}
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                    <input type="text" 
                           name="last_name" 
                           id="last_name" 
                           value="{{ $old['last_name'] ?? ($user ? $user->last_name : '') }}" 
                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2"
                           placeholder="Doe">
                </div>

                {{-- Locale --}}
                <div>
                    <label for="locale" class="block text-sm font-medium text-gray-700 mb-1">Locale</label>
                    <select name="locale" 
                            id="locale" 
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
                        @php 
                            $currentLocale = $old['locale'] ?? ($user ? $user->locale : 'en');
                        @endphp
                        <option value="en" {{ $currentLocale === 'en' ? 'selected' : '' }}>English</option>
                        <option value="es" {{ $currentLocale === 'es' ? 'selected' : '' }}>Español</option>
                        <option value="fr" {{ $currentLocale === 'fr' ? 'selected' : '' }}>Français</option>
                        <option value="de" {{ $currentLocale === 'de' ? 'selected' : '' }}>Deutsch</option>
                        <option value="pt" {{ $currentLocale === 'pt' ? 'selected' : '' }}>Português</option>
                    </select>
                </div>

                {{-- Timezone --}}
                <div>
                    <label for="timezone" class="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
                    <select name="timezone" 
                            id="timezone" 
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2">
                        @php 
                            $currentTz = $old['timezone'] ?? ($user ? $user->timezone : 'UTC');
                            $timezones = [
                                'UTC' => 'UTC',
                                'America/New_York' => 'Eastern Time (US)',
                                'America/Chicago' => 'Central Time (US)',
                                'America/Denver' => 'Mountain Time (US)',
                                'America/Los_Angeles' => 'Pacific Time (US)',
                                'America/Mexico_City' => 'Mexico City',
                                'Europe/London' => 'London',
                                'Europe/Paris' => 'Paris',
                                'Europe/Berlin' => 'Berlin',
                                'Europe/Madrid' => 'Madrid',
                                'Asia/Tokyo' => 'Tokyo',
                                'Asia/Shanghai' => 'Shanghai',
                                'Australia/Sydney' => 'Sydney',
                            ];
                        @endphp
                        @foreach($timezones as $tz => $label)
                            <option value="{{ $tz }}" {{ $currentTz === $tz ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Roles Section --}}
        <div class="border-t pt-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Roles</h3>
            <p class="text-sm text-gray-500 mb-4">Assign roles to grant permissions to this user.</p>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @php
                    $userRoleIds = [];
                    if (isset($old['role_ids'])) {
                        $userRoleIds = array_map('intval', $old['role_ids']);
                    } elseif ($user && !empty($user->roles)) {
                        $userRoleIds = array_map(fn($r) => $r->id, $user->roles);
                    }
                @endphp
                @foreach($roles as $role)
                    <label class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer transition">
                        <input type="checkbox" 
                               name="role_ids[]" 
                               value="{{ $role->id }}"
                               {{ in_array($role->id, $userRoleIds) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 h-5 w-5">
                        <div class="flex items-center gap-2 flex-1 min-w-0">
                            <span class="w-4 h-4 rounded-full flex-shrink-0" style="background-color: {{ $role->color }}"></span>
                            <span class="font-medium text-gray-900 truncate">{{ $role->name }}</span>
                        </div>
                        @if($role->is_system)
                            <span class="text-xs bg-gray-200 text-gray-600 px-1.5 py-0.5 rounded flex-shrink-0">System</span>
                        @endif
                    </label>
                @endforeach
            </div>
            
            @if(empty($roles))
                <p class="text-gray-400 text-sm">No roles available. <a href="/admin/roles/create" class="text-blue-600 hover:underline">Create a role</a></p>
            @endif
        </div>

        {{-- Actions --}}
        <div class="pt-4 border-t flex flex-col sm:flex-row justify-end gap-3">
            <a href="{{ $user ? '/admin/users/' . $user->id . '/view' : '/admin/users' }}" 
               class="px-4 py-2 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 text-center">
                Cancel
            </a>
            <button type="submit" 
                    class="px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                {{ $user ? 'Update User' : 'Create User' }}
            </button>
        </div>
    </form>
</div>
@endsection
