<?php
$pageTitle = 'Quản lý Đánh giá - WinK Admin';
include dirname(__DIR__) . '/includes/admin_header.php';

$pdo = getDBConnection();

// ── Tham số lọc / tìm kiếm / phân trang ─────────────────────────
$search = trim($_GET['search'] ?? '');
$ratingFilter = (int) ($_GET['rating'] ?? 0);
$replyFilter  = $_GET['replied'] ?? '';  // 'yes' | 'no' | ''
$perPage = 15;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $perPage;

// ── WHERE clause ─────────────────────────────────────────────────
$where = ['1=1'];
$params = [];

if ($search !== '') {
    $like = "%$search%";
    $where[] = '(u.full_name LIKE ? OR p.name LIKE ? OR pr.content LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($ratingFilter >= 1 && $ratingFilter <= 5) {
    $where[] = 'pr.rating = ?';
    $params[] = $ratingFilter;
}
if ($replyFilter === 'yes') {
    $where[] = 'pr.admin_reply IS NOT NULL';
} elseif ($replyFilter === 'no') {
    $where[] = 'pr.admin_reply IS NULL';
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
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $perPage;

// ── Lấy danh sách ────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT pr.id, pr.rating, pr.content, pr.image, pr.created_at,
           pr.admin_reply, pr.reply_at, pr.admin_id,
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
$stats = $pdo->query('
    SELECT
        COUNT(*)                                              AS total,
        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END)         AS r5,
        SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END)         AS r4,
        SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END)         AS r3,
        SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END)         AS r2,
        SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END)         AS r1,
        ROUND(AVG(rating), 1)                                AS avg_rating,
        SUM(CASE WHEN admin_reply IS NOT NULL THEN 1 ELSE 0 END) AS replied
    FROM product_reviews
')->fetch();

$baseUrl = url('admin/reviews/list.php')
    . '?search=' . urlencode($search)
    . '&rating=' . $ratingFilter
    . '&replied=' . urlencode($replyFilter);
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
            <div class="col-md-3 col-6 mb-3">
                <div class="info-box shadow-sm" style="border-radius:12px;">
                    <span class="info-box-icon bg-success" style="border-radius:12px 0 0 12px;">
                        <i class="fas fa-reply"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Đã phản hồi</span>
                        <span class="info-box-number"><?php echo number_format($stats['replied']); ?></span>
                    </div>
                </div>
            </div>
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

                    <!-- Lọc theo phản hồi -->
                    <div class="mr-2 mb-2">
                        <select name="replied" id="replied_filter" class="form-control" onchange="this.form.submit()">
                            <option value="">-- Trạng thái phản hồi --</option>
                            <option value="yes" <?php echo $replyFilter === 'yes' ? 'selected' : ''; ?>>Đã phản hồi</option>
                            <option value="no"  <?php echo $replyFilter === 'no'  ? 'selected' : ''; ?>>Chưa phản hồi</option>
                        </select>
                    </div>

                    <?php if ($search !== '' || $ratingFilter > 0 || $replyFilter !== ''): ?>
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
                                <th class="text-center">Phản hồi</th>
                                <th class="text-center">Ngày</th>
                                <th class="text-center" style="width:120px;">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $i => $rv): ?>
                            <tr id="review-row-<?php echo $rv['id']; ?>">
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
                                <td style="max-width:200px;">
                                    <?php if (!empty($rv['content'])): ?>
                                    <span style="font-size:13px;color:#444;">
                                        <?php echo e(mb_strimwidth($rv['content'], 0, 70, '...')); ?>
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

                                <!-- Trạng thái phản hồi -->
                                <td class="text-center" id="reply-status-<?php echo $rv['id']; ?>">
                                    <?php if (!empty($rv['admin_reply'])): ?>
                                    <span class="badge badge-success px-2 py-1" style="font-size:11px;">
                                        <i class="fas fa-check-circle mr-1"></i>Đã phản hồi
                                    </span>
                                    <div style="font-size:11px;color:#888;margin-top:2px;">
                                        <?php echo formatDate($rv['reply_at'], 'd/m/Y H:i'); ?>
                                    </div>
                                    <?php else: ?>
                                    <span class="badge badge-secondary px-2 py-1" style="font-size:11px;">
                                        <i class="fas fa-clock mr-1"></i>Chưa phản hồi
                                    </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Ngày -->
                                <td class="text-center text-muted" style="font-size:12px;">
                                    <?php echo formatDate($rv['created_at'], 'd/m/Y'); ?>
                                    <br><small><?php echo formatDate($rv['created_at'], 'H:i'); ?></small>
                                </td>

                                <!-- Thao tác -->
                                <td class="text-center">
                                    <button class="btn btn-sm btn-info mb-1" title="Xem chi tiết"
                                            onclick="viewReview(<?php echo htmlspecialchars(json_encode($rv), ENT_QUOTES); ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-success mb-1" title="Phản hồi đánh giá"
                                            onclick="openReplyModal(<?php echo $rv['id']; ?>, <?php echo htmlspecialchars(json_encode($rv['admin_reply'] ?? ''), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($rv['full_name']), ENT_QUOTES); ?>)">
                                        <i class="fas fa-reply"></i>
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
                    <?php if ($search !== '' || $ratingFilter > 0 || $replyFilter !== ''): ?>
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
                <!-- Admin Reply Preview -->
                <div id="dAdminReplyWrap" style="display:none;margin-top:16px;">
                    <hr>
                    <div style="background:linear-gradient(135deg,#e8f5e9,#f1f8e9);border-left:4px solid #28a745;border-radius:8px;padding:12px 16px;">
                        <div style="font-size:12px;font-weight:700;color:#28a745;margin-bottom:6px;">
                            <i class="fas fa-reply mr-1"></i> PHẢN HỒI CỦA ADMIN
                        </div>
                        <p id="dAdminReply" style="font-size:13px;color:#333;margin:0;white-space:pre-line;"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     MODAL PHẢN HỒI ĐÁNH GIÁ
