function mediaEdit(initialData) {
    return {
        isEditing: false,
        isSaving: false,
        originalData: { ...initialData },
        form: { ...initialData },

        init() {
            this.originalData = { ...initialData };
            this.form = { ...initialData };
        },

        toggleEdit() {
            if (this.isEditing) {
                // Cancel: revert
                this.form = { ...this.originalData };
                this.isEditing = false;
            } else {
                // Start editing
                this.isEditing = true;
            }
        },

        async save() {
            this.isSaving = true;

            try {
                const response = await fetch(`/admin/media/${this.originalData.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('input[name="csrf_token"]')?.value || '',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.form)
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.error || 'Failed to update media');
                }

                const result = await response.json();
                
                // Update success
                this.originalData = { ...this.form };
                this.isEditing = false;
                
                // Optional: Show notification
                // alert('Saved successfully'); 

            } catch (error) {
                console.error('Error saving media:', error);
                alert('Error: ' + error.message);
            } finally {
                this.isSaving = false;
            }
        }
    }
}
