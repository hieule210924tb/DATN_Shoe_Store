<?php
/**
 * Helper Functions - WinK Shoe Store
 * Các hàm tiện ích dùng chung cho toàn bộ hệ thống
 */

// =====================================================
// HÀM BẢO MẬT & ESCAPE
// =====================================================

/**
 * Escape output để chống XSS
 * @param string $string Chuỗi cần escape
 * @return string
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Tạo CSRF token
 * @return string
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Xác thực CSRF token
 * @param string $token Token cần kiểm tra
 * @return bool
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Hiển thị input hidden chứa CSRF token
 * @return string
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">';
}

// =====================================================
// HÀM REDIRECT & URL
// =====================================================

/**
 * Chuyển hướng đến URL
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Tạo URL đầy đủ từ path
 * @param string $path Đường dẫn tương đối
 * @return string
 */
function url($path = '') {
    return BASE_URL . '/' . ltrim($path, '/');
}

/**
 * Tạo URL cho assets
 * @param string $path Đường dẫn file asset
 * @return string
 */
function asset($path) {
    return BASE_URL . '/assets/' . ltrim($path, '/');
}

// =====================================================
// HÀM THÔNG BÁO (FLASH MESSAGE)
// =====================================================

/**
 * Đặt thông báo flash
 * @param string $type Loại thông báo: success, error, warning, info
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Lấy và xóa thông báo flash
 * @return array|null
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

/**
 * Hiển thị thông báo flash dạng Bootstrap alert
 * @return string HTML
 */
function displayFlashMessage() {
    $flash = getFlashMessage();
    if ($flash) {
        $type = $flash['type'];
        $message = e($flash['message']);
        $icon = '';
        switch ($type) {
            case 'success': $icon = '<i class="fas fa-check-circle me-2"></i>'; break;
            case 'error':   $type = 'danger'; $icon = '<i class="fas fa-exclamation-circle me-2"></i>'; break;
            case 'warning': $icon = '<i class="fas fa-exclamation-triangle me-2"></i>'; break;
            case 'info':    $icon = '<i class="fas fa-info-circle me-2"></i>'; break;
        }
        return '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">
                    ' . $icon . $message . '
                </div>';
    }
    return '';
}

// =====================================================
// HÀM ĐĂNG NHẬP & PHÂN QUYỀN
// =====================================================

/**
 * Kiểm tra đã đăng nhập chưa
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Kiểm tra có phải Admin không
 * @return bool
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Lấy ID người dùng hiện tại
 * @return int|null
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Lấy tên người dùng hiện tại
 * @return string|null
 */
function getCurrentUserName() {
    return $_SESSION['user_name'] ?? null;
}

/**
 * Lấy avatar người dùng hiện tại
 * @return string URL avatar
 */
function getCurrentUserAvatar() {
    if (!empty($_SESSION['user_avatar'])) {
        return UPLOAD_URL . '/avatars/' . $_SESSION['user_avatar'];
    }
    return asset('images/default/default-avatar.png');
}

// =====================================================
// HÀM FORMAT DỮ LIỆU
// =====================================================

/**
 * Format số tiền VNĐ
 * @param float $amount Số tiền
 * @return string
 */
function formatPrice($amount) {
    return number_format((float)($amount ?? 0), 0, ',', '.') . 'đ';
}

/**
 * Format ngày giờ tiếng Việt
 * @param string $datetime Chuỗi ngày giờ
 * @param string $format Format hiển thị
 * @return string
 */
function formatDate($datetime, $format = 'd/m/Y H:i') {
    if (empty($datetime)) return '';
    return date($format, strtotime($datetime));
}

/**
 * Tạo slug từ chuỗi tiếng Việt
 * @param string $string Chuỗi gốc
 * @return string Slug
 */
