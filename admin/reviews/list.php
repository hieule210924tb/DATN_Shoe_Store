<?php
/**
 * Admin - Quản lý Đánh giá sản phẩm
 * WinK Shoe Store
 */
$pageTitle = 'Quản lý Đánh giá - WinK Admin';
include dirname(__DIR__) . '/includes/admin_header.php';

$pdo = getDBConnection();

// ── Tham số lọc / tìm kiếm / phân trang ─────────────────────────
$search      = trim($_GET['search']  ?? '');
$ratingFilter = (int)($_GET['rating'] ?? 0);
$perPage     = 15;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

// ── WHERE clause ─────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $like = "%$search%";
    $where[]  = "(u.full_name LIKE ? OR p.name LIKE ? OR pr.content LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($ratingFilter >= 1 && $ratingFilter <= 5) {
    $where[]  = "pr.rating = ?";
    $params[] = $ratingFilter;
}

$whereSQL = implode(' AND ', $where);

// ── Đếm tổng ─────────────────────────────────────────────────────
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM product_reviews pr
    INNER JOIN users    u ON pr.user_id    = u.id
    INNER JOIN products p ON pr.product_id = p.id
    WHERE $whereSQL
");
$countStmt->execute($params);
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $perPage;

// ── Lấy danh sách ────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT pr.id, pr.rating, pr.content, pr.image, pr.created_at,
           u.id as user_id, u.full_name, u.avatar,
           p.id as product_id, p.name as product_name, p.slug as product_slug, p.thumbnail as product_thumbnail
    FROM product_reviews pr
    INNER JOIN users    u ON pr.user_id    = u.id
    INNER JOIN products p ON pr.product_id = p.id
    WHERE $whereSQL
    ORDER BY pr.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$reviews = $stmt->fetchAll();

// ── Thống kê tổng quan ────────────────────────────────────────────
$stats = $pdo->query("
    SELECT
        COUNT(*)                                              AS total,
        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END)         AS r5,
        SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END)         AS r4,
        SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END)         AS r3,
        SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END)         AS r2,
        SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END)         AS r1,
        ROUND(AVG(rating), 1)                                AS avg_rating
    FROM product_reviews
")->fetch();

