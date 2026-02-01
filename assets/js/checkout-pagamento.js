// Checkout Pagamento JavaScript - Sistema de Pagamento Mercado Pago
// Suporta: PIX, Cartao de Credito e Boleto

let mpInstance = null;
let cardFormInstance = null;

// Funcao global de notificacao
function showNotification(message, type = 'info') {
    const existing = document.querySelector('.checkout-notification');
    if (existing) existing.remove();
    
    const notification = document.createElement('div');
    notification.className = 'checkout-notification checkout-notification-' + type;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => notification.classList.add('show'), 10);
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

document.addEventListener('DOMContentLoaded', function() {
    initPaymentTabs();
    initCardForm();
    initFormSubmission();
    initMercadoPago();
});

// Inicializar tabs de pagamento
function initPaymentTabs() {
    const paymentTabs = document.querySelectorAll('.payment-tab-btn');
    const paymentContents = document.querySelectorAll('.payment-content');
    const paymentMethodInput = document.getElementById('payment_method');
    const btnMakeOrder = document.getElementById('btnMakeOrder');
    
    paymentTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const method = this.getAttribute('data-method');
            
            // Update tabs
            paymentTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Update content
            paymentContents.forEach(c => c.classList.remove('active'));
            
            if (method === 'pix') {
                document.getElementById('pixPayment').classList.add('active');
                btnMakeOrder.innerHTML = 'GERAR PIX';
            } else if (method === 'credit_card') {
                document.getElementById('creditCardPayment').classList.add('active');
                btnMakeOrder.innerHTML = 'PAGAR COM CARTAO';
            } else if (method === 'boleto') {
                document.getElementById('boletoPayment').classList.add('active');
                btnMakeOrder.innerHTML = 'GERAR BOLETO';
            }
            
            // Update hidden input
            paymentMethodInput.value = method;
        });
    });
}

// Inicializar Mercado Pago SDK
function initMercadoPago() {
    if (window.MP_PUBLIC_KEY && window.MercadoPago) {
        try {
            mpInstance = new MercadoPago(window.MP_PUBLIC_KEY, { locale: 'pt-BR' });
        } catch (e) {
            console.error('Erro ao inicializar Mercado Pago:', e);
        }
    }
}

// Inicializar formulario de cartao
function initCardForm() {
    const cardNumberInput = document.getElementById('card_number');
    const cardHolderInput = document.getElementById('card_holder');
    const cardExpiryInput = document.getElementById('card_expiry');
    const cardCvvInput = document.getElementById('card_cvv');
    
    // Card number mask e deteccao de bandeira
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{4})(?=\d)/g, '$1 ');
            e.target.value = value.substring(0, 19);
            
            // Update preview
            updateCardPreview('number', value);
            
            // Detectar bandeira
            detectCardBrand(value.replace(/\s/g, ''));
        });
    }
    
    // Card holder
    if (cardHolderInput) {
        cardHolderInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
            updateCardPreview('holder', e.target.value);
        });
    }
    
    // Card expiry mask
    if (cardExpiryInput) {
        cardExpiryInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
            updateCardPreview('expiry', value);
        });
    }
    
    // CVV mask
    if (cardCvvInput) {
        cardCvvInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
        });
    }
    
    // Atualizar parcelas
    updateInstallments();
}

// Atualizar preview do cartao
function updateCardPreview(field, value) {
    if (field === 'number') {
        const preview = document.querySelector('.card-number-preview');
        if (preview) {
            const formatted = value.replace(/\s/g, '').padEnd(16, '*').match(/.{1,4}/g).join(' ');
            preview.textContent = formatted || '**** **** **** ****';
        }
    } else if (field === 'holder') {
        const preview = document.querySelector('.card-holder-preview');
        if (preview) {
            preview.textContent = value || 'NOME DO TITULAR';
        }
    } else if (field === 'expiry') {
        const preview = document.querySelector('.card-expiry-preview');
        if (preview) {
            preview.textContent = value || 'MM/AA';
        }
    }
}

