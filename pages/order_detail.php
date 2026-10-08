<?php
/** Trang chi tiết đơn hàng - WinK Shoe Store */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$pdo = getDBConnection();
$userId = getCurrentUserId();
$orderId = (int) ($_GET['id'] ?? 0);

if ($orderId <= 0) {
    setFlashMessage('error', 'Đơn hàng không tồn tại.');
    redirect(url('pages/orders.php'));
}

// Lấy thông tin đơn hàng
$stmt = $pdo->prepare('
    SELECT o.*, v.code as voucher_code, v.discount_type, v.discount_value
    FROM orders o
    LEFT JOIN vouchers v ON o.voucher_id = v.id
    WHERE o.id = ? AND o.user_id = ?
');
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) {
    setFlashMessage('error', 'Đơn hàng không tồn tại.');
    redirect(url('pages/orders.php'));
}

// Lấy chi tiết sản phẩm trong đơn hàng
$stmtItems = $pdo->prepare('
    SELECT oi.*, p.thumbnail as product_image, p.slug as product_slug
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
    ORDER BY oi.id
');
$stmtItems->execute([$orderId]);
$orderItems = $stmtItems->fetchAll();

$pageTitle = 'Chi tiết đơn hàng ' . e($order['order_code']) . ' - WinK Shoe Store';
$extraCSS = ['product.css'];

include dirname(__DIR__) . '/includes/header.php';
?>

<section class="section-padding" style="padding-top: 30px;">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0"><i class="fas fa-file-invoice me-2"></i>Chi tiết đơn hàng <?php echo e($order['order_code']); ?></h4>
            <span class="badge <?php echo getOrderStatusBadge($order['status']); ?> fs-6">
                <?php echo getOrderStatusText($order['status']); ?>
            </span>
        </div>
        
        <div class="row g-4">
            <!-- Order Info -->
            <div class="col-lg-8">
                <div class="bg-white rounded-3 shadow-sm p-4 mb-4">
                    <h6 class="fw-bold mb-3"><i class="fas fa-info-circle me-2"></i>Thông tin đơn hàng</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">Mã đơn hàng:</small>
                            <div class="fw-bold"><?php echo e($order['order_code']); ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">Ngày đặt:</small>
                            <div><?php echo formatDate($order['created_at'], 'd/m/Y H:i'); ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">Phương thức thanh toán:</small>
                            <div><?php echo getPaymentMethodText($order['payment_method']); ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">Trạng thái thanh toán:</small>
                            <div>
                                <span class="badge <?php echo $order['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                    <?php echo getPaymentStatusText($order['payment_status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Shipping Info -->
                <div class="bg-white rounded-3 shadow-sm p-4 mb-4">
                    <h6 class="fw-bold mb-3"><i class="fas fa-truck me-2"></i>Thông tin giao hàng</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">Người nhận:</small>
                            <div class="fw-bold"><?php echo e($order['receiver_name']); ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">Số điện thoại:</small>
                            <div><?php echo e($order['receiver_phone']); ?></div>
                        </div>
                        <div class="col-12 mb-3">
                            <small class="text-muted">Địa chỉ:</small>
                            <div><?php echo e($order['receiver_address']); ?></div>
                            <div class="text-muted"><?php echo e($order['receiver_ward'] . ', ' . $order['receiver_province']); ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">Phương thức vận chuyển:</small>
                            <div><?php echo $order['shipping_method'] === 'express' ? 'Giao hàng hỏa tốc' : 'Giao hàng tiêu chuẩn'; ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Order Items -->
                <div class="bg-white rounded-3 shadow-sm p-4">
                    <h6 class="fw-bold mb-3"><i class="fas fa-shopping-bag me-2"></i>Sản phẩm đã đặt</h6>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Sản phẩm</th>
                                    <th>Giá</th>
                                    <th>Số lượng</th>
                                    <th>Thành tiền</th>
                                    <?php if ($order['status'] === 'delivered'): ?>
                                    <th class="text-center">Đánh giá</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <a href="<?php echo url('pages/product_detail.php?slug=' . e($item['product_slug'] ?? '')); ?>">
                                                <img src="<?php echo !empty($item['product_image']) ? PRODUCT_UPLOAD_URL . '/' . e($item['product_image']) : asset('images/default/no-product.png'); ?>" 
                                                     style="width:60px;height:60px;object-fit:cover;border-radius:8px;">
                                            </a>
                                            <div>
                                                <div class="fw-bold"><?php echo e($item['product_name']); ?></div>
                                                <small class="text-muted">Size: <?php echo e($item['size']); ?> | Màu: <?php echo e($item['color']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo formatPrice($item['price']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td class="fw-bold"><?php echo formatPrice($item['subtotal']); ?></td>
                                    <?php if ($order['status'] === 'delivered'): ?>
                                    <td class="text-center">
                                        <?php if ($item['is_reviewed']): ?>
                                            <span class="badge bg-success"><i class="fas fa-check me-1"></i>Đã đánh giá</span>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-warning fw-bold"
                                                    onclick="openReviewModal(<?php echo $item['id']; ?>, '<?php echo e($item['product_name']); ?>', '<?php echo !empty($item['product_image']) ? PRODUCT_UPLOAD_URL . '/' . e($item['product_image']) : asset('images/default/no-product.png'); ?>')"
                                                    style="font-size:12px;">
                                                <i class="fas fa-star me-1"></i>Viết đánh giá
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="bg-white rounded-3 shadow-sm p-4" style="position:sticky;top:90px;">
                    <h6 class="fw-bold mb-3"><i class="fas fa-calculator me-2"></i>Tóm tắt đơn hàng</h6>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tạm tính:</span>
                        <span><?php echo formatPrice($order['subtotal']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Phí vận chuyển:</span>
                        <span><?php echo formatPrice($order['shipping_fee']); ?></span>
                    </div>
                    <?php if ($order['discount_amount'] > 0): ?>
                    <div class="d-flex justify-content-between mb-2 text-success">
                        <span>Giảm giá:</span>
                        <span>-<?php echo formatPrice($order['discount_amount']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($order['voucher_code'])): ?>
                    <div class="d-flex justify-content-between mb-2 text-muted">
                        <small>Mã voucher:</small>
                        <small><?php echo e($order['voucher_code']); ?></small>
                    </div>
                    <?php endif; ?>
                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <strong>Tổng cộng:</strong>
                        <strong style="color:var(--primary);font-size:20px;"><?php echo formatPrice($order['total_amount']); ?></strong>
                    </div>
                    
                    <?php if (!empty($order['note'])): ?>
                    <div class="mt-3 pt-3" style="border-top:1px solid var(--gray-200);">
                        <small class="text-muted">Ghi chú:</small>
                        <div class="mt-1"><?php echo nl2br(e($order['note'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($order['status'] === 'pending'): ?>
                    <a href="javascript:void(0)" onclick="if(confirm('Bạn có chắc chắn muốn hủy đơn hàng này?')) cancelOrder(<?php echo $order['id']; ?>)" 
                       class="btn btn-outline-danger w-100 mt-3">
                        <i class="fas fa-times me-1"></i>Hủy đơn hàng
                    </a>
                    <?php endif; ?>

                    <?php if ($order['payment_status'] === 'unpaid' && $order['status'] === 'pending' && in_array($order['payment_method'], ['vnpay', 'momo'])): ?>
                    <a href="<?php echo url('payment/' . $order['payment_method'] . '_create.php?order_id=' . $order['id']); ?>" 
                       class="btn btn-warning w-100 mt-2 fw-bold">
                        <i class="fas fa-redo me-1"></i>Thanh toán lại
                    </a>
                    <?php endif; ?>

                    <a href="<?php echo url('pages/products.php'); ?>" class="btn-wink-outline w-100 justify-content-center mt-2">
                        <i class="fas fa-shopping-bag me-1"></i>Tiếp tục mua sắm
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════
     MODAL ĐÁNH GIÁ SẢN PHẨM
═══════════════════════════════════════════════════════ -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;border:none;">
            <div class="modal-header" style="background:linear-gradient(135deg,#f36811,#ff8c42);border:none;">
                <h5 class="modal-title text-white fw-bold" id="reviewModalLabel">
                    <i class="fas fa-star me-2"></i>Viết đánh giá
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Product preview -->
                <div class="d-flex align-items-center gap-3 mb-4 p-3" style="background:#fff8f3;border-radius:12px;border:1px solid #ffe0cc;">
                    <img id="reviewProductImg" src="" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:10px;">
                    <div>
                        <div class="fw-bold" id="reviewProductName" style="color:#1a1a2e;"></div>
                        <small class="text-muted">Chia sẻ trải nghiệm của bạn về sản phẩm này</small>
                    </div>
                </div>

                <form id="reviewForm" enctype="multipart/form-data">
                    <input type="hidden" id="reviewOrderItemId" name="order_item_id" value="">

                    <!-- Star Rating -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold mb-2">Đánh giá của bạn <span class="text-danger">*</span></label>
                        <div class="star-picker d-flex gap-1" id="starPicker">
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                            <button type="button" class="star-btn" data-val="<?php echo $s; ?>" onclick="setRating(<?php echo $s; ?>)">
                                <i class="fas fa-star"></i>
                            </button>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="rating" id="ratingInput" value="0">
                        <small id="ratingLabel" class="text-muted mt-1 d-block">Chọn số sao</small>
                    </div>

                    <!-- Content -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Nhận xét <span class="text-muted fw-normal" style="font-size:13px;">(tuỳ chọn)</span></label>
                        <textarea name="content" id="reviewContent" class="form-control"
                                  rows="4" placeholder="Hãy chia sẻ cảm nhận của bạn về chất lượng, kiểu dáng, độ êm ái..."
                                  style="border-radius:10px;resize:none;"></textarea>
                    </div>

                    <!-- Image Upload -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Ảnh đính kèm <span class="text-muted fw-normal" style="font-size:13px;">(tuỳ chọn, tối đa 5MB)</span></label>
                        <div class="review-upload-area" id="reviewUploadArea" onclick="document.getElementById('reviewImageInput').click()">
                            <input type="file" name="review_image" id="reviewImageInput" accept="image/*" style="display:none;" onchange="previewReviewImage(this)">
                            <div id="reviewUploadPlaceholder">
                                <i class="fas fa-camera fa-2x mb-2" style="color:#f36811;"></i>
                                <p class="mb-0">Nhấn để thêm ảnh</p>
                                <small class="text-muted">JPG, PNG, WebP</small>
                            </div>
                            <img id="reviewImagePreview" src="" alt="" style="display:none;max-width:100%;max-height:180px;border-radius:10px;object-fit:cover;">
                        </div>
                        <button type="button" id="removeReviewImage" class="btn btn-sm btn-outline-danger mt-2" style="display:none;" onclick="removeReviewImg()">
                            <i class="fas fa-trash me-1"></i>Xóa ảnh
                        </button>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="border-top:1px solid #f0f0f0;">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Hủy bỏ</button>
                <button type="button" class="btn btn-warning fw-bold px-4" id="submitReviewBtn" onclick="submitReview()">
                    <i class="fas fa-paper-plane me-2"></i>Gửi đánh giá
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* ── Star Picker ── */
.star-picker { display:flex; gap:6px; }
.star-btn {
    background:none; border:none; padding:4px;
    font-size:32px; color:#ddd; cursor:pointer;
    transition:color .15s, transform .15s;
    line-height:1;
}
.star-btn:hover, .star-btn.active { color:#ffc107; }
.star-btn:hover { transform: scale(1.15); }

/* ── Upload Area ── */
.review-upload-area {
    border: 2px dashed #f36811;
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    cursor: pointer;
    background: #fff8f3;
    transition: background .2s, border-color .2s;
    min-height: 120px;
    display:flex; align-items:center; justify-content:center; flex-direction:column;
}
.review-upload-area:hover { background:#fff0e6; border-color:#d95a0a; }
</style>

<script>
// ── Review Modal ────────────────────────────────────────
let currentRating = 0;
const ratingLabels = ['', 'Rất tệ', 'Không hài lòng', 'Bình thường', 'Hài lòng', 'Rất hài lòng ✨'];

function openReviewModal(orderItemId, productName, productImg) {
    document.getElementById('reviewOrderItemId').value = orderItemId;
    document.getElementById('reviewProductName').textContent = productName;
    document.getElementById('reviewProductImg').src = productImg;
    // Reset
    setRating(0);
    document.getElementById('reviewContent').value = '';
    removeReviewImg();
    new bootstrap.Modal(document.getElementById('reviewModal')).show();
}

function setRating(val) {
    currentRating = val;
    document.getElementById('ratingInput').value = val;
    document.getElementById('ratingLabel').textContent = val > 0 ? ratingLabels[val] : 'Chọn số sao';
    document.querySelectorAll('.star-btn').forEach((btn, i) => {
        btn.classList.toggle('active', i < val);
    });
}

function previewReviewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('reviewImagePreview').src = e.target.result;
            document.getElementById('reviewImagePreview').style.display = 'block';
            document.getElementById('reviewUploadPlaceholder').style.display = 'none';
            document.getElementById('removeReviewImage').style.display = 'inline-flex';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function removeReviewImg() {
    document.getElementById('reviewImageInput').value = '';
    document.getElementById('reviewImagePreview').src = '';
    document.getElementById('reviewImagePreview').style.display = 'none';
    document.getElementById('reviewUploadPlaceholder').style.display = 'flex';
    document.getElementById('reviewUploadPlaceholder').style.flexDirection = 'column';
    document.getElementById('removeReviewImage').style.display = 'none';
}

function submitReview() {
    if (currentRating === 0) {
        showToast('warning', 'Vui lòng chọn số sao đánh giá.');
        return;
    }

    const btn = document.getElementById('submitReviewBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang gửi...';

    const formData = new FormData(document.getElementById('reviewForm'));
    formData.append('action', 'submit_review');

    fetch(BASE_URL + '/ajax/review_actions.php', { method:'POST', body:formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('reviewModal')).hide();
                showToast('success', data.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('error', data.message || 'Có lỗi xảy ra.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Gửi đánh giá';
            }
        })
        .catch(() => {
            showToast('error', 'Có lỗi xảy ra. Vui lòng thử lại.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Gửi đánh giá';
        });
}
</script>

<script>
function cancelOrder(orderId) {
    const formData = new FormData();
    formData.append('action', 'cancel');
    formData.append('order_id', orderId);
    
    fetch(BASE_URL + '/ajax/order_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Đã hủy đơn hàng thành công.');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('error', data.message || 'Không thể hủy đơn hàng.');
        }
    })
    .catch(error => {
        console.error('Lỗi:', error);
        showToast('error', 'Có lỗi xảy ra. Vui lòng thử lại.');
    });
}
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
