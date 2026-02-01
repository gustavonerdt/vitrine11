<?php
/**
 * Popup de recuperacao de carrinho abandonado
 * Incluir este arquivo nas paginas publicas
 */
?>
<style>
/* Banner de Carrinho Abandonado - Estilo como a referencia */
.abandoned-cart-banner {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: #f5f5f5;
    border-bottom: 1px solid #ddd;
    padding: 10px 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    z-index: 99999;
    transform: translateY(-100%);
    transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.abandoned-cart-banner.show {
    transform: translateY(0);
}

.abandoned-cart-banner.dark-mode {
    background: #1a1a1a;
    border-bottom-color: #333;
}

.abandoned-cart-banner.dark-mode .abandoned-cart-link {
    color: #d4af37;
}

.abandoned-cart-banner.dark-mode .abandoned-cart-text {
    color: #ccc;
}

.abandoned-cart-banner.dark-mode .abandoned-cart-close {
    color: #888;
}

.abandoned-cart-link {
    color: #333;
    text-decoration: underline;
    font-weight: 500;
    font-size: 14px;
    cursor: pointer;
    transition: color 0.2s;
}

.abandoned-cart-link:hover {
    color: #000;
}

.abandoned-cart-text {
    color: #666;
    font-size: 14px;
}

.abandoned-cart-close {
    position: absolute;
    right: 20px;
    background: none;
    border: none;
    font-size: 18px;
    color: #999;
    cursor: pointer;
    padding: 5px;
    line-height: 1;
    transition: color 0.2s;
}

.abandoned-cart-close:hover {
    color: #333;
}

/* Ajustar body quando banner esta visivel */
body.has-abandoned-banner {
    padding-top: 44px;
}

/* Mobile */
@media (max-width: 768px) {
    .abandoned-cart-banner {
        padding: 8px 50px 8px 15px;
        font-size: 13px;
    }
    
    .abandoned-cart-link,
    .abandoned-cart-text {
        font-size: 13px;
    }
    
    .abandoned-cart-close {
        right: 10px;
    }
}
</style>

<!-- Banner de Recuperacao de Carrinho -->
<div id="abandonedCartBanner" class="abandoned-cart-banner">
    <a href="#" id="abandonedCartLink" class="abandoned-cart-link">Continuar compra</a>
    <span class="abandoned-cart-text">do seu ultimo pedido</span>
    <button type="button" class="abandoned-cart-close" id="abandonedCartClose" aria-label="Fechar">&times;</button>
</div>

<script>
(function() {
    // Verificar se estamos em uma pagina de checkout (nao mostrar la)
    const currentPath = window.location.pathname;
    const isCheckoutPage = currentPath.includes('checkout') || currentPath.includes('carrinho') || currentPath.includes('obrigado');
    
    if (isCheckoutPage) return;
    
    // Verificar se o banner ja foi fechado nessa sessao
    if (sessionStorage.getItem('abandoned_cart_dismissed')) return;
    
    // Verificar carrinho abandonado
    fetch(window.APP_URL + '/api/check-abandoned-cart.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.has_abandoned_cart) {
                showAbandonedCartBanner(data);
            }
        })
        .catch(err => console.log('Abandoned cart check:', err));
    
    function showAbandonedCartBanner(data) {
        const banner = document.getElementById('abandonedCartBanner');
        const link = document.getElementById('abandonedCartLink');
        const closeBtn = document.getElementById('abandonedCartClose');
        
        if (!banner || !link) return;
        
        // Detectar tema escuro
        const isDark = document.body.classList.contains('dark') || 
                      document.documentElement.getAttribute('data-theme') === 'dark' ||
                      window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        if (isDark) {
            banner.classList.add('dark-mode');
        }
        
        // Configurar link
        link.href = data.redirect_url;
        link.textContent = data.has_delivery_data ? 'Finalizar pagamento' : 'Continuar compra';
        
        // Mostrar banner
        setTimeout(() => {
            banner.classList.add('show');
            document.body.classList.add('has-abandoned-banner');
        }, 1500);
        
        // Handler do link
        link.addEventListener('click', function(e) {
            // Marcar como recuperado
            fetch(window.APP_URL + '/api/recover-cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=recover&lead_id=' + (data.lead_id || '')
            });
        });
        
        // Handler do fechar
        closeBtn.addEventListener('click', function() {
            banner.classList.remove('show');
            document.body.classList.remove('has-abandoned-banner');
            sessionStorage.setItem('abandoned_cart_dismissed', 'true');
            
            // Marcar popup como mostrado no servidor
            fetch(window.APP_URL + '/api/recover-cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=dismiss_popup'
            });
        });
    }
})();
</script>
