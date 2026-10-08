<?php
/**
 * VNPay - Xử lý kết quả trả về (Return URL)
 * WinK Shoe Store
 *
 * Verify hash theo đúng cách VNPay tạo: urlencode(key)=urlencode(value)
 */
require_once dirname(__DIR__) . '/config/config.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

$pdo = getDBConnection();

// Lấy toàn bộ params VNPay gửi về qua GET
$vnpData = $_GET;

$vnp_HashSecret   = VNPAY_HASH_SECRET;
$vnp_SecureHash   = $vnpData['vnp_SecureHash']   ?? '';
$vnp_ResponseCode = $vnpData['vnp_ResponseCode']  ?? '';
$vnp_TxnRef       = $vnpData['vnp_TxnRef']        ?? '';

// ============================================================
// Xác thực chữ ký
// Loại bỏ vnp_SecureHash & vnp_SecureHashType khỏi data trước khi hash
// Dùng urlencode(key)=urlencode(value) — đúng với cách VNPay ký
// ============================================================
unset($vnpData['vnp_SecureHash'], $vnpData['vnp_SecureHashType']);
ksort($vnpData);

$hashData = '';
foreach ($vnpData as $key => $value) {
    $hashData .= ($hashData ? '&' : '') . urlencode($key) . '=' . urlencode($value);
}
$expectedHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);

if (!hash_equals($expectedHash, $vnp_SecureHash)) {
    setFlashMessage('danger', 'Phản hồi thanh toán không hợp lệ (chữ ký sai). Vui lòng liên hệ hỗ trợ.');
    redirect(url('pages/orders.php'));
}

// ============================================================
// Tìm đơn hàng từ vnp_TxnRef (dạng: WK20261007001_1728296824)
// ============================================================
$parts     = explode('_', $vnp_TxnRef);
$orderCode = $parts[0]; // WK20261007001

$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_code = ? AND payment_method = 'vnpay'");
$stmt->execute([$orderCode]);
$order = $stmt->fetch();

if (!$order) {
    setFlashMessage('danger', 'Không tìm thấy đơn hàng. Vui lòng liên hệ hỗ trợ.');
    redirect(url('pages/orders.php'));
}

// Chống replay: nếu đã thanh toán thì bỏ qua
if ($order['payment_status'] === 'paid') {
    setFlashMessage('info', 'Đơn hàng ' . $order['order_code'] . ' đã được thanh toán trước đó.');
    redirect(url('pages/order_detail.php?id=' . $order['id']));
}

// ============================================================
// Xử lý kết quả
// ============================================================
if ($vnp_ResponseCode === '00') {
    // ✅ Thanh toán thành công
    $pdo->prepare("
        UPDATE orders
        SET payment_status = 'paid',
            status         = 'confirmed',
            updated_at     = NOW()
        WHERE id = ?
    ")->execute([$order['id']]);

    setFlashMessage('success', "Thanh toán VNPay thành công! Đơn hàng {$order['order_code']} đã được xác nhận.");
    redirect(url('pages/order_detail.php?id=' . $order['id']));

} else {
    // ❌ Thất bại / Huỷ → rollback đơn hàng + hoàn kho
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = ?")
            ->execute([$order['id']]);

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

    $msg = match($vnp_ResponseCode) {
        '24'    => 'Bạn đã huỷ giao dịch.',
        '11'    => 'Giao dịch đã hết hạn.',
        '09'    => 'Thẻ/Tài khoản chưa đăng ký dịch vụ Internet Banking.',
        '51'    => 'Tài khoản không đủ số dư.',
        default => "Thanh toán thất bại (mã: {$vnp_ResponseCode})."
    };
    setFlashMessage('danger', $msg . ' Đơn hàng đã được huỷ, vui lòng đặt hàng lại.');
    redirect(url('pages/cart.php'));
}
