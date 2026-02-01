<footer class="admin-footer">
    <div class="admin-footer-content">
        <p>Desenvolvido com <span style="color: var(--admin-accent);">&#9829;</span> por <strong>Gustavo A. Felix</strong></p>
        <p class="admin-footer-version">Vitrine Independente v1.1</p>
    </div>
</footer>

<style>
.admin-footer {
    margin-top: auto;
    padding: 24px 32px;
    border-top: 1px solid var(--admin-border);
    background: linear-gradient(180deg, var(--admin-bg-card) 0%, var(--admin-bg-secondary) 100%);
}

.admin-footer-content {
    text-align: center;
    color: var(--admin-text-muted);
    font-size: 0.85rem;
}

.admin-footer-content p {
    margin: 4px 0;
}

.admin-footer-content strong {
    color: var(--admin-accent);
}

.admin-footer-version {
    font-size: 0.75rem;
    opacity: 0.6;
}

@media (max-width: 768px) {
    .admin-footer {
        padding: 16px 20px;
    }
}
</style>

<script>
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