// Detectar bandeira do cartao
function detectCardBrand(number) {
    const brands = {
        'visa': /^4/,
        'mastercard': /^5[1-5]|^2[2-7]/,
        'amex': /^3[47]/,
        'elo': /^636368|^636369|^438935|^504175|^451416|^636297|^5067|^4576|^4011/,
        'hipercard': /^606282|^3841/
    };
    
    let detected = null;
    for (const [brand, pattern] of Object.entries(brands)) {
        if (pattern.test(number)) {
            detected = brand;
            break;
        }
    }
    
    const brandIcon = document.getElementById('cardBrandIcon');
    const previewBrand = document.querySelector('.card-brand-preview');
    
    if (detected) {
        const icons = {
            'visa': '<i class="fab fa-cc-visa" style="color:#1A1F71;"></i>',
            'mastercard': '<i class="fab fa-cc-mastercard" style="color:#EB001B;"></i>',
            'amex': '<i class="fab fa-cc-amex" style="color:#006FCF;"></i>',
            'elo': '<span style="font-weight:bold;color:#000;">ELO</span>',
            'hipercard': '<span style="font-weight:bold;color:#B3131B;">HIPER</span>'
        };
        if (brandIcon) brandIcon.innerHTML = icons[detected] || '';
        if (previewBrand) previewBrand.innerHTML = icons[detected] || '<i class="fab fa-cc-visa"></i>';
    } else {
        if (brandIcon) brandIcon.innerHTML = '';
        if (previewBrand) previewBrand.innerHTML = '<i class="fab fa-cc-visa"></i>';
    }
    
    return detected;
}

// Atualizar opcoes de parcelamento
function updateInstallments() {
    const select = document.getElementById('card_installments');
    if (!select) return;
    
    const total = window.orderTotal || 0;
    const maxInstallments = 12;
    
    select.innerHTML = '';
    
    for (let i = 1; i <= maxInstallments; i++) {
        const installmentValue = (total / i).toFixed(2).replace('.', ',');
        const option = document.createElement('option');
        option.value = i;
        option.textContent = `${i}x de R$ ${installmentValue}${i <= 3 ? ' (sem juros)' : ''}`;
        select.appendChild(option);
    }
}

// Inicializar submissao do formulario
function initFormSubmission() {
    const paymentForm = document.getElementById('paymentForm');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            processPayment();
        });
    }
}

// Processar pagamento
function processPayment() {
    const btnMakeOrder = document.getElementById('btnMakeOrder');
    const paymentMethod = document.getElementById('payment_method').value;
    
    btnMakeOrder.disabled = true;
    const originalText = btnMakeOrder.innerHTML;
    btnMakeOrder.innerHTML = '<i class="fas fa-spinner fa-spin"></i> PROCESSANDO...';
    
    if (paymentMethod === 'pix') {
        createPixPayment(btnMakeOrder, originalText);
    } else if (paymentMethod === 'credit_card') {
        createCardPayment(btnMakeOrder, originalText);
    } else if (paymentMethod === 'boleto') {
        createBoletoPayment(btnMakeOrder, originalText);
    }
}

