<?php
/** Admin - Thêm voucher mới */
require_once dirname(dirname(__DIR__)) . '/includes/admin_check.php';

$pageTitle = 'Thêm voucher - WinK Admin';

$pdo = getDBConnection();
$errors = [];
$old = [
    'code' => '',
    'name' => '',
    'discount_type' => 'percentage',
    'discount_value' => '',
    'max_discount' => '',
    'min_order_amount' => '0',
    'usage_limit' => '0',
    'start_date' => date('Y-m-d\TH:i'),
    'end_date' => date('Y-m-d\TH:i', strtotime('+30 days')),
    'status' => 'active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'code' => strtoupper(trim($_POST['code'] ?? '')),
        'name' => trim($_POST['name'] ?? ''),
        'discount_type' => in_array($_POST['discount_type'] ?? '', ['percentage', 'fixed']) ? $_POST['discount_type'] : 'percentage',
        'discount_value' => (float) ($_POST['discount_value'] ?? 0),
        'max_discount' => !empty($_POST['max_discount']) ? (float) $_POST['max_discount'] : null,
        'min_order_amount' => (float) ($_POST['min_order_amount'] ?? 0),
        'usage_limit' => (int) ($_POST['usage_limit'] ?? 0),
        'start_date' => trim($_POST['start_date'] ?? ''),
        'end_date' => trim($_POST['end_date'] ?? ''),
        'status' => in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active',
    ];

    // Validate
    if (empty($old['code'])) {
        $errors['code'] = 'Vui lòng nhập mã voucher.';
    } elseif (!preg_match('/^[A-Z0-9_\-]{3,30}$/', $old['code'])) {
        $errors['code'] = 'Mã voucher chỉ gồm chữ in hoa, số, dấu _ hoặc - (3–30 ký tự).';
    } else {
        $check = $pdo->prepare('SELECT id FROM vouchers WHERE code = ?');
        $check->execute([$old['code']]);
        if ($check->fetch())
            $errors['code'] = 'Mã voucher này đã tồn tại.';
    }

    if (empty($old['name'])) {
        $errors['name'] = 'Vui lòng nhập tên chương trình.';
    }

    if ($old['discount_value'] <= 0) {
        $errors['discount_value'] = 'Giá trị giảm phải lớn hơn 0.';
    } elseif ($old['discount_type'] === 'percentage' && $old['discount_value'] > 100) {
        $errors['discount_value'] = 'Tỷ lệ phần trăm không được vượt quá 100%.';
    }

    if ($old['min_order_amount'] < 0) {
        $errors['min_order_amount'] = 'Giá trị đơn tối thiểu không hợp lệ.';
    }

    if ($old['usage_limit'] < 0) {
        $errors['usage_limit'] = 'Giới hạn lượt dùng không hợp lệ.';
    }

    if (empty($old['start_date'])) {
        $errors['start_date'] = 'Vui lòng chọn ngày bắt đầu.';
    }
    if (empty($old['end_date'])) {
        $errors['end_date'] = 'Vui lòng chọn ngày kết thúc.';
    }
    if (!empty($old['start_date']) && !empty($old['end_date']) && $old['end_date'] <= $old['start_date']) {
        $errors['end_date'] = 'Ngày kết thúc phải sau ngày bắt đầu.';
    }

    if (empty($errors)) {
        $startDateDb = date('Y-m-d H:i:s', strtotime($old['start_date']));
        $endDateDb = date('Y-m-d H:i:s', strtotime($old['end_date']));

        $stmt = $pdo->prepare('
            INSERT INTO vouchers (code, name, discount_type, discount_value, max_discount, min_order_amount, usage_limit, start_date, end_date, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $old['code'], $old['name'], $old['discount_type'], $old['discount_value'],
            $old['max_discount'], $old['min_order_amount'], $old['usage_limit'],
            $startDateDb, $endDateDb, $old['status']
        ]);

        setFlashMessage('success', 'Thêm voucher <strong>' . e($old['code']) . '</strong> thành công!');
        redirect(url('admin/vouchers/list.php'));
    }
}