function createSlug($string) {
    // Chuyển đổi tiếng Việt sang không dấu
    $vietnameseMap = [
        'à'=>'a', 'á'=>'a', 'ả'=>'a', 'ã'=>'a', 'ạ'=>'a',
        'ă'=>'a', 'ằ'=>'a', 'ắ'=>'a', 'ẳ'=>'a', 'ẵ'=>'a', 'ặ'=>'a',
        'â'=>'a', 'ầ'=>'a', 'ấ'=>'a', 'ẩ'=>'a', 'ẫ'=>'a', 'ậ'=>'a',
        'đ'=>'d',
        'è'=>'e', 'é'=>'e', 'ẻ'=>'e', 'ẽ'=>'e', 'ẹ'=>'e',
        'ê'=>'e', 'ề'=>'e', 'ế'=>'e', 'ể'=>'e', 'ễ'=>'e', 'ệ'=>'e',
        'ì'=>'i', 'í'=>'i', 'ỉ'=>'i', 'ĩ'=>'i', 'ị'=>'i',
        'ò'=>'o', 'ó'=>'o', 'ỏ'=>'o', 'õ'=>'o', 'ọ'=>'o',
        'ô'=>'o', 'ồ'=>'o', 'ố'=>'o', 'ổ'=>'o', 'ỗ'=>'o', 'ộ'=>'o',
        'ơ'=>'o', 'ờ'=>'o', 'ớ'=>'o', 'ở'=>'o', 'ỡ'=>'o', 'ợ'=>'o',
        'ù'=>'u', 'ú'=>'u', 'ủ'=>'u', 'ũ'=>'u', 'ụ'=>'u',
        'ư'=>'u', 'ừ'=>'u', 'ứ'=>'u', 'ử'=>'u', 'ữ'=>'u', 'ự'=>'u',
        'ỳ'=>'y', 'ý'=>'y', 'ỷ'=>'y', 'ỹ'=>'y', 'ỵ'=>'y',
        'À'=>'A', 'Á'=>'A', 'Ả'=>'A', 'Ã'=>'A', 'Ạ'=>'A',
        'Ă'=>'A', 'Ằ'=>'A', 'Ắ'=>'A', 'Ẳ'=>'A', 'Ẵ'=>'A', 'Ặ'=>'A',
        'Â'=>'A', 'Ầ'=>'A', 'Ấ'=>'A', 'Ẩ'=>'A', 'Ẫ'=>'A', 'Ậ'=>'A',
        'Đ'=>'D',
        'È'=>'E', 'É'=>'E', 'Ẻ'=>'E', 'Ẽ'=>'E', 'Ẹ'=>'E',
        'Ê'=>'E', 'Ề'=>'E', 'Ế'=>'E', 'Ể'=>'E', 'Ễ'=>'E', 'Ệ'=>'E',
        'Ì'=>'I', 'Í'=>'I', 'Ỉ'=>'I', 'Ĩ'=>'I', 'Ị'=>'I',
        'Ò'=>'O', 'Ó'=>'O', 'Ỏ'=>'O', 'Õ'=>'O', 'Ọ'=>'O',
        'Ô'=>'O', 'Ồ'=>'O', 'Ố'=>'O', 'Ổ'=>'O', 'Ỗ'=>'O', 'Ộ'=>'O',
        'Ơ'=>'O', 'Ờ'=>'O', 'Ớ'=>'O', 'Ở'=>'O', 'Ỡ'=>'O', 'Ợ'=>'O',
        'Ù'=>'U', 'Ú'=>'U', 'Ủ'=>'U', 'Ũ'=>'U', 'Ụ'=>'U',
        'Ư'=>'U', 'Ừ'=>'U', 'Ứ'=>'U', 'Ử'=>'U', 'Ữ'=>'U', 'Ự'=>'U',
        'Ỳ'=>'Y', 'Ý'=>'Y', 'Ỷ'=>'Y', 'Ỹ'=>'Y', 'Ỵ'=>'Y',
    ];
    
    $string = strtr($string, $vietnameseMap);
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
    $string = preg_replace('/[\s-]+/', '-', $string);
    $string = trim($string, '-');
    
    return $string;
}

/**
 * Tạo mã đơn hàng
 * @return string VD: WK20260923001
 */
