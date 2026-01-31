/**
 * Image Upload Component - Reusable drag and drop + link input
 * Usage: new ImageUpload(elementId, options)
 */

class ImageUpload {
    constructor(elementId, options = {}) {
        this.container = document.getElementById(elementId);
        if (!this.container) {
            console.error(`Element with id "${elementId}" not found`);
            return;
        }

        this.options = {
            uploadUrl: options.uploadUrl || '/api/upload-image.php',
            maxSize: options.maxSize || 15 * 1024 * 1024, // 15MB
            allowedTypes: options.allowedTypes || ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/svg+xml'],
            currentImage: options.currentImage || '',
            inputName: options.inputName || 'image_url',
            folder: options.folder || 'general',
            onUploadSuccess: options.onUploadSuccess || null,
            onUploadError: options.onUploadError || null,
            showLinkOption: options.showLinkOption !== false, // default true
            aspectRatio: options.aspectRatio || null, // e.g., '16/9'
            ...options
        };

        this.currentImageUrl = this.options.currentImage;
        this.init();
    }

    init() {
        this.container.innerHTML = this.getHTML();
        this.bindEvents();
        if (this.currentImageUrl) {
            // Construct full URL for preview if it's a relative path
            let previewUrl = this.currentImageUrl;
            if (this.currentImageUrl && !this.currentImageUrl.startsWith('http://') && !this.currentImageUrl.startsWith('https://') && !this.currentImageUrl.startsWith('//')) {
                const baseUrl = window.location.origin;
                const pathWithSlash = this.currentImageUrl.startsWith('/') ? this.currentImageUrl : '/' + this.currentImageUrl;
                previewUrl = baseUrl + pathWithSlash;
            }
            this.showPreview(previewUrl);
        }
    }

    getHTML() {
        return `
            <div class="image-upload-wrapper">
                <div class="image-upload-area" id="${this.container.id}_uploadArea">
                        <input type="file" id="${this.container.id}_fileInput" accept="${this.options.folder === 'music' ? 'audio/*' : 'image/*'}" style="display: none;">
                    <div class="upload-placeholder" id="${this.container.id}_placeholder">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Arraste e solte o arquivo aqui ou clique para selecionar</p>
                        <small>${this.options.folder === 'music' ? 'Apenas áudio: MP3, WAV, OGG, WEBM, M4A' : 'Apenas imagens: JPG, PNG, GIF, WEBP, BMP, SVG'} (máx. ${this.formatFileSize(this.options.maxSize)})</small>
                    </div>
                    <div class="upload-preview" id="${this.container.id}_preview" style="display: none;">
                        ${this.options.folder === 'music' ? `
                        <div style="text-align: center; padding: 20px;">
                            <i class="fas fa-music" style="font-size: 3rem; color: var(--admin-accent); margin-bottom: 10px;"></i>
                            <p id="${this.container.id}_previewFileName" style="margin: 10px 0; color: var(--admin-text-primary); font-weight: 600;"></p>
                            <audio id="${this.container.id}_previewAudio" controls style="width: 100%; max-width: 400px; margin: 10px auto; display: block;">
                                <source id="${this.container.id}_previewAudioSource" src="" type="audio/mpeg">
                                Seu navegador não suporta o elemento de áudio.
                            </audio>
                        </div>
                        ` : `
                        <img id="${this.container.id}_previewImg" src="" alt="Preview">
                        `}
                        <button type="button" class="btn-remove-image" onclick="window.imageUpload_${this.container.id}.removeImage()">
                            <i class="fas fa-times"></i> Remover
                        </button>
                    </div>
                </div>
                ${this.options.showLinkOption ? `
                <div class="image-upload-divider">
                    <span>OU</span>
                </div>
                <div class="image-link-input">
                    <label>Cole o link ou caminho da imagem:</label>
                    <div class="input-group">
                        <input type="text" 
                               id="${this.container.id}_linkInput" 
                               name="${this.options.inputName}" 
                               placeholder="uploads/banners/imagem.png ou https://exemplo.com/imagem.jpg"
                               value="${this.currentImageUrl || ''}"
                               onchange="window.imageUpload_${this.container.id}.handleLinkChange()">
                        <button type="button" class="btn-apply-link" onclick="window.imageUpload_${this.container.id}.applyLink()">
                            <i class="fas fa-check"></i> Aplicar
                        </button>
                    </div>
                </div>
                ` : ''}
                <input type="hidden" 
                       id="${this.container.id}_hiddenInput" 
                       name="${this.options.inputName}" 
                       value="${this.currentImageUrl || ''}">
            </div>
        `;
    }