include dirname(__DIR__) . '/includes/admin_header.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1><i class="fas fa-plus-circle mr-2 text-primary"></i>Thêm voucher mới</h1>
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
                                <label>Mã voucher <span class="text-danger">*</span>
                                    <small class="text-muted">(chữ in hoa, số, dấu _/-)</small>
                                </label>
                                <div class="input-group">
                                    <input type="text" name="code" id="codeInput"
                                           class="form-control <?php echo !empty($errors['code']) ? 'is-invalid' : ''; ?>"
                                           value="<?php echo e($old['code']); ?>"
                                           placeholder="VD: SUMMER20, FREESHIP100" maxlength="30"
                                           style="text-transform:uppercase; font-family:monospace; font-weight:600; letter-spacing:2px;">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary" id="btnGenCode" title="Tạo mã ngẫu nhiên">
                                            <i class="fas fa-random"></i>
                                        </button>
                                    </div>
                                    <?php if (!empty($errors['code'])): ?>
                                        <div class="invalid-feedback"><?php echo e($errors['code']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Tên chương trình -->
                            <div class="form-group">
                                <label>Tên chương trình <span class="text-danger">*</span></label>
                                <input type="text" name="name"
                                       class="form-control <?php echo !empty($errors['name']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo e($old['name']); ?>"
                                       placeholder="VD: Giảm 20% mùa hè, Miễn phí vận chuyển toàn quốc">
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
                                            <option value="fixed"      <?php echo $old['discount_type'] === 'fixed' ? 'selected' : ''; ?>>Số tiền cố định (đ)</option>
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
                                               min="0" step="1000" placeholder="VD: 200000">
                                    </div>
                                </div>
                            </div>

                            <!-- Đơn tối thiểu & Giới hạn lượt -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Giá trị đơn tối thiểu (đ)</label>
                                        <input type="number" name="min_order_amount"
                                               class="form-control <?php echo !empty($errors['min_order_amount']) ? 'is-invalid' : ''; ?>"
                                               value="<?php echo e($old['min_order_amount']); ?>"
                                               min="0" step="1000" placeholder="0 = không giới hạn">
                                        <?php if (!empty($errors['min_order_amount'])): ?>
                                            <div class="invalid-feedback"><?php echo e($errors['min_order_amount']); ?></div>
                                        <?php endif; ?>
                                        <small class="text-muted">Nhập 0 nếu không yêu cầu giá trị tối thiểu</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Giới hạn lượt sử dụng</label>
                                        <input type="number" name="usage_limit"
                                               class="form-control <?php echo !empty($errors['usage_limit']) ? 'is-invalid' : ''; ?>"
                                               value="<?php echo e($old['usage_limit']); ?>"
                                               min="0" step="1" placeholder="0 = không giới hạn">
                                        <?php if (!empty($errors['usage_limit'])): ?>
                                            <div class="invalid-feedback"><?php echo e($errors['usage_limit']); ?></div>
                                        <?php endif; ?>
                                        <small class="text-muted">Nhập 0 nếu không giới hạn số lượt dùng</small>
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
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-cog mr-2"></i>Cài đặt</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Trạng thái</label>
                                <select name="status" class="form-control">
                                    <option value="active"   <?php echo $old['status'] === 'active' ? 'selected' : ''; ?>> Kích hoạt</option>
                                    <option value="inactive" <?php echo $old['status'] === 'inactive' ? 'selected' : ''; ?>> Tạm dừng</option>
                                </select>
                            </div>

                            <!-- Preview voucher -->
                            <div class="card mt-3" style="border:2px dashed #f36811;background:linear-gradient(135deg,#fff3e0,#ffe0b2);">
                                <div class="card-body text-center py-3">
                                    <div class="mb-2" style="font-size:11px;color:#999;letter-spacing:1px;text-transform:uppercase;">Preview Voucher</div>
                                    <div id="previewCode" style="font-size:22px;font-weight:700;color:#f36811;letter-spacing:3px;font-family:monospace;">
                                        <?php echo e($old['code'] ?: 'CODE'); ?>
                                    </div>
                                    <div id="previewName" style="font-size:13px;color:#555;margin-top:4px;">
                                        <?php echo e($old['name'] ?: 'Tên chương trình'); ?>
                                    </div>
                                    <hr style="border-color:#f36811;opacity:.3;">
                                    <div id="previewValue" style="font-size:16px;font-weight:600;color:#333;">
                                        Giảm <span style="color:#f36811;"><?php echo $old['discount_value'] ? e($old['discount_value']) . ($old['discount_type'] === 'percentage' ? '%' : 'đ') : '--'; ?></span>
                                    </div>
                                    <div id="previewMin" style="font-size:11px;color:#888;margin-top:4px;">
                                        <?php echo ($old['min_order_amount'] > 0) ? 'Đơn tối thiểu: ' . number_format($old['min_order_amount']) . 'đ' : 'Không yêu cầu đơn tối thiểu'; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-save mr-1"></i> Tạo voucher
                            </button>
                            <a href="<?php echo url('admin/vouchers/list.php'); ?>" class="btn btn-secondary btn-block">
                                Hủy
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>
</section>

<script>
// Toggle discount type
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

// Tạo mã ngẫu nhiên
document.getElementById('btnGenCode').addEventListener('click', function () {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let code = '';
    for (let i = 0; i < 8; i++) code += chars[Math.floor(Math.random() * chars.length)];
    document.getElementById('codeInput').value = code;
    updatePreview();
});

// Auto uppercase code
document.getElementById('codeInput').addEventListener('input', function () {
    this.value = this.value.toUpperCase();
    updatePreview();
});

// Live preview
function updatePreview() {
    const code    = document.getElementById('codeInput').value || 'CODE';
    const name    = document.querySelector('[name="name"]').value || 'Tên chương trình';
    const type    = document.getElementById('discountType').value;
    const value   = document.getElementById('discountValue').value;
    const minAmt  = document.querySelector('[name="min_order_amount"]').value;

    document.getElementById('previewCode').textContent = code;
    document.getElementById('previewName').textContent = name;
    document.getElementById('previewValue').innerHTML =
        'Giảm <span style="color:#f36811;">' + (value ? value + (type==='percentage'?'%':'đ') : '--') + '</span>';

    const minText = (minAmt && parseFloat(minAmt) > 0)
        ? 'Đơn tối thiểu: ' + Number(minAmt).toLocaleString('vi') + 'đ'
        : 'Không yêu cầu đơn tối thiểu';
    document.getElementById('previewMin').textContent = minText;
}

// Bind preview events
['name', 'discount_value', 'min_order_amount'].forEach(function(n) {
    const el = document.querySelector('[name="' + n + '"]');
    if (el) el.addEventListener('input', updatePreview);
});
document.getElementById('discountType').addEventListener('change', updatePreview);
</script>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
