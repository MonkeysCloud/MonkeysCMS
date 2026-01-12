@extends('layouts/admin')

@section('content')
<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
            <nav class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                <a href="/admin/users" class="hover:text-blue-600 transition-colors">Users</a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-900 font-medium"><?= htmlspecialchars($user->display_name ?: $user->username) ?></span>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900">User Profile</h1>
        </div>
        <div class="flex items-center gap-3">
            <a href="/admin/users" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                ← Back
            </a>
            <a href="/admin/users/<?= $user->id ?>/edit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                Edit User
            </a>
            <a href="/admin/users/<?= $user->id ?>/delete" 
               onclick="return confirm('Are you sure you want to delete this user?');"
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-red-600 bg-white border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
                Delete
            </a>
        </div>
    </div>

    {{-- Flash Messages --}}
    <?php if (isset($_GET['success'])): ?>
        <div class="p-4 bg-green-50 border border-green-200 rounded-lg flex items-center gap-3">
            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <p class="text-sm text-green-800"><?php if ($_GET['success'] === 'updated'): ?>User profile updated successfully.<?php endif; ?></p>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Content --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Profile Card --}}
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-6">
                    <div class="flex items-start gap-6">
                        {{-- Avatar --}}
                        <div class="flex-shrink-0">
                            <?php if ($user->avatar): ?>
                                <img src="<?= htmlspecialchars($user->avatar) ?>" alt="" class="w-24 h-24 rounded-xl object-cover border border-gray-200">
                            <?php else: ?>
                                <div class="w-24 h-24 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center">
                                    <span class="text-white font-bold text-3xl"><?= strtoupper(substr($user->display_name ?: $user->username, 0, 1)) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        {{-- Info --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h2 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($user->display_name ?: $user->username) ?></h2>
                                    <p class="text-gray-500">@<?= htmlspecialchars($user->username) ?></p>
                                </div>
                                <?php
                                    $statusColors = [
                                        'active' => 'bg-green-100 text-green-800',
                                        'inactive' => 'bg-gray-100 text-gray-800',
                                        'blocked' => 'bg-red-100 text-red-800',
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                    ];
                                    $statusClass = $statusColors[$user->status] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?= $statusClass ?>">
                                    <?= ucfirst($user->status) ?>
                                </span>
                            </div>
                            
                            <div class="mt-4 flex flex-wrap items-center gap-4 text-sm text-gray-600">
                                <a href="mailto:<?= htmlspecialchars($user->email) ?>" class="flex items-center gap-1.5 hover:text-blue-600">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    <?= htmlspecialchars($user->email) ?>
                                </a>
                                <?php if ($user->email_verified_at): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-green-100 text-green-700 text-xs font-medium">
                                        ✓ Verified
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-yellow-100 text-yellow-700 text-xs font-medium">
                                        Unverified
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                {{-- Stats Bar --}}
                <div class="border-t border-gray-100 bg-gray-50 px-6 py-4">
                    <div style="display: flex; justify-content: space-around; text-align: center;">
                        <div style="flex: 1;">
                            <p class="text-2xl font-bold text-gray-900"><?= number_format($user->login_count ?? 0) ?></p>
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Logins</p>
                        </div>
                        <div style="flex: 1;">
                            <p class="text-2xl font-bold text-gray-900"><?= count($user->roles ?? []) ?></p>
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Roles</p>
                        </div>
                        <div style="flex: 1;">
                            <?php $permissions = $user->getAllPermissions(); ?>
                            <p class="text-2xl font-bold text-gray-900"><?= count($permissions ?? []) ?></p>
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Permissions</p>
                        </div>
                        <div style="flex: 1;">
                            <?php $daysActive = $user->created_at ? (new DateTime())->diff($user->created_at)->days : 0; ?>
                            <p class="text-2xl font-bold text-gray-900"><?= $daysActive ?></p>
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Days Active</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Roles & Permissions --}}
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900">Roles & Permissions</h3>
                    <a href="/admin/roles" class="text-sm text-blue-600 hover:text-blue-700">Manage Roles →</a>
                </div>
                <div class="p-6 space-y-6">
                    {{-- Roles --}}
                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Assigned Roles</h4>
                        <div class="flex flex-wrap gap-2">
                            @foreach($user->roles as $role)
                                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium text-white" style="background-color: <?= $role->color ?>">
                                    <?= htmlspecialchars($role->name) ?>
                                </span>
                            @endforeach
                            @if(empty($user->roles))
                                <span class="text-sm text-gray-400">No roles assigned</span>
                            @endif
                        </div>
                    </div>
                    
                    {{-- Permissions --}}
                    <?php if (!empty($permissions)): ?>
                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Effective Permissions</h4>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($permissions as $permission): ?>
                                <code class="px-2 py-1 rounded bg-gray-100 text-gray-700 text-xs font-mono"><?= htmlspecialchars($permission->slug) ?></code>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Account Details --}}
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">Account Details</h3>
                </div>
                <div class="p-6">
                    <dl class="space-y-4">
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">User ID</dt>
                            <dd class="text-sm font-mono font-medium text-gray-900">#<?= $user->id ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Full Name</dt>
                            <dd class="text-sm font-medium text-gray-900">
                                <?= ($user->first_name || $user->last_name) ? htmlspecialchars(trim($user->first_name . ' ' . $user->last_name)) : '—' ?>
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Locale</dt>
                            <dd class="text-sm font-medium text-gray-900"><?= strtoupper($user->locale) ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Timezone</dt>
                            <dd class="text-sm font-medium text-gray-900"><?= htmlspecialchars($user->timezone) ?></dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Activity --}}
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">Activity</h3>
                </div>
                <div class="p-6">
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Last Login</dt>
                            <dd class="text-sm text-gray-900">
                                <?php if ($user->last_login_at): ?>
                                    <?= $user->last_login_at->format('M j, Y \a\t g:i A') ?>
                                    <?php if ($user->last_login_ip): ?>
                                        <span class="block text-xs text-gray-400 font-mono mt-0.5"><?= htmlspecialchars($user->last_login_ip) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-gray-400">Never</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Last Updated</dt>
                            <dd class="text-sm text-gray-900"><?= $user->updated_at ? $user->updated_at->format('M j, Y') : '—' ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Account Created</dt>
                            <dd class="text-sm text-gray-900"><?= $user->created_at ? $user->created_at->format('F j, Y') : '—' ?></dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
