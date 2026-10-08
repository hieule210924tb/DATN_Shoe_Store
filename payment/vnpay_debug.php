<?php
/**
 * DEBUG - Kiểm tra URL VNPay (XÓA FILE NÀY SAU KHI DEBUG XONG)
 */
require_once dirname(__DIR__) . '/config/config.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

$inputData = [
    'vnp_Version'    => '2.1.0',
    'vnp_TmnCode'    => VNPAY_TMN_CODE,
    'vnp_Amount'     => 100000 * 100,
    'vnp_Command'    => 'pay',
    'vnp_CreateDate' => date('YmdHis'),
    'vnp_CurrCode'   => 'VND',
    'vnp_IpAddr'     => '127.0.0.1',
    'vnp_Locale'     => 'vn',
    'vnp_OrderInfo'  => 'Test order',
    'vnp_OrderType'  => 'other',
    'vnp_ReturnUrl'  => VNPAY_RETURN_URL,
    'vnp_TxnRef'     => 'WK_TEST_' . time(),
    'vnp_ExpireDate' => date('YmdHis', strtotime('+20 minutes')),
];

ksort($inputData);
$hashData = '';
$query    = '';
foreach ($inputData as $key => $value) {
    $pair      = urlencode($key) . '=' . urlencode($value);
    $hashData .= ($hashData ? '&' : '') . $pair;
    $query    .= ($query    ? '&' : '') . $pair;
}

$hash = hash_hmac('sha512', $hashData, VNPAY_HASH_SECRET);
$url  = VNPAY_URL . '?' . $query . '&vnp_SecureHash=' . $hash;

echo '<pre>';
echo "TMN Code    : " . VNPAY_TMN_CODE . "\n";
echo "Hash Secret : " . substr(VNPAY_HASH_SECRET, 0, 8) . "...\n";
echo "CreateDate  : " . date('YmdHis') . " (timezone: " . date_default_timezone_get() . ")\n";
echo "Hash Input  : " . $hashData . "\n\n";
echo "Payment URL :\n" . $url . "\n";
echo '</pre>';
echo '<a href="' . htmlspecialchars($url) . '" target="_blank">→ Mở VNPay sandbox</a>';
