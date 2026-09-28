<?php
/**
 * Admin - Cập nhật tồn kho (AJAX)
 * WinK Shoe Store
 */
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/admin_check.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ.']);
    exit;
}

$pdo       = getDBConnection();
$variantId = (int)($_POST['variant_id'] ?? 0);
$quantity  = (int)($_POST['quantity']   ?? -1);

if ($variantId <= 0 || $quantity < 0) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
    exit;
}

// Kiểm tra biến thể tồn tại
$stmt = $pdo->prepare("SELECT id, stock_quantity, status FROM product_variants WHERE id = ?");
$stmt->execute([$variantId]);
$variant = $stmt->fetch();

if (!$variant) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy biến thể sản phẩm.']);
    exit;
}

// Tự động tính trạng thái tồn kho (ngưỡng sắp hết: ≤ 5)
if ($quantity === 0) {
    $newStatus = 'out_of_stock';
} elseif ($quantity <= 5) {
    $newStatus = 'low_stock';
} else {
    $newStatus = 'in_stock';
}

// Cập nhật database
$upd = $pdo->prepare("
    UPDATE product_variants
    SET stock_quantity = ?, status = ?, updated_at = NOW()
    WHERE id = ?
");
$upd->execute([$quantity, $newStatus, $variantId]);

$statusLabel = [
    'in_stock'     => 'Còn hàng',
    'low_stock'    => 'Sắp hết hàng',
    'out_of_stock' => 'Hết hàng',
];

echo json_encode([
    'success'    => true,
    'message'    => 'Đã cập nhật tồn kho: ' . $quantity . ' sản phẩm (' . ($statusLabel[$newStatus] ?? $newStatus) . ').',
    'new_status' => $newStatus,
    'quantity'   => $quantity,
]);