════════════════════════════════════════════════════════ -->
<div class="modal fade" id="replyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:560px;">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.15);">
            <!-- Header -->
            <div class="modal-header" style="background:linear-gradient(135deg,#28a745,#20c997);border:none;padding:20px 24px;">
                <div>
                    <h5 class="modal-title text-white mb-1" style="font-weight:700;">
                        <i class="fas fa-reply mr-2"></i>Phản hồi đánh giá
                    </h5>
                    <small class="text-white" style="opacity:.85;">Khách hàng: <span id="replyCustomerName" style="font-weight:600;"></span></small>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:.8;"><span style="font-size:24px;">&times;</span></button>
            </div>

            <!-- Body -->
            <div class="modal-body p-4">
                <!-- Preview phản hồi cũ (nếu có) -->
                <div id="existingReplyWrap" style="display:none;margin-bottom:16px;">
                    <div style="background:#f8f9fa;border:1px solid #dee2e6;border-radius:10px;padding:14px 16px;position:relative;">
                        <div style="font-size:11px;font-weight:700;color:#6c757d;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">
                            <i class="fas fa-history mr-1"></i> Phản hồi hiện tại
                        </div>
                        <p id="existingReplyText" style="font-size:13px;color:#444;margin:0;white-space:pre-line;line-height:1.6;"></p>
                        <button type="button" onclick="deleteReply()" id="deleteReplyBtn"
                                style="position:absolute;top:10px;right:10px;background:none;border:none;color:#dc3545;font-size:12px;cursor:pointer;padding:2px 6px;border-radius:4px;transition:background .2s;"
                                onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='none'">
                            <i class="fas fa-times"></i> Xoá phản hồi
                        </button>
                    </div>
                </div>

                <!-- Textarea nhập phản hồi -->
                <div class="form-group mb-0">
                    <label for="replyTextarea" style="font-weight:600;font-size:13px;color:#333;">
                        <i class="fas fa-pen mr-1" style="color:#28a745;"></i>
                        <span id="replyLabel">Nội dung phản hồi</span>
                    </label>
                    <textarea id="replyTextarea" class="form-control" rows="5"
                              placeholder="Nhập nội dung phản hồi dành cho khách hàng..."
                              style="border-radius:10px;border:1.5px solid #dee2e6;font-size:14px;resize:vertical;transition:border-color .2s;padding:12px 14px;"></textarea>
                    <div class="d-flex justify-content-between mt-2">
                        <small class="text-muted">Phản hồi sẽ hiển thị công khai dưới đánh giá của khách hàng.</small>
                        <small id="replyCharCount" style="color:#6c757d;">0 / 1000</small>
                    </div>
                    <div id="replyError" class="text-danger mt-1" style="font-size:13px;display:none;"></div>
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer" style="padding:16px 24px;background:#f8f9fa;">
                <button type="button" class="btn btn-light" data-dismiss="modal" style="border-radius:8px;">
                    <i class="fas fa-times mr-1"></i>Hủy
                </button>
                <button type="button" onclick="submitReply()" id="submitReplyBtn"
                        class="btn btn-success" style="border-radius:8px;min-width:130px;font-weight:600;">
                    <i class="fas fa-paper-plane mr-1"></i>Gửi phản hồi
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ── View Review Detail ────────────────────────────────────
function viewReview(rv) {
    document.getElementById('dUserAvatar').src  = rv.avatar ? '<?php echo AVATAR_UPLOAD_URL; ?>/' + rv.avatar : '<?php echo asset('images/default/default-avatar.png'); ?>';
    document.getElementById('dUserName').textContent   = rv.full_name;
    document.getElementById('dDate').textContent       = rv.created_at;
    document.getElementById('dProductImg').src  = rv.product_thumbnail ? '<?php echo PRODUCT_UPLOAD_URL; ?>/' + rv.product_thumbnail : '<?php echo asset('images/default/no-product.png'); ?>';
    document.getElementById('dProductName').textContent = rv.product_name;

    let stars = '';
    for (let i = 1; i <= 5; i++) {
        stars += `<i class="fas fa-star" style="color:${i <= rv.rating ? '#ffc107' : '#e0e0e0'};font-size:20px;margin-right:3px;"></i>`;
    }
    stars += ` <span class="ml-1 text-muted" style="font-size:14px;">${rv.rating}/5</span>`;
    document.getElementById('dStars').innerHTML = stars;

    document.getElementById('dContent').textContent = rv.content || '(Không có nhận xét)';

    if (rv.image) {
        document.getElementById('dImage').src = '<?php echo REVIEW_UPLOAD_URL; ?>/' + rv.image;
        document.getElementById('dImageWrap').style.display = 'block';
    } else {
        document.getElementById('dImageWrap').style.display = 'none';
    }

    // Admin reply
    if (rv.admin_reply) {
        document.getElementById('dAdminReply').textContent = rv.admin_reply;
        document.getElementById('dAdminReplyWrap').style.display = 'block';
    } else {
        document.getElementById('dAdminReplyWrap').style.display = 'none';
    }

    $('#reviewDetailModal').modal('show');
}



