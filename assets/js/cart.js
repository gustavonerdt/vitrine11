
// Cart Management JavaScript with Retention Modal
let productToRemove = null;

const retentionVariations = [
    {
        title: "Espere! Não perca essa oferta!",
        text: "A promoção deste produto vai acabar em breve e o preço pode aumentar a qualquer momento!",
        highlight: "O preço pode aumentar a qualquer momento!",
        badge: "! Oferta por tempo limitado !"
    },
    {
        title: "Tem certeza disso?",
        text: "Este item é um dos nossos mais vendidos e o estoque está acabando rapidamente.",
        highlight: "Garanta o seu antes que esgote!",
        badge: "🔥 Restam poucas unidades"
    },
    {
        title: "Não vá embora ainda!",
        text: "Ao remover este item, você pode perder o benefício do frete grátis ou descontos progressivos.",
        highlight: "Aproveite as condições exclusivas de hoje.",
        badge: "💎 Condição Especial Ativada"
    }
];

document.addEventListener('DOMContentLoaded', function() {
    // Inject Modal HTML if not exists
    if (!document.getElementById('retentionModal')) {
        const modalHTML = `
            <div id="retentionModal" class="retention-modal">
                <div class="retention-content">
                    <span class="modal-close" onclick="closeRetentionModal()">&times;</span>
                    <div class="retention-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h2 class="retention-title" id="retentionTitle">Espere! Não perca essa oferta!</h2>
                    <p class="retention-text" id="retentionText">A promoção deste produto vai acabar em breve</p>
                    <p class="retention-highlight" id="retentionHighlight">e o preço pode aumentar a qualquer momento!</p>
                    <div class="offer-badge" id="retentionBadge">! Oferta por tempo limitado !</div>
                    <button class="btn-keep-cart" onclick="closeRetentionModal()">
                        <i class="fas fa-shopping-cart"></i> Manter no Carrinho
                    </button>
                    <button class="btn-remove-anyway" onclick="confirmRemoval()">Remover mesmo assim</button>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    // Quantity buttons
    document.querySelectorAll('.qty-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            const input = document.querySelector(`.qty-input[data-product-id="${productId}"]`);
            let currentQty = parseInt(input.value) || 1;
            
            if (this.classList.contains('qty-minus')) {
                if (currentQty === 1) {
                    showRetentionModal(productId);
                    return;
                }
                currentQty = Math.max(1, currentQty - 1);
            } else {
                currentQty = currentQty + 1;
            }
            
            updateCartItem(productId, currentQty);
        });
    });
    
    // Quantity input change
    document.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('change', function() {
            const productId = this.getAttribute('data-product-id');
            let qty = parseInt(this.value) || 0;
            if (qty <= 0) {
                this.value = 1;
                showRetentionModal(productId);
                return;
            }
            updateCartItem(productId, qty);
        });
    });
    
    // Remove item buttons
    document.querySelectorAll('.btn-remove-item').forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            showRetentionModal(productId);
        });
    });
    
    // Add order bump buttons
    document.querySelectorAll('.btn-add-bump').forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            addToCart(productId, 1);
        });
    });
});

function showRetentionModal(productId) {
    productToRemove = productId;
    
    // Pick a random variation
    const variation = retentionVariations[Math.floor(Math.random() * retentionVariations.length)];
    
    document.getElementById('retentionTitle').textContent = variation.title;
    document.getElementById('retentionText').textContent = variation.text;
    document.getElementById('retentionHighlight').textContent = variation.highlight;
    document.getElementById('retentionBadge').textContent = variation.badge;
    
    document.getElementById('retentionModal').classList.add('show');
}

function closeRetentionModal() {
    document.getElementById('retentionModal').classList.remove('show');
    productToRemove = null;
}

function confirmRemoval() {
    if (productToRemove) {
        updateCartItem(productToRemove, 0);
        closeRetentionModal();
    }
}

function updateCartItem(productId, quantity) {
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('quantity', quantity);
    
    fetch(window.APP_URL + '/api/update-cart.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (quantity <= 0) {
                const item = document.querySelector(`.cart-item[data-product-id="${productId}"]`);
                if (item) {
                    item.style.opacity = '0';
                    item.style.transform = 'translateX(20px)';
                    setTimeout(() => {
                        item.remove();
                        if (document.querySelectorAll('.cart-item').length === 0) {
                            location.reload();
                        } else {
                            // Optionally update totals without reload if you have a JS function for it
                            location.reload(); 
                        }
                    }, 300);
                }
            } else {
                location.reload();
            }
            updateCartBadge(data.cart_count);
        } else {
            console.error('Erro ao atualizar carrinho:', data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function addToCart(productId, quantity = 1) {
    const btn = event?.target?.closest('.btn-add-bump');
    if (btn) {
        btn.style.pointerEvents = 'none';
        btn.style.opacity = '0.7';
        btn.textContent = 'Adicionando...';
    }
    
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('quantity', quantity);
    
    fetch(window.APP_URL + '/api/add-to-cart.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartBadge(data.cart_count);
            showNotification('Produto adicionado ao carrinho!', 'success');
            
            if (btn) {
                btn.textContent = 'Adicionado!';
                btn.style.background = '#22c55e';
                setTimeout(() => {
                    location.reload();
                }, 800);
            } else {
                setTimeout(() => {
                    location.reload();
                }, 1000);
            }
        } else {
            showNotification('Erro: ' + (data.error || 'Erro desconhecido'), 'error');
            if (btn) {
                btn.style.pointerEvents = 'auto';
                btn.style.opacity = '1';
                btn.textContent = 'Adicionar';
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Erro ao adicionar produto', 'error');
    });
}

function updateCartBadge(count) {
    const badge = document.querySelector('.cart-badge');
    if (badge) {
        badge.textContent = count;
        badge.style.display = count > 0 ? 'flex' : 'none';
    }
}

function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `checkout-notification checkout-notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}
