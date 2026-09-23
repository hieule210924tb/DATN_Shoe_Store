-- =====================================================
-- DATABASE: WinK Shoe Store
-- Đồ án tốt nghiệp: Xây dựng website bán giày trực tuyến
-- Ngày tạo: 2026-09-23
-- =====================================================

-- Tạo database
CREATE DATABASE IF NOT EXISTS wink_shoe_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE wink_shoe_store;

-- =====================================================
-- 1. BẢNG USERS - Người dùng
-- =====================================================
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

-- =====================================================
-- 2. BẢNG CATEGORIES - Danh mục sản phẩm
-- =====================================================
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

-- =====================================================
-- 3. BẢNG BRANDS - Thương hiệu
-- =====================================================
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

-- =====================================================
-- 4. BẢNG PRODUCTS - Sản phẩm
-- =====================================================
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    brand_id INT DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
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

-- =====================================================
-- 5. BẢNG PRODUCT_VARIANTS - Biến thể sản phẩm (Size + Màu)
-- =====================================================
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

-- =====================================================
-- 7. BẢNG VOUCHERS - Mã giảm giá
-- (Tạo trước bảng orders vì orders tham chiếu đến vouchers)
-- =====================================================
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

-- =====================================================
-- 8. BẢNG CARTS - Giỏ hàng
-- =====================================================
CREATE TABLE carts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_user (user_id),
    CONSTRAINT fk_carts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 9. BẢNG CART_ITEMS - Sản phẩm trong giỏ hàng
-- =====================================================
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

-- =====================================================
-- 10. BẢNG ORDERS - Đơn hàng
-- =====================================================
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

-- =====================================================
-- 11. BẢNG ORDER_ITEMS - Chi tiết đơn hàng
-- =====================================================
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

-- =====================================================
-- 12. BẢNG PAYMENTS - Thanh toán
-- =====================================================
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

-- =====================================================
-- 13. BẢNG WISHLISTS - Danh sách yêu thích
-- =====================================================
CREATE TABLE wishlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_user_product (user_id, product_id),
    CONSTRAINT fk_wishlists_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_wishlists_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 14. BẢNG PRODUCT_REVIEWS - Đánh giá sản phẩm
-- =====================================================
CREATE TABLE product_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    order_item_id INT NOT NULL,
    rating TINYINT NOT NULL,
    content TEXT DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL COMMENT 'Đường dẫn hình ảnh đánh giá',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_product (product_id),
    INDEX idx_user_product (user_id, product_id),
    INDEX idx_order_item (order_item_id),
    CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE,
    CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE CASCADE,
    CONSTRAINT fk_reviews_order_item FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON UPDATE CASCADE,
    CONSTRAINT chk_rating CHECK (rating >= 1 AND rating <= 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 15. BẢNG CHAT_CONVERSATIONS - Cuộc trò chuyện
-- =====================================================
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

-- =====================================================
-- 16. BẢNG CHAT_MESSAGES - Tin nhắn
-- =====================================================
CREATE TABLE chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    sender_role ENUM('user', 'admin') NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_conversation (conversation_id),
    INDEX idx_created_at (created_at),
    CONSTRAINT fk_messages_conversation FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DỮ LIỆU MẪU: Tài khoản Admin mặc định
-- Email: admin@wink.vn | Password: Admin@123
-- =====================================================
INSERT INTO users (full_name, email, password, phone, role, status)
VALUES (
    'Admin WinK',
    'admin@wink.vn',
    '$2y$10$JMd4I3CufyLCIN.Tl0RTveT1nfm30CFCqlbxseeY8xbIvPUi/ShDq', -- Admin@123
    '0123456789',
    'admin',
    'active'
);

-- =====================================================
-- DỮ LIỆU MẪU: Danh mục sản phẩm
-- =====================================================
INSERT INTO categories (name, slug, description, status) VALUES
('Giày thể thao', 'giay-the-thao', 'Giày thể thao nam nữ các thương hiệu', 'active'),
('Giày chạy bộ', 'giay-chay-bo', 'Giày chạy bộ chuyên dụng', 'active'),
('Giày sneaker', 'giay-sneaker', 'Giày sneaker thời trang', 'active'),
('Giày da', 'giay-da', 'Giày da công sở và thời trang', 'active'),
('Giày boot', 'giay-boot', 'Giày boot nam nữ', 'active'),
('Dép & Sandal', 'dep-sandal', 'Dép và sandal thời trang', 'active');

-- =====================================================
-- DỮ LIỆU MẪU: Thương hiệu
-- =====================================================
INSERT INTO brands (name, slug, status) VALUES
('Nike', 'nike', 'active'),
('Adidas', 'adidas', 'active'),
('Puma', 'puma', 'active'),
('New Balance', 'new-balance', 'active'),
('Converse', 'converse', 'active'),
('Vans', 'vans', 'active'),
('Asics', 'asics', 'active'),
('Reebok', 'reebok', 'active');

-- =====================================================
-- DỮ LIỆU MẪU: Voucher
-- =====================================================
INSERT INTO vouchers (code, name, discount_type, discount_value, max_discount, min_order_amount, usage_limit, start_date, end_date, status) VALUES
('WELCOME10', 'Giảm 10% cho khách mới', 'percentage', 10, 100000, 200000, 100, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 'active'),
('FREESHIP', 'Miễn phí vận chuyển', 'fixed', 60000, NULL, 300000, 50, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 'active'),
('SALE50K', 'Giảm 50K cho đơn từ 500K', 'fixed', 50000, NULL, 500000, 200, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 'active');