function generateOrderCode() {
    $pdo = getDBConnection();
    $today = date('Ymd');
    $prefix = 'WK' . $today;
    
    // Đếm số đơn hàng hôm nay
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $count = $stmt->fetchColumn() + 1;
    
    return $prefix . str_pad($count, 3, '0', STR_PAD_LEFT);
}

// =====================================================
// HÀM UPLOAD FILE
// =====================================================

/**
 * Upload hình ảnh an toàn
 * @param array $file $_FILES['input_name']
 * @param string $uploadDir Thư mục upload
 * @return array ['success' => bool, 'filename' => string, 'error' => string]
 */
function uploadImage($file, $uploadDir) {
    $result = ['success' => false, 'filename' => '', 'error' => ''];
    
    // Kiểm tra lỗi upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = 'Lỗi upload file.';
        return $result;
    }
    
    // Kiểm tra kích thước file
    if ($file['size'] > MAX_FILE_SIZE) {
        $result['error'] = 'File quá lớn. Tối đa ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB.';
        return $result;
    }
    
    // Kiểm tra MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
        $result['error'] = 'Định dạng file không được hỗ trợ. Chỉ chấp nhận: JPG, PNG, GIF, WebP.';
        return $result;
    }
    
    // Kiểm tra extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_IMAGE_EXTENSIONS)) {
        $result['error'] = 'Phần mở rộng file không hợp lệ.';
        return $result;
    }
    
    // Chặn file PHP hoặc file thực thi
    $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phar', 'exe', 'bat', 'cmd', 'sh'];
    if (in_array($extension, $dangerousExtensions)) {
        $result['error'] = 'Loại file này không được phép upload.';
        return $result;
    }
    
    // Tạo thư mục nếu chưa tồn tại
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Tạo tên file an toàn
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $destination = $uploadDir . '/' . $filename;
    
    // Di chuyển file
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        $result['success'] = true;
        $result['filename'] = $filename;
    } else {
        $result['error'] = 'Không thể lưu file. Vui lòng thử lại.';
    }
    
    return $result;
}

/**
 * Xóa file ảnh
 * @param string $filename Tên file
 * @param string $uploadDir Thư mục chứa file
 * @return bool
 */
function deleteImage($filename, $uploadDir) {
    $filepath = $uploadDir . '/' . $filename;
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return false;
}

// =====================================================
// HÀM VALIDATE DỮ LIỆU
// =====================================================

/**
 * Validate email
 * @param string $email
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate số điện thoại Việt Nam
 * @param string $phone
 * @return bool
 */
function isValidPhone($phone) {
    return preg_match('/^(0|\+84)[0-9]{9}$/', preg_replace('/\s+/', '', $phone));
}

/**
 * Validate mật khẩu (tối thiểu 6 ký tự)
 * @param string $password
 * @return bool
 */
function isValidPassword($password) {
    return strlen($password) >= 6;
}

/**
 * Lấy text trạng thái đơn hàng
 * @param string $status
 * @return string
 */
function getOrderStatusText($status) {
    $statuses = [
        'pending'   => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận',
        'shipping'  => 'Đang giao',
        'delivered' => 'Đã giao',
        'cancelled' => 'Đã hủy',
    ];
    return $statuses[$status] ?? $status;
}

/**
 * Lấy class badge trạng thái đơn hàng
 * @param string $status
 * @return string
 */
function getOrderStatusBadge($status) {
    $badges = [
        'pending'   => 'bg-warning text-dark',
        'confirmed' => 'bg-info',
        'shipping'  => 'bg-primary',
        'delivered' => 'bg-success',
        'cancelled' => 'bg-danger',
    ];
    return $badges[$status] ?? 'bg-secondary';
}

/**
 * Lấy text phương thức thanh toán
 * @param string $method
 * @return string
 */
function getPaymentMethodText($method) {
    $methods = [
        'cod'   => 'Thanh toán khi nhận hàng (COD)',
        'vnpay' => 'VNPay',
        'momo'  => 'MoMo',
    ];
    return $methods[$method] ?? $method;
}

