<?php
$title = 'MonkeysCMS Installer';
$heading = 'Setup Wizard';
$subheading = 'Configure your database connection to install MonkeysCMS.';
include ML_BASE_PATH . '/app/Views/layouts/header.ml.php';
?>

<form id="setupForm" class="space-y-6">
    <div id="alert" class="hidden p-4 rounded-lg text-sm border"></div>

    <div class="space-y-5">
        <div class="flex items-center gap-2 pb-2 border-b border-gray-100">
            <div class="p-2 rounded-lg bg-orange-50 text-brand">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-800">Database Connection</h3>
        </div>

        <!-- Connection Type -->
        <div class="input-group">
            <label>Connection Type</label>
            <div class="relative">
                <select name="DB_CONNECTION" id="db_connection" class="input-field" onchange="toggleFields()">
                    <option value="mysql">MySQL / MariaDB</option>
                    <option value="pgsql">PostgreSQL</option>
                    <option value="sqlite">SQLite</option>
                    <option value="sqlsrv">SQL Server</option>
                </select>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div id="field_host" class="input-group">
                <label>Host</label>
                <input type="text" name="DB_HOST" value="127.0.0.1" class="input-field" placeholder="e.g. 127.0.0.1" required>
            </div>
            <div id="field_port" class="input-group">
                <label>Port</label>
                <input type="number" name="DB_PORT" value="3306" class="input-field" placeholder="3306" required>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="input-group">
                <label>Database Name / Path</label>
                <input type="text" name="DB_DATABASE" value="monkeyscms" class="input-field" placeholder="monkeyscms" required>
                <p id="sqlite_hint" class="text-xs text-gray-500 mt-1 hidden flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Enter absolute file path for SQLite
                </p>
            </div>
            <div id="field_username" class="input-group">
                <label>Username</label>
                <input type="text" name="DB_USERNAME" value="root" class="input-field" placeholder="root">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div id="field_password" class="input-group">
                <label>Password</label>
                <input type="password" name="DB_PASSWORD" class="input-field" placeholder="••••••••">
            </div>
            <div id="field_prefix" class="input-group">
                <label>Table Prefix <span class="text-xs text-gray-400 font-normal">(Optional)</span></label>
                <input type="text" name="DB_PREFIX" placeholder="mc_" class="input-field">
            </div>
        </div>
    </div>

    <div class="pt-6 border-t border-gray-100 flex justify-between items-center bg-gray-50 -mx-8 -mb-8 p-8 rounded-b-xl">
         <button type="button" onclick="testConnection()" class="px-6 py-2.5 bg-white text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-50 hover:border-gray-300 transition font-medium text-sm shadow-sm">
            Test Connection
         </button>
         <button type="submit" class="btn-primary shadow-lg shadow-orange-500/20 text-sm">
            Save & Install
         </button>
    </div>
</form>

<script>
    const form = document.getElementById('setupForm');
    const alertBox = document.getElementById('alert');

    function toggleFields() {
        const type = document.getElementById('db_connection').value;
        const isSqlite = type === 'sqlite';
        
        // Toggle visibility
        document.getElementById('field_host').style.display = isSqlite ? 'none' : 'block';
        document.getElementById('field_port').style.display = isSqlite ? 'none' : 'block';
        document.getElementById('field_username').style.display = isSqlite ? 'none' : 'block';
        document.getElementById('field_password').style.display = isSqlite ? 'none' : 'block';
        document.getElementById('sqlite_hint').classList.toggle('hidden', !isSqlite);
        
        // Set default ports
        const portInput = document.querySelector('input[name="DB_PORT"]');
        if (type === 'mysql') portInput.value = 3306;
        if (type === 'pgsql') portInput.value = 5432;
        if (type === 'sqlsrv') portInput.value = 1433;
    }

    // Initialize state
    toggleFields();

    function showAlert(message, type = 'error', canCreate = false) {
        alertBox.classList.remove('hidden', 'bg-red-50', 'text-red-700', 'border-red-200', 'bg-green-50', 'text-green-700', 'border-green-200', 'bg-blue-50', 'text-blue-700', 'border-blue-200');
        
        if (type === 'error') {
            alertBox.classList.add('bg-red-50', 'text-red-700', 'border-red-200');
        } else if (type === 'success') {
            alertBox.classList.add('bg-green-50', 'text-green-700', 'border-green-200');
        } else {
            alertBox.classList.add('bg-blue-50', 'text-blue-700', 'border-blue-200');
        }
        
        alertBox.innerHTML = message;
        
        if (canCreate) {
            const btn = document.createElement('button');
            btn.className = 'mt-3 px-4 py-2 bg-white border border-red-300 text-red-700 rounded-lg text-xs font-semibold hover:bg-red-50 transition shadow-sm';
            btn.innerText = 'Create Database';
            btn.onclick = createDatabase;
            alertBox.appendChild(btn);
        }
        
        alertBox.classList.remove('hidden');
    }

    async function createDatabase() {
        const formData = new FormData(form);
        showAlert('Creating database...', 'info');
        try {
            const res = await fetch('/install/create-db', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                showAlert('Database created successfully! You can now install.', 'success');
            } else {
                showAlert('Failed to create database: ' + data.error);
            }
        } catch (e) {
            showAlert('An error occurred during creation.');
        }
    }

    async function testConnection() {
        const formData = new FormData(form);
        showAlert('Testing connection...', 'info');
        try {
            const res = await fetch('/install/test', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                showAlert('Connection successful!', 'success');
            } else {
                showAlert('Connection failed: ' + data.error, 'error', data.can_create);
            }
        } catch (e) {
            showAlert('An error occurred during testing.');
        }
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(form);
        try {
            const res = await fetch('/install/save', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                window.location.href = '/';
            } else {
                showAlert('Installation failed: ' + data.error);
            }
        } catch (e) {
            showAlert('An error occurred during installation.');
        }
    });
</script>

<?php include ML_BASE_PATH . '/app/Views/layouts/footer.ml.php'; ?>
