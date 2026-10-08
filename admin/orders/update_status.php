<?php
/**
 * Admin - Cập nhật trạng thái đơn hàng (AJAX)
 * WinK Shoe Store
 */
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/admin_check.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ.']);
    exit;
}

$pdo     = getDBConnection();
$orderId = (int)($_POST['order_id'] ?? 0);
$status  = $_POST['status'] ?? '';

$validStatuses = ['confirmed', 'shipping', 'delivered', 'cancelled'];

if ($orderId <= 0 || !in_array($status, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
    exit;
}

// Lấy đơn hàng hiện tại
$stmt = $pdo->prepare("SELECT id, status, payment_method, payment_status FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng.']);
    exit;
}

// Kiểm tra logic chuyển trạng thái hợp lệ
$flow = ['pending', 'confirmed', 'shipping', 'delivered'];
$currentIdx = array_search($order['status'], $flow);
$newIdx     = array_search($status, $flow);

// Đơn đã giao hoặc đã huỷ → không thể đổi nữa
if ($order['status'] === 'delivered' || $order['status'] === 'cancelled') {
    echo json_encode(['success' => false, 'message' => 'Đơn hàng đã hoàn thành hoặc đã huỷ, không thể thay đổi trạng thái.']);
    exit;
}

// Chỉ cho phép chuyển tiếp 1 bước hoặc hủy
if ($status !== 'cancelled' && ($newIdx === false || $newIdx !== $currentIdx + 1)) {
    echo json_encode(['success' => false, 'message' => 'Trạng thái chuyển không hợp lệ.']);
    exit;
}

// Bắt đầu transaction
$pdo->beginTransaction();

try {
    // Cập nhật trạng thái đơn & thời gian mốc tương ứng
    $timeCol = '';
    if ($status === 'confirmed') {
        $timeCol = ', confirmed_at = NOW()';
    } elseif ($status === 'shipping') {
        $timeCol = ', shipping_at = NOW()';
    } elseif ($status === 'delivered') {
        $timeCol = ', delivered_at = NOW()';
    } elseif ($status === 'cancelled') {
        $timeCol = ', cancelled_at = NOW()';
    }

    $upd = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() {$timeCol} WHERE id = ?");
    $upd->execute([$status, $orderId]);

    // Nếu giao thành công & COD → tự động đánh dấu đã thanh toán
    if ($status === 'delivered' && $order['payment_method'] === 'cod' && $order['payment_status'] === 'unpaid') {
        $pdo->prepare("UPDATE orders SET payment_status = 'paid', updated_at = NOW() WHERE id = ?")->execute([$orderId]);
    }

    // Nếu hủy đơn → hoàn lại tồn kho
    if ($status === 'cancelled') {
        $itemsStmt = $pdo->prepare("SELECT variant_id, quantity FROM order_items WHERE order_id = ?");
        $itemsStmt->execute([$orderId]);
        $items = $itemsStmt->fetchAll();

        foreach ($items as $item) {
            $pdo->prepare("
                UPDATE product_variants
                SET stock_quantity = stock_quantity + ?,
                    status = CASE
                        WHEN (stock_quantity + ?) = 0   THEN 'out_of_stock'
                        WHEN (stock_quantity + ?) <= 5  THEN 'low_stock'
                        ELSE 'in_stock'
                    END,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$item['quantity'], $item['quantity'], $item['quantity'], $item['variant_id']]);
        }

        // Nếu đã thanh toán online → chuyển về refunded
        if ($order['payment_status'] === 'paid' && $order['payment_method'] !== 'cod') {
            $pdo->prepare("UPDATE orders SET payment_status = 'refunded', updated_at = NOW() WHERE id = ?")->execute([$orderId]);
        }
    }

    $pdo->commit();

    $msgMap = [
        'confirmed' => 'Đã xác nhận đơn hàng thành công.',
        'shipping'  => 'Đã chuyển trạng thái "Đang giao hàng".',
        'delivered' => 'Đã xác nhận giao hàng thành công.',
        'cancelled' => 'Đã huỷ đơn hàng và hoàn lại tồn kho.',
    ];

    echo json_encode([
        'success'    => true,
        'message'    => $msgMap[$status] ?? 'Cập nhật thành công.',
        'new_status' => $status,
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}
