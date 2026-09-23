<?php
/**
 * Admin - Xóa danh mục
 */
require_once dirname(dirname(__DIR__)) . '/includes/admin_check.php';

$pdo = getDBConnection();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlashMessage('error', 'ID không hợp lệ.');
    redirect(url('admin/categories/list.php'));
}

// Kiểm tra có sản phẩm không
$stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
$stmt->execute([$id]);
if ($stmt->fetchColumn() > 0) {
    setFlashMessage('error', 'Không thể xóa danh mục có sản phẩm liên quan.');
    redirect(url('admin/categories/list.php'));
}

$stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
$stmt->execute([$id]);

setFlashMessage('success', 'Xóa danh mục thành công!');
redirect(url('admin/categories/list.php'));
