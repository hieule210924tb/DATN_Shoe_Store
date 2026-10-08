<?php
/**
 * VNPay - Tạo URL thanh toán & chuyển hướng
 * WinK Shoe Store
 *
 * Spec chính thức VNPay v2.1.0:
 * - Ký HMAC-SHA512 trên chuỗi: urlencode(key)=urlencode(value) ghép &
 * - Thời gian phải theo múi giờ Việt Nam (UTC+7)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

// BẮT BUỘC: VNPay dùng giờ Việt Nam
date_default_timezone_set('Asia/Ho_Chi_Minh');

$pdo    = getDBConnection();
$userId = getCurrentUserId();

$orderId = intval($_GET['order_id'] ?? 0);
if (!$orderId) {
    setFlashMessage('danger', 'Đơn hàng không hợp lệ.');
    redirect(url('pages/cart.php'));
}

// Lấy đơn hàng
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? AND payment_method = 'vnpay' AND payment_status = 'unpaid'");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) {
    setFlashMessage('danger', 'Đơn hàng không hợp lệ hoặc đã được thanh toán.');
    redirect(url('pages/order_detail.php?id=' . $orderId));
}

// ============================================================
// Tham số VNPay
// ============================================================
$vnp_TmnCode    = VNPAY_TMN_CODE;
$vnp_HashSecret = VNPAY_HASH_SECRET;
$vnp_Url        = VNPAY_URL;
$vnp_ReturnUrl  = VNPAY_RETURN_URL;

$vnp_TxnRef    = $order['order_code'] . '_' . time();
$vnp_OrderInfo = 'Thanh toan don hang ' . $order['order_code'];
$vnp_Amount    = (int)$order['total_amount'] * 100; // nhân 100 theo yêu cầu VNPay
$vnp_Locale    = 'vn';
$vnp_CurrCode  = 'VND';

// IP - ưu tiên lấy real IP
$vnp_IpAddr = $_SERVER['HTTP_CLIENT_IP']
           ?? $_SERVER['HTTP_X_FORWARDED_FOR']
           ?? $_SERVER['REMOTE_ADDR']
           ?? '127.0.0.1';

// Múi giờ VN, định dạng YmdHis
$vnp_CreateDate = date('YmdHis');
$vnp_ExpireDate = date('YmdHis', strtotime('+20 minutes'));

// Tập hợp params (chưa sort)
$inputData = [
    'vnp_Version'    => '2.1.0',
    'vnp_TmnCode'    => $vnp_TmnCode,
    'vnp_Amount'     => $vnp_Amount,
    'vnp_Command'    => 'pay',
    'vnp_CreateDate' => $vnp_CreateDate,
    'vnp_CurrCode'   => $vnp_CurrCode,
    'vnp_IpAddr'     => $vnp_IpAddr,
    'vnp_Locale'     => $vnp_Locale,
    'vnp_OrderInfo'  => $vnp_OrderInfo,
    'vnp_OrderType'  => 'other',
    'vnp_ReturnUrl'  => $vnp_ReturnUrl,
    'vnp_TxnRef'     => $vnp_TxnRef,
    'vnp_ExpireDate' => $vnp_ExpireDate,
];

// Lưu session
$_SESSION['vnpay_txn_ref']  = $vnp_TxnRef;
$_SESSION['vnpay_order_id'] = $orderId;

// ============================================================
// Sort & build chuỗi ký
// Theo spec VNPay: urlencode(key)=urlencode(value) nối bằng &
// Cả hashData và queryString dùng cùng cách encode
// ============================================================
ksort($inputData);

$hashData = '';
$query    = '';
foreach ($inputData as $key => $value) {
    $pair      = urlencode($key) . '=' . urlencode($value);
    $hashData .= ($hashData ? '&' : '') . $pair;
    $query    .= ($query    ? '&' : '') . $pair;
}

$vnp_SecureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);
$paymentUrl     = $vnp_Url . '?' . $query . '&vnp_SecureHash=' . $vnp_SecureHash;

// Redirect sang VNPay
header('Location: ' . $paymentUrl);
exit;
