<?php
/**
 * Admin - Xóa sản phẩm
 */
require_once dirname(dirname(__DIR__)) . '/includes/admin_check.php';

$pdo = getDBConnection();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlashMessage('error', 'ID không hợp lệ.');
    redirect(url('admin/products/list.php'));
}

// Kiểm tra đơn hàng liên quan
$stmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
$stmt->execute([$id]);
if ($stmt->fetchColumn() > 0) {
    setFlashMessage('error', 'Không thể xóa sản phẩm đã có trong đơn hàng. Hãy ẩn sản phẩm thay vì xóa.');
    redirect(url('admin/products/list.php'));
}

// Xóa ảnh từ thumbnail và images
$stmtProd = $pdo->prepare("SELECT thumbnail, images FROM products WHERE id = ?");
$stmtProd->execute([$id]);
$prodData = $stmtProd->fetch();
if ($prodData) {
    if (!empty($prodData['thumbnail'])) {
        @unlink(UPLOAD_PATH . '/products/' . $prodData['thumbnail']);
    }
    if (!empty($prodData['images'])) {
        $extraImages = json_decode($prodData['images'], true);
        if (is_array($extraImages)) {
            foreach ($extraImages as $img) {
                @unlink(UPLOAD_PATH . '/products/' . $img);
            }
        }
    }
}

// Xóa sản phẩm (CASCADE sẽ xóa variants, images, reviews, wishlist, cart items)
$pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);

setFlashMessage('success', 'Xóa sản phẩm thành công!');
redirect(url('admin/products/list.php'));
