<?php
/**
 * Admin - Sửa voucher
 */
require_once dirname(dirname(__DIR__)) . '/includes/admin_check.php';

$pageTitle = 'Sửa voucher - WinK Admin';

$pdo    = getDBConnection();
$id     = (int)($_GET['id'] ?? 0);
$errors = [];

if ($id <= 0) {
    setFlashMessage('error', 'ID voucher không hợp lệ.');
    redirect(url('admin/vouchers/list.php'));
}

// Lấy voucher
$stmt = $pdo->prepare("SELECT * FROM vouchers WHERE id = ?");
$stmt->execute([$id]);
$voucher = $stmt->fetch();

if (!$voucher) {
    setFlashMessage('error', 'Voucher không tồn tại.');
    redirect(url('admin/vouchers/list.php'));
}

// Số đơn đã dùng voucher này
$stmtOrders = $pdo->prepare("
    SELECT COUNT(*) as cnt, SUM(discount_amount) as total_discount
    FROM orders WHERE voucher_id = ?
");
$stmtOrders->execute([$id]);
$voucherStats = $stmtOrders->fetch();

$old = $voucher;
// Chuẩn hóa định dạng datetime cho input
$old['start_date'] = date('Y-m-d\TH:i', strtotime($voucher['start_date']));
$old['end_date']   = date('Y-m-d\TH:i', strtotime($voucher['end_date']));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'id'               => $id,
        'code'             => strtoupper(trim($_POST['code']            ?? '')),
        'name'             => trim($_POST['name']                       ?? ''),
        'discount_type'    => in_array($_POST['discount_type'] ?? '', ['percentage','fixed']) ? $_POST['discount_type'] : 'percentage',
        'discount_value'   => (float)($_POST['discount_value']          ?? 0),
        'max_discount'     => !empty($_POST['max_discount'])   ? (float)$_POST['max_discount']   : null,
        'min_order_amount' => (float)($_POST['min_order_amount']        ?? 0),
        'usage_limit'      => (int)($_POST['usage_limit']               ?? 0),
        'start_date'       => trim($_POST['start_date']                 ?? ''),
        'end_date'         => trim($_POST['end_date']                   ?? ''),
        'status'           => in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active',
        'used_count'       => $voucher['used_count'],
    ];

    // Validate
    if (empty($old['code'])) {
        $errors['code'] = 'Vui lòng nhập mã voucher.';
    } elseif (!preg_match('/^[A-Z0-9_\-]{3,30}$/', $old['code'])) {
        $errors['code'] = 'Mã voucher chỉ gồm chữ in hoa, số, dấu _ hoặc - (3–30 ký tự).';
    } else {
        $check = $pdo->prepare("SELECT id FROM vouchers WHERE code = ? AND id != ?");
        $check->execute([$old['code'], $id]);
        if ($check->fetch()) $errors['code'] = 'Mã voucher này đã được dùng cho voucher khác.';
    }

    if (empty($old['name'])) {
        $errors['name'] = 'Vui lòng nhập tên chương trình.';
    }

    if ($old['discount_value'] <= 0) {
        $errors['discount_value'] = 'Giá trị giảm phải lớn hơn 0.';
    } elseif ($old['discount_type'] === 'percentage' && $old['discount_value'] > 100) {
        $errors['discount_value'] = 'Tỷ lệ phần trăm không được vượt quá 100%.';
    }

    if ($old['usage_limit'] < 0) {
        $errors['usage_limit'] = 'Giới hạn lượt dùng không hợp lệ.';
    }
    if ($old['usage_limit'] > 0 && $old['usage_limit'] < $voucher['used_count']) {
        $errors['usage_limit'] = 'Giới hạn không được nhỏ hơn số lượt đã dùng (' . $voucher['used_count'] . ').';
    }

    if (empty($old['start_date'])) $errors['start_date'] = 'Vui lòng chọn ngày bắt đầu.';
    if (empty($old['end_date']))   $errors['end_date']   = 'Vui lòng chọn ngày kết thúc.';
    if (!empty($old['start_date']) && !empty($old['end_date']) && $old['end_date'] <= $old['start_date']) {
        $errors['end_date'] = 'Ngày kết thúc phải sau ngày bắt đầu.';
    }

    if (empty($errors)) {
        $startDateDb = date('Y-m-d H:i:s', strtotime($old['start_date']));
        $endDateDb   = date('Y-m-d H:i:s', strtotime($old['end_date']));

        $stmtUpd = $pdo->prepare("
            UPDATE vouchers
            SET code=?, name=?, discount_type=?, discount_value=?, max_discount=?,
                min_order_amount=?, usage_limit=?, start_date=?, end_date=?, status=?
            WHERE id=?
        ");
        $stmtUpd->execute([
            $old['code'], $old['name'], $old['discount_type'], $old['discount_value'],
            $old['max_discount'], $old['min_order_amount'], $old['usage_limit'],
            $startDateDb, $endDateDb, $old['status'], $id
        ]);

        setFlashMessage('success', 'Cập nhật voucher <strong>' . e($old['code']) . '</strong> thành công!');
        redirect(url('admin/vouchers/edit.php?id=' . $id));
    }
}

