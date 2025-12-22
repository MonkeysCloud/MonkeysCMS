@extends('layouts.admin')

@section('title', 'Users')

@section('breadcrumbs')
    <a href="/admin">Dashboard</a>
    <span>/</span>
    <span>Users</span>
@endsection

@section('page_title', 'Users')

@section('page_actions')
    <button class="btn btn-primary" data-modal-open="create-user-modal">
        ➕ Add User
    </button>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <div class="card-title">User List</div>
        <div class="card-actions">
            <input type="text" class="form-control" id="user-search" placeholder="Search users..." style="width: 200px;">
            <select class="form-control" id="filter-status" style="width: auto;">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="blocked">Blocked</option>
                <option value="pending">Pending</option>
            </select>
        </div>
    </div>
    <div class="card-body">
        <div class="table-wrapper">
            <table class="admin-table" id="users-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" class="select-all"></th>
                        <th data-sort="username">Username</th>
                        <th data-sort="email">Email</th>
                        <th>Roles</th>
                        <th data-sort="status">Status</th>
                        <th data-sort="last_login_at">Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Populated by JS -->
                </tbody>
            </table>
        </div>
        <div class="pagination-wrapper" id="pagination"></div>
    </div>
</div>

{{-- Create/Edit User Modal --}}
<div class="modal-overlay" id="user-modal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title" id="modal-title">Add User</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form id="user-form">
            <div class="modal-body">
                <input type="hidden" id="user-id" name="id">
                
                <div class="form-group">
                    <label class="form-label required" for="user-email">Email</label>
                    <input type="email" class="form-control" id="user-email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label required" for="user-username">Username</label>
                    <input type="text" class="form-control" id="user-username" name="username" required pattern="[a-zA-Z0-9_]{3,50}">
                    <span class="form-text">3-50 alphanumeric characters or underscores</span>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="user-password" id="password-label">Password</label>
                    <input type="password" class="form-control" id="user-password" name="password" minlength="8">
                    <span class="form-text">Minimum 8 characters</span>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="user-display-name">Display Name</label>
                    <input type="text" class="form-control" id="user-display-name" name="display_name">
                </div>
                
                <div class="form-row" style="display: flex; gap: 1rem;">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label" for="user-first-name">First Name</label>
                        <input type="text" class="form-control" id="user-first-name" name="first_name">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label" for="user-last-name">Last Name</label>
                        <input type="text" class="form-control" id="user-last-name" name="last_name">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="user-status">Status</label>
                    <select class="form-control" id="user-status" name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="blocked">Blocked</option>
                        <option value="pending">Pending Verification</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Roles</label>
                    <div id="roles-checkboxes" class="roles-grid">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Save User</button>
            </div>
        </form>
    </div>
</div>

{{-- Delete Confirmation Modal --}}
<div class="modal-overlay" id="delete-modal">
    <div class="modal" style="max-width: 400px;">
        <div class="modal-header">
            <h3 class="modal-title">Delete User</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete user <strong id="delete-user-name"></strong>?</p>
            <p class="form-text">This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
            <button type="button" class="btn btn-danger" id="confirm-delete">Delete User</button>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.roles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 0.5rem;
}

.role-checkbox {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-border-radius-sm);
}

.role-checkbox input {
    margin: 0;
}

.role-badge-small {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 0.25rem;
}

.user-roles {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
}

.card-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}
</style>
@endpush

