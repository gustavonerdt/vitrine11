// Tracking de visitas
function trackPageVisit(pageType, pageId = null) {
    fetch('/api/track-visit.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            page_type: pageType,
            page_id: pageId
        })
    }).catch(err => console.error('Tracking error:', err));
}

// Tracking de cliques
function trackClick(clickType, productId = null, variantId = null) {
    fetch('/api/track-click.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            click_type: clickType,
            product_id: productId,
            variant_id: variantId
        })
    }).catch(err => console.error('Click tracking error:', err));
}

// Auto-track na vitrine
document.addEventListener('DOMContentLoaded', function() {
    if (document.body.classList.contains('vitrine-page')) {
        trackPageVisit('vitrine');
    }
    
    // Auto-track em produto
    if (document.body.classList.contains('product-page')) {
        const urlParams = new URLSearchParams(window.location.search);
        const productId = urlParams.get('id');
        if (productId) {
            trackPageVisit('product', parseInt(productId));
        }
    }
    
    // Track cliques em botões de compra
    document.querySelectorAll('.btn-whatsapp-modern, .btn-buy-now').forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.dataset.productId || null;
            const variantId = this.dataset.variantId || null;
            trackClick('buy_button', productId, variantId);
        });
    });
    
    // Track cliques em links de produto
    document.querySelectorAll('.product-card-link').forEach(link => {
        link.addEventListener('click', function() {
            const href = this.getAttribute('href');
            const match = href.match(/[?&]id=(\d+)/);
            if (match) {
                trackClick('product_view', parseInt(match[1]));
            }
        });
    });
});

