// Checkout Pagamento JavaScript

let mpInstance = null;

// Função global de notificação
function showNotification(message, type = 'info') {
    const existing = document.querySelector('.checkout-notification');
    if (existing) {
        existing.remove();
    }
    
    const notification = document.createElement('div');
    notification.className = 'checkout-notification checkout-notification-' + type;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

document.addEventListener('DOMContentLoaded', function() {
    // Payment method tabs
    const paymentTabs = document.querySelectorAll('.payment-tab');
    const paymentContents = document.querySelectorAll('.payment-content');
    const paymentMethodInput = document.getElementById('payment_method');
    
    paymentTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const method = this.getAttribute('data-method');
            
            // Update tabs
            paymentTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Update content
            paymentContents.forEach(c => c.classList.remove('active'));
            document.getElementById(method === 'pix' ? 'pixPayment' : 'creditCardPayment').classList.add('active');
            
            // Update hidden input
            paymentMethodInput.value = method;
        });
    });
    
    // Mercado Pago sera usado via Checkout Pro (redirect)
    // Nao precisamos mais do Payment Brick
    
    // Form submission
    const paymentForm = document.getElementById('paymentForm');
    paymentForm.addEventListener('submit', function(e) {
        e.preventDefault();
        processPayment();
    });
    
    // Card number mask
    const cardNumberInput = document.getElementById('cardNumber');
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{4})(?=\d)/g, '$1 ');
            e.target.value = value;
        });
    }
    
    // Card expiry mask
    const cardExpiryInput = document.getElementById('cardExpiry');
    if (cardExpiryInput) {
        cardExpiryInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });
    }
    
    // CVV mask
    const cardCvvInput = document.getElementById('cardCvv');
    if (cardCvvInput) {
        cardCvvInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
    }
});

function processPayment() {
    const btnMakeOrder = document.getElementById('btnMakeOrder');
    const paymentMethod = document.getElementById('payment_method').value;
    
    btnMakeOrder.disabled = true;
    btnMakeOrder.innerHTML = '<i class="fas fa-spinner fa-spin"></i> PROCESSANDO...';
    
    if (paymentMethod === 'pix') {
        // Process Pix payment
        createPixPayment();
    } else if (paymentMethod === 'credit_card') {
        // Process credit card payment
        createCardPayment();
    }
}

function createPixPayment() {
    const formData = new FormData();
    formData.append('payment_method', 'pix');
    formData.append('seller_name', document.getElementById('seller_name').value);
    
    fetch(window.APP_URL + '/api/create-payment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Redirect to thank you page
            window.location.href = window.APP_URL + '/obrigado.php?order_id=' + data.order_id;
        } else {
            showNotification('Erro ao processar pagamento: ' + (data.error || 'Erro desconhecido'), 'error');
            document.getElementById('btnMakeOrder').disabled = false;
            document.getElementById('btnMakeOrder').textContent = 'FINALIZAR PEDIDO';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Erro ao processar pagamento. Tente novamente.', 'error');
        document.getElementById('btnMakeOrder').disabled = false;
        document.getElementById('btnMakeOrder').textContent = 'FINALIZAR PEDIDO';
    });
}

function createCardPayment() {
    // Para cartao de credito, redirecionar para checkout do Mercado Pago
    const formData = new FormData();
    formData.append('payment_method', 'credit_card');
    formData.append('seller_name', document.getElementById('seller_name').value || '');
    formData.append('redirect_to_mp', '1');
    
    // Primeiro cria o pedido, depois redireciona para Mercado Pago Checkout Pro
    fetch(window.APP_URL + '/api/create-mp-preference.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.init_point) {
            // Redirecionar para checkout do Mercado Pago
            window.location.href = data.init_point;
        } else if (data.success && data.order_id) {
            // Pedido criado, ir para pagina de obrigado
            window.location.href = window.APP_URL + '/obrigado.php?order_id=' + data.order_id;
        } else {
            showNotification(data.error || 'Erro ao processar. Tente novamente.', 'error');
            document.getElementById('btnMakeOrder').disabled = false;
            document.getElementById('btnMakeOrder').textContent = 'FINALIZAR PEDIDO';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Erro ao processar pagamento. Tente novamente.', 'error');
        document.getElementById('btnMakeOrder').disabled = false;
        document.getElementById('btnMakeOrder').textContent = 'FINALIZAR PEDIDO';
    });
}