$baseUrl = url('admin/reviews/list.php')
         . '?search=' . urlencode($search)
         . '&rating=' . $ratingFilter;
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">
                    <i class="fas fa-star mr-2" style="color:#ffc107;"></i>Quản lý Đánh giá
                </h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?php echo url('admin/index.php'); ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active">Đánh giá</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">

        <!-- ── Thống kê tổng quan ───────────────────────────────── -->
        <div class="row mb-4">
            <!-- Tổng đánh giá -->
            <div class="col-md-3 col-6 mb-3">
                <div class="info-box shadow-sm" style="border-radius:12px;">
                    <span class="info-box-icon" style="background:#f36811;border-radius:12px 0 0 12px;">
                        <i class="fas fa-star"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Tổng đánh giá</span>
                        <span class="info-box-number"><?php echo number_format($stats['total']); ?></span>
                    </div>
                </div>
            </div>
            <!-- Điểm TB -->
            <div class="col-md-3 col-6 mb-3">
                <div class="info-box shadow-sm" style="border-radius:12px;">
                    <span class="info-box-icon bg-warning" style="border-radius:12px 0 0 12px;">
                        <i class="fas fa-chart-line"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Điểm trung bình</span>
                        <span class="info-box-number">
                            <?php echo $stats['avg_rating'] ?? 'N/A'; ?>
                            <small class="text-muted" style="font-size:14px;">/5</small>
                        </span>
                    </div>
                </div>
            </div>
            <!-- 5 sao -->
            <div class="col-md-3 col-6 mb-3">
                <div class="info-box shadow-sm" style="border-radius:12px;">
                    <span class="info-box-icon bg-success" style="border-radius:12px 0 0 12px;">
                        <i class="fas fa-thumbs-up"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">5 sao</span>
                        <span class="info-box-number"><?php echo number_format($stats['r5']); ?></span>
                    </div>
                </div>
            </div>
            <!-- 1-2 sao -->
            <div class="col-md-3 col-6 mb-3">
                <div class="info-box shadow-sm" style="border-radius:12px;">
                    <span class="info-box-icon bg-danger" style="border-radius:12px 0 0 12px;">
                        <i class="fas fa-thumbs-down"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">1-2 sao (Tiêu cực)</span>
                        <span class="info-box-number"><?php echo number_format($stats['r1'] + $stats['r2']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Biểu đồ phân bố sao ──────────────────────────────── -->
        <?php if ($stats['total'] > 0): ?>
        <div class="card shadow-sm mb-4" style="border-radius:12px;">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-bar mr-2"></i>Phân bố điểm đánh giá</h6>
            </div>
            <div class="card-body">
                <?php
                $bars = [5 => $stats['r5'], 4 => $stats['r4'], 3 => $stats['r3'], 2 => $stats['r2'], 1 => $stats['r1']];
                $barColors = [5 => '#28a745', 4 => '#17a2b8', 3 => '#ffc107', 2 => '#fd7e14', 1 => '#dc3545'];
                foreach ($bars as $star => $cnt):
                    $pct = $stats['total'] > 0 ? round($cnt / $stats['total'] * 100) : 0;
                ?>
                <div class="d-flex align-items-center mb-2">
                    <span style="width:50px;font-size:13px;font-weight:600;"><?php echo $star; ?> <i class="fas fa-star" style="color:#ffc107;font-size:11px;"></i></span>
                    <div class="flex-grow-1 mx-2" style="background:#f0f0f0;border-radius:6px;height:14px;overflow:hidden;">
                        <div style="width:<?php echo $pct; ?>%;height:100%;background:<?php echo $barColors[$star]; ?>;border-radius:6px;transition:width .5s;"></div>
                    </div>
                    <span style="width:60px;font-size:13px;text-align:right;"><?php echo number_format($cnt); ?> (<?php echo $pct; ?>%)</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Card danh sách ───────────────────────────────────── -->
        <div class="card shadow-sm" style="border-radius:12px;">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h3 class="card-title mb-0">
                    <i class="fas fa-list mr-2"></i>Danh sách đánh giá
                    <span class="badge badge-secondary ml-2"><?php echo number_format($totalRows); ?></span>
                </h3>
            </div>

            <!-- Bộ lọc -->
            <div class="card-body border-bottom pb-3">
                <form method="GET" action="<?php echo url('admin/reviews/list.php'); ?>" class="form-inline flex-wrap gap-2">
                    <div class="input-group mr-2 mb-2" style="min-width:280px;">
                        <input type="text" name="search" id="search_input" class="form-control"
                               placeholder="Tên KH, tên sản phẩm, nội dung..."
                               value="<?php echo e($search); ?>">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                        </div>
                    </div>

                    <!-- Lọc theo số sao -->
                    <div class="mr-2 mb-2">
                        <select name="rating" id="rating_filter" class="form-control" onchange="this.form.submit()">
                            <option value="0">-- Tất cả số sao --</option>
                            <?php for ($s = 5; $s >= 1; $s--): ?>
                            <option value="<?php echo $s; ?>" <?php echo $ratingFilter === $s ? 'selected' : ''; ?>>
                                <?php echo $s; ?> sao
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <?php if ($search !== '' || $ratingFilter > 0): ?>
                    <a href="<?php echo url('admin/reviews/list.php'); ?>" class="btn btn-outline-secondary mb-2">
                        <i class="fas fa-times mr-1"></i>Xoá bộ lọc
                    </a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="card-body p-0">
                <?php if (!empty($reviews)): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead style="background:#f8f9fa;">
                            <tr>
                                <th style="width:50px;">#</th>
                                <th>Khách hàng</th>
                                <th>Sản phẩm</th>
                                <th class="text-center">Sao</th>
                                <th>Nội dung</th>
                                <th class="text-center">Ảnh</th>
                                <th class="text-center">Ngày</th>
                                <th class="text-center" style="width:80px;">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $i => $rv): ?>
                            <tr>
                                <td class="text-muted" style="font-size:13px;"><?php echo $offset + $i + 1; ?></td>

                                <!-- Khách hàng -->
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?php echo !empty($rv['avatar']) ? AVATAR_UPLOAD_URL . '/' . e($rv['avatar']) : asset('images/default/default-avatar.png'); ?>"
                                             style="width:36px;height:36px;border-radius:50%;object-fit:cover;" alt="">
                                        <div style="font-size:14px;font-weight:600;"><?php echo e($rv['full_name']); ?></div>
                                    </div>
                                </td>

                                <!-- Sản phẩm -->
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?php echo !empty($rv['product_thumbnail']) ? PRODUCT_UPLOAD_URL . '/' . e($rv['product_thumbnail']) : asset('images/default/no-product.png'); ?>"
                                             style="width:40px;height:40px;border-radius:8px;object-fit:cover;" alt="">
                                        <a href="<?php echo url('pages/product_detail.php?slug=' . e($rv['product_slug'])); ?>"
                                           target="_blank" style="font-size:13px;font-weight:500;color:#f36811;text-decoration:none;">
                                            <?php echo e(mb_strimwidth($rv['product_name'], 0, 35, '...')); ?>
                                        </a>
                                    </div>
                                </td>

                                <!-- Rating stars -->
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <i class="fas fa-star" style="font-size:13px;color:<?php echo $s <= $rv['rating'] ? '#ffc107' : '#e0e0e0'; ?>;"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <small style="font-size:11px;color:#888;"><?php echo $rv['rating']; ?>/5</small>
                                </td>

                                <!-- Nội dung -->
                                <td style="max-width:220px;">
                                    <?php if (!empty($rv['content'])): ?>
                                    <span style="font-size:13px;color:#444;">
                                        <?php echo e(mb_strimwidth($rv['content'], 0, 80, '...')); ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted" style="font-size:12px;font-style:italic;">Không có nội dung</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Ảnh -->
                                <td class="text-center">
                                    <?php if (!empty($rv['image'])): ?>
                                    <a href="<?php echo REVIEW_UPLOAD_URL . '/' . e($rv['image']); ?>" target="_blank">
                                        <img src="<?php echo REVIEW_UPLOAD_URL . '/' . e($rv['image']); ?>"
                                             style="width:42px;height:42px;border-radius:8px;object-fit:cover;border:2px solid #f36811;" alt="">
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted" style="font-size:12px;">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Ngày -->
                                <td class="text-center text-muted" style="font-size:12px;">
                                    <?php echo formatDate($rv['created_at'], 'd/m/Y'); ?>
                                    <br><small><?php echo formatDate($rv['created_at'], 'H:i'); ?></small>
                                </td>

                                <!-- Thao tác -->
                                <td class="text-center">
                                    <button class="btn btn-sm btn-info" title="Xem chi tiết"
                                            onclick="viewReview(<?php echo htmlspecialchars(json_encode($rv), ENT_QUOTES); ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger mt-1" title="Xoá đánh giá"
                                            onclick="deleteReview(<?php echo $rv['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Phân trang -->
                <?php if ($totalPages > 1): ?>
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <small class="text-muted">
                            Hiển thị <?php echo $offset + 1; ?>–<?php echo min($offset + $perPage, $totalRows); ?>
                            trong <?php echo number_format($totalRows); ?> đánh giá
                        </small>
                        <?php echo renderPagination($currentPage, $totalPages, $baseUrl); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-star fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Không tìm thấy đánh giá nào</h5>
                    <?php if ($search !== '' || $ratingFilter > 0): ?>
                    <a href="<?php echo url('admin/reviews/list.php'); ?>" class="btn btn-sm btn-outline-primary mt-2">Xoá bộ lọc</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</section>

