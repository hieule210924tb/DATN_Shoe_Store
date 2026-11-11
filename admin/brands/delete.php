<?php
/**
 * Admin - Xóa thương hiệu
 */
require_once dirname(dirname(__DIR__)) . '/includes/admin_check.php';
$pdo = getDBConnection();

$brandId = (int)($_GET['id'] ?? 0);
if ($brandId <= 0) {
    setFlashMessage('error', 'ID thương hiệu không hợp lệ.');
    redirect(url('admin/brands/list.php'));
}

// Kiểm tra có sản phẩm liên quan không
$stmtCheck = $pdo->prepare('SELECT COUNT(*) FROM products WHERE brand_id = ?');
$stmtCheck->execute([$brandId]);
$productCount = (int)$stmtCheck->fetchColumn();

if ($productCount > 0) {
    setFlashMessage('error', 'Không thể xóa thương hiệu này vì có ' . $productCount . ' sản phẩm liên quan.');
    redirect(url('admin/brands/list.php'));
}

// Xóa thật
$stmt = $pdo->prepare('DELETE FROM brands WHERE id = ?');
$stmt->execute([$brandId]);

setFlashMessage('success', 'Xóa thương hiệu thành công!');
redirect(url('admin/brands/list.php'));
