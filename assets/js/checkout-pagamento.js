// Checkout Pagamento JavaScript

let mpInstance = null;

document.addEventListener('DOMContentLoaded', function() {
    // Payment method options (novo estilo)
    const paymentOptions = document.querySelectorAll('.payment-option');
    const paymentContents = document.querySelectorAll('.payment-content-new');
    const paymentMethodInput = document.getElementById('payment_method');
    
    paymentOptions.forEach(option => {
        option.addEventListener('click', function() {
            const method = this.getAttribute('data-method');
            
            // Update options
            paymentOptions.forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
            
            // Update content
            paymentContents.forEach(c => c.classList.remove('active'));
            document.getElementById(method === 'pix' ? 'pixPayment' : 'creditCardPayment').classList.add('active');
            
            // Update hidden input
            paymentMethodInput.value = method;
            
            // Initialize Brick if credit card selected
            if (method === 'credit_card' && !window.paymentBrickController) {
                initializeMercadoPagoBrick();
            }
        });
    });
    
    // Initialize Brick on load if credit card is default selected
    setTimeout(() => {
        if (paymentMethodInput.value === 'credit_card') {
            initializeMercadoPagoBrick();
        }
    }, 500);
    
    // Show notification function
    window.showNotification = showNotification;
    
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
    btnMakeOrder.textContent = 'PROCESSANDO...';
    
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
    formData.append('seller_name', document.getElementById('seller_name').value || '');
    
    fetch(window.APP_URL + '/api/create-payment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(data => {
                throw new Error(data.message || data.error || 'Erro na comunicacao');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success || data.status === 'approved' || data.status === 'pending' || data.status === 'in_process') {
            showNotification('Pedido criado com sucesso! Redirecionando...', 'success');
            setTimeout(() => {
                window.location.href = window.APP_URL + '/obrigado.php?order_id=' + data.order_id;
            }, 1500);
        } else {
            showNotification('Erro ao processar pagamento: ' + (data.message || data.error || 'Erro desconhecido'), 'error');
            document.getElementById('btnMakeOrder').disabled = false;
            document.getElementById('btnMakeOrder').textContent = 'FINALIZAR PEDIDO';
        }
    })
    .catch(error => {
        console.error('[v0] PIX Payment Error:', error);
        showNotification('Erro ao processar pagamento: ' + error.message, 'error');
        document.getElementById('btnMakeOrder').disabled = false;
        document.getElementById('btnMakeOrder').textContent = 'FINALIZAR PEDIDO';
    });
}

function createCardPayment() {
    if (window.paymentBrickController) {
        showNotification('Preencha os dados do cartao no formulario acima', 'info');
    } else {
        showNotification('Formulario de pagamento nao carregado. Recarregue a pagina.', 'error');
        document.getElementById('btnMakeOrder').disabled = false;
        document.getElementById('btnMakeOrder').textContent = 'FINALIZAR PEDIDO';
    }
}

function initializeMercadoPagoBrick() {
    if (!window.MP_PUBLIC_KEY || window.MP_PUBLIC_KEY === '' || window.paymentBrickController) {
        return;
    }
    
    if (typeof MercadoPago === 'undefined') {
        console.error('Mercado Pago SDK not loaded');
        return;
    }
    
    mpInstance = new MercadoPago(window.MP_PUBLIC_KEY, { locale: 'pt-BR' });
    const bricksBuilder = mpInstance.bricks();
    
    const settings = {
        initialization: { amount: window.orderTotal },
        customization: {
            visual: { style: { theme: 'default' } },
            paymentMethods: { maxInstallments: 12 }
        },
        callbacks: {
            onReady: () => {
                console.log('Payment Brick ready');
            },
            onSubmit: async ({ formData }) => {
                const sellerName = document.getElementById('seller_name')?.value || '';
                const payload = {
                    token: formData.token,
                    payment_method_id: formData.payment_method_id,
                    issuer_id: formData.issuer_id || null,
                    installments: formData.installments || 1,
                    transaction_amount: window.orderTotal,
                    description: 'Compra online',
                    seller_name: sellerName,
                    payer: {
                        email: window.checkoutEmail || '',
                        identification: { type: 'CPF', number: window.checkoutCpf || '' }
                    }
                };
                
                try {
                    const res = await fetch(window.APP_URL + '/api/create-payment.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const data = await res.json();
                    
                    if (data.status === 'approved' || data.status === 'in_process') {
                        showNotification('Pagamento aprovado! Redirecionando...', 'success');
                        setTimeout(() => {
                            window.location.href = window.APP_URL + '/obrigado.php?order_id=' + data.order_id;
                        }, 1500);
                    } else {
                        showNotification(data.message || 'Pagamento recusado', 'error');
                    }
                } catch (err) {
                    console.error('Payment error:', err);
                    showNotification('Erro ao processar pagamento', 'error');
                }
            },
            onError: (error) => {
                console.error('Brick Error:', error);
                showNotification('Erro ao carregar formulario de pagamento', 'error');
            }
        }
    };
    
    bricksBuilder.create('payment', 'paymentBrick_container', settings)
        .then(controller => { window.paymentBrickController = controller; })
        .catch(err => { console.error('Error creating Brick:', err); });
}
