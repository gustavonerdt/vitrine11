-- Add shipping columns to products table for accurate freight calculation
-- These fields allow store owners to set weight and dimensions per product

-- Add shipping weight column (in kg)
ALTER TABLE products ADD COLUMN IF NOT EXISTS shipping_weight DECIMAL(10,3) DEFAULT 0.3 COMMENT 'Peso em kg para cálculo de frete';

-- Add shipping dimensions columns (in cm)
ALTER TABLE products ADD COLUMN IF NOT EXISTS shipping_height INT DEFAULT 15 COMMENT 'Altura em cm para cálculo de frete';
ALTER TABLE products ADD COLUMN IF NOT EXISTS shipping_width INT DEFAULT 8 COMMENT 'Largura em cm para cálculo de frete';
ALTER TABLE products ADD COLUMN IF NOT EXISTS shipping_length INT DEFAULT 8 COMMENT 'Comprimento em cm para cálculo de frete';
