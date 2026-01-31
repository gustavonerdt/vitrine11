// Checkout Entrega JavaScript - Versão Corrigida
console.log('✅ checkout-entrega.js carregado');

// Variáveis globais
let cepTimeout;
let addressForm, deliveryDataSection, invoiceDataSection, shippingOptionsDiv, shippingOptionsList;
let btnContinue, shippingMethodInput, shippingPriceInput, shippingRow, shippingCost, totalAmount;

// Inicialização
function initCheckout() {
    console.log('🔧 initCheckout() inicializando...');
    
    const cepInput = document.getElementById('cep');
    if (!cepInput) {
        console.warn('CEP Input não encontrado, tentando novamente...');
        setTimeout(initCheckout, 500);
        return;
    }
    
    // Buscar elementos do DOM
    addressForm = document.getElementById('addressForm');
    deliveryDataSection = document.getElementById('deliveryDataSection');
    invoiceDataSection = document.getElementById('invoiceDataSection');
    shippingOptionsDiv = document.getElementById('shippingOptions');
    shippingOptionsList = document.getElementById('shippingOptionsList');
    btnContinue = document.getElementById('btnContinue');
    shippingMethodInput = document.getElementById('shipping_method');
    shippingPriceInput = document.getElementById('shipping_price');
    shippingRow = document.getElementById('shippingRow');
    shippingCost = document.getElementById('shippingCost');
    totalAmount = document.getElementById('totalAmount');
    
    // Máscara de CEP e busca automática
    cepInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 5) {
            value = value.substring(0, 5) + '-' + value.substring(5, 8);
        }
        e.target.value = value;
        
        const cepClean = value.replace(/\D/g, '');
        if (cepClean.length === 8) {
            clearTimeout(cepTimeout);
            cepTimeout = setTimeout(() => {
                buscarCep();
            }, 500);
        }
    });

    // Se o CEP já estiver preenchido no load (ex: volta do navegador), buscar dados
    const initialCep = cepInput.value.replace(/\D/g, '');
    if (initialCep.length === 8) {
        setTimeout(buscarCep, 500);
    }
    
    initMasks();
}

function buscarCep() {
    const cepInputEl = document.getElementById('cep');
    if (!cepInputEl) return;
    
    const cep = cepInputEl.value.replace(/\D/g, '');
    if (cep.length !== 8) return;
    
    const cepLoading = document.getElementById('cepLoading');
    if (cepLoading) cepLoading.style.display = 'block';
    
    console.log('Buscando CEP:', cep);
    
    // Buscar endereço via ViaCEP
    fetch(`https://viacep.com.br/ws/${cep}/json/`)
        .then(response => response.json())
        .then(data => {
            if (cepLoading) cepLoading.style.display = 'none';
            
            if (data.erro) {
                showNotification('CEP não encontrado.', 'error');
                return;
            }
            
            // Preencher campos
            const fields = {
                'street': data.logradouro,
                'neighborhood': data.bairro,
                'city': data.localidade,
                'state': data.uf
            };
            
            for (let id in fields) {
                const el = document.getElementById(id);
                if (el) el.value = fields[id] || '';
            }
            
            // Mostrar seções
            if (addressForm) addressForm.style.display = 'block';
            if (deliveryDataSection) deliveryDataSection.style.display = 'block';
            if (invoiceDataSection) invoiceDataSection.style.display = 'block';
            
            // Calcular frete
            calcularFrete(cep);
        })
        .catch(error => {
            console.error('Erro ViaCEP:', error);
            if (cepLoading) cepLoading.style.display = 'none';
        });
}

function calcularFrete(cep) {
    const url = (window.APP_URL || '') + '/api/calculate-shipping.php';
    const formData = new FormData();
    formData.append('cep', cep);
    
    if (shippingOptionsList) shippingOptionsList.innerHTML = '<p><i class="fas fa-spinner fa-spin"></i> Calculando frete...</p>';
    if (shippingOptionsDiv) shippingOptionsDiv.style.display = 'block';

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.options) {
            mostrarOpcoesFrete(data.options);
        } else {
            if (shippingOptionsList) shippingOptionsList.innerHTML = `<p class="error">${data.error || 'Erro ao calcular frete'}</p>`;
        }
    })
    .catch(error => {
        console.error('Erro frete:', error);
        if (shippingOptionsList) shippingOptionsList.innerHTML = '<p class="error">Erro de conexão ao calcular frete.</p>';
    });
}

