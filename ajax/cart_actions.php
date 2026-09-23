<?php
/**
 * Cart AJAX Actions - WinK Shoe Store
 * Xử lý thêm/sửa/xóa giỏ hàng qua AJAX
 */
require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');

// Kiểm tra đăng nhập
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập.', 'login_required' => true]);
    exit;
}

$pdo = getDBConnection();
$userId = getCurrentUserId();

// GET: Lấy giỏ hàng
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_cart') {
    // Lấy hoặc tạo giỏ hàng
    $stmt = $pdo->prepare("SELECT id FROM carts WHERE user_id = ?");
    $stmt->execute([$userId]);
    $cart = $stmt->fetch();
    
    if (!$cart) {
        echo json_encode(['success' => true, 'items' => [], 'total_items' => 0, 'total_amount' => 0]);
        exit;
    }
    
    // Lấy sản phẩm trong giỏ
    $stmt = $pdo->prepare("
        SELECT ci.id, ci.quantity, ci.price,
               p.id as product_id, p.name, p.slug,
               pv.size, pv.color, pv.stock_quantity as max_qty,
               pi.image_path
        FROM cart_items ci
        INNER JOIN products p ON ci.product_id = p.id
        INNER JOIN product_variants pv ON ci.variant_id = pv.id
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
        WHERE ci.cart_id = ?
        ORDER BY ci.created_at DESC
    ");
    $stmt->execute([$cart['id']]);
    $items = $stmt->fetchAll();
    
    $totalItems = 0;
    $totalAmount = 0;
    $formattedItems = [];
    
    foreach ($items as $item) {
        $totalItems += $item['quantity'];
        $totalAmount += $item['price'] * $item['quantity'];
        
        $formattedItems[] = [
            'id' => $item['id'],
            'product_id' => $item['product_id'],
            'name' => $item['name'],
            'size' => $item['size'],
            'color' => $item['color'],
            'quantity' => $item['quantity'],
            'price' => (int)$item['price'],
            'max_qty' => $item['max_qty'],
            'image' => !empty($item['image_path']) 
                ? PRODUCT_UPLOAD_URL . '/' . $item['image_path'] 
                : asset('images/default/no-product.png')
        ];
    }
    
    echo json_encode([
        'success' => true,
        'items' => $formattedItems,
        'total_items' => $totalItems,
        'total_amount' => (int)$totalAmount
    ]);
    exit;
}

// POST: Các thao tác với giỏ hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            addToCart($pdo, $userId);
            break;
        case 'update':
            updateCartItem($pdo, $userId);
            break;
        case 'remove':
            removeCartItem($pdo, $userId);
            break;
        case 'clear':
            clearCart($pdo, $userId);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ.']);
    }
    exit;
}

/**
 * Thêm sản phẩm vào giỏ hàng
 */
