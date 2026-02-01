# Naipe da Gringa - E-commerce de Perfumes Importados

Sistema completo de e-commerce desenvolvido em PHP puro, otimizado para venda de perfumes importados com integração Mercado Pago, painel administrativo avançado e editor visual estilo Elementor.

---

## Indice

1. [Visao Geral](#visao-geral)
2. [Requisitos](#requisitos)
3. [Instalacao](#instalacao)
4. [Estrutura do Projeto](#estrutura-do-projeto)
5. [Configuracao](#configuracao)
6. [Funcionalidades](#funcionalidades)
7. [Painel Administrativo](#painel-administrativo)
8. [APIs](#apis)
9. [Banco de Dados](#banco-de-dados)
10. [Seguranca](#seguranca)

---

## Visao Geral

O Naipe da Gringa e uma plataforma de e-commerce completa com:

- **Frontend responsivo** com design moderno e otimizado para conversao
- **Checkout transparente** com Mercado Pago (Cartao, PIX, Boleto)
- **Painel administrativo** completo para gestao de produtos, pedidos e configuracoes
- **Editor visual** estilo Elementor para personalizacao de cores, fontes e conteudo
- **Sistema de marketing** com cupons, order bumps, upsells e faixa promocional

---

## Requisitos

- PHP 7.4 ou superior
- MySQL 5.7 ou superior / MariaDB 10.3+
- Extensoes PHP: PDO, cURL, JSON, mbstring
- Servidor web: Apache ou Nginx
- SSL (obrigatorio para pagamentos)

---

## Instalacao

### 1. Clone ou extraia os arquivos

```bash
# Via Git
git clone https://github.com/seu-usuario/naipedagringa.git

# Ou extraia o ZIP para a pasta do servidor
```

### 2. Configure o banco de dados

```bash
# Crie o banco de dados
mysql -u root -p -e "CREATE DATABASE naipedagringa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Importe o schema
mysql -u root -p naipedagringa < naipedagringa.sql
```

### 3. Configure as credenciais

Edite o arquivo `config/config.php`:

```php
// Banco de Dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'naipedagringa');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha');

// URL do Site
define('APP_URL', 'https://seudominio.com.br');

// Mercado Pago
define('MP_ACCESS_TOKEN', 'seu_access_token');
define('MP_PUBLIC_KEY', 'sua_public_key');

// Melhor Envio (opcional)
define('MELHOR_ENVIO_TOKEN', 'seu_token');
```

### 4. Configure permissoes

```bash
chmod 755 -R /caminho/do/projeto
chmod 777 -R /caminho/do/projeto/uploads
chmod 777 -R /caminho/do/projeto/logs
```

### 5. Acesse o painel admin

```
URL: https://seudominio.com.br/admin
Usuario: admin
Senha: admin123 (altere imediatamente)
```

---

## Estrutura do Projeto

```
naipedagringa/
├── admin/                      # Painel Administrativo
│   ├── index.php              # Dashboard
│   ├── products.php           # Gestao de Produtos
│   ├── orders.php             # Gestao de Pedidos
│   ├── brands.php             # Gestao de Marcas
│   ├── coupons.php            # Gestao de Cupons
│   ├── order-bumps.php        # Order Bumps
│   ├── upsells.php            # Upsells
│   ├── banners.php            # Banners Rotativos
│   ├── settings.php           # Configuracoes Gerais
│   ├── theme-editor.php       # Editor Visual (Elementor)
│   ├── shipping.php           # Configuracoes de Frete
│   └── integrations.php       # Integracoes (MP, Melhor Envio)
│
├── api/                        # APIs do Sistema
│   ├── create-payment.php     # Processa pagamentos MP
│   ├── webhook-mp.php         # Webhook Mercado Pago
│   ├── calculate-shipping.php # Calculo de Frete
│   ├── apply-coupon.php       # Aplicar Cupom
│   ├── upload-image.php       # Upload de Imagens
│   ├── admin/                 # APIs do Admin
│   │   ├── save-theme.php     # Salvar tema
│   │   ├── update-order.php   # Atualizar pedido
│   │   └── ...
│   └── ...
│
├── assets/                     # Assets Estaticos
│   ├── css/
│   ├── js/
│   │   ├── checkout-pagamento.js
│   │   ├── cart.js
│   │   └── ...
│   └── images/
│
├── config/                     # Configuracoes
│   └── config.php             # Configuracoes principais
│
├── includes/                   # Includes PHP
│   ├── functions.php          # Funcoes auxiliares
│   ├── db.php                 # Conexao PDO
│   └── auth.php               # Autenticacao Admin
│
├── uploads/                    # Uploads de Usuario
│   ├── products/
│   ├── banners/
│   └── brands/
│
├── index.php                   # Vitrine Principal
├── product.php                 # Pagina do Produto
├── carrinho.php                # Carrinho de Compras
├── checkout-entrega.php        # Checkout - Dados de Entrega
├── checkout-pagamento.php      # Checkout - Pagamento
├── obrigado.php                # Pagina Pos-Compra
├── naipedagringa.sql          # Schema do Banco de Dados
└── README.md                   # Este arquivo
```

---

## Configuracao

### Mercado Pago

1. Acesse https://www.mercadopago.com.br/developers
2. Crie uma aplicacao
3. Copie o Access Token e Public Key
4. Configure em `Admin > Integracoes > Mercado Pago`

### Melhor Envio (Frete)

1. Acesse https://melhorenvio.com.br
2. Gere um token de API
3. Configure em `Admin > Configuracoes > Frete`

### WhatsApp Float

Configure em `Admin > Configuracoes > WhatsApp`:
- Numero do WhatsApp (com DDD)
- Mensagem padrao
- Posicao do botao

---

## Funcionalidades

### Frontend (Loja)

| Funcionalidade | Descricao |
|----------------|-----------|
| **Vitrine Responsiva** | Grid de produtos otimizado para desktop e mobile |
| **Faixa Promocional** | Banner rotativo no topo com frases personalizaveis |
| **Filtro por Marca** | Filtragem dinamica de produtos |
| **Busca Inteligente** | Busca por nome e marca |
| **Carrossel de Marcas** | Exibicao animada das marcas |
| **Carrossel de Banners** | Banners principais rotativos |
| **Produtos em Destaque** | Showcase de produtos VIP |
| **Sistema De/Por** | Precos com desconto (original riscado) |
| **Carrinho Dinamico** | Adicionar/remover sem reload |
| **Order Bumps** | Sugestoes no carrinho |
| **Calculo de Frete** | Melhor Envio ou frete fixo |
| **Cupons de Desconto** | Percentual ou valor fixo |
| **WhatsApp Float** | Botao flutuante para contato |

### Checkout

| Funcionalidade | Descricao |
|----------------|-----------|
| **Checkout em 2 Etapas** | Entrega + Pagamento |
| **Validacao de CEP** | Preenchimento automatico |
| **Cartao de Credito** | Ate 12x com Mercado Pago |
| **PIX** | QR Code e copia/cola |
| **Boleto** | Geracao automatica |
| **Tela de Obrigado** | Confirmacao pos-compra |
| **Upsell Pos-Compra** | Oferta exclusiva |

### Sistema de Precos com Desconto

O sistema De/Por permite mostrar o preco original riscado e o preco promocional em destaque:

- Badge de desconto na imagem (-X% OFF)
- Preco original riscado
- Preco atual em verde
- Economia exibida na pagina do produto
- Funciona em todas as telas (vitrine, produto, carrinho, checkout)

---

## Painel Administrativo

### Dashboard
- Resumo de vendas (hoje, semana, mes)
- Ultimos pedidos
- Produtos mais vendidos
- Graficos de desempenho

### Produtos
- Cadastro com multiplas imagens (ate 5)
- Definicao de capa
- Sistema De/Por para descontos
- Marcacao VIP
- Vinculo com marca
- Ativacao/desativacao

### Pedidos
- Listagem com filtros
- Status: Pendente, Pago, Enviado, Entregue, Cancelado
- Detalhes completos do pedido
- Historico de pagamento

### Editor Visual (Elementor)

O editor visual permite personalizar sem codigo:

**Aba Cores:**
- Cor primaria (dourado)
- Cor secundaria
- Cor de fundo
- Cor do texto
- Cores de botoes
- Cores do header/footer

**Aba Conteudo:**
- Faixa promocional (ativar/desativar)
- Frases rotativas (ate 6)
- Links para cada frase
- Textos do site (busca, botoes, etc)
- Redirecionamentos

**Aba Tipografia:**
- Fonte de titulos
- Fonte do corpo
- Tamanho base

**Aba Layout:**
- Border radius
- Espacamento
- Produtos por linha

**Aba Historico:**
- Versoes salvas do tema
- Restaurar versao anterior

### Cupons
- Codigo personalizado
- Desconto percentual ou fixo
- Valor minimo de compra
- Data de validade
- Limite de usos

### Order Bumps
- Produtos sugeridos no carrinho
- Configuracao de desconto
- Selecao de produtos

### Upsells
- Oferta pos-compra
- Configuracao por pagina (checkout/obrigado)
- Desconto exclusivo

### Banners
- Upload de imagens
- Link de destino
- Ordem de exibicao
- Desktop e mobile separados

### Frete
- Melhor Envio (API)
- Frete fixo por valor
- Frete gratis acima de X
- CEP de origem

---

## APIs

### POST /api/create-payment.php

Processa pagamentos via Mercado Pago.

**Parametros:**
```json
{
  "payment_method": "credit_card|pix|boleto",
  "token": "card_token_mp",
  "installments": 1,
  "items": [{"product_id": 1, "quantity": 1}],
  "customer": {...},
  "shipping": {...}
}
```

### POST /api/calculate-shipping.php

Calcula frete via Melhor Envio ou frete fixo.

**Parametros:**
```json
{
  "cep": "01310100",
  "items": [{"product_id": 1, "quantity": 1}]
}
```

### POST /api/apply-coupon.php

Aplica cupom de desconto.

**Parametros:**
```json
{
  "code": "DESCONTO10",
  "subtotal": 299.90
}
```

### POST /api/webhook-mp.php

Webhook do Mercado Pago para atualizacao de status.

---

## Banco de Dados

### Tabelas Principais

| Tabela | Descricao |
|--------|-----------|
| `products` | Produtos (nome, preco, original_price, marca, etc) |
| `product_images` | Imagens dos produtos |
| `brands` | Marcas |
| `orders` | Pedidos |
| `order_items` | Itens dos pedidos |
| `coupons` | Cupons de desconto |
| `order_bumps` | Configuracao de order bumps |
| `upsell_settings` | Configuracao de upsells |
| `carousel_banners` | Banners do carrossel |
| `settings` | Configuracoes do sistema |
| `theme_versions` | Historico do editor visual |
| `admins` | Usuarios administrativos |
| `payment_logs` | Logs de pagamento |

### Schema Resumido

```sql
-- Produtos
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    brand_id INT,
    price DECIMAL(10,2) NOT NULL,
    original_price DECIMAL(10,2) NULL,  -- Para sistema De/Por
    description TEXT,
    image_path VARCHAR(500),
    is_vip TINYINT DEFAULT 0,
    is_active TINYINT DEFAULT 1,
    is_dynamic_ad TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Pedidos
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_name VARCHAR(255),
    customer_email VARCHAR(255),
    customer_phone VARCHAR(50),
    customer_cpf VARCHAR(20),
    shipping_address TEXT,
    shipping_cost DECIMAL(10,2),
    subtotal DECIMAL(10,2),
    discount DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2),
    payment_method VARCHAR(50),
    payment_status VARCHAR(50) DEFAULT 'pending',
    mp_payment_id VARCHAR(100),
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Configuracoes
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE,
    setting_value TEXT,
    setting_group VARCHAR(50)
);
```

---

## Seguranca

### Implementacoes

- **Prepared Statements**: Todas queries usam PDO com prepared statements
- **Validacao de Input**: Sanitizacao de todos os inputs
- **CSRF Protection**: Tokens em formularios
- **Password Hashing**: bcrypt para senhas de admin
- **Session Security**: Configuracoes seguras de sessao
- **SSL Required**: Pagamentos exigem HTTPS

### Boas Praticas

```php
// Sempre use prepared statements
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);

// Sempre escape output
echo htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');

// Valide inputs numericos
$price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
```

---

## Atualizacoes

### Versao 2.1 (Fev 2026)

- Sistema de desconto De/Por
- Faixa rotativa promocional personalizavel
- Aba "Conteudo" no Editor Visual
- Otimizacoes mobile (carrinho, botoes, inputs)
- Inputs numericos com teclado numerico no mobile
- Hierarquia visual aprimorada (banner maior, marcas menor)
- Tela de agradecimento personalizada para boleto

### Versao 2.0

- Editor Visual (Elementor)
- Sistema de cupons
- Order bumps e upsells
- Integracao Melhor Envio
- Multiplas imagens por produto

### Versao 1.0

- Lancamento inicial
- Checkout com Mercado Pago
- Painel administrativo basico

---

## Suporte

Para suporte tecnico:

- WhatsApp: Configure no painel admin
- Email: Configure em `Admin > Configuracoes`

---

## Licenca

Este software e proprietario. Todos os direitos reservados.

---

**Desenvolvido com PHP puro, sem frameworks, para maxima performance e controle.**
