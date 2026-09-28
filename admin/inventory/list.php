<?php

/**
 * Admin - Quản lý Tồn kho
 * WinK Shoe Store
 */
$pageTitle = 'Quản lý Tồn kho - WinK Admin';
include dirname(__DIR__) . '/includes/admin_header.php';

$pdo = getDBConnection();

// ── Tham số lọc / tìm kiếm / phân trang ─────────────────────────
$search = trim($_GET['search'] ?? '');
$stockFilter = $_GET['stock'] ?? '';  // '' | 'in_stock' | 'low_stock' | 'out_of_stock'
$catFilter = (int) ($_GET['cat'] ?? 0);
$perPage = 20;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));

// ── WHERE clause ─────────────────────────────────────────────────
$where = ['p.status = "active"'];
$params = [];

if ($search !== '') {
    $where[] = '(p.name LIKE ? OR pv.sku LIKE ? OR pv.color LIKE ?)';
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($stockFilter !== '') {
    $where[] = 'pv.status = ?';
    $params[] = $stockFilter;
}
if ($catFilter > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $catFilter;
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

// ── Đếm tổng ─────────────────────────────────────────────────────
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM product_variants pv
    INNER JOIN products p ON pv.product_id = p.id
    $whereSQL
");
$countStmt->execute($params);
$totalItems = (int) $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalItems / $perPage));
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $perPage;

// ── Lấy danh sách biến thể ───────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT pv.id, pv.product_id, pv.size, pv.color, pv.stock_quantity, pv.sku, pv.status,
           p.name AS product_name, p.thumbnail AS product_thumb, p.slug AS product_slug,
           c.name AS category_name
    FROM product_variants pv
    INNER JOIN products p  ON pv.product_id = p.id
    LEFT  JOIN categories c ON p.category_id = c.id
    $whereSQL
    ORDER BY pv.status ASC, pv.stock_quantity ASC, p.name ASC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$variants = $stmt->fetchAll();

