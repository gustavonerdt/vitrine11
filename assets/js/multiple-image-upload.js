/**
 * Multiple Image Upload Component - Suporta até 5 imagens
 * Usage: new MultipleImageUpload(elementId, options)
 */

class MultipleImageUpload {
    constructor(elementId, options = {}) {
        this.container = document.getElementById(elementId);
        if (!this.container) {
            console.error(`Element with id "${elementId}" not found`);
            return;
        }

        this.options = {
            uploadUrl: options.uploadUrl || '/api/upload-image.php',
            maxSize: options.maxSize || 15 * 1024 * 1024, // 15MB
            allowedTypes: options.allowedTypes || ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'],
            maxImages: options.maxImages || 5,
            inputName: options.inputName || 'product_images',
            folder: options.folder || 'products',
            showLinkOption: options.showLinkOption !== false,
            ...options
        };

        this.images = [];
        this.coverIndex = 0;
        this.init();
    }

    init() {
        this.container.innerHTML = this.getHTML();
        this.bindEvents();
    }

    getHTML() {
        return `
            <div class="multiple-image-upload-wrapper">
                <div class="images-grid" id="${this.container.id}_imagesGrid">
                    ${this.renderImages()}
                </div>
                
                ${this.images.length < this.options.maxImages ? `
                <div class="image-upload-area" id="${this.container.id}_uploadArea">
                    <input type="file" id="${this.container.id}_fileInput" accept="image/*" multiple style="display: none;">
                    <div class="upload-placeholder" id="${this.container.id}_placeholder">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p><strong>Arraste e solte</strong> as imagens aqui</p>
                        <p style="font-size: 0.875rem; margin-top: 4px;">ou <strong>clique para selecionar</strong></p>
                        <small>Máximo ${this.options.maxImages} imagens (JPG, PNG, GIF, WEBP)</small>
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
                               placeholder="uploads/banners/imagem.png ou https://exemplo.com/imagem.jpg"
                               class="form-control">
                        <button type="button" class="btn-apply-link" onclick="window.multipleImageUpload_${this.container.id}.addImageFromLink()">
                            <i class="fas fa-plus"></i> Adicionar
                        </button>
                    </div>
                </div>
                ` : ''}
                ` : `
                <div class="max-images-reached">
                    <i class="fas fa-info-circle"></i>
                    <p>Máximo de ${this.options.maxImages} imagens atingido. Remova uma imagem para adicionar outra.</p>
                </div>
                `}
                