// ── Reply Modal ───────────────────────────────────────────
let _currentReviewId = null;

function openReplyModal(reviewId, existingReply, customerName) {
    _currentReviewId = reviewId;

    document.getElementById('replyCustomerName').textContent = customerName || '';
    document.getElementById('replyTextarea').value = '';
    document.getElementById('replyError').style.display = 'none';
    document.getElementById('replyCharCount').textContent = '0 / 1000';

    if (existingReply && existingReply.trim() !== '') {
        document.getElementById('existingReplyText').textContent = existingReply;
        document.getElementById('existingReplyWrap').style.display = 'block';
        document.getElementById('replyLabel').textContent = 'Cập nhật phản hồi';
        document.getElementById('submitReplyBtn').innerHTML = '<i class="fas fa-sync mr-1"></i>Cập nhật';
        // Pre-fill textarea with existing reply
        document.getElementById('replyTextarea').value = existingReply;
        document.getElementById('replyCharCount').textContent = existingReply.length + ' / 1000';
    } else {
        document.getElementById('existingReplyWrap').style.display = 'none';
        document.getElementById('replyLabel').textContent = 'Nội dung phản hồi';
        document.getElementById('submitReplyBtn').innerHTML = '<i class="fas fa-paper-plane mr-1"></i>Gửi phản hồi';
    }

    $('#replyModal').modal('show');
    setTimeout(() => document.getElementById('replyTextarea').focus(), 400);
}

