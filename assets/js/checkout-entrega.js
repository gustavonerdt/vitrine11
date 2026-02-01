// Checkout Entrega JavaScript - Com auto-save de leads
console.log('checkout-entrega.js carregado');

// Variaveis globais
let cepTimeout;
let saveLeadTimeout;
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

// ========================================
// FUNCAO PARA SALVAR LEAD AUTOMATICAMENTE
// ========================================
function initAutoSaveLead() {
    // Campos que devem acionar o salvamento
    const fieldsToWatch = ['email', 'cep', 'street', 'number', 'neighborhood', 'city', 'state', 'recipient_name', 'phone', 'cpf_cnpj'];
    
    fieldsToWatch.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            // Salvar quando o usuario sair do campo (blur)
            field.addEventListener('blur', function() {
                scheduleLeadSave();
            });
            
            // Salvar enquanto digita (com debounce)
            field.addEventListener('input', function() {
                scheduleLeadSave();
            });
        }
    });
    
    // Salvar antes de sair da pagina
    window.addEventListener('beforeunload', function() {
        saveLeadData(true); // sync save
    });
    
    // Salvar a cada 30 segundos se houver dados
    setInterval(function() {
        const email = document.getElementById('email')?.value;
        if (email && email.includes('@')) {
            saveLeadData(false);
        }
    }, 30000);
}

function scheduleLeadSave() {
    clearTimeout(saveLeadTimeout);
    saveLeadTimeout = setTimeout(function() {
        saveLeadData(false);
    }, 2000); // Salvar 2 segundos apos parar de digitar
}

function saveLeadData(sync = false) {
    const email = document.getElementById('email')?.value || '';
    
    // Precisa ter email valido para salvar
    if (!email || !email.includes('@')) return;
    
    const formData = new FormData();
    formData.append('email', email);
    formData.append('recipient_name', document.getElementById('recipient_name')?.value || '');
    formData.append('phone', document.getElementById('phone')?.value || '');
    formData.append('cpf_cnpj', document.getElementById('cpf_cnpj')?.value || '');
    formData.append('cep', document.getElementById('cep')?.value || '');
    formData.append('street', document.getElementById('street')?.value || '');
    formData.append('number', document.getElementById('number')?.value || '');
    formData.append('neighborhood', document.getElementById('neighborhood')?.value || '');
    formData.append('city', document.getElementById('city')?.value || '');
    formData.append('state', document.getElementById('state')?.value || '');
    formData.append('checkout_step', 'delivery');
    
    if (sync) {
        // Envio sincrono (usado no beforeunload)
        navigator.sendBeacon(window.APP_URL + '/api/save-lead.php', formData);
    } else {
        // Envio assincrono normal
        fetch(window.APP_URL + '/api/save-lead.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                console.log('Lead salvo:', data.lead_id);
            }
        })
        .catch(err => console.log('Erro ao salvar lead:', err));
    }
}

function showNotification(msg, type) {
    alert(msg);
}

// Iniciar
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        initCheckout();
        initAutoSaveLead();
    });
} else {
    initCheckout();
    initAutoSaveLead();
}
