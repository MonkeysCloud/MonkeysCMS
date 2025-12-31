function mediaUpload() {
    return {
        isDragging: false,
        uploads: [],
        
        getCompletedCount() {
            return this.uploads.filter(u => u.status === 'success' || u.status === 'error').length;
        },

        getDropZoneClasses() {
            return {
                'border-blue-500 bg-blue-50 ring-4 ring-blue-100': this.isDragging,
                'border-gray-300 hover:border-gray-400 bg-white': !this.isDragging
            };
        },

        getProgressBarClasses(file) {
            return {
                'bg-blue-600': file.status === 'uploading', 
                'bg-green-500': file.status === 'success', 
                'bg-red-500': file.status === 'error'
            };
        },

        getStatusTextClasses(file) {
            return {
                'text-blue-600': file.status === 'uploading',
                'text-green-600': file.status === 'success',
                'text-red-600': file.status === 'error',
                'text-gray-500': file.status === 'pending'
            };
        },

        handleDrop(e) {
            this.isDragging = false;
            this.handleFiles(e.dataTransfer.files);
        },

        handleFiles(files) {
            if (!files.length) return;

            Array.from(files).forEach(file => {
                const upload = {
                    id: Math.random().toString(36).substr(2, 9),
                    file: file,
                    name: file.name,
                    size: file.size,
                    type: file.type,
                    progress: 0,
                    status: 'pending', 
                    statusText: 'Pending',
                    xhr: null,
                    // Metadata fields
                    title: file.name.split('.').slice(0, -1).join('.'), // Default title from filename
                    alt: '',
                    description: '',
                    isImage: file.type.startsWith('image/')
                };
                
                this.uploads.unshift(upload);
                // Do NOT auto-start upload
                // this.processUpload(upload);
            });
            
            // Reset input
            this.$refs.fileInput.value = '';
        },

        startUpload(uploadId) {
            const upload = this.uploads.find(u => u.id === uploadId);
            if (!upload || upload.status === 'uploading' || upload.status === 'success') return;

            this.processUpload(upload);
        },

        startAll() {
             this.uploads.forEach(upload => {
                if (upload.status === 'pending' || upload.status === 'error') {
                    this.processUpload(upload);
                }
             });
        },

        processUpload(upload) {
            upload.status = 'uploading';
            upload.statusText = 'Uploading...';
            
            const formData = new FormData();
            formData.append('file', upload.file);
            formData.append('title', upload.title);
            formData.append('alt', upload.alt);
            formData.append('description', upload.description);
            
            const xhr = new XMLHttpRequest();
            upload.xhr = xhr;
            
            // Store the upload ID for later lookup
            const id = upload.id;
            
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    // Find the reactive upload object in the array
                    const u = this.uploads.find(item => item.id === id);
                    if (u) {
                        u.progress = Math.round((e.loaded * 100) / e.total);
                    }
                }
            });
            
            xhr.addEventListener('load', () => {
                // Find the reactive upload object in the array
                const u = this.uploads.find(item => item.id === id);
                if (!u) return;
                
                if (xhr.status >= 200 && xhr.status < 300) {
                    u.status = 'success';
                    u.statusText = 'Upload complete';
                    u.progress = 100;
                } else {
                    u.status = 'error';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        u.statusText = response.error || 'Upload failed';
                    } catch (e) {
                         u.statusText = 'Upload failed';
                    }
                }
            });
            
            xhr.addEventListener('error', () => {
                const u = this.uploads.find(item => item.id === id);
                if (u) {
                    u.status = 'error';
                    u.statusText = 'Network error';
                }
            });
            
            xhr.addEventListener('abort', () => {
                const u = this.uploads.find(item => item.id === id);
                if (u) {
                    u.status = 'error';
                    u.statusText = 'Cancelled';
                }
            });
            
            xhr.open('POST', '/admin/media/upload');
            
            // Add CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (csrfToken) {
                xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
            }
            
            xhr.send(formData);
        },
        
        cancelUpload(id) {
            const upload = this.uploads.find(u => u.id === id);
            if (upload && upload.xhr) {
                upload.xhr.abort();
            } else if (upload) {
                // If pending, just remove? Or mark cancelled?
                // For now just remove from list if pending
                if (upload.status === 'pending') {
                    this.uploads = this.uploads.filter(u => u.id !== id);
                }
            }
        },

        removeUpload(id) {
             this.uploads = this.uploads.filter(u => u.id !== id);
        },

        formatSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }
    }
}
