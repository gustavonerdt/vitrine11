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

