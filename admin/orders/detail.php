<?php
/**
 * Admin - Chi tiết Đơn hàng
 * WinK Shoe Store
 */
$pageTitle = 'Chi tiết Đơn hàng - WinK Admin';
include dirname(__DIR__) . '/includes/admin_header.php';

$pdo = getDBConnection();

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    setFlashMessage('error', 'ID đơn hàng không hợp lệ.');
    redirect(url('admin/orders/list.php'));
}

// Lấy thông tin đơn hàng
$stmt = $pdo->prepare("
    SELECT o.*, u.full_name, u.email, u.phone AS customer_phone
    FROM orders o
    INNER JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    setFlashMessage('error', 'Không tìm thấy đơn hàng.');
    redirect(url('admin/orders/list.php'));
}

// Lấy sản phẩm trong đơn
$itemsStmt = $pdo->prepare("
    SELECT oi.*, p.slug, p.thumbnail AS product_image
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
    ORDER BY oi.id
");
$itemsStmt->execute([$orderId]);
$items = $itemsStmt->fetchAll();

// Lịch sử trạng thái theo thứ tự
$statusFlow = ['pending', 'confirmed', 'shipping', 'delivered'];
$currentStatusIdx = array_search($order['status'], $statusFlow);

// Trạng thái có thể chuyển tiếp (chỉ đơn chưa hủy)
$nextStatuses = [];
if ($order['status'] !== 'cancelled' && $order['status'] !== 'delivered') {
    $nextIdx = $currentStatusIdx + 1;
    if ($nextIdx < count($statusFlow)) {
        $nextStatuses[] = $statusFlow[$nextIdx];
    }
    $nextStatuses[] = 'cancelled';
}
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">
                    <i class="fas fa-file-invoice mr-2" style="color:#f36811;"></i>
                    Đơn hàng: <strong><?php echo e($order['order_code']); ?></strong>
                </h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?php echo url('admin/index.php'); ?>">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo url('admin/orders/list.php'); ?>">Đơn hàng</a></li>
                    <li class="breadcrumb-item active"><?php echo e($order['order_code']); ?></li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">

        <!-- Timeline trạng thái đơn -->
        <div class="card shadow-sm mb-4">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap" style="gap:0;">
                    <?php
                    $allStatuses = [
                        'pending'   => ['label'=>'Chờ xác nhận', 'icon'=>'fa-clock'],
                        'confirmed' => ['label'=>'Đã xác nhận',  'icon'=>'fa-check'],
                        'shipping'  => ['label'=>'Đang giao',    'icon'=>'fa-truck'],
                        'delivered' => ['label'=>'Đã giao',      'icon'=>'fa-check-circle'],
                    ];
                    if ($order['status'] === 'cancelled') {
                        $allStatuses = [
                            'pending'   => ['label'=>'Chờ xác nhận', 'icon'=>'fa-clock'],
                            'cancelled' => ['label'=>'Đã huỷ',       'icon'=>'fa-times-circle'],
                        ];
                    }
                    $reached = true;
                    foreach ($allStatuses as $sKey => $sVal):
                        $isActive = ($order['status'] === $sKey);
                        $isPast   = $reached && !$isActive;
                        if ($isActive) $reached = false;
                    ?>
                    <div class="d-flex align-items-center">
                        <div class="text-center px-3 py-1" style="min-width:110px;">
                            <div style="
                                width:40px;height:40px;border-radius:50%;
                                margin:0 auto 6px;
                                display:flex;align-items:center;justify-content:center;
                                font-size:16px;
                                <?php if ($isActive): ?>
                                    background:#f36811;color:#fff;box-shadow:0 0 0 4px rgba(243,104,17,.2);
                                <?php elseif ($isPast): ?>
                                    background:#28a745;color:#fff;
                                <?php else: ?>
                                    background:#e9ecef;color:#adb5bd;
                                <?php endif; ?>
                            ">
                                <i class="fas <?php echo $sVal['icon']; ?>"></i>
                            </div>
                            <small style="font-size:11px;font-weight:<?php echo $isActive?'700':'400'; ?>;
                                          color:<?php echo $isActive?'#f36811':($isPast?'#28a745':'#adb5bd'); ?>;">
                                <?php echo $sVal['label']; ?>
                            </small>
                        </div>
                        <?php if ($sKey !== array_key_last($allStatuses)): ?>
                        <div style="flex:1;height:2px;min-width:30px;
                            background:<?php echo $isPast ? '#28a745' : '#e9ecef'; ?>;"></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                    <!-- Nút đổi trạng thái -->
                    <?php if (!empty($nextStatuses)): ?>
                    <div class="ml-auto pl-3">
                        <?php foreach ($nextStatuses as $ns): ?>
                            <?php if ($ns === 'cancelled'): ?>
                            <button class="btn btn-sm btn-outline-danger btn-update-status mr-1"
                                    data-id="<?php echo $order['id']; ?>"
                                    data-status="cancelled"
                                    data-label="huỷ">
                                <i class="fas fa-times mr-1"></i>Huỷ đơn
                            </button>
                            <?php else: ?>
                            <button class="btn btn-sm btn-primary btn-update-status"
                                    data-id="<?php echo $order['id']; ?>"
                                    data-status="<?php echo $ns; ?>"
                                    data-label="<?php echo getOrderStatusText($ns); ?>">
                                <i class="fas fa-arrow-right mr-1"></i>
                                Chuyển: <?php echo getOrderStatusText($ns); ?>
                            </button>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row">

            <!-- Cột trái: Sản phẩm + Tổng tiền -->
            <div class="col-lg-8">

                <!-- Sản phẩm trong đơn -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-shoe-prints mr-2"></i>
                            Sản phẩm (<?php echo count($items); ?>)
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead style="background:#f8f9fa;">
                                <tr>
                                    <th colspan="2">Sản phẩm</th>
                                    <th class="text-center">Đơn giá</th>
                                    <th class="text-center">SL</th>
                                    <th class="text-right">Thành tiền</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                <tr>
                                    <td style="width:70px;">
                                        <?php if (!empty($item['product_image'])): ?>
                                        <img src="<?php echo PRODUCT_UPLOAD_URL . '/' . e($item['product_image']); ?>"
                                             style="width:55px;height:55px;object-fit:cover;border-radius:8px;">
                                        <?php else: ?>
                                        <div style="width:55px;height:55px;border-radius:8px;background:#f0f0f0;
                                                    display:flex;align-items:center;justify-content:center;">
                                            <i class="fas fa-shoe-prints text-muted"></i>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong style="font-size:14px;"><?php echo e($item['product_name']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            Size: <?php echo e($item['size']); ?> |
                                            Màu: <?php echo e($item['color']); ?>
                                        </small>
                                    </td>
                                    <td class="text-center"><?php echo formatPrice($item['price']); ?></td>
                                    <td class="text-center">
                                        <span class="badge badge-light border px-2"><?php echo (int)$item['quantity']; ?></span>
                                    </td>
                                    <td class="text-right font-weight-bold" style="color:#f36811;">
                                        <?php echo formatPrice($item['subtotal']); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Tổng tiền -->
                    <div class="card-footer bg-white">
                        <div class="row justify-content-end">
                            <div class="col-md-5">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <td class="text-muted">Tạm tính:</td>
                                        <td class="text-right font-weight-bold"><?php echo formatPrice($order['subtotal']); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">
                                            Phí vận chuyển
                                            <small class="badge badge-light border ml-1">
                                                <?php echo $order['shipping_method'] === 'express' ? 'Nhanh' : 'Thường'; ?>
                                            </small>:
                                        </td>
                                        <td class="text-right font-weight-bold"><?php echo formatPrice($order['shipping_fee']); ?></td>
                                    </tr>
                                    <?php if ($order['discount_amount'] > 0): ?>
                                    <tr>
                                        <td class="text-muted">Giảm giá (Voucher):</td>
                                        <td class="text-right text-success font-weight-bold">
                                            -<?php echo formatPrice($order['discount_amount']); ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr style="border-top:2px solid #dee2e6;">
                                        <td class="font-weight-bold">Tổng cộng:</td>
                                        <td class="text-right font-weight-bold" style="font-size:18px;color:#f36811;">
                                            <?php echo formatPrice($order['total_amount']); ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Cột phải: Thông tin -->
            <div class="col-lg-4">

                <!-- Trạng thái đơn hàng -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-info-circle mr-2"></i>Thông tin đơn</h3></div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between py-2">
                                <span class="text-muted">Mã đơn</span>
                                <strong><?php echo e($order['order_code']); ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between py-2">
                                <span class="text-muted">Ngày đặt</span>
                                <span><?php echo formatDate($order['created_at'], 'd/m/Y H:i'); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between py-2">
                                <span class="text-muted">Trạng thái</span>
                                <span class="badge <?php echo getOrderStatusBadge($order['status']); ?> px-2" id="status-badge">
                                    <?php echo getOrderStatusText($order['status']); ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between py-2">
                                <span class="text-muted">Thanh toán</span>
                                <?php
                                $payClass2 = [
                                    'unpaid'   => 'badge-secondary',
                                    'paid'     => 'badge-success',
                                    'refunded' => 'badge-warning',
                                ][$order['payment_status']] ?? 'badge-secondary';
                                ?>
                                <span class="badge <?php echo $payClass2; ?> px-2">
                                    <?php echo getPaymentStatusText($order['payment_status']); ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between py-2">
                                <span class="text-muted">PT thanh toán</span>
                                <span><?php echo getPaymentMethodText($order['payment_method']); ?></span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Thông tin khách hàng -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-user mr-2"></i>Khách hàng</h3></div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item py-2">
                                <strong><?php echo e($order['full_name']); ?></strong>
                                <a href="<?php echo url('admin/customers/detail.php?id=' . $order['user_id']); ?>"
                                   class="btn btn-xs btn-outline-info float-right">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </li>
                            <li class="list-group-item d-flex justify-content-between py-2">
                                <span class="text-muted"><i class="fas fa-envelope mr-1"></i>Email</span>
                                <span style="font-size:13px;"><?php echo e($order['email']); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between py-2">
                                <span class="text-muted"><i class="fas fa-phone mr-1"></i>SĐT</span>
                                <span><?php echo e($order['customer_phone'] ?? '—'); ?></span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Địa chỉ giao hàng -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-map-marker-alt mr-2"></i>Địa chỉ giao hàng</h3></div>
                    <div class="card-body" style="font-size:14px;">
                        <strong><?php echo e($order['receiver_name']); ?></strong><br>
                        <i class="fas fa-phone fa-xs text-muted mr-1"></i><?php echo e($order['receiver_phone']); ?><br>
                        <i class="fas fa-map-pin fa-xs text-muted mr-1"></i>
                        <?php
                        $addrParts = array_filter([
                            $order['receiver_address']  ?? '',
                            $order['receiver_ward']     ?? '',
                            $order['receiver_province'] ?? '',
                        ]);
                        echo e(implode(', ', $addrParts));
                        ?>
                    </div>
                </div>

                <!-- Ghi chú -->
                <?php if (!empty($order['note'])): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-sticky-note mr-2"></i>Ghi chú</h3></div>
                    <div class="card-body" style="font-size:14px;">
                        <?php echo nl2br(e($order['note'])); ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>

    </div>
</section>

<!-- Toast thông báo -->
<div id="statusToast" style="
    position:fixed;bottom:24px;right:24px;z-index:9999;
    min-width:280px;padding:14px 20px;border-radius:10px;
    display:none;color:#fff;font-weight:600;box-shadow:0 4px 20px rgba(0,0,0,.2);">
</div>

<script>
// ── Cập nhật trạng thái đơn hàng ─────────────────────────────────
document.querySelectorAll('.btn-update-status').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const orderId = this.dataset.id;
        const status  = this.dataset.status;
        const label   = this.dataset.label;

        const msg = status === 'cancelled'
            ? 'Bạn có chắc muốn HUỶ đơn hàng này? Hành động không thể hoàn tác.'
            : 'Chuyển trạng thái đơn sang "' + label + '"?';

        if (!confirm(msg)) return;

        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Đang xử lý...';

        const formData = new FormData();
        formData.append('order_id', orderId);
        formData.append('status', status);

        fetch('<?php echo url('admin/orders/update_status.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToastAdmin(data.message, 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                showToastAdmin(data.message || 'Có lỗi xảy ra.', 'error');
                btn.disabled = false;
                btn.innerHTML = btn.dataset.originalHtml || 'Thử lại';
            }
        })
        .catch(() => {
            showToastAdmin('Lỗi kết nối máy chủ.', 'error');
            btn.disabled = false;
        });
    });
});

function showToastAdmin(msg, type) {
    const toast = document.getElementById('statusToast');
    toast.style.background = type === 'success' ? '#28a745' : '#dc3545';
    toast.textContent = msg;
    toast.style.display = 'block';
    setTimeout(() => { toast.style.display = 'none'; }, 3000);
}
</script>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