                <div id="${this.container.id}_hiddenInputs"></div>
            </div>
        `;
    }

    renderImages() {
        if (this.images.length === 0) {
            return '';
        }

        return this.images.map((img, index) => {
            // Use previewUrl for display if available, otherwise construct from relative path
            let displayUrl = img.previewUrl || img.url;
            // If it's a relative path (starts with uploads/), construct full URL
            if (displayUrl && !displayUrl.startsWith('http://') && !displayUrl.startsWith('https://') && !displayUrl.startsWith('//')) {
                const baseUrl = window.location.origin;
                const pathWithSlash = displayUrl.startsWith('/') ? displayUrl : '/' + displayUrl;
                displayUrl = baseUrl + pathWithSlash;
            }
            return `
            <div class="image-card" data-index="${index}">
                <div class="image-card-wrapper">
                    <img src="${displayUrl}" alt="Imagem ${index + 1}">
                    <div class="image-card-overlay">
                        <button type="button" class="btn-set-cover" onclick="window.multipleImageUpload_${this.container.id}.setCover(${index})" title="Definir como capa">
                            <i class="fas fa-star"></i> ${index === this.coverIndex ? 'Capa' : 'Definir Capa'}
                        </button>
                        <button type="button" class="btn-remove-image" onclick="window.multipleImageUpload_${this.container.id}.removeImage(${index})" title="Remover">
                            <i class="fas fa-trash"></i> Remover
                        </button>
                    </div>
                    ${index === this.coverIndex ? '<div class="cover-badge"><i class="fas fa-star"></i> Capa</div>' : ''}
                </div>
            </div>
        `;
        }).join('');
    }

    bindEvents() {
        const uploadArea = document.getElementById(`${this.container.id}_uploadArea`);
        const fileInput = document.getElementById(`${this.container.id}_fileInput`);

        if (!uploadArea || !fileInput) return;

        // Click to select
        uploadArea.addEventListener('click', () => {
            fileInput.click();
        });

        // File input change
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                this.handleFiles(Array.from(e.target.files));
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
            
            const files = Array.from(e.dataTransfer.files).filter(f => f.type.startsWith('image/'));
            if (files.length > 0) {
                this.handleFiles(files);
            }
        });

        // Link input enter key
        if (this.options.showLinkOption) {
            const linkInput = document.getElementById(`${this.container.id}_linkInput`);
            if (linkInput) {
                linkInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        this.addImageFromLink();
                    }
                });
            }
        }

        // Store instance globally
        window[`multipleImageUpload_${this.container.id}`] = this;
    }

    handleFiles(files) {
        const remainingSlots = this.options.maxImages - this.images.length;
        const filesToProcess = files.slice(0, remainingSlots);

        if (files.length > remainingSlots) {
            this.showToast(`Apenas ${remainingSlots} imagem(ns) foram adicionadas. Máximo de ${this.options.maxImages} imagens.`, 'warning');
        }

        filesToProcess.forEach(file => {
            this.processFile(file);
        });
    }

    processFile(file) {
        // Validate type
        if (!this.options.allowedTypes.includes(file.type) && !file.type.startsWith('image/')) {
            this.showToast('Tipo de arquivo não permitido. Apenas imagens são aceitas.', 'error');
            return;
        }

        // Validate size
        if (file.size > this.options.maxSize) {
            this.showToast(`Arquivo muito grande. Tamanho máximo: ${this.formatFileSize(this.options.maxSize)}`, 'error');
            return;
        }

        // Show preview
        const reader = new FileReader();
        reader.onload = (e) => {
            this.images.push({ url: e.target.result, isUploaded: false });
            this.updateDisplay();
            this.uploadFile(file, this.images.length - 1);
        };
        reader.readAsDataURL(file);
    }

    uploadFile(file, index) {
        const formData = new FormData();
        formData.append('image', file);
        formData.append('folder', this.options.folder);

        console.log('Uploading file:', file.name, 'to:', this.options.uploadUrl, 'folder:', this.options.folder);
        
        fetch(this.options.uploadUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Upload response status:', response.status);
            if (!response.ok) {
                return response.text().then(text => {
                    console.error('Upload failed. Response:', text);
                    throw new Error('Upload failed with status: ' + response.status);
                });
            }
            return response.json();
        })
        .then(data => {
            console.log('Upload response data:', data);
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
                
                this.images[index].url = urlToSave;
                
                // For preview, construct full URL
                if (relativePath && !relativePath.startsWith('http://') && !relativePath.startsWith('https://')) {
                    // It's a relative path, construct full URL
                    const baseUrl = window.location.origin;
                    const pathWithSlash = relativePath.startsWith('/') ? relativePath : '/' + relativePath;
                    this.images[index].previewUrl = baseUrl + pathWithSlash;
                } else {
                    // Already a full URL
                    this.images[index].previewUrl = relativePath;
                }
                this.images[index].isUploaded = true;
                this.updateDisplay();
                this.showToast('Imagem enviada com sucesso!', 'success');
            } else {
                console.error('Upload failed:', data.message);
                this.showToast(data.message || 'Erro ao enviar imagem', 'error');
                this.images.splice(index, 1);
                this.updateDisplay();
            }
        })
        .catch(error => {
            console.error('Upload error:', error);
            this.showToast('Erro ao enviar imagem: ' + (error.message || 'Erro desconhecido'), 'error');
            this.images.splice(index, 1);
            this.updateDisplay();
        });
    }

    addImageFromLink() {
        const linkInput = document.getElementById(`${this.container.id}_linkInput`);
        if (!linkInput) return;

        const url = linkInput.value.trim();
        
        // Aceitar qualquer valor que não esteja vazio
        if (!url) {
            this.showToast('Por favor, insira algo no campo', 'error');
            return;
        }

        // Aceitar qualquer valor - sem validação de URL
        if (this.images.length >= this.options.maxImages) {
            this.showToast(`Máximo de ${this.options.maxImages} imagens atingido.`, 'error');
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

        // Adicionar imagem com URL fornecida
        const imageData = { url: urlToSave, isUploaded: true };
        
        // Para preview, construir URL completa se for caminho relativo
        if (url && !url.startsWith('http://') && !url.startsWith('https://') && !url.startsWith('//')) {
            const baseUrl = window.location.origin;
            const pathWithSlash = url.startsWith('/') ? url : '/' + url;
            imageData.previewUrl = baseUrl + pathWithSlash;
        } else {
            imageData.previewUrl = url;
        }
        
        this.images.push(imageData);
        linkInput.value = '';
        this.updateDisplay();
        this.showToast('Imagem adicionada com sucesso!', 'success');
    }

    // Função removida - não validamos mais URLs, aceitamos qualquer valor

    setCover(index) {
        this.coverIndex = index;
        this.updateDisplay();
    }

    removeImage(index) {
        if (confirm('Remover esta imagem?')) {
            this.images.splice(index, 1);
            if (this.coverIndex >= this.images.length) {
                this.coverIndex = Math.max(0, this.images.length - 1);
            }
            this.updateDisplay();
        }
    }

    updateDisplay() {
        this.container.innerHTML = this.getHTML();
        this.bindEvents();
        this.updateHiddenInputs();
    }

    updateHiddenInputs() {
        const hiddenContainer = document.getElementById(`${this.container.id}_hiddenInputs`);
        if (!hiddenContainer) return;

        hiddenContainer.innerHTML = '';
        
        this.images.forEach((img, index) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `${this.options.inputName}[]`;
            input.value = img.url;
            hiddenContainer.appendChild(input);
        });

        // Cover index
        const coverInput = document.createElement('input');
        coverInput.type = 'hidden';
        coverInput.name = `${this.options.inputName}_cover_index`;
        coverInput.value = this.coverIndex;
        hiddenContainer.appendChild(coverInput);
    }

    getImages() {
        return this.images.map(img => img.url);
    }

    getCoverIndex() {
        return this.coverIndex;
    }

    setImages(images, coverIndex = 0) {
        this.images = images.map(url => {
            const img = { url, isUploaded: true };
            // Set previewUrl for relative paths (format: uploads/banners/filename.png)
            if (url && !url.startsWith('http://') && !url.startsWith('https://') && !url.startsWith('//')) {
                // It's a relative path, construct full URL
                const baseUrl = window.location.origin;
                const pathWithSlash = url.startsWith('/') ? url : '/' + url;
                img.previewUrl = baseUrl + pathWithSlash;
            } else {
                // Already a full URL
                img.previewUrl = url;
            }
            return img;
        });
        this.coverIndex = coverIndex;
        this.updateDisplay();
    }

    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 100);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 3000);
        }, 3000);
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }
}

