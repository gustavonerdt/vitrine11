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
    
    // Initialize Mercado Pago Payment Brick if available
    if (window.MP_PUBLIC_KEY && window.MP_PUBLIC_KEY !== '') {
        if (typeof MercadoPago !== 'undefined') {
            mpInstance = new MercadoPago(window.MP_PUBLIC_KEY, {
                locale: 'pt-BR'
            });
            
            // Render Payment Brick
            const bricksBuilder = mpInstance.bricks();
            
            const renderPaymentBrick = async () => {
                const settings = {
                    initialization: {
                        amount: window.orderTotal
                    },
                    customization: {
                        visual: {
                            style: {
                                theme: 'dark'
                            }
                        },
                        paymentMethods: {
                            maxInstallments: 12
                        }
                    },
                    callbacks: {
                        onReady: () => {
                            const statusDiv = document.getElementById('payment-status');
                            if (statusDiv) {
                                statusDiv.style.display = 'block';
                                statusDiv.innerHTML = '<span style="color: #22c55e;">Formulário carregado. Preencha os dados.</span>';
                            }
                        },
                        onSubmit: async ({ formData }) => {
                            const statusDiv = document.getElementById('payment-status');
                            if (statusDiv) {
                                statusDiv.innerHTML = '<span style="color: orange;">Processando pagamento...</span>';
                            }
                            
                            const sellerName = document.getElementById('seller_name').value || '';
                            
                            const payload = {
                                token: formData.token,
                                payment_method_id: formData.payment_method_id,
                                issuer_id: formData.issuer_id || null,
                                installments: formData.installments || 1,
                                transaction_amount: window.orderTotal,
                                description: 'Compra no marketplace',
                                seller_name: sellerName,
                                payer: {
                                    email: window.checkoutEmail || '',
                                    identification: {
                                        type: 'CPF',
                                        number: window.checkoutCpf || ''
                                    }
                                }
                            };
                            
                            try {
                                const res = await fetch(window.APP_URL + '/api/create-payment.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify(payload)
                                });
                                
                                if (!res.ok) {
                                    const errorData = await res.json();
                                    throw new Error(errorData.message || 'Erro na comunicação');
                                }
                                
                                const data = await res.json();
                                
                                if (data.status === 'approved' || data.status === 'in_process') {
                                    if (statusDiv) {
                                        statusDiv.innerHTML = '<span style="color: green;">' + (data.message || 'Pagamento processado!') + '</span>';
                                    }
                                    setTimeout(() => {
                                        window.location.href = window.APP_URL + '/obrigado.php?order_id=' + data.order_id;
                                    }, 2000);
                                } else {
                                    if (statusDiv) {
                                        statusDiv.innerHTML = '<span style="color: red;">' + (data.message || 'Pagamento recusado') + '</span>';
                                    }
                                    showNotification(data.message || 'Pagamento recusado', 'error');
                                }
                            } catch (err) {
                                console.error('Payment error:', err);
                                if (statusDiv) {
                                    statusDiv.innerHTML = '<span style="color: red;">Erro: ' + err.message + '</span>';
                                }
                                showNotification('Erro ao processar pagamento: ' + err.message, 'error');
                            }
                        },
                        onError: (error) => {
                            console.error('Brick Error:', error);
                            const statusDiv = document.getElementById('payment-status');
                            if (statusDiv) {
                                statusDiv.style.display = 'block';
                                statusDiv.innerHTML = '<span style="color: red;">Erro ao carregar formulário: ' + (error.message || 'Tente recarregar a página') + '</span>';
                            }
                            showNotification('Erro ao carregar formulário de pagamento', 'error');
                        }
                    }
                };
                
                try {
                    window.paymentBrickController = await bricksBuilder.create('payment', 'paymentBrick_container', settings);
                } catch (error) {
                    console.error('Error creating Payment Brick:', error);
                    const statusDiv = document.getElementById('payment-status');
                    if (statusDiv) {
                        statusDiv.style.display = 'block';
                        statusDiv.innerHTML = '<span style="color: red;">Erro ao inicializar pagamento. Verifique as credenciais do Mercado Pago.</span>';
                    }
                }
            };
            
            // Render brick when credit card tab is active
            const creditCardTab = document.querySelector('.payment-tab[data-method="credit_card"]');
            if (creditCardTab) {
                creditCardTab.addEventListener('click', function() {
                    setTimeout(() => {
                        if (!window.paymentBrickController) {
                            renderPaymentBrick();
                        }
                    }, 100);
                });
            }
            
            // Also render if credit card is already active
            const creditCardContent = document.getElementById('creditCardPayment');
            if (creditCardContent && creditCardContent.classList.contains('active')) {
                renderPaymentBrick();
            }
        } else {
            console.error('Mercado Pago SDK not loaded');
        }
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
    // Payment Brick handles card payment submission
    // This function is kept for compatibility but payment is handled by Brick's onSubmit callback
    const sellerName = document.getElementById('seller_name').value || '';
    
    // Trigger Brick submission if available
    if (window.paymentBrickController) {
        // The Brick will handle submission via its onSubmit callback
        showNotification('Preencha os dados do cartão no formulário acima', 'info');
    } else {
        showNotification('Formulário de pagamento não carregado. Recarregue a página.', 'error');
        document.getElementById('btnMakeOrder').disabled = false;
        document.getElementById('btnMakeOrder').textContent = 'FAZER PEDIDO';
    }
}