@push('scripts')
<script>
(function() {
    'use strict';

    let users = [];
    let roles = [];
    let currentPage = 1;
    let totalPages = 1;
    let deleteUserId = null;

    async function init() {
        await loadRoles();
        await loadUsers();
        setupEventListeners();
    }

    async function loadRoles() {
        try {
            const response = await adminApi.get('/admin/roles');
            roles = response.roles;
            renderRoleCheckboxes();
        } catch (error) {
            showToast('Failed to load roles', 'danger');
        }
    }

    async function loadUsers(page = 1) {
        const search = document.getElementById('user-search').value;
        const status = document.getElementById('filter-status').value;
        
        let url = '/admin/users?page=' + page + '&per_page=20';
        if (search) url += '&q=' + encodeURIComponent(search);
        if (status) url += '&status=' + status;

        try {
            const response = await adminApi.get(url);
            users = response.data;
            currentPage = response.page;
            totalPages = response.total_pages;
            renderUsers();
            renderPagination();
        } catch (error) {
            showToast('Failed to load users', 'danger');
        }
    }

    function renderUsers() {
        const tbody = document.querySelector('#users-table tbody');
        
        if (users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center">No users found</td></tr>';
            return;
        }

        tbody.innerHTML = users.map(user => `
            <tr data-id="${user.id}">
                <td><input type="checkbox" value="${user.id}"></td>
                <td>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span class="user-avatar" style="width: 32px; height: 32px; font-size: 0.75rem;">
                            ${(user.display_name || user.username).charAt(0).toUpperCase()}
                        </span>
                        <div>
                            <strong>${escapeHtml(user.username)}</strong>
                            <div class="form-text">${escapeHtml(user.display_name || '')}</div>
                        </div>
                    </div>
                </td>
                <td>${escapeHtml(user.email)}</td>
                <td>
                    <div class="user-roles">
                        ${(user.roles || []).map(r => `
                            <span class="badge" style="background: ${r.color}20; color: ${r.color};">
                                ${escapeHtml(r.name)}
                            </span>
                        `).join('')}
                    </div>
                </td>
                <td>
                    <span class="badge badge-${getStatusBadge(user.status)}">
                        ${escapeHtml(user.status)}
                    </span>
                </td>
                <td>${user.last_login_at ? formatDate(user.last_login_at) : 'Never'}</td>
                <td class="actions">
                    <button class="btn btn-sm btn-outline" onclick="editUser(${user.id})">Edit</button>
                    <button class="btn btn-sm btn-danger" onclick="deleteUser(${user.id}, '${escapeHtml(user.username)}')">Delete</button>
                </td>
            </tr>
        `).join('');
    }

    function renderRoleCheckboxes() {
        const container = document.getElementById('roles-checkboxes');
        container.innerHTML = roles.map(role => `
            <label class="role-checkbox">
                <input type="checkbox" name="role_ids[]" value="${role.id}">
                <span class="role-badge-small" style="background: ${role.color};"></span>
                ${escapeHtml(role.name)}
            </label>
        `).join('');
    }

    function renderPagination() {
        const container = document.getElementById('pagination');
        if (totalPages <= 1) {
            container.innerHTML = '';
            return;
        }

        let html = '<ul class="pagination">';
        
        if (currentPage > 1) {
            html += `<li><a href="#" data-page="${currentPage - 1}">&laquo;</a></li>`;
        }
        
        for (let i = 1; i <= totalPages; i++) {
            if (i === currentPage) {
                html += `<li class="active"><span>${i}</span></li>`;
            } else {
                html += `<li><a href="#" data-page="${i}">${i}</a></li>`;
            }
        }
        
        if (currentPage < totalPages) {
            html += `<li><a href="#" data-page="${currentPage + 1}">&raquo;</a></li>`;
        }
        
        html += '</ul>';
        container.innerHTML = html;

        container.querySelectorAll('a').forEach(a => {
            a.addEventListener('click', function(e) {
                e.preventDefault();
                loadUsers(parseInt(this.dataset.page));
            });
        });
    }

    function setupEventListeners() {
        // Search
        let searchTimeout;
        document.getElementById('user-search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => loadUsers(1), 300);
        });

        // Filter
        document.getElementById('filter-status').addEventListener('change', () => loadUsers(1));

        // Add user button
        document.querySelector('[data-modal-open="create-user-modal"]').addEventListener('click', function() {
            openUserModal();
        });

        // Form submit
        document.getElementById('user-form').addEventListener('submit', handleFormSubmit);

        // Confirm delete
        document.getElementById('confirm-delete').addEventListener('click', confirmDelete);
    }

    function openUserModal(user = null) {
        const modal = document.getElementById('user-modal');
        const form = document.getElementById('user-form');
        const title = document.getElementById('modal-title');
        const passwordLabel = document.getElementById('password-label');
        const passwordInput = document.getElementById('user-password');

        form.reset();

        if (user) {
            title.textContent = 'Edit User';
            passwordLabel.classList.remove('required');
            passwordInput.required = false;
            
            document.getElementById('user-id').value = user.id;
            document.getElementById('user-email').value = user.email;
            document.getElementById('user-username').value = user.username;
            document.getElementById('user-display-name').value = user.display_name || '';
            document.getElementById('user-first-name').value = user.first_name || '';
            document.getElementById('user-last-name').value = user.last_name || '';
            document.getElementById('user-status').value = user.status;

            // Check roles
            const roleIds = (user.roles || []).map(r => r.id);
            document.querySelectorAll('#roles-checkboxes input').forEach(input => {
                input.checked = roleIds.includes(parseInt(input.value));
            });
        } else {
            title.textContent = 'Add User';
            passwordLabel.classList.add('required');
            passwordInput.required = true;
            document.getElementById('user-id').value = '';
        }

        modal.classList.add('is-active');
    }

    async function handleFormSubmit(e) {
        e.preventDefault();

        const id = document.getElementById('user-id').value;
        const isEdit = !!id;

        const data = {
            email: document.getElementById('user-email').value,
            username: document.getElementById('user-username').value,
            display_name: document.getElementById('user-display-name').value,
            first_name: document.getElementById('user-first-name').value,
            last_name: document.getElementById('user-last-name').value,
            status: document.getElementById('user-status').value,
            role_ids: Array.from(document.querySelectorAll('#roles-checkboxes input:checked')).map(i => parseInt(i.value)),
        };

        const password = document.getElementById('user-password').value;
        if (password) {
            data.password = password;
        }

        try {
            if (isEdit) {
                await adminApi.put('/admin/users/' + id, data);
                showToast('User updated successfully', 'success');
            } else {
                await adminApi.post('/admin/users', data);
                showToast('User created successfully', 'success');
            }

            document.getElementById('user-modal').classList.remove('is-active');
            loadUsers(currentPage);
        } catch (error) {
            showToast(error.message, 'danger');
        }
    }

    window.editUser = async function(id) {
        try {
            const user = await adminApi.get('/admin/users/' + id);
            openUserModal(user);
        } catch (error) {
            showToast('Failed to load user', 'danger');
        }
    };

    window.deleteUser = function(id, username) {
        deleteUserId = id;
        document.getElementById('delete-user-name').textContent = username;
        document.getElementById('delete-modal').classList.add('is-active');
    };

    async function confirmDelete() {
        if (!deleteUserId) return;

        try {
            await adminApi.delete('/admin/users/' + deleteUserId);
            showToast('User deleted successfully', 'success');
            document.getElementById('delete-modal').classList.remove('is-active');
            loadUsers(currentPage);
        } catch (error) {
            showToast(error.message, 'danger');
        }

        deleteUserId = null;
    }

    function getStatusBadge(status) {
        const map = {
            'active': 'success',
            'inactive': 'secondary',
            'blocked': 'danger',
            'pending': 'warning'
        };
        return map[status] || 'secondary';
    }

    function formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    }

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
