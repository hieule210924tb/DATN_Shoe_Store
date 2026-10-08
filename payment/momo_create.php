<?php
/**
 * MoMo - Tạo yêu cầu thanh toán & chuyển hướng
 * WinK Shoe Store — Sandbox v2
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$pdo    = getDBConnection();
$userId = getCurrentUserId();

$orderId = intval($_GET['order_id'] ?? 0);
if (!$orderId) {
    setFlashMessage('danger', 'Đơn hàng không hợp lệ.');
    redirect(url('pages/cart.php'));
}

// Lấy đơn hàng
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? AND payment_method = 'momo' AND payment_status = 'unpaid'");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) {
    setFlashMessage('danger', 'Đơn hàng không hợp lệ hoặc đã được thanh toán.');
    redirect(url('pages/order_detail.php?id=' . $orderId));
}

// ============================================================
// Tham số MoMo Sandbox
// ============================================================
$partnerCode = MOMO_PARTNER_CODE;
$accessKey   = MOMO_ACCESS_KEY;
$secretKey   = MOMO_SECRET_KEY;
$endpoint    = MOMO_ENDPOINT;
$returnUrl   = MOMO_RETURN_URL;
$notifyUrl   = MOMO_NOTIFY_URL;

$orderId_momo    = $order['order_code'] . '_' . time();
$orderInfo       = 'Thanh toan don hang ' . $order['order_code'];
$amount          = (string)(int)$order['total_amount'];
$requestId       = $partnerCode . '_' . time();
$redirectUrl     = $returnUrl;
$ipnUrl          = $notifyUrl;
$requestType     = 'payWithMethod'; // captureWallet / payWithMethod
$extraData       = base64_encode(json_encode(['order_id' => $orderId]));
$lang            = 'vi';

// Lưu requestId vào session để xác thực khi return
$_SESSION['momo_request_id'] = $requestId;
$_SESSION['momo_order_id']   = $orderId;

// Tạo chữ ký HMAC SHA256
$rawHash = "accessKey={$accessKey}"
         . "&amount={$amount}"
         . "&extraData={$extraData}"
         . "&ipnUrl={$ipnUrl}"
         . "&orderId={$orderId_momo}"
         . "&orderInfo={$orderInfo}"
         . "&partnerCode={$partnerCode}"
         . "&redirectUrl={$redirectUrl}"
         . "&requestId={$requestId}"
         . "&requestType={$requestType}";

$signature = hash_hmac('sha256', $rawHash, $secretKey);

$body = json_encode([
    'partnerCode' => $partnerCode,
    'partnerName' => STORE_NAME,
    'storeId'     => $partnerCode,
    'requestId'   => $requestId,
    'amount'      => $amount,
    'orderId'     => $orderId_momo,
    'orderInfo'   => $orderInfo,
    'redirectUrl' => $redirectUrl,
    'ipnUrl'      => $ipnUrl,
    'lang'        => $lang,
    'requestType' => $requestType,
    'autoCapture' => true,
    'extraData'   => $extraData,
    'signature'   => $signature,
]);

// ============================================================
// Gọi MoMo API (hỗ trợ cả curl & file_get_contents)
// ============================================================
$response = false;
$httpCode = 0;
$callError = '';

if (function_exists('curl_init')) {
    // --- Dùng cURL ---
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Content-Length: ' . strlen($body)],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $response  = curl_exec($ch);
    $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $callError = curl_error($ch);
    curl_close($ch);
} else {
    // --- Fallback: file_get_contents + stream_context ---
    $context = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n",
            'content'       => $body,
            'timeout'       => 30,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ],
    ]);
    $response = @file_get_contents($endpoint, false, $context);
    if ($response === false) {
        $callError = error_get_last()['message'] ?? 'file_get_contents failed';
    } else {
        // Lấy HTTP status code từ $http_response_header
        if (!empty($http_response_header[0])) {
            preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $m);
            $httpCode = (int)($m[1] ?? 200);
        } else {
            $httpCode = 200;
        }
    }
}

if ($callError || $httpCode < 200 || $httpCode >= 300) {
    setFlashMessage('danger', 'Không thể kết nối tới MoMo. Vui lòng thử lại. (HTTP ' . $httpCode . ($callError ? " | {$callError}" : '') . ')');
    $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$orderId]);
    redirect(url('pages/cart.php'));
}

$result = json_decode($response, true);

if (isset($result['payUrl']) && !empty($result['payUrl'])) {
    // Chuyển hướng đến trang thanh toán MoMo
    header('Location: ' . $result['payUrl']);
    exit;
} else {
    $errMsg = $result['message'] ?? 'Lỗi không xác định từ MoMo.';
    setFlashMessage('danger', "MoMo từ chối yêu cầu: {$errMsg}");
    $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$orderId]);
    redirect(url('pages/cart.php'));
}