    bindEvents() {
        const uploadArea = document.getElementById(`${this.container.id}_uploadArea`);
        const fileInput = document.getElementById(`${this.container.id}_fileInput`);

        // Click to select
        uploadArea.addEventListener('click', (e) => {
            if (!e.target.closest('.btn-remove-image')) {
                fileInput.click();
            }
        });

        // File input change
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                this.handleFile(e.target.files[0]);
            }
        });

        // Drag and drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                this.handleFile(files[0]);
            }
        });

        // Link input enter key
        if (this.options.showLinkOption) {
            const linkInput = document.getElementById(`${this.container.id}_linkInput`);
            linkInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.applyLink();
                }
            });
        }

        // Store instance globally for onclick handlers
        window[`imageUpload_${this.container.id}`] = this;
    }

    handleFile(file) {
        // Validate type - check if it's an image or audio (depending on folder)
        const isMusicFolder = this.options.folder === 'music';
        const allowedImageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
        const allowedAudioExtensions = ['mp3', 'wav', 'ogg', 'webm', 'm4a'];
        const fileExtension = file.name.split('.').pop().toLowerCase();
        
        if (isMusicFolder) {
            // For music folder, accept audio files
            const allowedAudioTypes = this.options.allowedTypes || ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/webm', 'audio/x-m4a'];
            if (!allowedAudioTypes.includes(file.type) && !allowedAudioExtensions.includes(fileExtension)) {
                this.showError('Tipo de arquivo não permitido. Apenas arquivos de áudio são aceitos (MP3, WAV, OGG, WEBM, M4A).');
                return;
            }
            if (!file.type.startsWith('audio/')) {
                this.showError('Arquivo não é um arquivo de áudio válido.');
                return;
            }
        } else {
            // For other folders, accept images only
            const allowedImageTypes = this.options.allowedTypes || ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/svg+xml'];
            if (!allowedImageTypes.includes(file.type) && !allowedImageExtensions.includes(fileExtension)) {
                this.showError('Tipo de arquivo não permitido. Apenas imagens são aceitas (JPG, PNG, GIF, WEBP, BMP, SVG).');
                return;
            }
            if (!file.type.startsWith('image/')) {
                this.showError('Arquivo não é uma imagem válida.');
                return;
            }
        }

        // Validate size
        if (file.size > this.options.maxSize) {
            this.showError(`Arquivo muito grande. Tamanho máximo: ${this.formatFileSize(this.options.maxSize)}`);
            return;
        }

        // Show preview
        const reader = new FileReader();
        reader.onload = (e) => {
            this.showPreview(e.target.result);
            this.uploadFile(file);
        };
        reader.readAsDataURL(file);
    }

    uploadFile(file) {
        const formData = new FormData();
        formData.append('image', file);
        formData.append('folder', this.options.folder);

        // Show loading
        this.showLoading();

        fetch(this.options.uploadUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Check if we're in admin panel
                const isAdmin = window.location.pathname.includes('/admin/');
                const relativePath = data.url;
                
                // If in admin, save full URL; otherwise save relative path
                let urlToSave = relativePath;
                if (isAdmin && relativePath && !relativePath.startsWith('http://') && !relativePath.startsWith('https://')) {
                    const baseUrl = window.location.origin;
                    const pathWithSlash = relativePath.startsWith('/') ? relativePath : '/' + relativePath;
                    urlToSave = baseUrl + pathWithSlash;
                }
                
                this.currentImageUrl = urlToSave;
                
                // For preview, construct full URL
                let previewUrl = relativePath;
                if (relativePath && !relativePath.startsWith('http://') && !relativePath.startsWith('https://')) {
                    const baseUrl = window.location.origin;
                    const pathWithSlash = relativePath.startsWith('/') ? relativePath : '/' + relativePath;
                    previewUrl = baseUrl + pathWithSlash;
                }
                
                // Show preview with full URL
                this.showPreview(previewUrl);
                this.updateHiddenInput();
                
                if (this.options.onUploadSuccess) {
                    this.options.onUploadSuccess(urlToSave);
                }
                this.showSuccess('Imagem enviada com sucesso!');
            } else {
                this.showError(data.message || 'Erro ao enviar imagem');
                this.removeImage();
            }
        })
        .catch(error => {
            console.error('Upload error:', error);
            this.showError('Erro ao enviar imagem. Tente novamente.');
            this.removeImage();
        })
        .finally(() => {
            this.hideLoading();
        });
    }

    handleLinkChange() {
        const linkInput = document.getElementById(`${this.container.id}_linkInput`);
        const url = linkInput.value.trim();
        
        // Aceitar qualquer valor que não esteja vazio
        if (url) {
            this.currentImageUrl = url;
            // Para preview, construir URL completa se for caminho relativo
            let previewUrl = url;
            if (url && !url.startsWith('http://') && !url.startsWith('https://') && !url.startsWith('//')) {
                const baseUrl = window.location.origin;
                const pathWithSlash = url.startsWith('/') ? url : '/' + url;
                previewUrl = baseUrl + pathWithSlash;
            }
            this.showPreview(previewUrl);
            this.updateHiddenInput();
        }
    }

    applyLink() {
        const linkInput = document.getElementById(`${this.container.id}_linkInput`);
        const url = linkInput.value.trim();
        
        // Aceitar qualquer valor que não esteja vazio
        if (!url) {
            this.showError('Por favor, insira algo no campo');
            return;
        }

        // Check if we're in admin panel
        const isAdmin = window.location.pathname.includes('/admin/');
        
        // If in admin and URL is relative, convert to full URL
        let urlToSave = url;
        if (isAdmin && url && !url.startsWith('http://') && !url.startsWith('https://') && !url.startsWith('//')) {
            const baseUrl = window.location.origin;
            const pathWithSlash = url.startsWith('/') ? url : '/' + url;
            urlToSave = baseUrl + pathWithSlash;
        }
        
        this.currentImageUrl = urlToSave;
        
        // Para preview, construir URL completa se for caminho relativo
        let previewUrl = url;
        if (url && !url.startsWith('http://') && !url.startsWith('https://') && !url.startsWith('//')) {
            const baseUrl = window.location.origin;
            const pathWithSlash = url.startsWith('/') ? url : '/' + url;
            previewUrl = baseUrl + pathWithSlash;
        }
        
        this.showPreview(previewUrl);
        this.updateHiddenInput();
        
        if (this.options.onUploadSuccess) {
            this.options.onUploadSuccess(urlToSave);
        }
        
        this.showSuccess('Link aplicado com sucesso!');
    }

    showPreview(url) {
        const placeholder = document.getElementById(`${this.container.id}_placeholder`);
        const preview = document.getElementById(`${this.container.id}_preview`);
        const isMusicFolder = this.options.folder === 'music';
        
        if (placeholder) placeholder.style.display = 'none';
        if (preview) preview.style.display = 'block';
        
        if (isMusicFolder) {
            // For music files, show audio player
            const previewAudio = document.getElementById(`${this.container.id}_previewAudio`);
            const previewAudioSource = document.getElementById(`${this.container.id}_previewAudioSource`);
            const previewFileName = document.getElementById(`${this.container.id}_previewFileName`);
            
            if (previewAudioSource) {
                // Construct full URL if relative path
                let audioUrl = url;
                if (url && !url.startsWith('http://') && !url.startsWith('https://') && !url.startsWith('//')) {
                    const baseUrl = window.location.origin;
                    const pathWithSlash = url.startsWith('/') ? url : '/' + url;
                    audioUrl = baseUrl + pathWithSlash;
                }
                
                previewAudioSource.src = audioUrl;
                if (previewAudio) {
                    previewAudio.load();
                }
            }
            
            if (previewFileName) {
                // Extract filename from URL
                const fileName = url.split('/').pop() || 'Arquivo de áudio';
                previewFileName.textContent = fileName;
            }
        } else {
            // For images, show image preview
            const previewImg = document.getElementById(`${this.container.id}_previewImg`);
            if (previewImg) {
                previewImg.src = url;
                if (this.options.aspectRatio) {
                    previewImg.style.aspectRatio = this.options.aspectRatio;
                }
            }
        }
    }

    removeImage() {
        const placeholder = document.getElementById(`${this.container.id}_placeholder`);
        const preview = document.getElementById(`${this.container.id}_preview`);
        const linkInput = document.getElementById(`${this.container.id}_linkInput`);
        
        if (placeholder) placeholder.style.display = 'flex';
        if (preview) preview.style.display = 'none';
        if (linkInput) linkInput.value = '';
        
        this.currentImageUrl = '';
        this.updateHiddenInput();
    }

    updateHiddenInput() {
        const hiddenInput = document.getElementById(`${this.container.id}_hiddenInput`);
        if (hiddenInput) {
            hiddenInput.value = this.currentImageUrl;
        }
    }

    showLoading() {
        const uploadArea = document.getElementById(`${this.container.id}_uploadArea`);
        if (uploadArea) {
            uploadArea.classList.add('uploading');
        }
    }

    hideLoading() {
        const uploadArea = document.getElementById(`${this.container.id}_uploadArea`);
        if (uploadArea) {
            uploadArea.classList.remove('uploading');
        }
    }

    showError(message) {
        this.showToast(message, 'error');
    }

    showSuccess(message) {
        this.showToast(message, 'success');
    }

    showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 100);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    getValue() {
        return this.currentImageUrl;
    }

    setValue(url) {
        this.currentImageUrl = url;
        this.updateHiddenInput();
        if (url) {
            this.showPreview(url);
        } else {
            this.removeImage();
        }
    }
}


