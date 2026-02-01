<footer class="admin-footer">
    <div class="admin-footer-content">
        <p>Desenvolvido com <span style="color: var(--error);">&#9829;</span> por <strong>Gustavo A. Felix</strong></p>
        <p class="admin-footer-version">Vitrine Independente v1.1</p>
    </div>
</footer>

<style>
.admin-footer {
    margin-top: auto;
    padding: 20px 24px;
    border-top: 1px solid var(--border);
    background: var(--card);
}

.admin-footer-content {
    text-align: center;
    color: var(--muted-foreground);
    font-size: 0.75rem;
}

.admin-footer-content p {
    margin: 2px 0;
}

.admin-footer-content strong {
    color: var(--foreground);
    font-weight: 500;
}

.admin-footer-version {
    font-size: 0.7rem;
    opacity: 0.6;
}

@media (max-width: 768px) {
    .admin-footer {
        padding: 16px;
    }
}
</style>

<script>
// Sidebar Toggle (Desktop)
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle && sidebar) {
        // Check saved state
        const sidebarExpanded = localStorage.getItem('sidebar-expanded');
        if (sidebarExpanded === 'true') {
            sidebar.classList.add('expanded');
        }
        
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('expanded');
            const isExpanded = sidebar.classList.contains('expanded');
            localStorage.setItem('sidebar-expanded', isExpanded);
        });
    }
});

// Toast notification function
function showNotification(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" style="background:none;border:none;color:inherit;cursor:pointer;margin-left:auto;padding:4px;"><i class="fas fa-times"></i></button>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}
</script>