// ── Thống kê nhanh ───────────────────────────────────────────────
$stats = $pdo->query("
    SELECT
        COUNT(*)                                                          AS total_variants,
        SUM(pv.stock_quantity)                                            AS total_stock,
        SUM(CASE WHEN pv.status='in_stock'     THEN 1 ELSE 0 END)        AS in_stock,
        SUM(CASE WHEN pv.status='low_stock'    THEN 1 ELSE 0 END)        AS low_stock,
        SUM(CASE WHEN pv.status='out_of_stock' THEN 1 ELSE 0 END)        AS out_of_stock,
        COUNT(DISTINCT pv.product_id)                                     AS total_products
    FROM product_variants pv
    INNER JOIN products p ON pv.product_id = p.id
    WHERE p.status = 'active'
")->fetch();

// ── Danh mục cho filter ──────────────────────────────────────────
$categories = $pdo->query("SELECT id, name FROM categories WHERE status='active' ORDER BY name")->fetchAll();

$baseUrl = url('admin/inventory/list.php')
    . '?search=' . urlencode($search)
    . '&stock=' . urlencode($stockFilter)
    . '&cat=' . $catFilter;
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0"><i class="fas fa-warehouse mr-2" style="color:#f36811;"></i>Quản lý Tồn kho</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?php echo url('admin/index.php'); ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active">Tồn kho</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">

        <!-- Thống kê nhanh -->
        <div class="row mb-4">
            <?php
            $statCards = [
                ['label' => 'Tổng biến thể', 'val' => number_format($stats['total_variants']), 'icon' => 'fa-layer-group', 'color' => 'bg-secondary', 'key' => ''],
                ['label' => 'Tổng tồn kho', 'val' => number_format($stats['total_stock']), 'icon' => 'fa-boxes', 'color' => 'bg-info', 'key' => ''],
                ['label' => 'Còn hàng', 'val' => number_format($stats['in_stock']), 'icon' => 'fa-check-circle', 'color' => 'bg-success', 'key' => 'in_stock'],
                ['label' => 'Sắp hết (≤5)', 'val' => number_format($stats['low_stock']), 'icon' => 'fa-exclamation-triangle', 'color' => 'bg-warning', 'key' => 'low_stock'],
                ['label' => 'Hết hàng', 'val' => number_format($stats['out_of_stock']), 'icon' => 'fa-times-circle', 'color' => 'bg-danger', 'key' => 'out_of_stock'],
            ];
            foreach ($statCards as $card):
                $cardUrl = url('admin/inventory/list.php') . ($card['key'] !== '' ? '?stock=' . $card['key'] : '');
                ?>
            <div class="col-6 col-md mb-2">
                <a href="<?php echo $cardUrl; ?>" style="text-decoration:none;">
                    <div class="small-box <?php echo $card['color']; ?> shadow-sm mb-0">
                        <div class="inner">
                            <h4><?php echo $card['val']; ?></h4>
                            <p style="font-size:12px;"><?php echo $card['label']; ?></p>
                        </div>
                        <div class="icon"><i class="fas <?php echo $card['icon']; ?>"></i></div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Card chính -->
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h3 class="card-title mb-0">
                    <i class="fas fa-list mr-2"></i>Danh sách biến thể sản phẩm
                    <span class="badge badge-secondary ml-2"><?php echo number_format($totalItems); ?></span>
                </h3>
            </div>

            <!-- Bộ lọc -->
            <div class="card-body border-bottom pb-3">
                <form method="GET" action="<?php echo url('admin/inventory/list.php'); ?>" class="form-inline flex-wrap gap-2">
                    <div class="input-group mr-2 mb-2" style="min-width:240px;">
                        <input type="text" name="search" id="inv_search" class="form-control"
                               placeholder="Tên SP, SKU, màu sắc..."
                               value="<?php echo e($search); ?>">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                        </div>
                    </div>

                    <select name="stock" id="stock_filter" class="form-control mr-2 mb-2" onchange="this.form.submit()">
                        <option value="">-- Tất cả tồn kho --</option>
                        <option value="in_stock"     <?php echo $stockFilter === 'in_stock' ? 'selected' : ''; ?>> Còn hàng</option>
                        <option value="low_stock"    <?php echo $stockFilter === 'low_stock' ? 'selected' : ''; ?>> Sắp hết hàng</option>
                        <option value="out_of_stock" <?php echo $stockFilter === 'out_of_stock' ? 'selected' : ''; ?>>Hết hàng</option>
                    </select>

                    <select name="cat" id="cat_filter" class="form-control mr-2 mb-2" onchange="this.form.submit()">
                        <option value="0">-- Tất cả danh mục --</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $catFilter === $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo e($cat['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if ($search !== '' || $stockFilter !== '' || $catFilter > 0): ?>
                    <a href="<?php echo url('admin/inventory/list.php'); ?>" class="btn btn-outline-secondary mb-2">
                        <i class="fas fa-times mr-1"></i>Xoá bộ lọc
                    </a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="card-body p-0">
                <?php if (!empty($variants)): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="inventory-table">
                        <thead style="background:#f8f9fa;">
                            <tr>
                                <th style="width:60px;">#</th>
                                <th>Sản phẩm</th>
                                <th class="text-center">Size</th>
                                <th class="text-center">Màu</th>
                                <th>SKU</th>
                                <th class="text-center">Trạng thái</th>
                                <th class="text-center">Tồn kho</th>
                                <th class="text-center" style="width:100px;">Sửa sản phẩm</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($variants as $i => $v): ?>
                            <tr id="row-<?php echo $v['id']; ?>"
                                class="<?php echo $v['status'] === 'out_of_stock' ? 'table-danger' : ($v['status'] === 'low_stock' ? 'table-warning' : ''); ?>">
                                <td class="text-muted"><?php echo $offset + $i + 1; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($v['product_thumb'])): ?>
                                        <img src="<?php echo PRODUCT_UPLOAD_URL . '/' . e($v['product_thumb']); ?>"
                                             style="width:40px;height:40px;object-fit:cover;border-radius:6px;margin-right:10px;flex-shrink:0;">
                                        <?php else: ?>
                                        <div style="width:40px;height:40px;border-radius:6px;background:#f0f0f0;
                                                    display:flex;align-items:center;justify-content:center;margin-right:10px;flex-shrink:0;">
                                            <i class="fas fa-shoe-prints text-muted" style="font-size:12px;"></i>
                                        </div>
                                        <?php endif; ?>
                                        <div>
                                            <a href="<?php echo url('admin/products/edit.php?id=' . $v['product_id']); ?>"
                                               class="font-weight-bold text-dark" style="font-size:13px;">
                                                <?php echo e($v['product_name']); ?>
                                            </a>
                                            <br><small class="text-muted"><?php echo e($v['category_name'] ?? ''); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-light border px-2"><?php echo e($v['size']); ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-light border px-2"><?php echo e($v['color']); ?></span>
                                </td>
                                <td>
                                    <code style="font-size:12px;color:#666;"><?php echo $v['sku'] ? e($v['sku']) : '<span class="text-muted">—</span>'; ?></code>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $statusLabel = ['in_stock' => 'Còn hàng', 'low_stock' => 'Sắp hết', 'out_of_stock' => 'Hết hàng'];
                                    $statusClass = ['in_stock' => 'badge-success', 'low_stock' => 'badge-warning', 'out_of_stock' => 'badge-danger'];
                                    ?>
                                    <span class="badge <?php echo $statusClass[$v['status']] ?? 'badge-secondary'; ?> px-2"
                                          id="status-badge-<?php echo $v['id']; ?>">
                                        <?php echo $statusLabel[$v['status']] ?? $v['status']; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="font-weight-bold" style="font-size:15px;">
                                        <?php echo number_format($v['stock_quantity']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="<?php echo url('admin/products/variants.php?id=' . $v['product_id']); ?>"
                                       class="btn btn-sm btn-outline-primary" title="Sửa biến thể">
                                        <i class="fas fa-edit"></i>
                                    </a>
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
                            Hiển thị <?php echo $offset + 1; ?>–<?php echo min($offset + $perPage, $totalItems); ?>
                            trong <?php echo number_format($totalItems); ?> biến thể
                        </small>
                        <?php echo renderPagination($currentPage, $totalPages, $baseUrl); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-warehouse fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Không tìm thấy biến thể nào</h5>
                    <?php if ($search !== '' || $stockFilter !== '' || $catFilter > 0): ?>
                        <a href="<?php echo url('admin/inventory/list.php'); ?>" class="btn btn-sm btn-outline-primary mt-2">Xoá bộ lọc</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</section>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