function addToCart($pdo, $userId) {
    $productId = (int)($_POST['product_id'] ?? 0);
    $variantId = (int)($_POST['variant_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);
    
    if ($productId <= 0 || $variantId <= 0 || $quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
        return;
    }
    
    // Kiểm tra sản phẩm tồn tại
    $stmt = $pdo->prepare("SELECT id, name, price, sale_price FROM products WHERE id = ? AND status = 'active'");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Sản phẩm không tồn tại.']);
        return;
    }
    
    // Kiểm tra biến thể và tồn kho
    $stmt = $pdo->prepare("SELECT id, stock_quantity FROM product_variants WHERE id = ? AND product_id = ?");
    $stmt->execute([$variantId, $productId]);
    $variant = $stmt->fetch();
    
    if (!$variant) {
        echo json_encode(['success' => false, 'message' => 'Biến thể sản phẩm không tồn tại.']);
        return;
    }
    
    if ($variant['stock_quantity'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'Sản phẩm đã hết hàng.']);
        return;
    }
    
    // Lấy hoặc tạo giỏ hàng
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
    
    // Kiểm tra đã có trong giỏ chưa (cùng biến thể)
    $stmt = $pdo->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND variant_id = ?");
    $stmt->execute([$cartId, $variantId]);
    $existingItem = $stmt->fetch();
    
    $price = $product['sale_price'] ?: $product['price'];
    
    if ($existingItem) {
        // Cùng biến thể → tăng số lượng
        $newQuantity = $existingItem['quantity'] + $quantity;
        
        if ($newQuantity > $variant['stock_quantity']) {
            echo json_encode(['success' => false, 'message' => 'Số lượng vượt quá tồn kho (còn ' . $variant['stock_quantity'] . ' sản phẩm).']);
            return;
        }
        
        $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ?, price = ? WHERE id = ?");
        $stmt->execute([$newQuantity, $price, $existingItem['id']]);
    } else {
        // Biến thể mới → thêm dòng mới
        if ($quantity > $variant['stock_quantity']) {
            echo json_encode(['success' => false, 'message' => 'Số lượng vượt quá tồn kho (còn ' . $variant['stock_quantity'] . ' sản phẩm).']);
            return;
        }
        
        $stmt = $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, price) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$cartId, $productId, $variantId, $quantity, $price]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Đã thêm "' . $product['name'] . '" vào giỏ hàng!']);
}

/**
 * Cập nhật số lượng sản phẩm trong giỏ
 */
function updateCartItem($pdo, $userId) {
    $cartItemId = (int)($_POST['cart_item_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    
    if ($cartItemId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
        return;
    }
    
    // Kiểm tra item thuộc giỏ hàng của user
    $stmt = $pdo->prepare("
        SELECT ci.id, ci.variant_id, pv.stock_quantity 
        FROM cart_items ci 
        INNER JOIN carts c ON ci.cart_id = c.id 
        INNER JOIN product_variants pv ON ci.variant_id = pv.id
        WHERE ci.id = ? AND c.user_id = ?
    ");
    $stmt->execute([$cartItemId, $userId]);
    $item = $stmt->fetch();
    
    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Sản phẩm không tồn tại trong giỏ hàng.']);
        return;
    }
    
    if ($quantity <= 0) {
        // Xóa nếu số lượng = 0
        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id = ?");
        $stmt->execute([$cartItemId]);
        echo json_encode(['success' => true, 'message' => 'Đã xóa sản phẩm.']);
        return;
    }
    
    if ($quantity > $item['stock_quantity']) {
        echo json_encode(['success' => false, 'message' => 'Số lượng vượt quá tồn kho (còn ' . $item['stock_quantity'] . ' sản phẩm).']);
        return;
    }
    
    $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
    $stmt->execute([$quantity, $cartItemId]);
    
    echo json_encode(['success' => true, 'message' => 'Cập nhật thành công.']);
}

/**
 * Xóa sản phẩm khỏi giỏ hàng
 */
function removeCartItem($pdo, $userId) {
    $cartItemId = (int)($_POST['cart_item_id'] ?? 0);
    
    // Kiểm tra item thuộc giỏ hàng của user
    $stmt = $pdo->prepare("
        SELECT ci.id FROM cart_items ci 
        INNER JOIN carts c ON ci.cart_id = c.id 
        WHERE ci.id = ? AND c.user_id = ?
    ");
    $stmt->execute([$cartItemId, $userId]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Sản phẩm không tồn tại.']);
        return;
    }
    
    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id = ?");
    $stmt->execute([$cartItemId]);
    
    echo json_encode(['success' => true, 'message' => 'Đã xóa sản phẩm khỏi giỏ hàng.']);
}

/**
 * Xóa toàn bộ giỏ hàng
 */
function clearCart($pdo, $userId) {
    $stmt = $pdo->prepare("
        DELETE ci FROM cart_items ci 
        INNER JOIN carts c ON ci.cart_id = c.id 
        WHERE c.user_id = ?
    ");
    $stmt->execute([$userId]);
    
    echo json_encode(['success' => true, 'message' => 'Đã xóa toàn bộ giỏ hàng.']);
}
