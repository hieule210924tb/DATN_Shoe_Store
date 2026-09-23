<?php
/**
 * Đổi mật khẩu - WinK
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$pdo = getDBConnection();
$userId = getCurrentUserId();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword)) {
        $errors['current'] = 'Vui lòng nhập mật khẩu hiện tại.';
    }
    
    if (empty($newPassword)) {
        $errors['new'] = 'Vui lòng nhập mật khẩu mới.';
    } elseif (!isValidPassword($newPassword)) {
        $errors['new'] = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
    }
    
    if ($newPassword !== $confirmPassword) {
        $errors['confirm'] = 'Mật khẩu xác nhận không khớp.';
    }
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!password_verify($currentPassword, $user['password'])) {
            $errors['current'] = 'Mật khẩu hiện tại không đúng.';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmtUpdate->execute([$hashedPassword, $userId]);
            
            setFlashMessage('success', 'Đổi mật khẩu thành công! Vui lòng đăng nhập lại.');
            redirect(url('auth/logout.php'));
        }
    }
}

$pageTitle = 'Đổi mật khẩu - WinK';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="wink-breadcrumb">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>">Trang chủ</a></li>
                <li class="breadcrumb-item active">Đổi mật khẩu</li>
            </ol>
        </nav>
    </div>
</div>

<section class="section-padding">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="bg-white rounded-3 shadow-sm p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center bg-light text-primary rounded-circle mb-3" style="width:60px;height:60px;font-size:24px;">
                            <i class="fas fa-key"></i>
                        </div>
                        <h4 class="fw-bold">Đổi Mật Khẩu</h4>
                        <p class="text-muted">Để bảo mật tài khoản, vui lòng không chia sẻ mật khẩu cho người khác</p>
                    </div>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Mật khẩu hiện tại</label>
                            <input type="password" name="current_password" class="form-control <?php echo !empty($errors['current']) ? 'is-invalid' : ''; ?>" required>
                            <?php if (!empty($errors['current'])): ?><div class="invalid-feedback"><?php echo $errors['current']; ?></div><?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Mật khẩu mới</label>
                            <input type="password" name="new_password" class="form-control <?php echo !empty($errors['new']) ? 'is-invalid' : ''; ?>" required>
                            <?php if (!empty($errors['new'])): ?><div class="invalid-feedback"><?php echo $errors['new']; ?></div><?php endif; ?>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Xác nhận mật khẩu mới</label>
                            <input type="password" name="confirm_password" class="form-control <?php echo !empty($errors['confirm']) ? 'is-invalid' : ''; ?>" required>
                            <?php if (!empty($errors['confirm'])): ?><div class="invalid-feedback"><?php echo $errors['confirm']; ?></div><?php endif; ?>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn-wink py-2">Xác nhận</button>
                            <a href="<?php echo url('pages/profile.php'); ?>" class="btn btn-outline-secondary py-2">Trở về trang cá nhân</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