function mostrarOpcoesFrete(options) {
    if (!shippingOptionsList) return;
    shippingOptionsList.innerHTML = '';
    
    options.forEach(option => {
        const div = document.createElement('div');
        div.className = 'shipping-option';
        div.innerHTML = `
            <input type="radio" name="shipping_option_radio" id="ship_${option.code}" 
                   value="${option.code}" data-price="${option.price}" data-name="${option.name}">
            <label for="ship_${option.code}">
                <div class="shipping-option-header">
                    <span class="shipping-name">${option.name}</span>
                    <span class="shipping-price">R$ ${parseFloat(option.price).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
                </div>
                <div class="shipping-delivery">${option.delivery_text}</div>
            </label>
        `;
        
        div.querySelector('input').addEventListener('change', function() {
            selecionarFrete(this.dataset.price, this.dataset.name);
        });
        
        shippingOptionsList.appendChild(div);
    });
}

function selecionarFrete(price, name) {
    if (shippingMethodInput) shippingMethodInput.value = name;
    if (shippingPriceInput) shippingPriceInput.value = price;
    
    const p = parseFloat(price);
    const sub = window.cartSubtotal || 0;
    const total = sub + p;
    
    if (shippingCost) shippingCost.textContent = 'R$ ' + p.toLocaleString('pt-BR', {minimumFractionDigits: 2});
    if (totalAmount) totalAmount.textContent = 'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits: 2});
    if (shippingRow) shippingRow.style.display = 'flex';
}

function initMasks() {
    const phone = document.getElementById('phone');
    if (phone) {
        phone.addEventListener('input', function(e) {
            let x = e.target.value.replace(/\D/g, '').match(/(\d{0,2})(\d{0,5})(\d{0,4})/);
            e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
        });
    }
    
    const cpf = document.getElementById('cpf_cnpj');
    if (cpf) {
        cpf.addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length <= 11) {
                v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
            } else {
                v = v.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, "$1.$2.$3/$4-$5");
            }
            e.target.value = v;
        });
    }
}

function showNotification(msg, type) {
    const existing = document.querySelector('.checkout-notification');
    if (existing) existing.remove();
    
    const notification = document.createElement('div');
    notification.className = 'checkout-notification checkout-notification-' + type;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${msg}</span>
        </div>
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => notification.classList.add('show'), 10);
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

function scrollToElement(element) {
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        element.focus();
        element.style.outline = '2px solid #C7A333';
        setTimeout(() => { element.style.outline = ''; }, 2000);
    }
}

function validateForm() {
    const email = document.getElementById('email');
    const cep = document.getElementById('cep');
    const street = document.getElementById('street');
    const number = document.getElementById('number');
    const neighborhood = document.getElementById('neighborhood');
    const city = document.getElementById('city');
    const state = document.getElementById('state');
    const recipientName = document.getElementById('recipient_name');
    const phone = document.getElementById('phone');
    const cpfCnpj = document.getElementById('cpf_cnpj');
    const shippingMethod = document.getElementById('shipping_method');
    
    if (!email || !email.value.trim()) {
        showNotification('Preencha seu e-mail', 'error');
        scrollToElement(email);
        return false;
    }
    
    if (!cep || cep.value.replace(/\D/g, '').length !== 8) {
        showNotification('Preencha o CEP corretamente', 'error');
        scrollToElement(cep);
        return false;
    }
    
    if (!shippingMethod || !shippingMethod.value) {
        showNotification('Selecione uma opcao de frete', 'error');
        const shippingOptions = document.getElementById('shippingOptions');
        if (shippingOptions) scrollToElement(shippingOptions);
        return false;
    }
    
    if (!street || !street.value.trim()) {
        showNotification('Preencha a rua', 'error');
        scrollToElement(street);
        return false;
    }
    
    if (!number || !number.value.trim()) {
        showNotification('Preencha o numero', 'error');
        scrollToElement(number);
        return false;
    }
    
    if (!neighborhood || !neighborhood.value.trim()) {
        showNotification('Preencha o bairro', 'error');
        scrollToElement(neighborhood);
        return false;
    }
    
    if (!city || !city.value.trim()) {
        showNotification('Preencha a cidade', 'error');
        scrollToElement(city);
        return false;
    }
    
    if (!state || !state.value.trim()) {
        showNotification('Preencha o estado', 'error');
        scrollToElement(state);
        return false;
    }
    
    if (!recipientName || !recipientName.value.trim()) {
        showNotification('Preencha o nome do destinatario', 'error');
        scrollToElement(recipientName);
        return false;
    }
    
    if (!phone || phone.value.replace(/\D/g, '').length < 10) {
        showNotification('Preencha o telefone corretamente', 'error');
        scrollToElement(phone);
        return false;
    }
    
    if (!cpfCnpj || cpfCnpj.value.replace(/\D/g, '').length < 11) {
        showNotification('Preencha o CPF ou CNPJ', 'error');
        scrollToElement(cpfCnpj);
        return false;
    }
    
    return true;
}

// Form submission validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('checkoutForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }
        });
    }
});

// Iniciar
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCheckout);
} else {
    initCheckout();
}
