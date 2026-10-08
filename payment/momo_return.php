<?php
/**
 * MoMo - Xử lý Return URL (redirect từ MoMo về)
 * WinK Shoe Store
 */
require_once dirname(__DIR__) . '/config/config.php';

$pdo = getDBConnection();

// Tham số MoMo gửi về qua GET (redirectUrl)
$partnerCode  = $_GET['partnerCode']  ?? '';
$orderId_momo = $_GET['orderId']      ?? '';  // WK20261007001_timestamp
$requestId    = $_GET['requestId']    ?? '';
$amount       = intval($_GET['amount'] ?? 0);
$resultCode   = intval($_GET['resultCode'] ?? -1);
$message      = $_GET['message']      ?? '';
$signature    = $_GET['signature']    ?? '';
$extraData    = $_GET['extraData']    ?? '';

// Xác thực chữ ký
$accessKey = MOMO_ACCESS_KEY;
$secretKey = MOMO_SECRET_KEY;

$rawHash = "accessKey={$accessKey}"
         . "&amount={$amount}"
         . "&extraData={$extraData}"
         . "&message={$message}"
         . "&orderId={$orderId_momo}"
         . "&orderInfo=" . ($_GET['orderInfo'] ?? '')
         . "&orderType=" . ($_GET['orderType'] ?? '')
         . "&partnerCode={$partnerCode}"
         . "&payType="    . ($_GET['payType'] ?? '')
         . "&requestId={$requestId}"
         . "&responseTime=" . ($_GET['responseTime'] ?? '')
         . "&resultCode={$resultCode}"
         . "&transId="   . ($_GET['transId'] ?? '');

$expectedSig = hash_hmac('sha256', $rawHash, $secretKey);

// Nếu chữ ký sai, từ chối
if (!hash_equals($expectedSig, $signature)) {
    setFlashMessage('danger', 'Phản hồi MoMo không hợp lệ (chữ ký sai).');
    redirect(url('pages/orders.php'));
}

// Giải mã extraData để lấy order_id nội bộ
$extra   = json_decode(base64_decode($extraData), true);
$orderId = intval($extra['order_id'] ?? 0);

if (!$orderId) {
    // Fallback: tìm qua order_code trong orderId_momo
    $orderCodeParts = explode('_', $orderId_momo);
    $orderCode      = $orderCodeParts[0];
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_code = ? AND payment_method = 'momo'");
    $stmt->execute([$orderCode]);
    $order = $stmt->fetch();
} else {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND payment_method = 'momo'");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
}

if (!$order) {
    setFlashMessage('danger', 'Không tìm thấy đơn hàng.');
    redirect(url('pages/orders.php'));
}

// Chống replay
if ($order['payment_status'] === 'paid') {
    setFlashMessage('info', 'Đơn hàng ' . $order['order_code'] . ' đã được thanh toán trước đó.');
    redirect(url('pages/order_detail.php?id=' . $order['id']));
}

if ($resultCode === 0) {
    // Thanh toán thành công
    $pdo->prepare("
        UPDATE orders
        SET payment_status = 'paid',
            status         = 'confirmed',
            updated_at     = NOW()
        WHERE id = ?
    ")->execute([$order['id']]);

    setFlashMessage('success', "Thanh toán MoMo thành công! Đơn hàng {$order['order_code']} đã được xác nhận.");
    redirect(url('pages/order_detail.php?id=' . $order['id']));
} else {
    // Thất bại / huỷ
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = ?")
            ->execute([$order['id']]);

        // Hoàn kho
        $items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $items->execute([$order['id']]);
        foreach ($items->fetchAll() as $item) {
            $pdo->prepare("
                UPDATE product_variants
                SET stock_quantity = stock_quantity + ?,
                    status = CASE
                        WHEN stock_quantity + ? > 5 THEN 'in_stock'
                        WHEN stock_quantity + ? > 0 THEN 'low_stock'
                        ELSE 'out_of_stock'
                    END
                WHERE id = ?
            ")->execute([$item['quantity'], $item['quantity'], $item['quantity'], $item['variant_id']]);

            $pdo->prepare("UPDATE products SET total_sold = GREATEST(0, total_sold - ?) WHERE id = ?")
                ->execute([$item['quantity'], $item['product_id']]);
        }

        if ($order['voucher_id']) {
            $pdo->prepare("UPDATE vouchers SET used_count = GREATEST(0, used_count - 1) WHERE id = ?")
                ->execute([$order['voucher_id']]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
    }

    $errMsg = match($resultCode) {
        1006 => 'Giao dịch đã bị người dùng huỷ.',
        1003 => 'Giao dịch đã hết hạn.',
        default => "Thanh toán thất bại: {$message} (mã: {$resultCode})."
    };
    setFlashMessage('danger', $errMsg . ' Đơn hàng đã được huỷ, vui lòng đặt lại.');
    redirect(url('pages/cart.php'));
}
