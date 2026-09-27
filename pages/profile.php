<?php
/** Trang thông tin cá nhân - WinK */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$pdo = getDBConnection();
$userId = getCurrentUserId();
$errors = [];

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (empty($fullName))
        $errors['full_name'] = 'Vui lòng nhập họ tên.';

    // Upload avatar
    $avatarPath = $user['avatar'];
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadImage($_FILES['avatar'], UPLOAD_PATH . '/avatars');
        if ($upload['success']) {
            // Xóa avatar cũ
            if (!empty($user['avatar'])) {
                deleteImage($user['avatar'], UPLOAD_PATH . '/avatars');
            }
            $avatarPath = $upload['filename'];
            $_SESSION['user_avatar'] = $avatarPath;  // Update session
        } else {
            $errors['avatar'] = $upload['error'];
        }
    }

    if (empty($errors)) {
        try {
            $stmtUpdate = $pdo->prepare('UPDATE users SET full_name=?, phone=?, province=?, address=?, avatar=? WHERE id=?');
            $stmtUpdate->execute([$fullName, $phone, $city, $address, $avatarPath, $userId]);
        } catch (PDOException $e) {
            // Trường hợp DB chưa có cột province hoặc khác tên
            $stmtUpdate = $pdo->prepare('UPDATE users SET full_name=?, phone=?, address=?, avatar=? WHERE id=?');
            $stmtUpdate->execute([$fullName, $phone, $address, $avatarPath, $userId]);
        }

        $_SESSION['user_name'] = $fullName;
        setFlashMessage('success', 'Cập nhật thông tin thành công!');
        redirect(url('pages/profile.php'));
    }
}

$pageTitle = 'Thông tin cá nhân - WinK';
include dirname(__DIR__) . '/includes/header.php';
?>
<section class="section-padding">
    <div class="container">
        <div class="row g-4">
            <!-- Sidebar User -->
            <div class="col-lg-3">
                <div class="bg-white rounded-3 shadow-sm p-4 text-center mb-4">
                    <img src="<?php echo getCurrentUserAvatar(); ?>" class="rounded-circle mb-3" style="width:100px;height:100px;object-fit:cover;border:3px solid var(--primary-bg);">
                    <h5 class="fw-bold mb-1"><?php echo e($user['full_name']); ?></h5>
                    <p class="text-muted mb-0" style="font-size:13px;"><?php echo e($user['email']); ?></p>
                </div>
                
                <div class="list-group rounded-3 shadow-sm" style="border:none;">
                    <a href="<?php echo url('pages/profile.php'); ?>" class="list-group-item list-group-item-action active fw-semibold" style="border:none;"><i class="fas fa-user me-2"></i>Thông tin cá nhân</a>
                    <a href="<?php echo url('pages/order_history.php'); ?>" class="list-group-item list-group-item-action" style="border:none;"><i class="fas fa-shopping-bag me-2"></i>Lịch sử đơn hàng</a>
                    <a href="<?php echo url('pages/wishlist.php'); ?>" class="list-group-item list-group-item-action" style="border:none;"><i class="fas fa-heart me-2"></i>Sản phẩm yêu thích</a>
                    <a href="<?php echo url('auth/change_password.php'); ?>" class="list-group-item list-group-item-action" style="border:none;"><i class="fas fa-key me-2"></i>Đổi mật khẩu</a>
                    <a href="<?php echo url('auth/logout.php'); ?>" class="list-group-item list-group-item-action text-danger" style="border:none;"><i class="fas fa-sign-out-alt me-2"></i>Đăng xuất</a>
                </div>
            </div>
            
            <!-- Content -->
            <div class="col-lg-9">
                <div class="bg-white rounded-3 shadow-sm p-4 p-md-5">
                    <h4 class="fw-bold mb-4">Hồ sơ của tôi</h4>
                    <p class="text-muted mb-4">Quản lý thông tin hồ sơ để bảo mật tài khoản</p>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label text-muted">Email đăng nhập</label>
                                    <input type="text" class="form-control" value="<?php echo e($user['email']); ?>" disabled style="background:#f8f9fa;">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Họ và tên</label>
                                    <input type="text" name="full_name" class="form-control <?php echo !empty($errors['full_name']) ? 'is-invalid' : ''; ?>" value="<?php echo e($_POST['full_name'] ?? $user['full_name']); ?>">
                                    <?php if (!empty($errors['full_name'])): ?><div class="invalid-feedback"><?php echo $errors['full_name']; ?></div><?php endif; ?>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Số điện thoại</label>
                                    <input type="text" name="phone" class="form-control" value="<?php echo e($_POST['phone'] ?? $user['phone'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Tỉnh/Thành phố</label>
                                    <input type="text" name="city" class="form-control" value="<?php echo e($_POST['city'] ?? $user['province'] ?? $user['city'] ?? ''); ?>">
                                </div>
                                <div class="mb-4">
                                    <label class="form-label">Địa chỉ chi tiết</label>
                                    <textarea name="address" class="form-control" rows="2"><?php echo e($_POST['address'] ?? $user['address'] ?? ''); ?></textarea>
                                </div>
                                <button type="submit" class="btn-wink px-4 py-2">Lưu Thay Đổi</button>
                            </div>
                            
                            <div class="col-md-4 mt-4 mt-md-0">
                                <div class="text-center border-start ps-0 ps-md-4" style="height:100%;">
                                    <div class="mb-3">
                                        <img src="<?php echo getCurrentUserAvatar(); ?>" id="previewAvatar" class="rounded-circle" style="width:120px;height:120px;object-fit:cover;border:1px solid #ddd;">
                                    </div>
                                    <div class="position-relative overflow-hidden mb-3">
                                        <button type="button" class="btn btn-outline-secondary btn-sm px-3">Chọn ảnh mới</button>
                                        <input type="file" name="avatar" accept="image/*" class="position-absolute" style="top:0;left:0;opacity:0;cursor:pointer;width:100%;height:100%;" onchange="previewImage(this)">
                                    </div>
                                    <div class="text-muted" style="font-size:12px;">
                                        Dụng lượng file tối đa 2 MB<br>
                                        Định dạng: .JPEG, .PNG
                                    </div>
                                    <?php if (!empty($errors['avatar'])): ?>
                                        <div class="text-danger mt-2" style="font-size:13px;"><?php echo $errors['avatar']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewAvatar').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
