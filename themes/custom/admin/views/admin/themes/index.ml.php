@extends('layouts/admin')



@section('content')
<!-- Page Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Themes</h1>
        <p class="text-sm text-gray-500 mt-1">Manage your site's appearance with custom and contributed themes.</p>
    </div>
    <div class="flex items-center gap-3">
        <button onclick="clearThemeCache()" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-xl border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Clear Cache
        </button>
        <button onclick="openCreateModal()" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Theme
        </button>
    </div>
</div>

<!-- Filters -->
<div class="flex flex-wrap items-center gap-3 mb-6">
    <button onclick="filterThemes('all')" class="filter-btn active px-4 py-2 text-sm font-medium rounded-lg transition-colors" data-filter="all">All</button>
    <button onclick="filterThemes('custom')" class="filter-btn px-4 py-2 text-sm font-medium rounded-lg transition-colors" data-filter="custom">Custom</button>
    <button onclick="filterThemes('contrib')" class="filter-btn px-4 py-2 text-sm font-medium rounded-lg transition-colors" data-filter="contrib">Contributed</button>
</div>

<!-- Loading State -->
<div id="themes-loading" class="flex items-center justify-center py-16">
    <div class="flex flex-col items-center gap-3">
        <svg class="animate-spin h-8 w-8 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <span class="text-sm text-gray-500">Loading themes...</span>
    </div>
</div>

<!-- Themes Grid -->
<div id="themes-grid" class="hidden grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6"></div>

<!-- Empty State -->
<div id="themes-empty" class="hidden flex flex-col items-center justify-center py-16 text-center">
    <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
    <h3 class="text-lg font-semibold text-gray-600 mb-1">No themes found</h3>
    <p class="text-sm text-gray-400">Create a new custom theme to get started.</p>
</div>

<!-- Create Theme Modal -->
<div id="create-modal" class="fixed inset-0 z-50 hidden" aria-modal="true">
    <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" onclick="closeCreateModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900">Create New Theme</h3>
                <p class="text-sm text-gray-500 mt-1">Scaffold a new custom theme with a directory structure.</p>
            </div>
            <form id="create-theme-form" onsubmit="handleCreateTheme(event)">
                <div class="px-6 py-5 space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Theme Name <span class="text-red-500">*</span></label>
                        <input type="text" id="theme-name" name="name" required
                               pattern="[a-zA-Z][a-zA-Z0-9_-]*"
                               placeholder="my-custom-theme"
                               class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
                        <p class="text-xs text-gray-400 mt-1">Alphanumeric, hyphens, and underscores only.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Parent Theme</label>
                        <select id="theme-parent" name="parent"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
                            <option value="">None (standalone)</option>
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Optionally inherit from an existing theme.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                        <textarea id="theme-description" name="description" rows="3"
                                  placeholder="A brief description of this theme..."
                                  class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors resize-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Author</label>
                        <input type="text" id="theme-author" name="author"
                               placeholder="Your name"
                               class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors">
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-3">
                    <button type="button" onclick="closeCreateModal()" class="px-4 py-2.5 text-sm font-medium rounded-xl border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 transition-colors">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">Create Theme</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Theme Detail Modal -->
<div id="detail-modal" class="fixed inset-0 z-50 hidden" aria-modal="true">
    <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" onclick="closeDetailModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between sticky top-0 bg-white z-10">
                <h3 class="text-lg font-semibold text-gray-900" id="detail-title">Theme Details</h3>
                <button onclick="closeDetailModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div id="detail-content" class="px-6 py-5">
                <!-- Populated by JS -->
            </div>
        </div>
    </div>
</div>
@push('styles')
<style>
.filter-btn {
    color: #6b7280;
    background: #f3f4f6;
}
.filter-btn:hover {
    background: #e5e7eb;
}
.filter-btn.active {
    color: #4f46e5;
    background: #eef2ff;
}
.theme-card {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.theme-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.08), 0 8px 10px -6px rgba(0,0,0,0.04);
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
.theme-card {
    animation: fadeIn 0.3s ease forwards;
}
</style>
@endpush