/**
 * Lấy text trạng thái thanh toán
 * @param string $status
 * @return string
 */
function getPaymentStatusText($status) {
    $statuses = [
        'unpaid'   => 'Chưa thanh toán',
        'paid'     => 'Đã thanh toán',
        'refunded' => 'Đã hoàn tiền',
    ];
    return $statuses[$status] ?? $status;
}

/**
 * Lấy số lượng sản phẩm trong giỏ hàng
 * @return int
 */
function getCartItemCount() {
    if (!isLoggedIn()) return 0;
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(ci.quantity), 0) 
        FROM cart_items ci 
        INNER JOIN carts c ON ci.cart_id = c.id 
        WHERE c.user_id = ?
    ");
    $stmt->execute([getCurrentUserId()]);
    return (int) $stmt->fetchColumn();
}

/**
 * Lấy số lượng wishlist
 * @return int
 */
function getWishlistCount() {
    if (!isLoggedIn()) return 0;
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlists WHERE user_id = ?");
    $stmt->execute([getCurrentUserId()]);
    return (int) $stmt->fetchColumn();
}

/**
 * Lấy số tin nhắn chat chưa đọc của người dùng hiện tại (tin từ admin)
 * @return int
 */
function getChatUnreadCount() {
    if (!isLoggedIn()) return 0;
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT COALESCE(unread_by_user, 0) FROM chat_conversations WHERE user_id = ?");
    $stmt->execute([getCurrentUserId()]);
    return (int) $stmt->fetchColumn();
}

// =====================================================
// HÀM CHAT
// =====================================================

/**
 * Xử lý ảnh đính kèm trong tin nhắn chat ($_FILES['image'])
 * Không chọn ảnh vẫn trả về success với filename = null
 * @param string $field Tên input file
 * @return array ['success' => bool, 'filename' => string|null, 'error' => string]
 */
function processChatImageUpload($field = 'image') {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'filename' => null, 'error' => ''];
    }

    $upload = uploadImage($_FILES[$field], CHAT_UPLOAD_PATH);
    return [
        'success'  => $upload['success'],
        'filename' => $upload['success'] ? $upload['filename'] : null,
        'error'    => $upload['error'],
    ];
}

/**
 * URL đầy đủ của ảnh chat (null nếu tin nhắn không có ảnh)
 * @param string|null $filename
 * @return string|null
 */
function chatImageUrl($filename) {
    return !empty($filename) ? CHAT_UPLOAD_URL . '/' . $filename : null;
}

/**
 * Nội dung hiển thị tóm tắt của tin nhắn (dùng cho last_message)
 */
function chatPreviewText($message, $hasImage) {
    if ($message !== '') return $message;
    return $hasImage ? '[Hình ảnh]' : '';
}

// =====================================================
// HÀM PHÂN TRANG
// =====================================================

/**
 * Tạo HTML phân trang
 * @param int $currentPage Trang hiện tại
 * @param int $totalPages Tổng số trang
 * @param string $baseUrl URL cơ sở
 * @return string HTML phân trang
 */
function renderPagination($currentPage, $totalPages, $baseUrl) {
    if ($totalPages <= 1) return '';
    
    // Đảm bảo baseUrl có dấu ? hoặc &
    $separator = (strpos($baseUrl, '?') !== false) ? '&' : '?';
    
    $html = '<nav aria-label="Phân trang"><ul class="pagination justify-content-center">';
    
    // Nút Previous
    if ($currentPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=' . ($currentPage - 1) . '"><i class="fas fa-chevron-left"></i></a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-left"></i></span></li>';
    }
    
    // Số trang
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    
    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=1">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $currentPage) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=' . $totalPages . '">' . $totalPages . '</a></li>';
    }
    
    // Nút Next
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=' . ($currentPage + 1) . '"><i class="fas fa-chevron-right"></i></a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-right"></i></span></li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}