// Criar pagamento PIX
function createPixPayment(btn, originalText) {
    const formData = new FormData();
    formData.append('seller_name', document.getElementById('seller_name')?.value || '');
    
    fetch(window.APP_URL + '/api/create-pix-payment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mostrar modal com QR Code PIX
            showPixModal(data);
        } else {
            showNotification(data.error || 'Erro ao gerar PIX', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Erro ao processar pagamento', 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

// Mostrar modal do PIX
function showPixModal(data) {
    // Remover modal anterior se existir
    const existingModal = document.getElementById('pixResultModal');
    if (existingModal) existingModal.remove();
    
    const modal = document.createElement('div');
    modal.id = 'pixResultModal';
    modal.className = 'checkout-modal';
    modal.innerHTML = `
        <div class="checkout-modal-content" style="max-width: 450px; text-align: center;">
            <div class="checkout-modal-header" style="border: none; padding-bottom: 0;">
                <h3 style="width: 100%; text-align: center;">
                    <svg width="24" height="24" viewBox="0 0 512 512" fill="#00BCAA" style="vertical-align: middle; margin-right: 8px;">
                        <path d="M242.4 292.5c-5.3 5.3-14 5.3-19.3 0L112.3 181.6c-23.1-23.1-60.5-23.1-83.6 0-23.1 23.1-23.1 60.5 0 83.6l110.8 110.8c46.2 46.2 121.1 46.2 167.3 0l110.8-110.8c23.1-23.1 23.1-60.5 0-83.6-23.1-23.1-60.5-23.1-83.6 0L223.1 292.5z"/>
                        <path d="M484.1 181.6L373.3 70.8c-46.2-46.2-121.1-46.2-167.3 0L95.2 181.6c-23.1 23.1-23.1 60.5 0 83.6 23.1 23.1 60.5 23.1 83.6 0l110.8-110.8c5.3-5.3 14-5.3 19.3 0l110.8 110.8c23.1 23.1 60.5 23.1 83.6 0 23.1-23.1 23.1-60.5 0-83.6z"/>
                    </svg>
                    Pague com Pix
                </h3>
                <button type="button" class="checkout-modal-close" onclick="closePixModal()">&times;</button>
            </div>
            <div class="checkout-modal-body" style="padding: 1.5rem;">
                <div style="background: #f8f9fa; border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem;">
                    ${data.qr_code_base64 ? `<img src="data:image/png;base64,${data.qr_code_base64}" alt="QR Code PIX" style="width: 200px; height: 200px; margin: 0 auto; display: block; border-radius: 8px;">` : '<p>QR Code nao disponivel</p>'}
                </div>
                
                <p style="color: #666; font-size: 0.9rem; margin-bottom: 1rem;">Escaneie o QR Code acima ou copie o codigo abaixo:</p>
                
                <div style="background: #f0f0f0; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; position: relative;">
                    <input type="text" id="pixCopyCode" value="${data.qr_code || ''}" readonly style="width: 100%; border: none; background: transparent; font-size: 0.75rem; color: #333; text-align: center; padding-right: 40px;">
                    <button type="button" onclick="copyPixCode()" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: #C7A333; border: none; border-radius: 6px; padding: 0.5rem 0.75rem; cursor: pointer; color: #000; font-weight: 600;">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                
                <p style="color: #22c55e; font-weight: 600; margin-bottom: 1rem;">
                    <i class="fas fa-check-circle"></i> Pedido #${data.order_id} criado!
                </p>
                
                <p style="color: #888; font-size: 0.85rem;">
                    Apos o pagamento, voce sera redirecionado automaticamente.
                </p>
                
                <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e5e5e5;">
                    <strong style="font-size: 1.25rem; color: #1a1a1a;">Total: R$ ${data.total.toFixed(2).replace('.', ',')}</strong>
                </div>
            </div>
            <div class="checkout-modal-footer" style="justify-content: center; gap: 1rem;">
                <button type="button" onclick="window.location.href='${window.APP_URL}/obrigado.php?order_id=${data.order_id}&status=pending'" class="btn-modal-save">
                    <i class="fas fa-check"></i> Ja Paguei
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';
    setTimeout(() => modal.classList.add('show'), 10);
    
    // Limpar carrinho e guardar order_id
    window.pendingOrderId = data.order_id;
    window.pendingPaymentId = data.payment_id;
    
    // Iniciar verificacao de status do pagamento
    startPaymentStatusCheck(data.payment_id, data.order_id);
}

// Copiar codigo PIX
function copyPixCode() {
    const input = document.getElementById('pixCopyCode');
    input.select();
    document.execCommand('copy');
    showNotification('Codigo PIX copiado!', 'success');
}

// Fechar modal PIX
function closePixModal() {
    const modal = document.getElementById('pixResultModal');
    if (modal) {
        modal.classList.add('closing');
        modal.classList.remove('show');
        document.body.style.overflow = '';
        setTimeout(() => modal.remove(), 400);
    }
}

// Criar pagamento com Cartao
function createCardPayment(btn, originalText) {
    // Validar campos
    const cardNumber = document.getElementById('card_number')?.value.replace(/\s/g, '');
    const cardHolder = document.getElementById('card_holder')?.value;
    const cardExpiry = document.getElementById('card_expiry')?.value;
    const cardCvv = document.getElementById('card_cvv')?.value;
    const installments = document.getElementById('card_installments')?.value || 1;
    
    if (!cardNumber || cardNumber.length < 15) {
        showNotification('Numero do cartao invalido', 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
        return;
    }
    
    if (!cardHolder || cardHolder.length < 3) {
        showNotification('Nome do titular invalido', 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
        return;
    }
    
    if (!cardExpiry || cardExpiry.length < 5) {
        showNotification('Data de validade invalida', 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
        return;
    }
    
    if (!cardCvv || cardCvv.length < 3) {
        showNotification('CVV invalido', 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
        return;
    }
    
    // Detectar bandeira
    const brand = detectCardBrand(cardNumber);
    const paymentMethodId = brand || 'visa';
    
    // Criar token do cartao com MP SDK
    if (mpInstance) {
        createCardToken(cardNumber, cardHolder, cardExpiry, cardCvv, paymentMethodId, installments, btn, originalText);
    } else {
        // Fallback: redirecionar para checkout pro
        redirectToCheckoutPro(btn, originalText);
    }
}

// Criar token do cartao
async function createCardToken(cardNumber, cardHolder, cardExpiry, cardCvv, paymentMethodId, installments, btn, originalText) {
    try {
        const [expMonth, expYear] = cardExpiry.split('/');
        const fullYear = expYear.length === 2 ? '20' + expYear : expYear;
        
        const cardData = {
            cardNumber: cardNumber,
            cardholderName: cardHolder,
            cardExpirationMonth: expMonth,
            cardExpirationYear: fullYear,
            securityCode: cardCvv,
            identificationType: 'CPF',
            identificationNumber: window.checkoutCpf || ''
        };
        
        const token = await mpInstance.createCardToken(cardData);
        
        if (token.id) {
            // Enviar para backend
            const formData = new FormData();
            formData.append('token', token.id);
            formData.append('payment_method_id', paymentMethodId);
            formData.append('installments', installments);
            formData.append('seller_name', document.getElementById('seller_name')?.value || '');
            
            fetch(window.APP_URL + '/api/create-card-payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.status === 'approved') {
                    showNotification('Pagamento aprovado!', 'success');
                    setTimeout(() => {
                        window.location.href = data.redirect_url;
                    }, 1500);
                } else if (data.success && data.status === 'pending') {
                    showNotification('Pagamento em analise', 'info');
                    setTimeout(() => {
                        window.location.href = window.APP_URL + '/obrigado.php?order_id=' + data.order_id + '&status=pending';
                    }, 2000);
                } else {
                    showNotification(data.error || 'Pagamento recusado', 'error');
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Erro ao processar pagamento', 'error');
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        } else {
            throw new Error('Token nao gerado');
        }
    } catch (error) {
        console.error('Token error:', error);
        // Fallback para checkout pro
        redirectToCheckoutPro(btn, originalText);
    }
}

// Fallback para Checkout Pro
function redirectToCheckoutPro(btn, originalText) {
    const formData = new FormData();
    formData.append('payment_method', 'credit_card');
    formData.append('seller_name', document.getElementById('seller_name')?.value || '');
    
    fetch(window.APP_URL + '/api/create-mp-preference.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.init_point) {
            window.location.href = data.init_point;
        } else {
            showNotification(data.error || 'Erro ao processar', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

// Criar pagamento Boleto
function createBoletoPayment(btn, originalText) {
    const formData = new FormData();
    formData.append('seller_name', document.getElementById('seller_name')?.value || '');
    
    fetch(window.APP_URL + '/api/create-boleto-payment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showBoletoModal(data);
        } else {
            showNotification(data.error || 'Erro ao gerar boleto', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Erro ao processar pagamento', 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

// Mostrar modal do Boleto
function showBoletoModal(data) {
    const existingModal = document.getElementById('boletoResultModal');
    if (existingModal) existingModal.remove();
    
    const modal = document.createElement('div');
    modal.id = 'boletoResultModal';
    modal.className = 'checkout-modal';
    modal.innerHTML = `
        <div class="checkout-modal-content" style="max-width: 500px; text-align: center;">
            <div class="checkout-modal-header" style="border: none; padding-bottom: 0;">
                <h3 style="width: 100%; text-align: center;">
                    <i class="fas fa-barcode" style="color: #C7A333; margin-right: 8px;"></i>
                    Boleto Gerado
                </h3>
                <button type="button" class="checkout-modal-close" onclick="closeBoletoModal()">&times;</button>
            </div>
            <div class="checkout-modal-body" style="padding: 1.5rem;">
                <div style="background: #f0fdf4; border: 2px solid #22c55e; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem;">
                    <i class="fas fa-check-circle" style="font-size: 2.5rem; color: #22c55e; margin-bottom: 0.75rem;"></i>
                    <p style="color: #166534; font-weight: 600; margin: 0;">Pedido #${data.order_id} criado com sucesso!</p>
                </div>
                
                <div style="background: #f8f9fa; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem;">
                    <p style="color: #666; font-size: 0.9rem; margin-bottom: 0.75rem;">Codigo de barras:</p>
                    <div style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 0.75rem; font-family: monospace; font-size: 0.8rem; word-break: break-all;">
                        ${data.barcode || 'Codigo nao disponivel'}
                    </div>
                    <button type="button" onclick="copyBarcodeCode('${data.barcode}')" style="margin-top: 0.75rem; background: #e5e5e5; border: none; border-radius: 6px; padding: 0.5rem 1rem; cursor: pointer; font-weight: 600;">
                        <i class="fas fa-copy"></i> Copiar Codigo
                    </button>
                </div>
                
                <div style="background: #fef9e0; border: 1px solid #C7A333; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem;">
                    <p style="color: #8B6914; font-size: 0.9rem; margin: 0;">
                        <i class="fas fa-clock"></i> Vencimento: <strong>${formatDate(data.due_date)}</strong>
                    </p>
                </div>
                
                <div style="padding-top: 1rem; border-top: 1px solid #e5e5e5;">
                    <strong style="font-size: 1.25rem; color: #1a1a1a;">Total: R$ ${data.total.toFixed(2).replace('.', ',')}</strong>
                </div>
            </div>
            <div class="checkout-modal-footer" style="justify-content: center; gap: 1rem; flex-wrap: wrap;">
                ${data.boleto_url ? `<a href="${data.boleto_url}" target="_blank" class="btn-modal-save" style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-file-pdf"></i> Baixar PDF
                </a>` : ''}
                <button type="button" onclick="window.location.href='${window.APP_URL}/obrigado.php?order_id=${data.order_id}&status=pending'" class="btn-modal-cancel" style="background: #22c55e; color: #fff; border: none;">
                    <i class="fas fa-check"></i> Concluir
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';
    setTimeout(() => modal.classList.add('show'), 10);
}

// Copiar codigo de barras
function copyBarcodeCode(code) {
    navigator.clipboard.writeText(code).then(() => {
        showNotification('Codigo de barras copiado!', 'success');
    });
}

// Fechar modal Boleto
function closeBoletoModal() {
    const modal = document.getElementById('boletoResultModal');
    if (modal) {
        modal.classList.add('closing');
        modal.classList.remove('show');
        document.body.style.overflow = '';
        setTimeout(() => modal.remove(), 400);
    }
}

// Formatar data
function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('pt-BR');
}

// Verificar status do pagamento (para PIX)
let statusCheckInterval = null;
function startPaymentStatusCheck(paymentId, orderId) {
    if (statusCheckInterval) clearInterval(statusCheckInterval);
    
    statusCheckInterval = setInterval(() => {
        fetch(window.APP_URL + '/api/check-payment-status.php?payment_id=' + paymentId)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'approved') {
                clearInterval(statusCheckInterval);
                showNotification('Pagamento aprovado!', 'success');
                setTimeout(() => {
                    window.location.href = window.APP_URL + '/obrigado.php?order_id=' + orderId;
                }, 1500);
            }
        })
        .catch(() => {});
    }, 5000); // Verificar a cada 5 segundos
    
    // Parar apos 10 minutos
    setTimeout(() => {
        if (statusCheckInterval) clearInterval(statusCheckInterval);
    }, 600000);
}
