-- Đồng bộ lại cột status trong product_variants
-- dựa trên stock_quantity hiện tại (ngưỡng sắp hết: ≤ 5)
UPDATE product_variants
SET status = CASE
    WHEN stock_quantity = 0   THEN 'out_of_stock'
    WHEN stock_quantity <= 5  THEN 'low_stock'
    ELSE 'in_stock'
END;
