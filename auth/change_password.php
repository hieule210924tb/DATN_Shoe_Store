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
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Phiên làm việc không hợp lệ, vui lòng thử lại.');
        redirect(url('auth/change_password.php'));
    }

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
    
    if (empty($confirmPassword)) {
        $errors['confirm'] = 'Vui lòng xác nhận mật khẩu mới.';
    } elseif ($newPassword !== $confirmPassword) {
        $errors['confirm'] = 'Mật khẩu xác nhận không khớp.';
    }

    if (empty($errors['new']) && $newPassword !== '' && $newPassword === $currentPassword) {
        $errors['new'] = 'Mật khẩu mới phải khác mật khẩu hiện tại.';
    }
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($currentPassword, $user['password'])) {
            $errors['current'] = 'Mật khẩu hiện tại không đúng.';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmtUpdate->execute([$hashedPassword, $userId]);

            // Đăng xuất (hủy session) rồi chuyển về trang đăng nhập kèm thông báo
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            session_start();
            setFlashMessage('success', 'Đổi mật khẩu thành công! Vui lòng đăng nhập lại bằng mật khẩu mới.');
            redirect(url('auth/login.php'));
        }
    }
}

// Thông tin user hiển thị ở panel trái
$stmtInfo = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
$stmtInfo->execute([$userId]);
$userInfo = $stmtInfo->fetch() ?: ['full_name' => getCurrentUserName(), 'email' => ''];

$pageTitle = 'Đổi mật khẩu - WinK';
$pageDescription = 'Đổi mật khẩu tài khoản WinK Shoe Store để bảo vệ tài khoản của bạn.';
$extraCSS = ['change_password.css'];
include dirname(__DIR__) . '/includes/header.php';

/** Render class trạng thái cho field */
$fieldClass = function ($key) use ($errors) {
    return !empty($errors[$key]) ? ' is-invalid' : '';
};
?>

