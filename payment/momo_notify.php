<?php
/**
 * MoMo - IPN / Notify URL (Server-to-server callback)
 * WinK Shoe Store
 * 
 * MoMo gọi URL này trực tiếp để xác nhận giao dịch phía server.
 * Phải trả về HTTP 200 + JSON.
 */
require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json');

$pdo = getDBConnection();

// Đọc body JSON từ MoMo
$rawBody = file_get_contents('php://input');
$data    = json_decode($rawBody, true);

if (empty($data)) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid request']);
    exit;
}

$partnerCode  = $data['partnerCode']  ?? '';
$orderId_momo = $data['orderId']      ?? '';
$requestId    = $data['requestId']    ?? '';
$amount       = intval($data['amount'] ?? 0);
$resultCode   = intval($data['resultCode'] ?? -1);
$message      = $data['message']      ?? '';
$signature    = $data['signature']    ?? '';
$extraData    = $data['extraData']    ?? '';

// Xác thực chữ ký
$accessKey = MOMO_ACCESS_KEY;
$secretKey = MOMO_SECRET_KEY;

$rawHash = "accessKey={$accessKey}"
         . "&amount={$amount}"
         . "&extraData={$extraData}"
         . "&message={$message}"
         . "&orderId={$orderId_momo}"
         . "&orderInfo=" . ($data['orderInfo'] ?? '')
         . "&orderType=" . ($data['orderType'] ?? '')
         . "&partnerCode={$partnerCode}"
         . "&payType="    . ($data['payType'] ?? '')
         . "&requestId={$requestId}"
         . "&responseTime=" . ($data['responseTime'] ?? '')
         . "&resultCode={$resultCode}"
         . "&transId="   . ($data['transId'] ?? '');

$expectedSig = hash_hmac('sha256', $rawHash, $secretKey);

if (!hash_equals($expectedSig, $signature)) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid signature']);
    exit;
}

// Tìm đơn hàng
$extra   = json_decode(base64_decode($extraData), true);
$orderId = intval($extra['order_id'] ?? 0);

if ($orderId) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND payment_method = 'momo'");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
} else {
    $parts     = explode('_', $orderId_momo);
    $orderCode = $parts[0];
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_code = ? AND payment_method = 'momo'");
    $stmt->execute([$orderCode]);
    $order = $stmt->fetch();
}

if (!$order) {
    http_response_code(200); // Trả 200 để MoMo không retry vô hạn
    echo json_encode(['message' => 'Order not found']);
    exit;
}

// Xử lý (idempotent)
if ($order['payment_status'] !== 'paid') {
    if ($resultCode === 0) {
        $pdo->prepare("
            UPDATE orders
            SET payment_status = 'paid',
                status         = 'confirmed',
                updated_at     = NOW()
            WHERE id = ?
        ")->execute([$order['id']]);
    } else {
        // Huỷ đơn & hoàn kho nếu chưa huỷ
        if ($order['status'] !== 'cancelled') {
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
        }
    }
}

http_response_code(200);
echo json_encode(['message' => 'OK']);
exit;
