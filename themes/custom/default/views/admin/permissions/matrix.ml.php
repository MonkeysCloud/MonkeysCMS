@layout('layouts.admin')

@section('title', 'Permission Matrix')

@section('content')
<div class="page-header">
    <h1 class="page-title">Permission Matrix</h1>
    <div class="page-actions">
        <button type="button" class="btn btn-primary" id="save-permissions">
            💾 Save Changes
        </button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Role Permissions</h2>
        <div class="card-actions">
            <select id="filter-group" class="form-control" style="width: auto; display: inline-block;">
                <option value="">All Groups</option>
                <!-- Groups populated by JS -->
            </select>
        </div>
    </div>
    <div class="card-body">
        <div class="permission-matrix" id="permission-matrix">
            <div class="loading">Loading permissions...</div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.permission-matrix {
    overflow-x: auto;
}

.permission-matrix table {
    border-collapse: collapse;
    width: 100%;
    font-size: 0.875rem;
}

.permission-matrix th,
.permission-matrix td {
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--admin-border);
    text-align: center;
}

.permission-matrix thead th {
    background: var(--admin-bg);
    font-weight: 600;
    position: sticky;
    top: 0;
    z-index: 10;
}

.permission-matrix .role-header {
    writing-mode: vertical-lr;
    text-orientation: mixed;
    transform: rotate(180deg);
    white-space: nowrap;
    padding: 1rem 0.5rem;
    min-width: 50px;
}

.permission-matrix .permission-row td:first-child {
    text-align: left;
    font-weight: 500;
    white-space: nowrap;
    background: var(--admin-card-bg);
    position: sticky;
    left: 0;
    z-index: 5;
}

.permission-matrix .group-header {
    background: var(--admin-primary);
    color: white;
    font-weight: 600;
    text-align: left !important;
}

.permission-matrix .group-header td {
    padding: 0.75rem 1rem;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
}

.permission-matrix input[type="checkbox"] {
    width: 1.125rem;
    height: 1.125rem;
    cursor: pointer;
}

.permission-matrix input[type="checkbox"]:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

.role-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 500;
    margin-bottom: 0.25rem;
}

.permission-description {
    font-size: 0.75rem;
    color: var(--admin-text-muted);
    font-weight: normal;
}

.unsaved-changes {
    position: fixed;
    bottom: 1rem;
    right: 1rem;
    background: var(--admin-warning);
    color: white;
    padding: 0.75rem 1rem;
    border-radius: var(--admin-border-radius);
    box-shadow: var(--admin-shadow-lg);
    display: none;
    align-items: center;
    gap: 0.5rem;
    z-index: 100;
}

.unsaved-changes.is-visible {
    display: flex;
}

.loading {
    padding: 2rem;
    text-align: center;
    color: var(--admin-text-muted);
}
</style>
@endpush

