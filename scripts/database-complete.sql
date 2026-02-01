-- ============================================
-- VITRINE INDEPENDENTE - DATABASE COMPLETO
-- Versao: 1.0
-- Data: 2024
-- ============================================
-- Execute este arquivo para criar todas as tabelas
-- e dados de teste do sistema
-- ============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ============================================
-- TABELA: settings (Configuracoes do Sistema)
-- ============================================
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT,
    `setting_type` ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: users (Usuarios/Administradores)
-- ============================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `cpf` VARCHAR(14) DEFAULT NULL,
    `role` ENUM('admin', 'manager', 'seller', 'customer') DEFAULT 'customer',
    `avatar` VARCHAR(255) DEFAULT NULL,
    `referral_code` VARCHAR(20) DEFAULT NULL,
    `referred_by` INT(11) UNSIGNED DEFAULT NULL,
    `balance_available` DECIMAL(10,2) DEFAULT 0.00,
    `balance_retained` DECIMAL(10,2) DEFAULT 0.00,
    `total_accumulated` DECIMAL(10,2) DEFAULT 0.00,
    `is_active` TINYINT(1) DEFAULT 1,
    `email_verified_at` DATETIME DEFAULT NULL,
    `last_login` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_email` (`email`),
    UNIQUE KEY `idx_referral_code` (`referral_code`),
    KEY `idx_referred_by` (`referred_by`),
    KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: brands (Marcas)
-- ============================================
DROP TABLE IF EXISTS `brands`;
CREATE TABLE `brands` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) DEFAULT NULL,
    `description` TEXT,
    `logo_url` VARCHAR(500) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `display_order` INT(11) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_name` (`name`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: categories (Categorias)
-- ============================================
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) DEFAULT NULL,
    `description` TEXT,
    `parent_id` INT(11) UNSIGNED DEFAULT NULL,
    `image_url` VARCHAR(500) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `display_order` INT(11) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_parent` (`parent_id`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: products (Produtos)
-- ============================================
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) DEFAULT NULL,
    `description` TEXT,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `original_price` DECIMAL(10,2) DEFAULT NULL,
    `cost_price` DECIMAL(10,2) DEFAULT NULL,
    `sku` VARCHAR(100) DEFAULT NULL,
    `barcode` VARCHAR(100) DEFAULT NULL,
    `brand_id` INT(11) UNSIGNED DEFAULT NULL,
    `category_id` INT(11) UNSIGNED DEFAULT NULL,
    `image_path` VARCHAR(500) DEFAULT NULL,
    `stock_quantity` INT(11) DEFAULT 0,
    `min_stock` INT(11) DEFAULT 0,
    `weight` DECIMAL(10,3) DEFAULT 0.000,
    `is_vip` TINYINT(1) DEFAULT 0,
    `is_featured` TINYINT(1) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `is_dynamic_ad` TINYINT(1) DEFAULT 0,
    `view_count` INT(11) DEFAULT 0,
    `sale_count` INT(11) DEFAULT 0,
    `shipping_weight` DECIMAL(10,3) DEFAULT 0.30 COMMENT 'Peso em kg para calculo de frete',
    `shipping_height` INT(11) DEFAULT 15 COMMENT 'Altura em cm para calculo de frete',
    `shipping_width` INT(11) DEFAULT 8 COMMENT 'Largura em cm para calculo de frete',
    `shipping_length` INT(11) DEFAULT 8 COMMENT 'Comprimento em cm para calculo de frete',
    `meta_title` VARCHAR(255) DEFAULT NULL,
    `meta_description` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_brand` (`brand_id`),
    KEY `idx_category` (`category_id`),
    KEY `idx_active` (`is_active`),
    KEY `idx_vip` (`is_vip`),
    KEY `idx_featured` (`is_featured`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: product_images (Imagens dos Produtos)
-- ============================================
DROP TABLE IF EXISTS `product_images`;
CREATE TABLE `product_images` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT(11) UNSIGNED NOT NULL,
    `image_url` VARCHAR(500) NOT NULL,
    `is_cover` TINYINT(1) DEFAULT 0,
    `display_order` INT(11) DEFAULT 0,
    `sort_order` INT(11) DEFAULT 0,
    `alt_text` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_product` (`product_id`),
    KEY `idx_cover` (`is_cover`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: product_variants (Variantes de Produtos)
-- ============================================
DROP TABLE IF EXISTS `product_variants`;
CREATE TABLE `product_variants` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT(11) UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `points` INT(11) DEFAULT 0,
    `sku` VARCHAR(100) DEFAULT NULL,
    `stock_quantity` INT(11) DEFAULT 0,
    `image_path` VARCHAR(500) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `display_order` INT(11) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_product` (`product_id`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: dynamic_ads (Anuncios Dinamicos)
-- ============================================
DROP TABLE IF EXISTS `dynamic_ads`;
CREATE TABLE `dynamic_ads` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT(11) UNSIGNED NOT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `description` TEXT,
    `keywords` TEXT,
    `show_variants` TINYINT(1) DEFAULT 1,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_product` (`product_id`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: dynamic_showcases (Vitrines Dinamicas)
-- ============================================
DROP TABLE IF EXISTS `dynamic_showcases`;
CREATE TABLE `dynamic_showcases` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `description` TEXT,
    `banner_url` VARCHAR(500) DEFAULT NULL,
    `banner_link` VARCHAR(500) DEFAULT NULL,
    `layout_type` ENUM('grid', 'carousel', 'list') DEFAULT 'grid',
    `products_per_row` INT(11) DEFAULT 4,
    `max_products` INT(11) DEFAULT 12,
    `is_active` TINYINT(1) DEFAULT 1,
    `display_order` INT(11) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: dynamic_showcase_products (Produtos das Vitrines)
-- ============================================
DROP TABLE IF EXISTS `dynamic_showcase_products`;
CREATE TABLE `dynamic_showcase_products` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `showcase_id` INT(11) UNSIGNED NOT NULL,
    `product_id` INT(11) UNSIGNED NOT NULL,
    `display_order` INT(11) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_showcase_product` (`showcase_id`, `product_id`),
    KEY `idx_showcase` (`showcase_id`),
    KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: banners (Banners/Carrossel)
-- ============================================
DROP TABLE IF EXISTS `banners`;
CREATE TABLE `banners` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) DEFAULT NULL,
    `carousel_type` ENUM('carousel', 'single', 'hero') DEFAULT 'carousel',
    `image_url_1` VARCHAR(500) DEFAULT NULL,
    `image_url_2` VARCHAR(500) DEFAULT NULL,
    `image_url_3` VARCHAR(500) DEFAULT NULL,
    `image_url_4` VARCHAR(500) DEFAULT NULL,
    `image_url_5` VARCHAR(500) DEFAULT NULL,
    `image_url_6` VARCHAR(500) DEFAULT NULL,
    `image_url_7` VARCHAR(500) DEFAULT NULL,
    `image_url_8` VARCHAR(500) DEFAULT NULL,
    `link_url_1` VARCHAR(500) DEFAULT NULL,
    `link_url_2` VARCHAR(500) DEFAULT NULL,
    `link_url_3` VARCHAR(500) DEFAULT NULL,
    `link_url_4` VARCHAR(500) DEFAULT NULL,
    `link_url_5` VARCHAR(500) DEFAULT NULL,
    `link_url_6` VARCHAR(500) DEFAULT NULL,
    `link_url_7` VARCHAR(500) DEFAULT NULL,
    `link_url_8` VARCHAR(500) DEFAULT NULL,
    `link_type_1` VARCHAR(50) DEFAULT 'url',
    `link_type_2` VARCHAR(50) DEFAULT 'url',
    `link_type_3` VARCHAR(50) DEFAULT 'url',
    `link_type_4` VARCHAR(50) DEFAULT 'url',
    `link_type_5` VARCHAR(50) DEFAULT 'url',
    `link_type_6` VARCHAR(50) DEFAULT 'url',
    `link_type_7` VARCHAR(50) DEFAULT 'url',
    `link_type_8` VARCHAR(50) DEFAULT 'url',
    `mobile_image_url_1` VARCHAR(500) DEFAULT NULL,
    `mobile_image_url_2` VARCHAR(500) DEFAULT NULL,
    `mobile_image_url_3` VARCHAR(500) DEFAULT NULL,
    `mobile_image_url_4` VARCHAR(500) DEFAULT NULL,
    `mobile_image_url_5` VARCHAR(500) DEFAULT NULL,
    `mobile_image_url_6` VARCHAR(500) DEFAULT NULL,
    `mobile_image_url_7` VARCHAR(500) DEFAULT NULL,
    `mobile_image_url_8` VARCHAR(500) DEFAULT NULL,
    `mobile_link_url_1` VARCHAR(500) DEFAULT NULL,
    `mobile_link_url_2` VARCHAR(500) DEFAULT NULL,
    `mobile_link_url_3` VARCHAR(500) DEFAULT NULL,
    `mobile_link_url_4` VARCHAR(500) DEFAULT NULL,
    `mobile_link_url_5` VARCHAR(500) DEFAULT NULL,
    `mobile_link_url_6` VARCHAR(500) DEFAULT NULL,
    `mobile_link_url_7` VARCHAR(500) DEFAULT NULL,
    `mobile_link_url_8` VARCHAR(500) DEFAULT NULL,
    `mobile_link_type_1` VARCHAR(50) DEFAULT 'url',
    `mobile_link_type_2` VARCHAR(50) DEFAULT 'url',
    `mobile_link_type_3` VARCHAR(50) DEFAULT 'url',
    `mobile_link_type_4` VARCHAR(50) DEFAULT 'url',
    `mobile_link_type_5` VARCHAR(50) DEFAULT 'url',
    `mobile_link_type_6` VARCHAR(50) DEFAULT 'url',
    `mobile_link_type_7` VARCHAR(50) DEFAULT 'url',
    `mobile_link_type_8` VARCHAR(50) DEFAULT 'url',
    `is_active` TINYINT(1) DEFAULT 1,
    `display_order` INT(11) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_type` (`carousel_type`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: orders (Pedidos)
-- ============================================
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_number` VARCHAR(50) DEFAULT NULL,
    `session_id` VARCHAR(255) DEFAULT NULL,
    `user_id` INT(11) UNSIGNED DEFAULT NULL,
    `seller_id` INT(11) UNSIGNED DEFAULT NULL,
    `order_type` ENUM('product', 'package', 'subscription') DEFAULT 'product',
    `package_id` INT(11) UNSIGNED DEFAULT NULL,
    `status` ENUM('pending', 'processing', 'paid', 'shipped', 'delivered', 'cancelled', 'refunded') DEFAULT 'pending',
    `payment_status` ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    `payment_method` VARCHAR(50) DEFAULT NULL,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `subtotal` DECIMAL(10,2) DEFAULT 0.00,
    `shipping_cost` DECIMAL(10,2) DEFAULT 0.00,
    `discount_amount` DECIMAL(10,2) DEFAULT 0.00,
    `coupon_code` VARCHAR(50) DEFAULT NULL,
    `total_points` INT(11) DEFAULT 0,
    `tracking_code` VARCHAR(100) DEFAULT NULL,
    `shipping_method` VARCHAR(100) DEFAULT NULL,
    `notes` TEXT,
    `mercado_pago_order_id` VARCHAR(100) DEFAULT NULL,
    `mercado_pago_payment_id` VARCHAR(100) DEFAULT NULL,
    `pix_qr_code` TEXT,
    `pix_qr_code_base64` LONGTEXT,
    `pix_copy_paste` TEXT,
    `pix_expiration` DATETIME DEFAULT NULL,
    `viewed_at` DATETIME DEFAULT NULL,
    `paid_at` DATETIME DEFAULT NULL,
    `shipped_at` DATETIME DEFAULT NULL,
    `delivered_at` DATETIME DEFAULT NULL,
    `cancelled_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_order_number` (`order_number`),
    KEY `idx_user` (`user_id`),
    KEY `idx_seller` (`seller_id`),
    KEY `idx_status` (`status`),
    KEY `idx_payment_status` (`payment_status`),
    KEY `idx_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: order_items (Itens do Pedido)
-- ============================================
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT(11) UNSIGNED NOT NULL,
    `product_id` INT(11) UNSIGNED NOT NULL,
    `variant_id` INT(11) UNSIGNED DEFAULT NULL,
    `product_name` VARCHAR(255) DEFAULT NULL,
    `variant_name` VARCHAR(255) DEFAULT NULL,
    `quantity` INT(11) NOT NULL DEFAULT 1,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `points` INT(11) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order` (`order_id`),
    KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: order_metadata (Metadados do Pedido)
-- ============================================
DROP TABLE IF EXISTS `order_metadata`;
CREATE TABLE `order_metadata` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT(11) UNSIGNED NOT NULL,
    `seller_name` VARCHAR(255) DEFAULT NULL,
    `utm_source` VARCHAR(255) DEFAULT NULL,
    `utm_medium` VARCHAR(255) DEFAULT NULL,
    `utm_campaign` VARCHAR(255) DEFAULT NULL,
    `utm_term` VARCHAR(255) DEFAULT NULL,
    `utm_content` VARCHAR(255) DEFAULT NULL,
    `referrer` VARCHAR(500) DEFAULT NULL,
    `user_agent` TEXT,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: shipping_addresses (Enderecos de Entrega)
-- ============================================
DROP TABLE IF EXISTS `shipping_addresses`;
CREATE TABLE `shipping_addresses` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT(11) UNSIGNED DEFAULT NULL,
    `session_id` VARCHAR(255) DEFAULT NULL,
    `user_id` INT(11) UNSIGNED DEFAULT NULL,
    `recipient_name` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `cpf_cnpj` VARCHAR(20) DEFAULT NULL,
    `cep` VARCHAR(10) NOT NULL,
    `street` VARCHAR(255) NOT NULL,
    `number` VARCHAR(20) NOT NULL,
    `complement` VARCHAR(255) DEFAULT NULL,
    `neighborhood` VARCHAR(255) NOT NULL,
    `city` VARCHAR(255) NOT NULL,
    `state` VARCHAR(2) NOT NULL,
    `country` VARCHAR(2) DEFAULT 'BR',
    `is_default` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order` (`order_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: invoices (Faturas)
-- ============================================
DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT(11) UNSIGNED NOT NULL,
    `user_id` INT(11) UNSIGNED DEFAULT NULL,
    `invoice_number` VARCHAR(50) DEFAULT NULL,
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `points` INT(11) DEFAULT 0,
    `status` ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    `due_date` DATE DEFAULT NULL,
    `paid_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order` (`order_id`),
    KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: coupons (Cupons de Desconto)
-- ============================================
DROP TABLE IF EXISTS `coupons`;
CREATE TABLE `coupons` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `discount_type` ENUM('percentage', 'fixed') DEFAULT 'percentage',
    `discount_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `min_order_value` DECIMAL(10,2) DEFAULT 0.00,
    `max_discount` DECIMAL(10,2) DEFAULT NULL,
    `usage_limit` INT(11) DEFAULT NULL,
    `used_count` INT(11) DEFAULT 0,
    `valid_from` DATE DEFAULT NULL,
    `valid_until` DATE DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_code` (`code`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: coupon_usages (Uso de Cupons)
-- ============================================
DROP TABLE IF EXISTS `coupon_usages`;
CREATE TABLE `coupon_usages` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `coupon_id` INT(11) UNSIGNED NOT NULL,
    `order_id` INT(11) UNSIGNED NOT NULL,
    `user_id` INT(11) UNSIGNED DEFAULT NULL,
    `discount_applied` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_coupon` (`coupon_id`),
    KEY `idx_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: packages (Pacotes/Planos)
-- ============================================
DROP TABLE IF EXISTS `packages`;
CREATE TABLE `packages` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `points` INT(11) DEFAULT 0,
    `duration_days` INT(11) DEFAULT 30,
    `generates_commission` TINYINT(1) DEFAULT 1,
    `commission_percentage` DECIMAL(5,2) DEFAULT 0.00,
    `is_active` TINYINT(1) DEFAULT 1,
    `display_order` INT(11) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: package_features (Recursos dos Pacotes)
-- ============================================
DROP TABLE IF EXISTS `package_features`;
CREATE TABLE `package_features` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `package_id` INT(11) UNSIGNED NOT NULL,
    `feature_name` VARCHAR(255) NOT NULL,
    `feature_value` VARCHAR(255) DEFAULT NULL,
    `is_included` TINYINT(1) DEFAULT 1,
    `display_order` INT(11) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_package` (`package_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: commissions (Comissoes)
-- ============================================
DROP TABLE IF EXISTS `commissions`;
CREATE TABLE `commissions` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `from_user_id` INT(11) UNSIGNED DEFAULT NULL,
    `order_id` INT(11) UNSIGNED DEFAULT NULL,
    `level` INT(11) DEFAULT 1,
    `percentage` DECIMAL(5,2) DEFAULT 0.00,
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('pending', 'retained', 'released', 'paid') DEFAULT 'pending',
    `retained_until` DATETIME DEFAULT NULL,
    `released_at` DATETIME DEFAULT NULL,
    `paid_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_from_user` (`from_user_id`),
    KEY `idx_order` (`order_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: withdrawals (Saques)
-- ============================================
DROP TABLE IF EXISTS `withdrawals`;
CREATE TABLE `withdrawals` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `fee` DECIMAL(10,2) DEFAULT 0.00,
    `net_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `method` ENUM('pix', 'bank_transfer') DEFAULT 'pix',
    `pix_key_type` VARCHAR(20) DEFAULT NULL,
    `pix_key` VARCHAR(255) DEFAULT NULL,
    `bank_name` VARCHAR(100) DEFAULT NULL,
    `bank_agency` VARCHAR(20) DEFAULT NULL,
    `bank_account` VARCHAR(30) DEFAULT NULL,
    `status` ENUM('pending', 'processing', 'completed', 'rejected') DEFAULT 'pending',
    `rejected_reason` TEXT,
    `processed_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: withdrawal_receipts (Comprovantes de Saque)
-- ============================================
DROP TABLE IF EXISTS `withdrawal_receipts`;
CREATE TABLE `withdrawal_receipts` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `withdrawal_id` INT(11) UNSIGNED NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_name` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_withdrawal` (`withdrawal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: point_transactions (Transacoes de Pontos)
-- ============================================
DROP TABLE IF EXISTS `point_transactions`;
CREATE TABLE `point_transactions` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `order_id` INT(11) UNSIGNED DEFAULT NULL,
    `type` ENUM('credit', 'debit') NOT NULL,
    `origin` VARCHAR(100) DEFAULT NULL,
    `amount` INT(11) NOT NULL DEFAULT 0,
    `balance_after` INT(11) DEFAULT 0,
    `status` ENUM('pending', 'completed', 'cancelled') DEFAULT 'completed',
    `description` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: leads (Leads/Contatos)
-- ============================================
DROP TABLE IF EXISTS `leads`;
CREATE TABLE `leads` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `source` VARCHAR(100) DEFAULT NULL,
    `opted_in` TINYINT(1) DEFAULT 1,
    `tags` VARCHAR(500) DEFAULT NULL,
    `notes` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: activity_logs (Logs de Atividade)
-- ============================================
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_action` (`action`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: page_visits (Visitas de Paginas)
-- ============================================
DROP TABLE IF EXISTS `page_visits`;
CREATE TABLE `page_visits` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` VARCHAR(255) DEFAULT NULL,
    `page_url` VARCHAR(500) NOT NULL,
    `page_title` VARCHAR(255) DEFAULT NULL,
    `referrer` VARCHAR(500) DEFAULT NULL,
    `user_agent` TEXT,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `visited_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_session` (`session_id`),
    KEY `idx_visited` (`visited_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: theme_versions (Historico de Versoes do Tema)
-- ============================================
DROP TABLE IF EXISTS `theme_versions`;
CREATE TABLE `theme_versions` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `settings_data` LONGTEXT NOT NULL COMMENT 'JSON com todas as configuracoes do tema',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: click_tracking (Rastreamento de Cliques)
-- ============================================
DROP TABLE IF EXISTS `click_tracking`;
CREATE TABLE `click_tracking` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` VARCHAR(255) DEFAULT NULL,
    `element_id` VARCHAR(100) DEFAULT NULL,
    `element_class` VARCHAR(255) DEFAULT NULL,
    `element_text` VARCHAR(255) DEFAULT NULL,
    `page_url` VARCHAR(500) DEFAULT NULL,
    `target_url` VARCHAR(500) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `clicked_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_session` (`session_id`),
    KEY `idx_clicked` (`clicked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DADOS DE TESTE - Settings
-- ============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('app_name', 'Vitrine Independente', 'string', 'Nome da aplicacao'),
('logo_url', '/assets/logo.png', 'string', 'URL do logo'),
('logo_width', 'auto', 'string', 'Largura do logo'),
('logo_height', '50', 'string', 'Altura do logo'),
('primary_color', '#C7A333', 'string', 'Cor primaria'),
('whatsapp_number', '5511999999999', 'string', 'Numero do WhatsApp'),
('whatsapp_message', 'Ola! Tenho interesse em:', 'string', 'Mensagem padrao WhatsApp'),
('whatsapp_floating_enabled', '1', 'boolean', 'Habilitar WhatsApp flutuante'),
('correios_cep_origem', '01310100', 'string', 'CEP de origem para frete'),
('melhor_envio_token', '', 'string', 'Token do Melhor Envio'),
('frete_gratis_valor_minimo', '299', 'number', 'Valor minimo para frete gratis'),
('mercado_pago_public_key', '', 'string', 'Chave publica Mercado Pago'),
('mercado_pago_access_token', '', 'string', 'Token de acesso Mercado Pago'),
('pix_enabled', '1', 'boolean', 'Habilitar pagamento PIX'),
('credit_card_enabled', '1', 'boolean', 'Habilitar cartao de credito'),
('instagram_url', '', 'string', 'URL do Instagram'),
('facebook_url', '', 'string', 'URL do Facebook'),
('tiktok_url', '', 'string', 'URL do TikTok'),
('checkout_upsell_enabled', '1', 'boolean', 'Habilitar upsell no checkout'),
('checkout_upsell_product_id', '', 'string', 'ID do produto de upsell'),
('checkout_upsell_discount', '20', 'number', 'Desconto do upsell em %');

-- ============================================
-- DADOS DE TESTE - Usuario Admin
-- ============================================
INSERT INTO `users` (`name`, `email`, `password`, `role`, `is_active`) VALUES
('Administrador', 'admin@vitrine.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X.VU3rLwu0Y9H0XvK', 'admin', 1);
-- Senha: admin123

-- ============================================
-- DADOS DE TESTE - Marcas
-- ============================================
INSERT INTO `brands` (`name`, `description`, `is_active`, `display_order`) VALUES
('Carolina Herrera', 'Perfumes de luxo Carolina Herrera', 1, 1),
('Dior', 'Fragancias classicas e modernas', 1, 2),
('Chanel', 'A essencia do luxo frances', 1, 3),
('Jean Paul Gaultier', 'Perfumes ousados e unicos', 1, 4),
('Dolce & Gabbana', 'Elegancia italiana', 1, 5),
('Paco Rabanne', 'Inovacao e modernidade', 1, 6),
('Versace', 'Luxo mediterraneo', 1, 7),
('Giorgio Armani', 'Sofisticacao atemporal', 1, 8);

-- ============================================
-- DADOS DE TESTE - Categorias
-- ============================================
INSERT INTO `categories` (`name`, `slug`, `description`, `is_active`, `display_order`) VALUES
('Masculino', 'masculino', 'Perfumes masculinos', 1, 1),
('Feminino', 'feminino', 'Perfumes femininos', 1, 2),
('Unissex', 'unissex', 'Perfumes unissex', 1, 3),
('Lancamentos', 'lancamentos', 'Ultimos lancamentos', 1, 4),
('Promocoes', 'promocoes', 'Produtos em promocao', 1, 5);

-- ============================================
-- DADOS DE TESTE - Produtos
-- ============================================
INSERT INTO `products` (`name`, `description`, `price`, `original_price`, `brand_id`, `category_id`, `is_vip`, `is_featured`, `is_active`, `shipping_weight`, `shipping_height`, `shipping_width`, `shipping_length`) VALUES
('212 VIP Men', 'Perfume masculino sofisticado com notas de couro e especiarias.', 299.90, 399.90, 1, 1, 1, 1, 1, 0.35, 18, 8, 8),
('212 VIP Rose', 'Fragrancia feminina floral e frutal, elegante e moderna.', 319.90, 419.90, 1, 2, 1, 1, 1, 0.35, 18, 8, 8),
('Sauvage EDT', 'Masculino fresco e marcante, ideal para o dia a dia.', 459.90, 559.90, 2, 1, 0, 1, 1, 0.40, 20, 10, 10),
('Miss Dior', 'Feminino romantico com notas de rosa e peonia.', 489.90, 599.90, 2, 2, 1, 1, 1, 0.38, 19, 9, 9),
('Coco Mademoiselle', 'Classico feminino com notas orientais e citricas.', 549.90, 699.90, 3, 2, 1, 1, 1, 0.42, 20, 10, 10),
('Bleu de Chanel', 'Masculino amadeirado e aromatico, atemporal.', 529.90, 649.90, 3, 1, 1, 1, 1, 0.40, 20, 10, 10),
('Le Male', 'Icone masculino com lavanda e baunilha.', 389.90, 489.90, 4, 1, 0, 1, 1, 0.36, 18, 9, 9),
('Scandal', 'Feminino provocante com mel e gardenia.', 419.90, 519.90, 4, 2, 1, 0, 1, 0.37, 19, 9, 9),
('Light Blue', 'Feminino fresco e mediterraneo para o verao.', 349.90, 449.90, 5, 2, 0, 1, 1, 0.34, 17, 8, 8),
('The One EDP', 'Masculino sofisticado para ocasioes especiais.', 429.90, 529.90, 5, 1, 1, 1, 1, 0.38, 19, 9, 9),
('1 Million', 'Masculino ousado com notas de couro e especiarias.', 379.90, 479.90, 6, 1, 0, 1, 1, 0.35, 18, 8, 8),
('Lady Million', 'Feminino glamouroso e marcante.', 399.90, 499.90, 6, 2, 1, 0, 1, 0.36, 18, 9, 9),
('Eros', 'Masculino intenso com menta e baunilha.', 369.90, 469.90, 7, 1, 0, 1, 1, 0.37, 18, 9, 9),
('Bright Crystal', 'Feminino delicado e floral.', 339.90, 439.90, 7, 2, 0, 1, 1, 0.33, 17, 8, 8),
('Acqua di Gio', 'Classico masculino aquatico e fresco.', 419.90, 519.90, 8, 1, 1, 1, 1, 0.38, 19, 9, 9),
('Si Passione', 'Feminino intenso e apaixonante.', 449.90, 549.90, 8, 2, 1, 1, 1, 0.39, 19, 9, 9);

-- ============================================
-- DADOS DE TESTE - Imagens dos Produtos
-- ============================================
INSERT INTO `product_images` (`product_id`, `image_url`, `is_cover`, `display_order`) VALUES
(1, '/uploads/products/212-vip-men.jpg', 1, 0),
(2, '/uploads/products/212-vip-rose.jpg', 1, 0),
(3, '/uploads/products/sauvage.jpg', 1, 0),
(4, '/uploads/products/miss-dior.jpg', 1, 0),
(5, '/uploads/products/coco-mademoiselle.jpg', 1, 0),
(6, '/uploads/products/bleu-de-chanel.jpg', 1, 0),
(7, '/uploads/products/le-male.jpg', 1, 0),
(8, '/uploads/products/scandal.jpg', 1, 0),
(9, '/uploads/products/light-blue.jpg', 1, 0),
(10, '/uploads/products/the-one.jpg', 1, 0),
(11, '/uploads/products/1-million.jpg', 1, 0),
(12, '/uploads/products/lady-million.jpg', 1, 0),
(13, '/uploads/products/eros.jpg', 1, 0),
(14, '/uploads/products/bright-crystal.jpg', 1, 0),
(15, '/uploads/products/acqua-di-gio.jpg', 1, 0),
(16, '/uploads/products/si-passione.jpg', 1, 0);

-- ============================================
-- DADOS DE TESTE - Variantes
-- ============================================
INSERT INTO `product_variants` (`product_id`, `name`, `price`, `points`, `is_active`, `display_order`) VALUES
(1, '50ml', 299.90, 0, 1, 1),
(1, '100ml', 449.90, 0, 1, 2),
(2, '50ml', 319.90, 0, 1, 1),
(2, '80ml', 429.90, 0, 1, 2),
(3, '60ml', 459.90, 0, 1, 1),
(3, '100ml', 599.90, 0, 1, 2),
(3, '200ml', 799.90, 0, 1, 3);

-- ============================================
-- DADOS DE TESTE - Cupons
-- ============================================
INSERT INTO `coupons` (`code`, `description`, `discount_type`, `discount_value`, `min_order_value`, `max_discount`, `usage_limit`, `valid_from`, `valid_until`, `is_active`) VALUES
('BEMVINDO10', 'Desconto de boas-vindas', 'percentage', 10.00, 100.00, 50.00, 1000, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 90 DAY), 1),
('FRETE20', 'Desconto de R$20 no frete', 'fixed', 20.00, 150.00, NULL, 500, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 60 DAY), 1),
('VIP15', 'Desconto exclusivo VIP', 'percentage', 15.00, 200.00, 100.00, 100, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 1);

-- ============================================
-- DADOS DE TESTE - Vitrines Dinamicas
-- ============================================
INSERT INTO `dynamic_showcases` (`name`, `title`, `description`, `layout_type`, `is_active`, `display_order`) VALUES
('Destaques', 'Produtos em Destaque', 'Os perfumes mais desejados do momento', 'grid', 1, 1),
('Lancamentos', 'Lancamentos', 'Novidades que acabaram de chegar', 'carousel', 1, 2),
('Mais Vendidos', 'Mais Vendidos', 'Os favoritos dos nossos clientes', 'grid', 1, 3);

-- ============================================
-- DADOS DE TESTE - Produtos nas Vitrines
-- ============================================
INSERT INTO `dynamic_showcase_products` (`showcase_id`, `product_id`, `display_order`) VALUES
(1, 1, 1), (1, 3, 2), (1, 5, 3), (1, 6, 4), (1, 10, 5), (1, 15, 6),
(2, 4, 1), (2, 8, 2), (2, 12, 3), (2, 16, 4),
(3, 1, 1), (3, 5, 2), (3, 11, 3), (3, 13, 4), (3, 15, 5);

-- ============================================
-- DADOS DE TESTE - Banner
-- ============================================
INSERT INTO `banners` (`name`, `carousel_type`, `is_active`, `display_order`) VALUES
('Carousel Principal', 'carousel', 1, 1);

-- ============================================
-- FINALIZAR
-- ============================================
SET FOREIGN_KEY_CHECKS = 1;

-- Script executado com sucesso!
-- Acesse o admin com: admin@vitrine.com / admin123
