// Checkout Entrega JavaScript - Simplificado (frete é selecionado no pagamento)
console.log('checkout-entrega.js carregado');

// Variáveis globais
let cepTimeout;
let addressForm, deliveryDataSection, invoiceDataSection;

// Inicialização
function initCheckout() {
    console.log('initCheckout() inicializando...');
    
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
            
            // Mostrar seções de endereço e dados
            if (addressForm) addressForm.style.display = 'block';
            if (deliveryDataSection) deliveryDataSection.style.display = 'block';
            if (invoiceDataSection) invoiceDataSection.style.display = 'block';
            
            // Frete será calculado na página de pagamento
            // Não exibir opções de frete aqui
        })
        .catch(error => {
            console.error('Erro ViaCEP:', error);
            if (cepLoading) cepLoading.style.display = 'none';
        });
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
    alert(msg);
}

// Iniciar
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCheckout);
} else {
    initCheckout();
}