@push('scripts')
<script>
(function() {
    'use strict';

    let allThemes = [];
    let activeTheme = '';
    let currentFilter = 'all';

    // ─── API Helper (uses native fetch) ──────────────────────
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.content : '';

    async function api(method, url, data) {
        const opts = {
            method: method,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        };
        if (data && (method === 'POST' || method === 'PUT')) {
            opts.headers['Content-Type'] = 'application/json';
            if (csrfToken) opts.headers['X-CSRF-Token'] = csrfToken;
            opts.body = JSON.stringify(data);
        }
        const res = await fetch(url, opts);
        const json = await res.json();
        if (!res.ok) throw new Error(json.error || json.message || 'Request failed');
        return json;
    }

    // ─── Toast Helper ────────────────────────────────────────
    function toast(message, type) {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
            return;
        }
        // Inline fallback toast
        const colors = {
            success: 'bg-green-500', danger: 'bg-red-500',
            warning: 'bg-yellow-500', info: 'bg-blue-500',
        };
        const el = document.createElement('div');
        el.className = `fixed top-4 right-4 z-[9999] px-5 py-3 rounded-xl text-white text-sm font-medium shadow-lg ${colors[type] || colors.info} transition-all opacity-0`;
        el.textContent = message;
        document.body.appendChild(el);
        requestAnimationFrame(() => { el.style.opacity = '1'; });
        setTimeout(() => {
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 300);
        }, 3500);
    }

    // ─── Init ────────────────────────────────────────────────

    async function init() {
        await loadThemes();
    }

    async function loadThemes() {
        showLoading(true);
        try {
            const response = await api('GET', '/api/admin/themes');
            allThemes = response.themes || [];
            activeTheme = response.active || '';
            renderThemes();
            populateParentSelect();
        } catch (error) {
            toast('Failed to load themes: ' + (error.message || 'Unknown error'), 'danger');
        }
        showLoading(false);
    }

    function renderThemes() {
        const grid = document.getElementById('themes-grid');
        const empty = document.getElementById('themes-empty');

        let filtered = allThemes;
        if (currentFilter !== 'all') {
            filtered = allThemes.filter(t => t.source === currentFilter);
        }

        if (filtered.length === 0) {
            grid.classList.add('hidden');
            empty.classList.remove('hidden');
            return;
        }

        empty.classList.add('hidden');
        grid.classList.remove('hidden');

        grid.innerHTML = filtered.map((theme, i) => {
            const isActive = theme.is_active;
            const sourceColor = theme.source === 'custom'
                ? 'bg-emerald-100 text-emerald-700'
                : 'bg-blue-100 text-blue-700';
            const typeIcon = theme.type === 'admin'
                ? '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>'
                : '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>';

            return `
            <div class="theme-card bg-white rounded-2xl border border-gray-200 overflow-hidden cursor-pointer" style="animation-delay: ${i * 0.05}s" onclick="viewThemeDetails('${escapeHtml(theme.name)}')">
                <!-- Theme Header -->
                <div class="p-5">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br ${theme.source === 'custom' ? 'from-emerald-400 to-teal-500' : 'from-blue-400 to-indigo-500'} flex items-center justify-center text-white font-bold text-sm shadow-sm">
                                ${theme.name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900 text-sm">${escapeHtml(theme.name)}</h3>
                                <p class="text-xs text-gray-400">v${escapeHtml(theme.version)}</p>
                            </div>
                        </div>
                        ${isActive ? '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Active</span>' : ''}
                    </div>

                    ${theme.description ? `<p class="text-sm text-gray-500 mb-3 line-clamp-2">${escapeHtml(theme.description)}</p>` : ''}

                    <div class="flex flex-wrap items-center gap-2 mb-4">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium ${sourceColor}">
                            ${theme.source}
                        </span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-gray-100 text-gray-600">
                            ${typeIcon}
                            ${theme.type}
                        </span>
                        ${theme.parent ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-purple-100 text-purple-700">↳ ${escapeHtml(theme.parent)}</span>` : ''}
                    </div>

                    ${theme.author ? `<p class="text-xs text-gray-400">by ${escapeHtml(theme.author)}</p>` : ''}
                </div>

                <!-- Actions Footer -->
                <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                    <button onclick="event.stopPropagation(); viewThemeDetails('${escapeHtml(theme.name)}')" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 transition-colors">
                        Details
                    </button>
                    <div class="flex items-center gap-2">
                        <button onclick="event.stopPropagation(); validateTheme('${escapeHtml(theme.name)}')" class="text-xs font-medium text-gray-500 hover:text-gray-700 transition-colors">
                            Validate
                        </button>
                        ${!isActive ? `<button onclick="event.stopPropagation(); activateTheme('${escapeHtml(theme.name)}')" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">Activate</button>` : ''}
                    </div>
                </div>
            </div>`;
        }).join('');
    }

    function populateParentSelect() {
        const select = document.getElementById('theme-parent');
        const options = ['<option value="">None (standalone)</option>'];
        allThemes.forEach(t => {
            options.push(`<option value="${escapeHtml(t.name)}">${escapeHtml(t.name)} (${t.source})</option>`);
        });
        select.innerHTML = options.join('');
    }

    function showLoading(show) {
        document.getElementById('themes-loading').classList.toggle('hidden', !show);
        if (show) {
            document.getElementById('themes-grid').classList.add('hidden');
            document.getElementById('themes-empty').classList.add('hidden');
        }
    }

    // ─── Public Actions ───────────────────────────────────────

    window.filterThemes = function(filter) {
        currentFilter = filter;
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.filter === filter);
        });
        renderThemes();
    };

    window.activateTheme = async function(name) {
        if (!confirm(`Activate theme "${name}"? This will change the site's appearance.`)) return;
        try {
            await api('POST', '/api/admin/themes/' + encodeURIComponent(name) + '/activate');
            toast(`Theme "${name}" activated successfully`, 'success');
            await loadThemes();
        } catch (error) {
            toast('Failed to activate theme: ' + (error.message || 'Unknown error'), 'danger');
        }
    };

    window.validateTheme = async function(name) {
        try {
            const result = await api('GET', '/api/admin/themes/' + encodeURIComponent(name) + '/validate');
            if (result.valid) {
                toast(`Theme "${name}" is valid ✓`, 'success');
            } else {
                toast(`Theme "${name}" has ${result.errors.length} validation error(s)`, 'warning');
            }
        } catch (error) {
            toast('Validation failed: ' + (error.message || 'Unknown error'), 'danger');
        }
    };

    window.clearThemeCache = async function() {
        try {
            await api('POST', '/api/admin/themes/cache/clear');
            toast('Theme cache cleared', 'success');
            await loadThemes();
        } catch (error) {
            toast('Failed to clear cache: ' + (error.message || 'Unknown error'), 'danger');
        }
    };

    window.viewThemeDetails = async function(name) {
        try {
            const theme = await api('GET', '/api/admin/themes/' + encodeURIComponent(name));
            document.getElementById('detail-title').textContent = theme.name;

            const regionsHtml = theme.regions && Object.keys(theme.regions).length > 0
                ? Object.entries(theme.regions).map(([id, label]) => `
                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-gray-100 text-gray-700">${escapeHtml(label)}</span>
                `).join('')
                : '<span class="text-sm text-gray-400">No regions defined</span>';

            const validationHtml = (theme.validation && theme.validation.length > 0)
                ? `<div class="p-3 rounded-xl bg-red-50 border border-red-200">
                     <p class="text-sm font-medium text-red-700 mb-1">Validation Errors:</p>
                     <ul class="text-sm text-red-600 list-disc list-inside">${theme.validation.map(e => `<li>${escapeHtml(e)}</li>`).join('')}</ul>
                   </div>`
                : '<div class="p-3 rounded-xl bg-green-50 border border-green-200"><p class="text-sm text-green-700">✓ Theme passes all validation checks</p></div>';

            document.getElementById('detail-content').innerHTML = `
                <div class="space-y-5">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Name</p>
                            <p class="text-sm font-semibold text-gray-900">${escapeHtml(theme.name)}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Version</p>
                            <p class="text-sm font-semibold text-gray-900">${escapeHtml(theme.version)}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Source</p>
                            <p class="text-sm font-semibold text-gray-900 capitalize">${escapeHtml(theme.source)}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Type</p>
                            <p class="text-sm font-semibold text-gray-900 capitalize">${escapeHtml(theme.type)}</p>
                        </div>
                        ${theme.author ? `<div>
                            <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Author</p>
                            <p class="text-sm font-semibold text-gray-900">${escapeHtml(theme.author)}</p>
                        </div>` : ''}
                        ${theme.parent ? `<div>
                            <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Parent Theme</p>
                            <p class="text-sm font-semibold text-gray-900">${escapeHtml(theme.parent)}</p>
                        </div>` : ''}
                    </div>

                    ${theme.description ? `<div>
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Description</p>
                        <p class="text-sm text-gray-700">${escapeHtml(theme.description)}</p>
                    </div>` : ''}

                    <div>
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Status</p>
                        ${theme.is_active
                            ? '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-green-100 text-green-700"><span class="w-2 h-2 rounded-full bg-green-500"></span>Active</span>'
                            : '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Inactive</span>'}
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Regions</p>
                        <div class="flex flex-wrap gap-2">${regionsHtml}</div>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Validation</p>
                        ${validationHtml}
                    </div>

                    ${theme.paths ? `<div>
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Paths</p>
                        <div class="bg-gray-50 rounded-xl p-3 space-y-1 text-xs font-mono text-gray-600">
                            <p><span class="text-gray-400">Views:</span> ${escapeHtml(theme.paths.views)}</p>
                            <p><span class="text-gray-400">Components:</span> ${escapeHtml(theme.paths.components)}</p>
                            <p><span class="text-gray-400">Assets:</span> ${escapeHtml(theme.paths.assets)}</p>
                        </div>
                    </div>` : ''}
                </div>

                <div class="mt-6 pt-5 border-t border-gray-100 flex justify-end gap-3">
                    ${!theme.is_active ? `<button onclick="activateTheme('${escapeHtml(theme.name)}'); closeDetailModal();" class="px-4 py-2.5 text-sm font-medium rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">Activate</button>` : ''}
                    <button onclick="closeDetailModal()" class="px-4 py-2.5 text-sm font-medium rounded-xl border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 transition-colors">Close</button>
                </div>
            `;

            document.getElementById('detail-modal').classList.remove('hidden');
        } catch (error) {
            toast('Failed to load theme details: ' + (error.message || 'Unknown error'), 'danger');
        }
    };

    window.openCreateModal = function() {
        document.getElementById('create-theme-form').reset();
        populateParentSelect();
        document.getElementById('create-modal').classList.remove('hidden');
    };

    window.closeCreateModal = function() {
        document.getElementById('create-modal').classList.add('hidden');
    };

    window.closeDetailModal = function() {
        document.getElementById('detail-modal').classList.add('hidden');
    };

    window.handleCreateTheme = async function(e) {
        e.preventDefault();
        const data = {
            name: document.getElementById('theme-name').value.trim(),
            parent: document.getElementById('theme-parent').value || null,
            description: document.getElementById('theme-description').value.trim(),
            author: document.getElementById('theme-author').value.trim(),
        };

        try {
            await api('POST', '/api/admin/themes', data);
            toast(`Theme "${data.name}" created successfully`, 'success');
            closeCreateModal();
            await loadThemes();
        } catch (error) {
            toast('Failed to create theme: ' + (error.message || 'Unknown error'), 'danger');
        }
    };

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    init();
})();
</script>
@endpush
@endsection