<!-- ════════════════════════════════════════════════════════
     MODAL XEM CHI TIẾT ĐÁNH GIÁ
════════════════════════════════════════════════════════ -->
<div class="modal fade" id="reviewDetailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;">
            <div class="modal-header" style="background:linear-gradient(135deg,#f36811,#ff8c42);border:none;">
                <h5 class="modal-title text-white fw-bold"><i class="fas fa-star mr-2"></i>Chi tiết đánh giá</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-4">
                <!-- Reviewer -->
                <div class="d-flex align-items-center mb-3">
                    <img id="dUserAvatar" src="" style="width:48px;height:48px;border-radius:50%;object-fit:cover;margin-right:12px;" alt="">
                    <div>
                        <div class="font-weight-bold" id="dUserName"></div>
                        <small class="text-muted" id="dDate"></small>
                    </div>
                </div>
                <hr>
                <!-- Product -->
                <div class="d-flex align-items-center mb-3">
                    <img id="dProductImg" src="" style="width:52px;height:52px;border-radius:10px;object-fit:cover;margin-right:12px;" alt="">
                    <div>
                        <div class="font-weight-bold" style="font-size:14px;" id="dProductName"></div>
                        <small class="text-muted">Sản phẩm được đánh giá</small>
                    </div>
                </div>
                <hr>
                <!-- Stars -->
                <div class="mb-3">
                    <strong>Điểm đánh giá:</strong>
                    <div class="mt-1" id="dStars"></div>
                </div>
                <!-- Content -->
                <div class="mb-3" id="dContentWrap">
                    <strong>Nhận xét:</strong>
                    <p class="mt-1 text-muted" id="dContent" style="font-size:14px;line-height:1.7;white-space:pre-line;"></p>
                </div>
                <!-- Image -->
                <div id="dImageWrap" style="display:none;">
                    <strong>Ảnh đính kèm:</strong>
                    <div class="mt-2">
                        <img id="dImage" src="" style="max-width:100%;border-radius:10px;max-height:240px;object-fit:cover;" alt="">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<script>
