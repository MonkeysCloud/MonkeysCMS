/**
 * Author Select - Alpine.js Component
 * Client-side searchable user selection for content authorship
 */

function authorSelect() {
    return {
        users: [],
        selectedId: null,
        selectedName: '',
        search: '',
        open: false,
        filteredUsers: [],
        focusedIndex: -1,

        // Initialize from a script element with JSON data
        initFromScript(scriptId) {
            const scriptEl = document.getElementById(scriptId);
            if (scriptEl) {
                try {
                    const data = JSON.parse(scriptEl.textContent);
                    this.users = data.users || [];
                    this.selectedId = data.selectedId || null;
                    this.selectedName = data.selectedName || '';
                } catch (e) {
                    console.error('Failed to parse author data:', e);
                }
            }

            // If we have ID but no name, find it from users
            if (this.selectedId && !this.selectedName) {
                const user = this.users.find(u => u.id === this.selectedId);
                if (user) {
                    this.selectedName = user.display_name || user.email;
                }
            }

            // Initialize filtered list (show first 20)
            this.filteredUsers = this.users.slice(0, 20);
        },

        filterUsers() {
            const query = this.search.toLowerCase().trim();
            if (!query) {
                this.filteredUsers = this.users.slice(0, 20);
            } else {
                // Search by name OR email
                this.filteredUsers = this.users.filter(user => {
                    const name = (user.display_name || '').toLowerCase();
                    const email = (user.email || '').toLowerCase();
                    return name.includes(query) || email.includes(query);
                }).slice(0, 20);
            }
            this.focusedIndex = -1;
        },

        selectUser(user) {
            this.selectedId = user.id;
            this.selectedName = user.display_name || user.email;
            this.search = '';
            this.open = false;
        },

        clearSelection() {
            this.selectedId = null;
            this.selectedName = '';
            this.search = '';
        },

        focusNext() {
            if (this.focusedIndex < this.filteredUsers.length - 1) {
                this.focusedIndex++;
            }
        },

        focusPrev() {
            if (this.focusedIndex > 0) {
                this.focusedIndex--;
            }
        },

        selectFocused() {
            if (this.focusedIndex >= 0 && this.filteredUsers[this.focusedIndex]) {
                this.selectUser(this.filteredUsers[this.focusedIndex]);
            }
        }
    };
}
