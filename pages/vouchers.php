<?php
/** Trang danh sách voucher - WinK Shoe Store */
require_once dirname(__DIR__) . '/config/config.php';

$pdo = getDBConnection();

// Lấy danh sách voucher đang hoạt động
$stmt = $pdo->prepare("
    SELECT * 
    FROM vouchers 
    WHERE status = 'active' 
    AND start_date <= NOW() 
    AND end_date >= NOW()
    ORDER BY created_at DESC
");
$stmt->execute();
$vouchers = $stmt->fetchAll();

$pageTitle = 'Khuyến mãi - WinK Shoe Store';
$extraCSS = ['product.css'];

include dirname(__DIR__) . '/includes/header.php';
?>
<section class="section-padding" style="padding-top: 30px;">
    <div class="container">
        <h4 class="fw-bold mb-4"><i class="fas fa-tags me-2"></i>Mã giảm giá đang có (<?php echo count($vouchers); ?>)</h4>
        
        <?php if (!empty($vouchers)): ?>
        <div class="row g-4">
            <?php foreach ($vouchers as $voucher): ?>
            <div class="col-md-6 col-lg-4">
                <div class="voucher-card">
                    <div class="voucher-card-header">
                        <div class="voucher-code"><?php echo e($voucher['code']); ?></div>
                        <div class="voucher-discount">
                            <?php if ($voucher['discount_type'] === 'percentage'): ?>
                                Giảm <?php echo $voucher['discount_value']; ?>%
                            <?php else: ?>
                                Giảm <?php echo formatPrice($voucher['discount_value']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="voucher-card-body">
                        <h6 class="voucher-name"><?php echo e($voucher['name']); ?></h6>
                        <div class="voucher-info">
                            <div class="voucher-info-item">
                                <i class="fas fa-shopping-cart me-2"></i>
                                <span>Đơn tối thiểu: <?php echo formatPrice($voucher['min_order_amount']); ?></span>
                            </div>
                            <?php if ($voucher['max_discount']): ?>
                            <div class="voucher-info-item">
                                <i class="fas fa-arrow-up me-2"></i>
                                <span>Giảm tối đa: <?php echo formatPrice($voucher['max_discount']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="voucher-info-item">
                                <i class="fas fa-calendar me-2"></i>
                                <span>Hết hạn: <?php echo formatDate($voucher['end_date'], 'd/m/Y'); ?></span>
                            </div>
                            <div class="voucher-info-item">
                                <i class="fas fa-ticket-alt me-2"></i>
                                <span>
                                    <?php if ($voucher['usage_limit'] > 0): ?>
                                        Còn <?php echo $voucher['usage_limit'] - $voucher['used_count']; ?>/<?php echo $voucher['usage_limit']; ?> lượt
                                    <?php else: ?>
                                        Không giới hạn
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="voucher-card-footer">
                        <button class="btn-wink w-100 justify-content-center" onclick="copyVoucherCode('<?php echo e($voucher['code']); ?>')">
                            <i class="fas fa-copy me-1"></i>Sao chép mã
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-tags fa-4x text-muted mb-3"></i>
                <h5>Hiện không có mã giảm giá nào</h5>
                <p class="text-muted">Vui lòng quay lại sau để cập nhật các ưu đãi mới!</p>
                <a href="<?php echo url('pages/products.php'); ?>" class="btn-wink mt-2">Mua sắm ngay</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<style>
.voucher-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    overflow: hidden;
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.voucher-card-header {
    padding: 20px;
    text-align: center;
    border-bottom: 2px dashed rgba(255,255,255,0.3);
}

.voucher-code {
    font-size: 28px;
    font-weight: 700;
    letter-spacing: 2px;
    margin-bottom: 8px;
}

.voucher-discount {
    font-size: 18px;
    font-weight: 600;
}

.voucher-card-body {
    padding: 20px;
    background: rgba(255,255,255,0.1);
}

.voucher-name {
    font-weight: 600;
    margin-bottom: 15px;
    font-size: 16px;
}

.voucher-info-item {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
    font-size: 14px;
    opacity: 0.9;
}

.voucher-card-footer {
    padding: 15px 20px;
    background: rgba(0,0,0,0.1);
}

.voucher-card-footer .btn-wink {
    color: #667eea;
    border: none;
}

.voucher-card-footer .btn-wink:hover {
    color: #764ba2;
    background: linear-gradient(135deg, #ff5e00 0%, #ff8800 25%);;
}
</style>

<script>
function copyVoucherCode(code) {
    navigator.clipboard.writeText(code).then(() => {
        showToast('success', 'Đã sao chép mã: ' + code);
    }).catch(() => {
        // Fallback cho các trình duyệt cũ
        const textarea = document.createElement('textarea');
        textarea.value = code;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showToast('success', 'Đã sao chép mã: ' + code);
    });
}
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