@push('scripts')
<script>
(function() {
    'use strict';

    let originalState = {};
    let currentState = {};
    let roles = [];
    let permissions = [];
    let groups = [];

    /**
     * Initialize the permission matrix
     */
    async function init() {
        try {
            const response = await adminApi.get('/admin/permissions/matrix');
            roles = response.roles;
            permissions = response.permissions;
            groups = response.groups;

            // Store original state
            roles.forEach(r => {
                originalState[r.role.id] = [...r.permissions];
                currentState[r.role.id] = [...r.permissions];
            });

            renderMatrix();
            populateGroupFilter();
            setupEventListeners();
        } catch (error) {
            document.getElementById('permission-matrix').innerHTML = 
                '<div class="alert alert-danger">Failed to load permissions: ' + error.message + '</div>';
        }
    }

    /**
     * Render the permission matrix table
     */
    function renderMatrix(filterGroup = '') {
        const container = document.getElementById('permission-matrix');
        
        // Filter permissions by group
        let filteredPermissions = permissions;
        if (filterGroup) {
            filteredPermissions = permissions.filter(p => p.group === filterGroup);
        }

        // Group permissions
        const groupedPermissions = {};
        filteredPermissions.forEach(p => {
            if (!groupedPermissions[p.group]) {
                groupedPermissions[p.group] = [];
            }
            groupedPermissions[p.group].push(p);
        });

        let html = '<table>';
        
        // Header row with roles
        html += '<thead><tr>';
        html += '<th style="text-align: left; min-width: 200px;">Permission</th>';
        roles.forEach(r => {
            html += '<th class="role-header">';
            html += '<span class="role-badge" style="background: ' + r.role.color + '; color: white;">';
            html += escapeHtml(r.role.name);
            html += '</span>';
            if (r.role.slug === 'super_admin') {
                html += '<br><small style="writing-mode: horizontal-tb; transform: none;">All</small>';
            }
            html += '</th>';
        });
        html += '</tr></thead>';

        html += '<tbody>';

        // Render each group
        Object.keys(groupedPermissions).sort().forEach(group => {
            // Group header
            html += '<tr class="group-header"><td colspan="' + (roles.length + 1) + '">';
            html += escapeHtml(group);
            html += '</td></tr>';

            // Permissions in group
            groupedPermissions[group].forEach(perm => {
                html += '<tr class="permission-row" data-permission="' + perm.slug + '">';
                html += '<td>';
                html += '<div>' + escapeHtml(perm.name) + '</div>';
                html += '<div class="permission-description">' + escapeHtml(perm.slug) + '</div>';
                html += '</td>';

                roles.forEach(r => {
                    const isSuperAdmin = r.role.slug === 'super_admin';
                    const isChecked = isSuperAdmin || currentState[r.role.id].includes(perm.slug);
                    const isDisabled = isSuperAdmin;

                    html += '<td>';
                    html += '<input type="checkbox"';
                    html += ' data-role="' + r.role.id + '"';
                    html += ' data-permission="' + perm.slug + '"';
                    if (isChecked) html += ' checked';
                    if (isDisabled) html += ' disabled';
                    html += '>';
                    html += '</td>';
                });

                html += '</tr>';
            });
        });

        html += '</tbody></table>';

        container.innerHTML = html;

        // Bind checkbox events
        container.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', handleCheckboxChange);
        });
    }

    /**
     * Populate group filter dropdown
     */
    function populateGroupFilter() {
        const select = document.getElementById('filter-group');
        groups.forEach(group => {
            const option = document.createElement('option');
            option.value = group;
            option.textContent = group;
            select.appendChild(option);
        });

        select.addEventListener('change', function() {
            renderMatrix(this.value);
        });
    }

    /**
     * Handle checkbox change
     */
    function handleCheckboxChange(e) {
        const roleId = parseInt(e.target.dataset.role);
        const permission = e.target.dataset.permission;

        if (e.target.checked) {
            if (!currentState[roleId].includes(permission)) {
                currentState[roleId].push(permission);
            }
        } else {
            currentState[roleId] = currentState[roleId].filter(p => p !== permission);
        }

        updateUnsavedIndicator();
    }

    /**
     * Setup event listeners
     */
    function setupEventListeners() {
        document.getElementById('save-permissions').addEventListener('click', savePermissions);
    }

    /**
     * Check if there are unsaved changes
     */
    function hasUnsavedChanges() {
        for (const roleId of Object.keys(originalState)) {
            const original = [...originalState[roleId]].sort();
            const current = [...currentState[roleId]].sort();
            
            if (JSON.stringify(original) !== JSON.stringify(current)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Update unsaved changes indicator
     */
    function updateUnsavedIndicator() {
        const indicator = document.querySelector('.unsaved-changes') || createIndicator();
        
        if (hasUnsavedChanges()) {
            indicator.classList.add('is-visible');
        } else {
            indicator.classList.remove('is-visible');
        }
    }

    /**
     * Create unsaved changes indicator
     */
    function createIndicator() {
        const indicator = document.createElement('div');
        indicator.className = 'unsaved-changes';
        indicator.innerHTML = '⚠️ You have unsaved changes';
        document.body.appendChild(indicator);
        return indicator;
    }

    /**
     * Save permissions
     */
    async function savePermissions() {
        const btn = document.getElementById('save-permissions');
        btn.disabled = true;
        btn.innerHTML = '⏳ Saving...';

        try {
            // Build assignments - convert permission slugs to IDs
            const assignments = {};
            
            for (const [roleId, permSlugs] of Object.entries(currentState)) {
                const permIds = permSlugs
                    .map(slug => {
                        const perm = permissions.find(p => p.slug === slug);
                        return perm ? perm.id : null;
                    })
                    .filter(id => id !== null);
                
                assignments[roleId] = permIds;
            }

            await adminApi.put('/admin/permissions/matrix', { assignments });

            // Update original state
            for (const roleId of Object.keys(currentState)) {
                originalState[roleId] = [...currentState[roleId]];
            }

            showToast('Permissions saved successfully', 'success');
            updateUnsavedIndicator();
        } catch (error) {
            showToast('Failed to save permissions: ' + error.message, 'danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '💾 Save Changes';
        }
    }

    /**
     * Escape HTML
     */
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Warn on page leave with unsaved changes
    window.addEventListener('beforeunload', function(e) {
        if (hasUnsavedChanges()) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // Initialize
    init();
})();
</script>
@endpush
