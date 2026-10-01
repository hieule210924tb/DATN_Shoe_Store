<?php
/**
 * Admin - Xóa voucher
 */
require_once dirname(dirname(__DIR__)) . '/includes/admin_check.php';

$pdo = getDBConnection();
$id  = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlashMessage('error', 'ID voucher không hợp lệ.');
    redirect(url('admin/vouchers/list.php'));
}

// Lấy thông tin voucher
$stmt = $pdo->prepare("SELECT * FROM vouchers WHERE id = ?");
$stmt->execute([$id]);
$voucher = $stmt->fetch();

if (!$voucher) {
    setFlashMessage('error', 'Voucher không tồn tại.');
    redirect(url('admin/vouchers/list.php'));
}

// Kiểm tra xem voucher có đang được dùng trong đơn hàng không
$checkStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE voucher_id = ?");
$checkStmt->execute([$id]);
$orderCount = (int)$checkStmt->fetchColumn();

if ($orderCount > 0) {
    setFlashMessage('error', "Không thể xóa voucher <strong>" . e($voucher['code']) . "</strong> vì đã được sử dụng trong <strong>{$orderCount}</strong> đơn hàng. Hãy tạm dừng (inactive) voucher thay vì xóa.");
    redirect(url('admin/vouchers/list.php'));
}

// Xóa voucher
$pdo->prepare("DELETE FROM vouchers WHERE id = ?")->execute([$id]);

setFlashMessage('success', "Đã xóa voucher <strong>" . e($voucher['code']) . "</strong> thành công!");
redirect(url('admin/vouchers/list.php'));
