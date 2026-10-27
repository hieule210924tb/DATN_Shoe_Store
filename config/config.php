<?php
// Bắt đầu session nếu chưa có
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// URL gốc của website - tự động detect protocol & host (hoạt động với localhost và ngrok)
if (!defined('BASE_URL')) {
    // Detect protocol: ngrok gửi X-Forwarded-Proto = https
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $protocol = $_SERVER['HTTP_X_FORWARDED_PROTO'];
    } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $protocol = 'https';
    } else {
        $protocol = 'http';
    }

    // Detect host: ngrok gửi HTTP_HOST = xxxx.ngrok-free.app
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Subfolder luôn giữ nguyên (ngrok tunnel qua port 80 nên path vẫn như localhost)
    $subFolder = '/DATN_Shoe_Store';

    define('BASE_URL', $protocol . '://' . $host . $subFolder);
}

// Đường dẫn thư mục gốc
define('ROOT_PATH', dirname(__DIR__));

// Đường dẫn upload
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('UPLOAD_URL', BASE_URL . '/uploads');

// Đường dẫn upload cụ thể
define('PRODUCT_UPLOAD_PATH', UPLOAD_PATH . '/products');
define('REVIEW_UPLOAD_PATH', UPLOAD_PATH . '/reviews');
define('AVATAR_UPLOAD_PATH', UPLOAD_PATH . '/avatars');
define('CHAT_UPLOAD_PATH', UPLOAD_PATH . '/chat');

// URL upload cụ thể
define('PRODUCT_UPLOAD_URL', UPLOAD_URL . '/products');
define('REVIEW_UPLOAD_URL', UPLOAD_URL . '/reviews');
define('AVATAR_UPLOAD_URL', UPLOAD_URL . '/avatars');
define('CHAT_UPLOAD_URL', UPLOAD_URL . '/chat');

// Cấu hình upload
define('MAX_FILE_SIZE', 5 * 1024 * 1024);  // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Phân trang
define('ITEMS_PER_PAGE', 12);
define('ADMIN_ITEMS_PER_PAGE', 15);

// Phí vận chuyển
define('SHIPPING_FEE_EXPRESS', 60000);  // Giao hàng nhanh: 60.000đ
define('SHIPPING_FEE_STANDARD', 0);  // Giao hàng tiêu chuẩn: miễn phí

// Thông tin cửa hàng
define('STORE_NAME', 'WinK Shoe Store');
define('STORE_EMAIL', 'hieule210924@wink.vn');
define('STORE_PHONE', '0372953009');
define('STORE_ADDRESS', 'Trung Kính,Hà Nội, Việt Nam');

// Cấu hình VNPay Sandbox
// *** Đăng ký tại https://sandbox.vnpayment.vn/devreg/ để lấy TMN Code và Hash Secret riêng ***
define('VNPAY_TMN_CODE', 'UGBE4L5Q');  // Thay bằng TMN Code của bạn
define('VNPAY_HASH_SECRET', 'FTHCQFMCNZRCNYYDZIKVKQJWRREXQLBU');  // Thay bằng Hash Secret của bạn
define('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
define('VNPAY_RETURN_URL', BASE_URL . '/payment/vnpay_return.php');
define('VNPAY_IPN_URL', BASE_URL . '/payment/vnpay_return.php');

// Cấu hình MoMo Sandbox
// Tài liệu: https://developers.momo.vn/
define('MOMO_PARTNER_CODE', 'MOMO');  // Partner code sandbox
define('MOMO_ACCESS_KEY', 'F8BBA842ECF85');  // Access key sandbox
define('MOMO_SECRET_KEY', 'K951B6PE1waDMi640xX08PD3vg6EkVlz');  // Secret sandbox
define('MOMO_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/create');
define('MOMO_RETURN_URL', BASE_URL . '/payment/momo_return.php');
define('MOMO_NOTIFY_URL', BASE_URL . '/payment/momo_notify.php');

// Cấu hình gửi Email (Gmail SMTP qua PHPMailer)
// *** Bật xác minh 2 bước cho Gmail, rồi tạo App Password tại https://myaccount.google.com/apppasswords ***
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);  // 587 = STARTTLS
define('SMTP_USERNAME', 'manu210924@gmail.com');  // VD: wink.shoestore@gmail.com
define('SMTP_PASSWORD', 'wypwtdxwxjzclwjf');  // App Password 16 ký tự (không phải mật khẩu Gmail)
define('SMTP_FROM_EMAIL', SMTP_USERNAME);
define('SMTP_FROM_NAME', 'WinK Shoe Store');

// Quên mật khẩu (OTP)
define('OTP_EXPIRE_MINUTES', 10);  // Thời gian hiệu lực mã OTP
define('OTP_RESEND_SECONDS', 60);  // Thời gian chờ trước khi gửi lại mã
define('OTP_MAX_ATTEMPTS', 5);  // Số lần nhập sai tối đa

// Tải file database connection
require_once ROOT_PATH . '/config/database.php';

// Tải file helper functions
require_once ROOT_PATH . '/includes/helpers.php';