// ── View Review Detail ────────────────────────────────────
function viewReview(rv) {
    const baseUrl = '<?php echo BASE_URL; ?>';
    document.getElementById('dUserAvatar').src  = rv.avatar ? '<?php echo AVATAR_UPLOAD_URL; ?>/' + rv.avatar : '<?php echo asset("images/default/default-avatar.png"); ?>';
    document.getElementById('dUserName').textContent   = rv.full_name;
    document.getElementById('dDate').textContent       = rv.created_at;
    document.getElementById('dProductImg').src  = rv.product_thumbnail ? '<?php echo PRODUCT_UPLOAD_URL; ?>/' + rv.product_thumbnail : '<?php echo asset("images/default/no-product.png"); ?>';
    document.getElementById('dProductName').textContent = rv.product_name;

    // Stars
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        stars += `<i class="fas fa-star" style="color:${i <= rv.rating ? '#ffc107' : '#e0e0e0'};font-size:20px;margin-right:3px;"></i>`;
    }
    stars += ` <span class="ml-1 text-muted" style="font-size:14px;">${rv.rating}/5</span>`;
    document.getElementById('dStars').innerHTML = stars;

    // Content
    document.getElementById('dContent').textContent = rv.content || '(Không có nhận xét)';

    // Image
    if (rv.image) {
        document.getElementById('dImage').src = '<?php echo REVIEW_UPLOAD_URL; ?>/' + rv.image;
        document.getElementById('dImageWrap').style.display = 'block';
    } else {
        document.getElementById('dImageWrap').style.display = 'none';
    }

    $('#reviewDetailModal').modal('show');
}

// ── Delete Review ─────────────────────────────────────────
function deleteReview(reviewId) {
    if (!confirm('Bạn có chắc chắn muốn xoá đánh giá này?\nHành động này không thể hoàn tác.')) return;

    const formData = new FormData();
    formData.append('action', 'delete_review');
    formData.append('review_id', reviewId);

    fetch('<?php echo url("admin/reviews/review_ajax.php"); ?>', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Xoá dòng khỏi bảng
                const btn = document.querySelector(`button[onclick="deleteReview(${reviewId})"]`);
                if (btn) btn.closest('tr').remove();
                // Toast nếu có hàm showToast
                if (typeof showToast === 'function') showToast('success', data.message);
                else alert(data.message);
            } else {
                if (typeof showToast === 'function') showToast('error', data.message);
                else alert(data.message);
            }
        })
        .catch(() => alert('Có lỗi xảy ra. Vui lòng thử lại.'));
}
</script>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>