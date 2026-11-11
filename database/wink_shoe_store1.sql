

-- Tạo database
CREATE DATABASE IF NOT EXISTS wink_shoe_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE wink_shoe_store;

-- 1. BẢNG USERS - Người dùng
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    province VARCHAR(100) DEFAULT NULL,
    ward VARCHAR(100) DEFAULT NULL,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    status ENUM('active', 'locked') NOT NULL DEFAULT 'active',
    reset_token VARCHAR(255) DEFAULT NULL,
    reset_token_expiry DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. BẢNG CATEGORIES - Danh mục sản phẩm
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_name (name),
    UNIQUE KEY idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. BẢNG BRANDS - Thương hiệu
CREATE TABLE brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    logo VARCHAR(255) DEFAULT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_name (name),
    UNIQUE KEY idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. BẢNG PRODUCTS - Sản phẩm
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    brand_id INT DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    style ENUM('low_top', 'high_top', 'mid_top') NOT NULL DEFAULT 'low_top' COMMENT 'Kiểu dáng: cổ thấp, cổ cao, cổ lửng',
    thumbnail VARCHAR(255) DEFAULT NULL COMMENT 'Ảnh đại diện chính',
    images TEXT DEFAULT NULL COMMENT 'JSON mảng đường dẫn ảnh phụ',
    description TEXT DEFAULT NULL,
    price DECIMAL(12, 0) NOT NULL,
    sale_price DECIMAL(12, 0) DEFAULT NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_new TINYINT(1) NOT NULL DEFAULT 1,
    total_sold INT NOT NULL DEFAULT 0,
    avg_rating DECIMAL(2, 1) NOT NULL DEFAULT 0.0,
    total_reviews INT NOT NULL DEFAULT 0,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_slug (slug),
    INDEX idx_category (category_id),
    INDEX idx_brand (brand_id),
    INDEX idx_status (status),
    INDEX idx_featured (is_featured),
    INDEX idx_total_sold (total_sold),
    INDEX idx_created_at (created_at),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON UPDATE CASCADE,
    CONSTRAINT fk_products_brand FOREIGN KEY (brand_id) REFERENCES brands(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. BẢNG PRODUCT_VARIANTS - Biến thể sản phẩm (Size + Màu)
CREATE TABLE product_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    size VARCHAR(10) NOT NULL,
    color VARCHAR(50) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    sku VARCHAR(50) DEFAULT NULL,
    status ENUM('in_stock', 'low_stock', 'out_of_stock') NOT NULL DEFAULT 'in_stock',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_sku (sku),
    UNIQUE KEY idx_product_size_color (product_id, size, color),
    INDEX idx_product (product_id),
    CONSTRAINT fk_variants_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. BẢNG VOUCHERS - Mã giảm giá
-- (Tạo trước bảng orders vì orders tham chiếu đến vouchers)
CREATE TABLE vouchers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    discount_value DECIMAL(12, 0) NOT NULL,
    max_discount DECIMAL(12, 0) DEFAULT NULL,
    min_order_amount DECIMAL(12, 0) NOT NULL DEFAULT 0,
    usage_limit INT NOT NULL DEFAULT 0 COMMENT '0 = không giới hạn',
    used_count INT NOT NULL DEFAULT 0,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_code (code),
    INDEX idx_status_dates (status, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. BẢNG CARTS - Giỏ hàng
CREATE TABLE carts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_user (user_id),
    CONSTRAINT fk_carts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. BẢNG CART_ITEMS - Sản phẩm trong giỏ hàng
CREATE TABLE cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cart_id INT NOT NULL,
    product_id INT NOT NULL,
    variant_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(12, 0) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_cart_variant (cart_id, variant_id),
    INDEX idx_product (product_id),
    CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_cart_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE,
    CONSTRAINT fk_cart_items_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. BẢNG ORDERS - Đơn hàng
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_code VARCHAR(20) NOT NULL,
    receiver_name VARCHAR(100) NOT NULL,
    receiver_phone VARCHAR(20) NOT NULL,
    receiver_address TEXT NOT NULL,
    receiver_province VARCHAR(100) NOT NULL,
    receiver_ward VARCHAR(100) NOT NULL,
    note TEXT DEFAULT NULL,
    subtotal DECIMAL(12, 0) NOT NULL,
    shipping_fee DECIMAL(12, 0) NOT NULL DEFAULT 0,
    shipping_method ENUM('standard', 'express') NOT NULL DEFAULT 'standard',
    discount_amount DECIMAL(12, 0) NOT NULL DEFAULT 0,
    voucher_id INT DEFAULT NULL,
    total_amount DECIMAL(12, 0) NOT NULL,
    status ENUM('pending', 'confirmed', 'shipping', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
    payment_method ENUM('cod', 'vnpay', 'momo') NOT NULL,
    payment_status ENUM('unpaid', 'paid', 'refunded') NOT NULL DEFAULT 'unpaid',
    confirmed_at DATETIME DEFAULT NULL,
    shipping_at DATETIME DEFAULT NULL,
    delivered_at DATETIME DEFAULT NULL,
    cancelled_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_order_code (order_code),
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_created_at (created_at),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE,
    CONSTRAINT fk_orders_voucher FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. BẢNG ORDER_ITEMS - Chi tiết đơn hàng
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    variant_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL COMMENT 'Snapshot tên SP',
    product_image VARCHAR(255) DEFAULT NULL COMMENT 'Snapshot ảnh SP',
    size VARCHAR(10) NOT NULL COMMENT 'Snapshot size',
    color VARCHAR(50) NOT NULL COMMENT 'Snapshot màu',
    quantity INT NOT NULL,
    price DECIMAL(12, 0) NOT NULL COMMENT 'Đơn giá tại thời điểm đặt',
    subtotal DECIMAL(12, 0) NOT NULL COMMENT 'Thành tiền = quantity * price',
    is_reviewed TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order (order_id),
    INDEX idx_product (product_id),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE,
    CONSTRAINT fk_order_items_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. BẢNG PAYMENTS - Thanh toán
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_method ENUM('cod', 'vnpay', 'momo') NOT NULL,
    transaction_id VARCHAR(100) DEFAULT NULL,
    amount DECIMAL(12, 0) NOT NULL,
    status ENUM('pending', 'success', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    response_data TEXT DEFAULT NULL COMMENT 'Dữ liệu phản hồi JSON',
    paid_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order (order_id),
    INDEX idx_transaction (transaction_id),
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. BẢNG WISHLISTS - Danh sách yêu thích
CREATE TABLE wishlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_user_product (user_id, product_id),
    CONSTRAINT fk_wishlists_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_wishlists_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. BẢNG PRODUCT_REVIEWS - Đánh giá sản phẩm
CREATE TABLE product_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    order_item_id INT NOT NULL,
    rating TINYINT NOT NULL,
    content TEXT DEFAULT NULL,
    admin_reply TEXT DEFAULT NULL COMMENT 'Nội dung phản hồi của admin',
    reply_at DATETIME DEFAULT NULL COMMENT 'Thời gian admin phản hồi',
    admin_id INT DEFAULT NULL COMMENT 'ID admin đã phản hồi',
    image VARCHAR(255) DEFAULT NULL COMMENT 'Đường dẫn hình ảnh đánh giá',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_product (product_id),
    INDEX idx_user_product (user_id, product_id),
    INDEX idx_order_item (order_item_id),
    INDEX idx_admin_id (admin_id),
    CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE,
    CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE,
    CONSTRAINT fk_reviews_order_item FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON UPDATE CASCADE,
    CONSTRAINT chk_rating CHECK (rating >= 1 AND rating <= 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. BẢNG CHAT_CONVERSATIONS - Cuộc trò chuyện
CREATE TABLE chat_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    last_message TEXT DEFAULT NULL,
    last_message_at DATETIME DEFAULT NULL,
    unread_by_admin INT NOT NULL DEFAULT 0,
    unread_by_user INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_user (user_id),
    CONSTRAINT fk_conversations_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. BẢNG CHAT_MESSAGES - Tin nhắn
CREATE TABLE chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    sender_role ENUM('user', 'admin') NOT NULL,
    message TEXT NOT NULL,
    image VARCHAR(255) DEFAULT NULL COMMENT 'Tên file ảnh đính kèm (uploads/chat)',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_conversation (conversation_id),
    INDEX idx_created_at (created_at),
    CONSTRAINT fk_messages_conversation FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 17. DỮ LIỆU MẪU - DANH MỤC GIÀY ĐÁ BÓNG
-- =====================================================

-- Đảm bảo danh mục Giày đá bóng tồn tại
INSERT INTO categories (name, slug, description, image, status)
VALUES ('Giày đá bóng', 'giay-da-bong', 'Chuyên trang giày đá bóng sân cỏ nhân tạo, tự nhiên chính hãng', 'cat_football.jpg', 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), status = 'active';

-- Đảm bảo các thương hiệu phổ biến tồn tại
INSERT INTO brands (name, slug, status)
VALUES 
    ('Nike', 'nike', 'active'),
    ('Adidas', 'adidas', 'active'),
    ('Puma', 'puma', 'active')
ON DUPLICATE KEY UPDATE status = 'active';

-- Thêm sản phẩm mẫu Giày đá bóng
INSERT INTO products (
    category_id, brand_id, name, slug, style, 
    thumbnail, images, description, 
    price, sale_price, is_featured, is_new, 
    total_sold, avg_rating, total_reviews, status
) VALUES 
(
    (SELECT id FROM categories WHERE slug = 'giay-da-bong' LIMIT 1),
    (SELECT id FROM brands WHERE slug = 'nike' LIMIT 1),
    'Nike Mercurial Vapor 16 Pro TF',
    'nike-mercurial-vapor-16-pro-tf',
    'low_top',
    'shoe_nike_mercurial_vapor16.jpg',
    '["shoe_nike_mercurial_vapor16.jpg"]',
    'Giày đá bóng Nike Mercurial Vapor 16 Pro TF thiết kế chuyên dụng cho mặt sân cỏ nhân tạo. Trang bị bộ đệm Air Zoom cải tiến mang lại khả năng phản hồi lực bùng nổ, thân giày Flyknit nhẹ bọc chân êm ái cùng bề mặt Gripknit tăng độ bám và cảm giác kiểm soát bóng tối ưu ở tốc độ cao.',
    2800000,
    2450000,
    1,
    1,
    38,
    4.9,
    19,
    'active'
),
(
    (SELECT id FROM categories WHERE slug = 'giay-da-bong' LIMIT 1),
    (SELECT id FROM brands WHERE slug = 'adidas' LIMIT 1),
    'Adidas Predator 24 Elite TF',
    'adidas-predator-24-elite-tf',
    'low_top',
    'shoe_adidas_predator_24.jpg',
    '["shoe_adidas_predator_24.jpg"]',
    'Siêu phẩm giày đá bóng Adidas Predator 24 Elite TF dành cho sân cỏ nhân tạo. Sử dụng công nghệ gai cao su Strikeskin hỗ trợ xoáy bóng và những cú sút uy lực hiểm hóc, kết hợp chất liệu HybridTouch 2.0 mềm mại ôm khít bàn chân cùng đệm đế Lightstrike êm ái giảm chấn tuyệt vời.',
    3200000,
    2890000,
    1,
    1,
    45,
    4.9,
    26,
    'active'
),
(
    (SELECT id FROM categories WHERE slug = 'giay-da-bong' LIMIT 1),
    (SELECT id FROM brands WHERE slug = 'puma' LIMIT 1),
    'Puma Future 7 Ultimate Cage TF',
    'puma-future-7-ultimate-cage-tf',
    'mid_top',
    'shoe_puma_future_7.jpg',
    '["shoe_puma_future_7.jpg"]',
    'Giày đá banh Puma Future 7 Ultimate Cage TF nổi bật với dải PWRTAPE trợ lực và upper dệt FUZIONFIT360 co giãn thích ứng hoàn hảo với mọi form chân (kể cả chân bè). Thiết kế đinh cao su đa hướng thế hệ mới giúp xoay trở và bứt tốc linh hoạt trong không gian hẹp.',
    2500000,
    2150000,
    1,
    1,
    27,
    4.8,
    14,
    'active'
),
(
    (SELECT id FROM categories WHERE slug = 'giay-da-bong' LIMIT 1),
    (SELECT id FROM brands WHERE slug = 'nike' LIMIT 1),
    'Nike Phantom GX 2 Pro TF',
    'nike-phantom-gx-2-pro-tf',
    'low_top',
    'shoe_nike_phantom_gx2.jpg',
    '["shoe_nike_phantom_gx2.jpg"]',
    'Nike Phantom GX 2 Pro TF là mẫu giày đá bóng định hình lối chơi kiểm soát và kiến tạo. Lớp phủ bọc vân nhám toàn diện giúp từng cú chạm và chuyền bóng đạt độ chuẩn xác tối đa, đế đệm mềm hỗ trợ thi đấu liên tục với cường độ cao mà không gây mỏi chân.',
    2700000,
    2390000,
    0,
    1,
    19,
    4.7,
    11,
    'active'
)
ON DUPLICATE KEY UPDATE 
    name = VALUES(name),
    price = VALUES(price),
    sale_price = VALUES(sale_price),
    thumbnail = VALUES(thumbnail),
    description = VALUES(description);

-- Thêm các biến thể Size (39 đến 43) cho các sản phẩm Giày đá bóng
INSERT INTO product_variants (product_id, size, color, stock_quantity, sku, status)
SELECT p.id, s.size, 'Mặc định' AS color, 20 AS stock_quantity, CONCAT('FB-', p.id, '-', s.size) AS sku, 'in_stock' AS status
FROM products p
CROSS JOIN (
    SELECT '39' AS size UNION ALL
    SELECT '40' UNION ALL
    SELECT '41' UNION ALL
    SELECT '42' UNION ALL
    SELECT '43'
) s
WHERE p.category_id = (SELECT id FROM categories WHERE slug = 'giay-da-bong' LIMIT 1)
ON DUPLICATE KEY UPDATE stock_quantity = VALUES(stock_quantity);