include dirname(__DIR__) . '/includes/admin_header.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1><i class="fas fa-edit mr-2 text-primary"></i>Sửa voucher: <code><?php echo e($voucher['code']); ?></code></h1>
            </div>
            <div class="col-sm-6">
                <a href="<?php echo url('admin/vouchers/list.php'); ?>" class="btn btn-secondary float-sm-right">
                    <i class="fas fa-arrow-left mr-1"></i> Quay lại
                </a>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <form method="POST" id="voucherForm">
            <div class="row">

                <!-- Cột trái -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-info-circle mr-2"></i>Thông tin voucher</h3>
                        </div>
                        <div class="card-body">

                            <!-- Mã voucher -->
                            <div class="form-group">
                                <label>Mã voucher <span class="text-danger">*</span></label>
                                <input type="text" name="code" id="codeInput"
                                       class="form-control <?php echo !empty($errors['code']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo e($old['code']); ?>"
                                       maxlength="30"
                                       style="text-transform:uppercase; font-family:monospace; font-weight:600; letter-spacing:2px;">
                                <?php if (!empty($errors['code'])): ?>
                                    <div class="invalid-feedback"><?php echo e($errors['code']); ?></div>
                                <?php endif; ?>
                                <?php if ($voucher['used_count'] > 0): ?>
                                    <small class="text-warning">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        Voucher này đã được dùng <?php echo $voucher['used_count']; ?> lần. Thay đổi mã có thể ảnh hưởng đến lịch sử.
                                    </small>
                                <?php endif; ?>
                            </div>

                            <!-- Tên chương trình -->
                            <div class="form-group">
                                <label>Tên chương trình <span class="text-danger">*</span></label>
                                <input type="text" name="name"
                                       class="form-control <?php echo !empty($errors['name']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo e($old['name']); ?>">
                                <?php if (!empty($errors['name'])): ?>
                                    <div class="invalid-feedback"><?php echo e($errors['name']); ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Loại giảm giá -->
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Loại giảm giá <span class="text-danger">*</span></label>
                                        <select name="discount_type" class="form-control" id="discountType" onchange="toggleDiscountType()">
                                            <option value="percentage" <?php echo $old['discount_type'] === 'percentage' ? 'selected' : ''; ?>>Phần trăm (%)</option>
                                            <option value="fixed"      <?php echo $old['discount_type'] === 'fixed'      ? 'selected' : ''; ?>>Số tiền cố định (đ)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label id="discountValueLabel">
                                            <?php echo $old['discount_type'] === 'percentage' ? 'Tỷ lệ giảm (%)' : 'Số tiền giảm (đ)'; ?>
                                            <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <input type="number" name="discount_value" id="discountValue"
                                                   class="form-control <?php echo !empty($errors['discount_value']) ? 'is-invalid' : ''; ?>"
                                                   value="<?php echo e($old['discount_value']); ?>"
                                                   min="1" step="<?php echo $old['discount_type'] === 'percentage' ? '1' : '1000'; ?>">
                                            <div class="input-group-append">
                                                <span class="input-group-text" id="discountUnit">
                                                    <?php echo $old['discount_type'] === 'percentage' ? '%' : 'đ'; ?>
                                                </span>
                                            </div>
                                            <?php if (!empty($errors['discount_value'])): ?>
                                                <div class="invalid-feedback"><?php echo e($errors['discount_value']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4" id="maxDiscountGroup" style="<?php echo $old['discount_type'] !== 'percentage' ? 'display:none;' : ''; ?>">
                                    <div class="form-group">
                                        <label>Giảm tối đa (đ) <small class="text-muted">(để trống = không giới hạn)</small></label>
                                        <input type="number" name="max_discount"
                                               class="form-control"
                                               value="<?php echo e($old['max_discount'] ?? ''); ?>"
                                               min="0" step="1000">
                                    </div>
                                </div>
                            </div>

                            <!-- Đơn tối thiểu & Giới hạn lượt -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Giá trị đơn tối thiểu (đ)</label>
                                        <input type="number" name="min_order_amount"
                                               class="form-control"
                                               value="<?php echo e($old['min_order_amount']); ?>"
                                               min="0" step="1000">
                                        <small class="text-muted">Nhập 0 nếu không yêu cầu</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Giới hạn lượt sử dụng</label>
                                        <input type="number" name="usage_limit"
                                               class="form-control <?php echo !empty($errors['usage_limit']) ? 'is-invalid' : ''; ?>"
                                               value="<?php echo e($old['usage_limit']); ?>"
                                               min="0" step="1">
                                        <?php if (!empty($errors['usage_limit'])): ?>
                                            <div class="invalid-feedback"><?php echo e($errors['usage_limit']); ?></div>
                                        <?php endif; ?>
                                        <small class="text-muted">
                                            Đã dùng: <strong class="text-primary"><?php echo $voucher['used_count']; ?></strong> lần.
                                            Nhập 0 = không giới hạn.
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Thời gian -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Ngày bắt đầu <span class="text-danger">*</span></label>
                                        <input type="datetime-local" name="start_date"
                                               class="form-control <?php echo !empty($errors['start_date']) ? 'is-invalid' : ''; ?>"
                                               value="<?php echo e($old['start_date']); ?>">
                                        <?php if (!empty($errors['start_date'])): ?>
                                            <div class="invalid-feedback"><?php echo e($errors['start_date']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Ngày kết thúc <span class="text-danger">*</span></label>
                                        <input type="datetime-local" name="end_date"
                                               class="form-control <?php echo !empty($errors['end_date']) ? 'is-invalid' : ''; ?>"
                                               value="<?php echo e($old['end_date']); ?>">
                                        <?php if (!empty($errors['end_date'])): ?>
                                            <div class="invalid-feedback"><?php echo e($errors['end_date']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Cột phải -->
                <div class="col-md-4">
                    <!-- Trạng thái -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-cog mr-2"></i>Cài đặt</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Trạng thái</label>
                                <select name="status" class="form-control">
                                    <option value="active"   <?php echo $old['status'] === 'active'   ? 'selected' : ''; ?>>✅ Kích hoạt</option>
                                    <option value="inactive" <?php echo $old['status'] === 'inactive' ? 'selected' : ''; ?>>⏸️ Tạm dừng</option>
                                </select>
                            </div>

                            <!-- Preview voucher -->
                            <div class="card mt-2" style="border:2px dashed #f36811;background:linear-gradient(135deg,#fff3e0,#ffe0b2);">
                                <div class="card-body text-center py-3">
                                    <div class="mb-2" style="font-size:11px;color:#999;letter-spacing:1px;text-transform:uppercase;">Preview</div>
                                    <div id="previewCode" style="font-size:22px;font-weight:700;color:#f36811;letter-spacing:3px;font-family:monospace;">
                                        <?php echo e($old['code']); ?>
                                    </div>
                                    <div id="previewName" style="font-size:13px;color:#555;margin-top:4px;">
                                        <?php echo e($old['name']); ?>
                                    </div>
                                    <hr style="border-color:#f36811;opacity:.3;">
                                    <div id="previewValue" style="font-size:16px;font-weight:600;color:#333;">
                                        Giảm <span style="color:#f36811;">
                                            <?php echo e($old['discount_value']); ?><?php echo $old['discount_type']==='percentage'?'%':'đ'; ?>
                                        </span>
                                    </div>
                                    <div id="previewMin" style="font-size:11px;color:#888;margin-top:4px;">
                                        <?php echo ($old['min_order_amount'] > 0) ? 'Đơn tối thiểu: ' . number_format($old['min_order_amount']) . 'đ' : 'Không yêu cầu đơn tối thiểu'; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-save mr-1"></i> Lưu thay đổi
                            </button>
                            <a href="<?php echo url('admin/vouchers/list.php'); ?>" class="btn btn-secondary btn-block">Hủy</a>
                        </div>
                    </div>

                    <!-- Thống kê sử dụng -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-chart-bar mr-2"></i>Thống kê sử dụng</h3>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Số lần sử dụng:</span>
                                <strong class="text-primary"><?php echo $voucher['used_count']; ?> lần</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Số đơn hàng:</span>
                                <strong class="text-info"><?php echo $voucherStats['cnt']; ?> đơn</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span>Tổng đã giảm:</span>
                                <strong class="text-success"><?php echo formatPrice($voucherStats['total_discount'] ?? 0); ?></strong>
                            </div>
                            <?php if ($voucher['usage_limit'] > 0): ?>
                            <div>
                                <small class="text-muted">Tiến độ sử dụng (<?php echo $voucher['used_count']; ?>/<?php echo $voucher['usage_limit']; ?>)</small>
                                <?php $pct = round($voucher['used_count'] / $voucher['usage_limit'] * 100); ?>
                                <div class="progress mt-1" style="height:8px;">
                                    <div class="progress-bar <?php echo $pct >= 100 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-success'); ?>"
                                         style="width:<?php echo min($pct, 100); ?>%"></div>
                                </div>
                                <small class="text-muted"><?php echo $pct; ?>%</small>
                            </div>
                            <?php endif; ?>

                            <?php if ($voucherStats['cnt'] > 0): ?>
                            <a href="<?php echo url('admin/orders/list.php?voucher_id=' . $id); ?>"
                               class="btn btn-outline-info btn-sm btn-block mt-3">
                                <i class="fas fa-list mr-1"></i> Xem đơn hàng đã dùng
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>
</section>

<script>
function toggleDiscountType() {
    const type = document.getElementById('discountType').value;
    const label = document.getElementById('discountValueLabel');
    const unit  = document.getElementById('discountUnit');
    const maxGroup = document.getElementById('maxDiscountGroup');
    const valueInput = document.getElementById('discountValue');

    if (type === 'percentage') {
        label.innerHTML = 'Tỷ lệ giảm (%) <span class="text-danger">*</span>';
        unit.textContent = '%';
        valueInput.max  = 100;
        valueInput.step = 1;
        maxGroup.style.display = '';
    } else {
        label.innerHTML = 'Số tiền giảm (đ) <span class="text-danger">*</span>';
        unit.textContent = 'đ';
        valueInput.removeAttribute('max');
        valueInput.step = 1000;
        maxGroup.style.display = 'none';
    }
    updatePreview();
}

document.getElementById('codeInput').addEventListener('input', function () {
    this.value = this.value.toUpperCase();
    updatePreview();
});

function updatePreview() {
    const code   = document.getElementById('codeInput').value || 'CODE';
    const name   = document.querySelector('[name="name"]').value || 'Tên chương trình';
    const type   = document.getElementById('discountType').value;
    const value  = document.getElementById('discountValue').value;
    const minAmt = document.querySelector('[name="min_order_amount"]').value;

    document.getElementById('previewCode').textContent = code;
    document.getElementById('previewName').textContent = name;
    document.getElementById('previewValue').innerHTML =
        'Giảm <span style="color:#f36811;">' + (value ? value + (type==='percentage'?'%':'đ') : '--') + '</span>';

    const minText = (minAmt && parseFloat(minAmt) > 0)
        ? 'Đơn tối thiểu: ' + Number(minAmt).toLocaleString('vi') + 'đ'
        : 'Không yêu cầu đơn tối thiểu';
    document.getElementById('previewMin').textContent = minText;
}

['name', 'discount_value', 'min_order_amount'].forEach(function(n) {
    const el = document.querySelector('[name="' + n + '"]');
    if (el) el.addEventListener('input', updatePreview);
});
document.getElementById('discountType').addEventListener('change', updatePreview);
</script>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