<section class="cp-section">
    <div class="container">
        <div class="cp-card">
            <!-- Panel trái -->
            <aside class="cp-aside">
                <a href="<?php echo url('pages/profile.php'); ?>" class="cp-back" id="cpBackLink">
                    <i class="fas fa-arrow-left"></i> Về trang cá nhân
                </a>

                <div class="cp-shield"><i class="fas fa-shield-halved"></i></div>
                <h1>Đổi mật khẩu</h1>
                <p class="cp-lead">Cập nhật mật khẩu định kỳ giúp tài khoản và đơn hàng của bạn luôn an toàn.</p>

                <div class="cp-user">
                    <img src="<?php echo getCurrentUserAvatar(); ?>" alt="Avatar">
                    <div>
                        <strong><?php echo e($userInfo['full_name']); ?></strong>
                        <small><?php echo e($userInfo['email']); ?></small>
                    </div>
                </div>

                <ul class="cp-tips">
                    <li><i class="fas fa-lock"></i><span>Không dùng lại mật khẩu của các website khác.</span></li>
                    <li><i class="fas fa-shuffle"></i><span>Kết hợp chữ hoa, chữ thường, số và ký tự đặc biệt.</span></li>
                    <li><i class="fas fa-user-secret"></i><span>Không chia sẻ mật khẩu cho bất kỳ ai, kể cả nhân viên WinK.</span></li>
                </ul>
            </aside>

            <!-- Form -->
            <div class="cp-main">
                <h2>Tạo mật khẩu mới</h2>
                <p class="cp-sub">Nhập mật khẩu hiện tại và mật khẩu mới bạn muốn sử dụng.</p>

                <form method="POST" id="changePasswordForm" novalidate>
                    <?php echo csrfField(); ?>

                    <!-- Mật khẩu hiện tại -->
                    <div class="cp-field<?php echo $fieldClass('current'); ?>">
                        <label class="cp-label" for="current_password">Mật khẩu hiện tại</label>
                        <div class="cp-input">
                            <i class="fas fa-lock cp-icon"></i>
                            <input type="password" id="current_password" name="current_password"
                                   placeholder="Nhập mật khẩu hiện tại" autocomplete="current-password" required>
                            <button type="button" class="cp-toggle" data-target="current_password" aria-label="Hiện mật khẩu"><i class="fas fa-eye"></i></button>
                        </div>
                        <?php if (!empty($errors['current'])): ?>
                            <div class="cp-error"><i class="fas fa-circle-exclamation"></i><?php echo e($errors['current']); ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Mật khẩu mới -->
                    <div class="cp-field<?php echo $fieldClass('new'); ?>" id="newField">
                        <label class="cp-label" for="new_password">Mật khẩu mới</label>
                        <div class="cp-input">
                            <i class="fas fa-key cp-icon"></i>
                            <input type="password" id="new_password" name="new_password"
                                   placeholder="Tối thiểu 6 ký tự" autocomplete="new-password" minlength="6" required>
                            <button type="button" class="cp-toggle" data-target="new_password" aria-label="Hiện mật khẩu"><i class="fas fa-eye"></i></button>
                        </div>
                        <?php if (!empty($errors['new'])): ?>
                            <div class="cp-error"><i class="fas fa-circle-exclamation"></i><?php echo e($errors['new']); ?></div>
                        <?php endif; ?>

                        <div class="cp-strength" id="strengthBar" data-level="0">
                            <span></span><span></span><span></span><span></span>
                        </div>
                        <div class="cp-strength-text">
                            <span>Độ mạnh mật khẩu</span>
                            <b id="strengthLabel">—</b>
                        </div>

                        <ul class="cp-rules" id="pwRules">
                            <li data-rule="length"><i class="fas fa-check"></i>Ít nhất 6 ký tự</li>
                            <li data-rule="case"><i class="fas fa-check"></i>Chữ hoa &amp; chữ thường</li>
                            <li data-rule="number"><i class="fas fa-check"></i>Có chữ số</li>
                            <li data-rule="special"><i class="fas fa-check"></i>Có ký tự đặc biệt</li>
                        </ul>
                    </div>

                    <!-- Xác nhận -->
                    <div class="cp-field<?php echo $fieldClass('confirm'); ?>" id="confirmField">
                        <label class="cp-label" for="confirm_password">Xác nhận mật khẩu mới</label>
                        <div class="cp-input">
                            <i class="fas fa-circle-check cp-icon"></i>
                            <input type="password" id="confirm_password" name="confirm_password"
                                   placeholder="Nhập lại mật khẩu mới" autocomplete="new-password" required>
                            <button type="button" class="cp-toggle" data-target="confirm_password" aria-label="Hiện mật khẩu"><i class="fas fa-eye"></i></button>
                        </div>
                        <?php if (!empty($errors['confirm'])): ?>
                            <div class="cp-error"><i class="fas fa-circle-exclamation"></i><?php echo e($errors['confirm']); ?></div>
                        <?php else: ?>
                            <div class="cp-hint" id="matchHint" hidden></div>
                        <?php endif; ?>
                    </div>

                    <div class="cp-actions">
                        <a href="<?php echo url('pages/profile.php'); ?>" class="cp-cancel" id="cpCancelBtn">Hủy</a>
                        <button type="submit" class="cp-submit" id="cpSubmitBtn">
                            <span class="cp-spinner"></span>
                            <i class="fas fa-shield-halved cp-submit-icon"></i>
                            Cập nhật mật khẩu
                        </button>
                    </div>

                    <p class="cp-note">
                        <i class="fas fa-circle-info"></i>
                        Sau khi đổi mật khẩu thành công, bạn sẽ được đăng xuất và cần đăng nhập lại bằng mật khẩu mới.
                    </p>
                </form>
            </div>
        </div>
    </div>
</section>

<script src="<?php echo asset('js/password-form.js'); ?>"></script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