// Đếm ký tự
document.getElementById('replyTextarea').addEventListener('input', function() {
    const len = this.value.length;
    document.getElementById('replyCharCount').textContent = len + ' / 1000';
    if (len > 1000) this.value = this.value.substring(0, 1000);
});

// Focus style
document.getElementById('replyTextarea').addEventListener('focus', function() {
    this.style.borderColor = '#28a745';
    this.style.boxShadow = '0 0 0 3px rgba(40,167,69,.15)';
});
document.getElementById('replyTextarea').addEventListener('blur', function() {
    this.style.borderColor = '#dee2e6';
    this.style.boxShadow = 'none';
});

function submitReply() {
    const content = document.getElementById('replyTextarea').value.trim();
    const errEl   = document.getElementById('replyError');
    const btn     = document.getElementById('submitReplyBtn');

    if (content === '') {
        errEl.textContent = 'Vui lòng nhập nội dung phản hồi.';
        errEl.style.display = 'block';
        document.getElementById('replyTextarea').focus();
        return;
    }
    errEl.style.display = 'none';

    // Loading state
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Đang gửi...';

    const formData = new FormData();
    formData.append('action', 'reply_review');
    formData.append('review_id', _currentReviewId);
    formData.append('reply_content', content);

    fetch('<?php echo url('admin/reviews/review_ajax.php'); ?>', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane mr-1"></i>Gửi phản hồi';

            if (data.success) {
                $('#replyModal').modal('hide');

                // Cập nhật badge trên bảng không cần reload
                const statusEl = document.getElementById('reply-status-' + _currentReviewId);
                if (statusEl) {
                    const now = new Date();
                    const pad = n => String(n).padStart(2, '0');
                    const dateStr = pad(now.getDate()) + '/' + pad(now.getMonth()+1) + '/' + now.getFullYear()
                                  + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes());
                    statusEl.innerHTML = `
                        <span class="badge badge-success px-2 py-1" style="font-size:11px;">
                            <i class="fas fa-check-circle mr-1"></i>Đã phản hồi
                        </span>
                        <div style="font-size:11px;color:#888;margin-top:2px;">${dateStr}</div>`;
                }

                if (typeof showToast === 'function') showToast('success', data.message);
                else {
                    // Hiển thị thông báo thành công ngắn gọn
                    const toast = document.createElement('div');
                    toast.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;background:#28a745;color:#fff;padding:12px 20px;border-radius:10px;font-size:14px;box-shadow:0 4px 20px rgba(0,0,0,.2);transition:opacity .3s;';
                    toast.innerHTML = '<i class="fas fa-check-circle mr-2"></i>' + data.message;
                    document.body.appendChild(toast);
                    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3000);
                }
            } else {
                errEl.textContent = data.message;
                errEl.style.display = 'block';
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane mr-1"></i>Gửi phản hồi';
            errEl.textContent = 'Có lỗi xảy ra. Vui lòng thử lại.';
            errEl.style.display = 'block';
        });
}

function deleteReply() {
    if (!confirm('Bạn có chắc muốn xoá phản hồi này?')) return;

    const btn = document.getElementById('deleteReplyBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    const formData = new FormData();
    formData.append('action', 'delete_reply');
    formData.append('review_id', _currentReviewId);

    fetch('<?php echo url('admin/reviews/review_ajax.php'); ?>', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-times"></i> Xoá phản hồi';

            if (data.success) {
                document.getElementById('existingReplyWrap').style.display = 'none';
                document.getElementById('replyTextarea').value = '';
                document.getElementById('replyCharCount').textContent = '0 / 1000';
                document.getElementById('replyLabel').textContent = 'Nội dung phản hồi';
                document.getElementById('submitReplyBtn').innerHTML = '<i class="fas fa-paper-plane mr-1"></i>Gửi phản hồi';

                // Cập nhật badge
                const statusEl = document.getElementById('reply-status-' + _currentReviewId);
                if (statusEl) {
                    statusEl.innerHTML = `<span class="badge badge-secondary px-2 py-1" style="font-size:11px;">
                        <i class="fas fa-clock mr-1"></i>Chưa phản hồi</span>`;
                }
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-times"></i> Xoá phản hồi';
        });
}
</script>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>