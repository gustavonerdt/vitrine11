<footer class="admin-footer">
    <div class="admin-footer-content">
        <p>Desenvolvido com 🧡 por <strong>Gustavo A. Félix</strong></p>
        <p class="admin-footer-version">Vitrine Independente v1.1</p>
    </div>
</footer>

<style>
.admin-footer {
    margin-top: auto;
    padding: var(--admin-space-lg) var(--admin-space-xl);
    border-top: 1px solid var(--admin-border);
    background: var(--admin-bg-secondary);
}

.admin-footer-content {
    text-align: center;
    color: var(--admin-text-muted);
    font-size: 0.875rem;
}

.admin-footer-content p {
    margin: var(--admin-space-xs) 0;
}

.admin-footer-version {
    font-size: 0.75rem;
    opacity: 0.7;
}

@media (max-width: 768px) {
    .admin-footer {
        padding: var(--admin-space-md);
    }
    
    .admin-footer-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: var(--admin-space-xs);
    }
    
    .admin-footer-content p {
        text-align: center;
        margin: 0;
    }
}
</style>

<!-- Overlay para mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
    // Sidebar Toggle (Expandir/Recolher)
    const sidebarToggle = document.getElementById('sidebarToggle');
    const adminSidebar = document.getElementById('adminSidebar');
    
    // Carregar estado salvo
    const sidebarExpanded = localStorage.getItem('sidebarExpanded') === 'true';
    if (sidebarExpanded && adminSidebar) {
        adminSidebar.classList.add('expanded');
    }
    
    if (sidebarToggle && adminSidebar) {
        sidebarToggle.addEventListener('click', function() {
            adminSidebar.classList.toggle('expanded');
            const isExpanded = adminSidebar.classList.contains('expanded');
            localStorage.setItem('sidebarExpanded', isExpanded);
        });
    }
    
    // Toggle mobile menu
    const menuToggle = document.querySelector('.mobile-menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    function closeMobileSidebar() {
        if (sidebar) sidebar.classList.remove('mobile-open');
        if (sidebarOverlay) sidebarOverlay.classList.remove('show');
        document.body.classList.remove('menu-open');
    }
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('mobile-open');
            if (sidebarOverlay) sidebarOverlay.classList.toggle('show');
            document.body.classList.toggle('menu-open');
        });
    }
    
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeMobileSidebar);
    }
    
    // Toast notification function
    function showNotification(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }
</script>

