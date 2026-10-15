<?php
/**
 * Order AJAX Actions - WinK Shoe Store
 */
require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập.', 'login_required' => true]);
    exit;
}

$pdo = getDBConnection();
$userId = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'cancel') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        
        if ($orderId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
            exit;
        }
        
        // Kiểm tra đơn hàng thuộc về user và có thể hủy
        $stmt = $pdo->prepare("
            SELECT id, status 
            FROM orders 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$orderId, $userId]);
        $order = $stmt->fetch();
        
        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Đơn hàng không tồn tại.']);
            exit;
        }
        
        if ($order['status'] !== 'pending') {
            echo json_encode(['success' => false, 'message' => 'Đơn hàng này không thể hủy.']);
            exit;
        }
        
        // Cập nhật trạng thái đơn hàng
        $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled', cancelled_at = NOW(), updated_at = NOW() WHERE id = ?");
        $stmt->execute([$orderId]);
        
        // Hoàn lại tồn kho cho các biến thể sản phẩm
        $itemsStmt = $pdo->prepare("SELECT variant_id, quantity FROM order_items WHERE order_id = ?");
        $itemsStmt->execute([$orderId]);
        $orderCancelItems = $itemsStmt->fetchAll();

        foreach ($orderCancelItems as $ci) {
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
            ")->execute([$ci['quantity'], $ci['quantity'], $ci['quantity'], $ci['variant_id']]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Đã hủy đơn hàng thành công.'
        ]);
        exit;
    }
    
    if ($action === 'reorder') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        
        if ($orderId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
            exit;
        }
        
        // Kiểm tra đơn hàng thuộc về user và trạng thái là cancelled hoặc delivered
        $stmt = $pdo->prepare("
            SELECT id, order_code, status 
            FROM orders 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$orderId, $userId]);
        $order = $stmt->fetch();
        
        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Đơn hàng không tồn tại.']);
            exit;
        }
        
        if (!in_array($order['status'], ['cancelled', 'delivered'])) {
            echo json_encode(['success' => false, 'message' => 'Chỉ có thể đặt lại đơn hàng đã hủy hoặc đã giao thành công.']);
            exit;
        }
        
        // Lấy các sản phẩm trong đơn hàng
        $stmt = $pdo->prepare("
            SELECT oi.*, 
                   p.id as prod_id, p.name as prod_name, p.status as prod_status, p.price as current_price, p.sale_price as current_sale_price,
                   pv.id as var_id, pv.stock_quantity as current_stock
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            LEFT JOIN product_variants pv ON oi.variant_id = pv.id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$orderId]);
        $orderItems = $stmt->fetchAll();
        
        if (empty($orderItems)) {
            echo json_encode(['success' => false, 'message' => 'Đơn hàng không có sản phẩm nào.']);
            exit;
        }
        
        // Lấy hoặc tạo giỏ hàng cho user
        $stmt = $pdo->prepare("SELECT id FROM carts WHERE user_id = ?");
        $stmt->execute([$userId]);
        $cart = $stmt->fetch();
        if (!$cart) {
            $stmt = $pdo->prepare("INSERT INTO carts (user_id) VALUES (?)");
            $stmt->execute([$userId]);
            $cartId = $pdo->lastInsertId();
        } else {
            $cartId = $cart['id'];
        }
        
        $addedCount = 0;
        $unavailableCount = 0;
        $outOfStockCount = 0;
        
        foreach ($orderItems as $item) {
            // Kiểm tra sản phẩm còn tồn tại và active
            if (!$item['prod_id'] || $item['prod_status'] !== 'active') {
                $unavailableCount++;
                continue;
            }
            
            $variantId = $item['var_id'];
            $stock = isset($item['current_stock']) ? (int)$item['current_stock'] : 0;
            
            // Nếu variant_id cũ không còn, thử tìm theo product_id + size + color
            if (!$variantId) {
                $stmtVar = $pdo->prepare("SELECT id, stock_quantity FROM product_variants WHERE product_id = ? AND size = ? AND color = ?");
                $stmtVar->execute([$item['product_id'], $item['size'], $item['color']]);
                $foundVar = $stmtVar->fetch();
                if ($foundVar) {
                    $variantId = $foundVar['id'];
                    $stock = (int)$foundVar['stock_quantity'];
                }
            }
            
            if (!$variantId) {
                $unavailableCount++;
                continue;
            }
            
            if ($stock <= 0) {
                $outOfStockCount++;
                continue;
            }
            
            // Đơn giá hiện tại
            $currentPrice = ($item['current_sale_price'] > 0) ? $item['current_sale_price'] : $item['current_price'];
            $orderQty = max(1, (int)$item['quantity']);
            
            // Kiểm tra trong giỏ hàng đã có biến thể này chưa
            $stmtCart = $pdo->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND variant_id = ?");
            $stmtCart->execute([$cartId, $variantId]);
            $existingCart = $stmtCart->fetch();
            
            if ($existingCart) {
                $currentCartQty = (int)$existingCart['quantity'];
                $newQty = min($currentCartQty + $orderQty, $stock);
                if ($newQty > $currentCartQty) {
                    $stmtUpd = $pdo->prepare("UPDATE cart_items SET quantity = ?, price = ?, updated_at = NOW() WHERE id = ?");
                    $stmtUpd->execute([$newQty, $currentPrice, $existingCart['id']]);
                    $addedCount++;
                } else {
                    // Đã có số lượng tối đa trong giỏ
                    $addedCount++;
                }
            } else {
                $qtyToAdd = min($orderQty, $stock);
                $stmtIns = $pdo->prepare("
                    INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, price, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmtIns->execute([$cartId, $item['product_id'], $variantId, $qtyToAdd, $currentPrice]);
                $addedCount++;
            }
        }
        
        // Lấy tổng số lượng sản phẩm trong giỏ
        $stmtTotal = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE cart_id = ?");
        $stmtTotal->execute([$cartId]);
        $totalCartCount = (int)$stmtTotal->fetchColumn();
        
        if ($addedCount === 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Các sản phẩm trong đơn hàng hiện đã hết hàng hoặc ngừng kinh doanh.'
            ]);
            exit;
        }
        
        $msg = "Đã thêm sản phẩm từ đơn hàng #" . $order['order_code'] . " vào giỏ hàng!";
        if ($outOfStockCount > 0 || $unavailableCount > 0) {
            $msg .= " (Một số sản phẩm tạm thời hết hàng hoặc ngừng bán).";
        }
        
        setFlashMessage('success', $msg);
        
        echo json_encode([
            'success' => true,
            'message' => $msg,
            'cart_count' => $totalCartCount,
            'redirect_url' => url('pages/cart.php')
        ]);
        exit;
    }
}

