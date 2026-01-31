<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . APP_URL . '/admin/login.php');
    exit;
}

$page_title = 'Gerenciar Marcas';
$page_subtitle = 'Adicione e edite marcas de perfumes';
$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marcas | <?php echo APP_NAME; ?> Admin</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="<?php echo APP_URL; ?>/assets/js/admin-tooltips.js"></script>
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <div class="admin-container">
                <div class="page-header-admin">
                    <div>
                        <h1><i class="fas fa-tags"></i> Gerenciar Marcas</h1>
                        <p>Adicione, edite e gerencie as marcas de perfumes disponíveis no marketplace</p>
                    </div>
                    <div style="display: flex; gap: var(--admin-space-sm);">
                        <button onclick="openImportModal()" class="btn-outline">
                            <i class="fas fa-file-upload"></i> Importar em Massa
                        </button>
                    <button class="btn-primary" onclick="openModal('create')">
                            <i class="fas fa-plus"></i> Nova Marca
                    </button>
                    </div>
                </div>

                <div class="admin-card">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> Marcas Cadastradas</h3>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <div id="bulkActions" style="display: none; gap: 10px; align-items: center;">
                                <button onclick="bulkActivateBrands()" class="btn-sm btn-success">
                                    <i class="fas fa-check"></i> Ativar Selecionadas
                                </button>
                                <button onclick="bulkDeactivateBrands()" class="btn-sm btn-warning">
                                    <i class="fas fa-times"></i> Desativar Selecionadas
                                </button>
                                <span id="selectedCount" style="color: var(--admin-text-secondary); font-size: 0.875rem;"></span>
                            </div>
                        <button onclick="exportBrandsCSV()" class="btn-sm btn-outline">
                            <i class="fas fa-download"></i> Exportar CSV
                        </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="brandsContainer">
                        <div class="flex justify-center items-center gap-4" style="padding: 40px;">
                            <div class="loading-spinner"></div>
                                <span class="text-muted">Carregando marcas...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>


    <!-- Modal -->
    <div id="brandModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Nova Marca</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            
            <form id="brandForm">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="brandId">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

                <div class="form-group">
                    <label class="label">
                        <i class="fas fa-info-circle" data-tooltip="Nome da marca de perfume"></i>
                        Nome da Marca <span class="required">*</span>
                    </label>
                    <input type="text" name="name" id="brandName" class="form-control" required placeholder="Ex: Chanel">
                    <div class="form-example">
                        <strong>Exemplos:</strong> "Chanel", "Dior", "Versace", "Tom Ford", "Creed"<br>
                        <strong>Dica:</strong> Use o nome oficial da marca como aparece nos produtos
                    </div>
                    <small class="form-hint">Nome da marca que aparecerá nos filtros e produtos do marketplace</small>
                </div>

                <div class="form-group">
                    <label class="label">
                        <i class="fas fa-info-circle" data-tooltip="Descrição opcional sobre a marca"></i>
                        Descrição
                    </label>
                    <textarea name="description" id="brandDesc" class="form-control" rows="3" placeholder="Ex: Marca francesa de luxo fundada em 1910, conhecida por fragrâncias icônicas..."></textarea>
                    <div class="form-example">
                        <strong>Exemplo:</strong> "Marca francesa de luxo fundada em 1910, conhecida por fragrâncias icônicas como Chanel N°5. Especializada em perfumes exclusivos e sofisticados."
                    </div>
                    <small class="form-hint">Descrição opcional sobre a marca (história, características, etc.)</small>
                </div>

                <label class="checkbox">
                    <input type="checkbox" name="is_active" id="brandActive" value="1" checked>
                    <span><strong>Marca Ativa</strong></span>
                    <div class="checkbox-hint">Quando marcado, a marca aparecerá nos filtros e poderá ser selecionada ao cadastrar produtos. Desmarque para ocultar temporariamente.</div>
                </label>

                <div class="modal-footer">
                    <button type="button" class="btn-outline" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Import Modal -->
    <div id="importModal" class="modal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h2><i class="fas fa-file-upload"></i> Importar Marcas em Massa</h2>
                <button class="modal-close" onclick="closeImportModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="label">
                        <i class="fas fa-info-circle" data-tooltip="Cole ou digite os nomes das marcas, um por linha"></i>
                        Conteúdo do Arquivo TXT <span class="required">*</span>
                    </label>
                    <textarea 
                        id="importContent" 
                        class="form-control" 
                        rows="15" 
                        placeholder="Cole aqui o conteúdo do arquivo TXT com os nomes das marcas, um por linha.&#10;&#10;Exemplo:&#10;Chanel&#10;Dior&#10;Versace&#10;Tom Ford&#10;Creed"
                        style="font-family: monospace; font-size: 0.9rem;"
                    ></textarea>
                    <div class="form-example">
                        <strong>Formato esperado:</strong> Uma marca por linha, sem vírgulas ou separadores especiais.<br>
                        <strong>Exemplo:</strong><br>
                        <code style="display: block; margin-top: 5px; padding: 10px; background: var(--admin-bg-secondary); border-radius: 4px;">
                            Chanel<br>
                            Dior<br>
                            Versace<br>
                            Tom Ford<br>
                            Creed
                        </code>
                    </div>
                    <small class="form-hint">Cole o conteúdo do arquivo TXT ou digite os nomes das marcas, um por linha</small>
                </div>

                <div class="form-group">
                    <label class="label">
                        <i class="fas fa-info-circle" data-tooltip="Descrição opcional que será aplicada a todas as marcas importadas"></i>
                        Descrição (Opcional)
                    </label>
                    <textarea 
                        id="importDescription" 
                        class="form-control" 
                        rows="3" 
                        placeholder="Ex: Marcas importadas em massa em 11/12/2025"
                    ></textarea>
                    <small class="form-hint">Descrição opcional que será aplicada a todas as marcas importadas</small>
                </div>

                <div class="form-group">
                    <label class="label">
                        <i class="fas fa-info-circle" data-tooltip="Status inicial das marcas importadas"></i>
                        Status Inicial
                    </label>
                    <select id="importStatus" class="form-control">
                        <option value="1">Ativo</option>
                        <option value="0">Inativo</option>
                    </select>
                    <small class="form-hint">Status inicial das marcas importadas (podem ser ativadas depois)</small>
                </div>

                <div class="alert alert-info" style="margin-top: 20px;">
                    <i class="fas fa-info-circle"></i>
                    <strong>Dica:</strong> Marcas duplicadas (mesmo nome, ignorando maiúsculas/minúsculas) serão automaticamente ignoradas.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-outline" onclick="closeImportModal()">Cancelar</button>
                <button type="button" id="importBtn" class="btn-primary">
                    <i class="fas fa-upload"></i> <span id="importBtnText">Importar Marcas</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div class="toast-container">
        <div id="toast" class="toast" style="display: none;">
            <span id="toastMsg"></span>
        </div>
    </div>

    <script>
        const modal = document.getElementById('brandModal');
        
        function showToast(message, type = 'success', duration = 3000) {
            const toast = document.getElementById('toast');
            const msg = document.getElementById('toastMsg');
            msg.textContent = message;
            toast.className = 'toast toast-' + type;
            toast.style.display = 'flex';
            
            setTimeout(() => {
                toast.style.display = 'none';
            }, duration);
        }

        async function loadBrands() {
            try {
                const res = await fetch('../api/admin-brands.php?action=list');
                const data = await res.json();
                
                if (data.success) {
                    renderBrands(data.brands);
                } else {
                    document.getElementById('brandsContainer').innerHTML = 
                        '<div class="alert alert-danger">' + (data.message || 'Erro ao carregar') + '</div>';
                }
            } catch (e) {
                console.error(e);
                showToast('Erro de conexao', 'error');
            }
        }

        function renderBrands(brands) {
            const container = document.getElementById('brandsContainer');
            
            if (brands.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">🏷️</div>
                        <h3>Nenhuma marca cadastrada</h3>
                        <p>Clique em "Nova Marca" para adicionar.</p>
                    </div>
                `;
                return;
            }

            let html = `
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">
                                    <label class="checkbox-label" style="margin: 0; cursor: pointer;">
                                        <input type="checkbox" id="selectAllBrands" onchange="toggleSelectAll()">
                                        <span></span>
                                    </label>
                                </th>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Status</th>
                                <th>Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            brands.forEach(b => {
                html += `
                    <tr>
                        <td>
                            <label class="checkbox-label" style="margin: 0; cursor: pointer;">
                                <input type="checkbox" class="brand-checkbox" value="${b.id}" onchange="updateBulkActions()">
                                <span></span>
                            </label>
                        </td>
                        <td>#${b.id}</td>
                        <td>
                            <strong>${escapeHtml(b.name)}</strong>
                            ${b.description ? '<br><small class="muted">' + escapeHtml(b.description) + '</small>' : ''}
                        </td>
                        <td>
                            ${b.is_active == 1 
                                ? '<span class="badge badge-success">Ativo</span>' 
                                : '<span class="badge badge-danger">Inativo</span>'}
                        </td>
                        <td>
                            <div class="table-actions">
                                <button class="btn-sm ghost" onclick='editBrand(${JSON.stringify(b)})'>Editar</button>
                                <button class="btn-sm danger" onclick="deleteBrand(${b.id})">Excluir</button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            html += '</tbody></table></div>';
            container.innerHTML = html;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function openModal(mode) {
            modal.classList.add('show');
            if (mode === 'create') {
                document.getElementById('modalTitle').textContent = 'Nova Marca';
                document.getElementById('formAction').value = 'create';
                document.getElementById('brandForm').reset();
                document.getElementById('brandId').value = '';
                document.getElementById('brandActive').checked = true;
            }
        }

        function editBrand(brand) {
            modal.classList.add('show');
            document.getElementById('modalTitle').textContent = 'Editar Marca';
            document.getElementById('formAction').value = 'update';
            document.getElementById('brandId').value = brand.id;
            document.getElementById('brandName').value = brand.name;
            document.getElementById('brandDesc').value = brand.description || '';
            document.getElementById('brandActive').checked = brand.is_active == 1;
        }

        function closeModal() {
            modal.classList.remove('show');
        }

        document.getElementById('brandForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            
            try {
                const res = await fetch('../api/admin-brands.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if (data.success) {
                    closeModal();
                    loadBrands();
                    showToast(data.message || 'Marca salva!');
                } else {
                    showToast(data.message || 'Erro ao salvar', 'error');
                }
            } catch (e) {
                showToast('Erro de conexao', 'error');
            }
        });

        async function deleteBrand(id) {
            if (!confirm('⚠️ ATENÇÃO: Tem certeza que deseja excluir esta marca?\n\nEsta ação não pode ser desfeita. Produtos vinculados a esta marca não serão excluídos, mas a marca será removida dos filtros.')) return;

            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);
            formData.append('csrf_token', '<?php echo $csrf; ?>');

            try {
                const res = await fetch('../api/admin-brands.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if (data.success) {
                    loadBrands();
                    showToast('Marca removida!');
                } else {
                    showToast(data.message || 'Erro ao excluir', 'error');
                }
            } catch (e) {
                showToast('Erro de conexao', 'error');
            }
        }

        // Close modal on outside click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });

        // Close import modal on outside click
        const importModal = document.getElementById('importModal');
        if (importModal) {
            importModal.addEventListener('click', (e) => {
                if (e.target === importModal) closeImportModal();
            });
        }

        function openImportModal() {
            const modal = document.getElementById('importModal');
            if (modal) {
                modal.classList.add('show');
                // Ensure button has event listener when modal opens
                setTimeout(function() {
                    const btn = document.getElementById('importBtn');
                    if (btn) {
                        // Remove any existing onclick
                        btn.removeAttribute('onclick');
                        btn.onclick = null;
                        // Remove existing listeners by cloning
                        const newBtn = btn.cloneNode(true);
                        btn.parentNode.replaceChild(newBtn, btn);
                        // Add fresh listener
                        newBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            console.log('=== BOTÃO CLICADO (modal aberto) ===');
                            processImport(e);
                            return false;
                        }, true);
                    }
                }, 50);
            }
        }

        function closeImportModal() {
            document.getElementById('importModal').classList.remove('show');
            document.getElementById('importContent').value = '';
            document.getElementById('importDescription').value = '';
            document.getElementById('importStatus').value = '1';
        }

        async function processImport(event) {
            // Prevent any default behavior
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            console.log('processImport chamada');
            
            const importBtn = document.getElementById('importBtn');
            const importBtnText = document.getElementById('importBtnText');
            const contentEl = document.getElementById('importContent');
            const statusEl = document.getElementById('importStatus');
            const descriptionEl = document.getElementById('importDescription');
            
            if (!contentEl || !statusEl || !descriptionEl) {
                console.error('Elementos do formulário não encontrados');
                showToast('Erro: Elementos do formulário não encontrados', 'error');
                return;
            }
            
            const content = contentEl.value.trim();
            const status = statusEl.value;
            const description = descriptionEl.value.trim();

            console.log('Conteúdo:', content);
            console.log('Status:', status);
            console.log('Descrição:', description);

            if (!content) {
                showToast('Por favor, insira o conteúdo do arquivo TXT', 'error');
                return;
            }

            const lines = content.split('\n').map(line => line.trim()).filter(line => line.length > 0);
            
            if (lines.length === 0) {
                showToast('Nenhuma marca válida encontrada', 'error');
                return;
            }

            // Disable button and show loading
            if (importBtn) {
                importBtn.disabled = true;
                if (importBtnText) {
                    importBtnText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importando...';
                }
            }

            try {
                console.log('=== INICIANDO REQUISIÇÃO ===');
                console.log('Linhas processadas:', lines);
                console.log('Total de linhas:', lines.length);
                console.log('Status:', status);
                console.log('Descrição:', description);
                
                const apiUrl = '<?php echo APP_URL; ?>/api/admin-brands.php';
                console.log('URL da API:', apiUrl);
                
                const requestBody = {
                        action: 'bulk_import',
                        brands: lines,
                        is_active: status,
                        description: description || null,
                        csrf_token: '<?php echo $csrf; ?>'
                };
                
                console.log('Request Body:', JSON.stringify(requestBody, null, 2));
                
                const response = await fetch(apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(requestBody)
                });

                console.log('Response Status:', response.status);
                console.log('Response OK:', response.ok);
                console.log('Response Headers:', response.headers);

                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('Erro na resposta:', errorText);
                    throw new Error(`HTTP ${response.status}: ${response.statusText} - ${errorText}`);
                }
                
                const responseText = await response.text();
                console.log('Response Text:', responseText);
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('Erro ao fazer parse do JSON:', parseError);
                    console.error('Response text que falhou:', responseText);
                    throw new Error('Resposta inválida do servidor: ' + responseText.substring(0, 200));
                }
                
                console.log('Data parsed:', data);
                
                if (data.success) {
                    let message = `${data.created} marca(s) importada(s) com sucesso!`;
                    if (data.skipped > 0) {
                        message += ` (${data.skipped} já existiam)`;
                    }
                    showToast(message, 'success', 5000);
                    closeImportModal();
                    loadBrands();
                } else {
                    showToast(data.message || 'Erro ao importar marcas', 'error', 6000);
                }
            } catch (e) {
                console.error('Erro na importação:', e);
                console.error('Stack:', e.stack);
                showToast('Erro de conexão: ' + e.message, 'error', 6000);
            } finally {
                // Re-enable button
                if (importBtn) {
                    importBtn.disabled = false;
                    if (importBtnText) {
                        importBtnText.innerHTML = '<i class="fas fa-upload"></i> Importar Marcas';
                    }
                }
            }
        }

        function exportBrandsCSV() {
            const container = document.getElementById('brandsContainer');
            const table = container.querySelector('table');
            
            if (!table) {
                showToast('Nenhum dado para exportar', 'error');
                return;
            }

            let csv = [];
            const rows = table.querySelectorAll('tr');

            rows.forEach(row => {
                const cols = row.querySelectorAll('th, td');
                const rowData = [];
                cols.forEach((col, index) => {
                    // Skip actions column
                    if (index === cols.length - 1) return;
                    let text = col.innerText.replace(/"/g, '""');
                    rowData.push('"' + text + '"');
                });
                if (rowData.length > 0) {
                    csv.push(rowData.join(','));
                }
            });

            const csvContent = csv.join('\n');
            const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'marcas_' + new Date().toISOString().split('T')[0] + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAllBrands');
            const checkboxes = document.querySelectorAll('.brand-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
            });
            updateBulkActions();
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.brand-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            const selectAll = document.getElementById('selectAllBrands');
            
            const count = checkboxes.length;
            
            if (count > 0) {
                bulkActions.style.display = 'flex';
                selectedCount.textContent = `${count} selecionada(s)`;
            } else {
                bulkActions.style.display = 'none';
            }
            
            // Update select all checkbox
            const allCheckboxes = document.querySelectorAll('.brand-checkbox');
            if (selectAll && allCheckboxes.length > 0) {
                selectAll.checked = checkboxes.length === allCheckboxes.length;
                selectAll.indeterminate = checkboxes.length > 0 && checkboxes.length < allCheckboxes.length;
            }
        }

        async function bulkActivateBrands() {
            const checkboxes = document.querySelectorAll('.brand-checkbox:checked');
            const ids = Array.from(checkboxes).map(cb => parseInt(cb.value));
            
            if (ids.length === 0) {
                showToast('Selecione pelo menos uma marca', 'error');
                return;
            }
            
            if (!confirm(`Deseja ativar ${ids.length} marca(s) selecionada(s)?`)) {
                return;
            }
            
            try {
                const response = await fetch('<?php echo APP_URL; ?>/api/admin-brands.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'bulk_activate',
                        ids: ids,
                        csrf_token: '<?php echo $csrf; ?>'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast(`${ids.length} marca(s) ativada(s) com sucesso!`, 'success');
                    // Uncheck all
                    checkboxes.forEach(cb => cb.checked = false);
                    const selectAll = document.getElementById('selectAllBrands');
                    if (selectAll) selectAll.checked = false;
                    updateBulkActions();
                    loadBrands();
                } else {
                    showToast(data.message || 'Erro ao ativar marcas', 'error');
                }
            } catch (e) {
                console.error('Erro:', e);
                showToast('Erro de conexão', 'error');
            }
        }

        async function bulkDeactivateBrands() {
            const checkboxes = document.querySelectorAll('.brand-checkbox:checked');
            const ids = Array.from(checkboxes).map(cb => parseInt(cb.value));
            
            if (ids.length === 0) {
                showToast('Selecione pelo menos uma marca', 'error');
                return;
            }
            
            if (!confirm(`Deseja desativar ${ids.length} marca(s) selecionada(s)?`)) {
                return;
            }
            
            try {
                const response = await fetch('<?php echo APP_URL; ?>/api/admin-brands.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'bulk_deactivate',
                        ids: ids,
                        csrf_token: '<?php echo $csrf; ?>'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast(`${ids.length} marca(s) desativada(s) com sucesso!`, 'success');
                    // Uncheck all
                    checkboxes.forEach(cb => cb.checked = false);
                    const selectAll = document.getElementById('selectAllBrands');
                    if (selectAll) selectAll.checked = false;
                    updateBulkActions();
                    loadBrands();
                } else {
                    showToast(data.message || 'Erro ao desativar marcas', 'error');
                }
            } catch (e) {
                console.error('Erro:', e);
                showToast('Erro de conexão', 'error');
            }
        }

        // Load brands on page load
        loadBrands();
        
        // Add event listener to import button
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOMContentLoaded - Configurando botão de importação');
            setupImportButton();
        });
        
        function setupImportButton() {
            const importBtn = document.getElementById('importBtn');
            if (importBtn) {
                console.log('Botão importBtn encontrado, configurando event listener');
                // Remove any existing onclick
                importBtn.removeAttribute('onclick');
                importBtn.onclick = null;
                
                // Remove all existing event listeners by cloning
                const newBtn = importBtn.cloneNode(true);
                importBtn.parentNode.replaceChild(newBtn, importBtn);
                
                // Add fresh event listener
                newBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    console.log('=== BOTÃO CLICADO ===');
                    processImport(e);
                    return false;
                }, true); // Use capture phase
                
                console.log('Event listener configurado com sucesso');
            } else {
                console.warn('Botão importBtn não encontrado ainda, tentando novamente...');
                setTimeout(setupImportButton, 500);
            }
        }
    </script>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
