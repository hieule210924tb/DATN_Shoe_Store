<?php
/**
 * Trang Đăng Ký - WinK Shoe Store
 */
require_once dirname(__DIR__) . '/config/config.php';

// Nếu đã đăng nhập thì chuyển hướng
if (isLoggedIn()) {
    redirect(url('index.php'));
}

$pageTitle = 'Đăng ký - WinK Shoe Store';
$errors = [];
$old = [
    'full_name' => '',
    'email' => '',
    'phone' => ''
];

// Xử lý form đăng ký
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $old['phone'] = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate họ tên
    if (empty($old['full_name'])) {
        $errors['full_name'] = 'Vui lòng nhập họ tên.';
    } elseif (mb_strlen($old['full_name']) < 2 || mb_strlen($old['full_name']) > 100) {
        $errors['full_name'] = 'Họ tên phải từ 2 đến 100 ký tự.';
    }
    
    // Validate email
    if (empty($old['email'])) {
        $errors['email'] = 'Vui lòng nhập email.';
    } elseif (!isValidEmail($old['email'])) {
        $errors['email'] = 'Email không hợp lệ.';
    } else {
        // Kiểm tra email đã tồn tại
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$old['email']]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Email này đã được sử dụng.';
        }
    }
    
    // Validate số điện thoại
    if (empty($old['phone'])) {
        $errors['phone'] = 'Vui lòng nhập số điện thoại.';
    } elseif (!isValidPhone($old['phone'])) {
        $errors['phone'] = 'Số điện thoại không hợp lệ (VD: 0912345678).';
    }
    
    // Validate mật khẩu
    if (empty($password)) {
        $errors['password'] = 'Vui lòng nhập mật khẩu.';
    } elseif (!isValidPassword($password)) {
        $errors['password'] = 'Mật khẩu phải có ít nhất 6 ký tự.';
    }
    
    // Validate xác nhận mật khẩu
    if (empty($confirmPassword)) {
        $errors['confirm_password'] = 'Vui lòng xác nhận mật khẩu.';
    } elseif ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Mật khẩu xác nhận không khớp.';
    }
    
    // Nếu không có lỗi thì tạo tài khoản
    if (empty($errors)) {
        $pdo = getDBConnection();
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (full_name, email, password, phone, role, status) 
            VALUES (?, ?, ?, ?, 'user', 'active')
        ");
        
        try {
            $stmt->execute([$old['full_name'], $old['email'], $hashedPassword, $old['phone']]);
            setFlashMessage('success', 'Đăng ký thành công! Vui lòng đăng nhập.');
            redirect(url('auth/login.php'));
        } catch (PDOException $e) {
            $errors['general'] = 'Có lỗi xảy ra. Vui lòng thử lại.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="<?php echo asset('css/style.css'); ?>" rel="stylesheet">
    <link href="<?php echo asset('css/auth.css'); ?>" rel="stylesheet">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card" style="max-width: 520px;">
            <!-- Header -->
            <div class="auth-header">
                <div class="auth-logo">Win<span>K</span></div>
                <p>Tạo tài khoản mới để mua sắm</p>
            </div>
            
            <!-- Body -->
            <div class="auth-body">
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger py-2 px-3" style="font-size: 14px;">
                        <i class="fas fa-exclamation-circle me-1"></i> <?php echo e($errors['general']); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="registerForm" novalidate>
                    <!-- Họ tên -->
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Họ và tên <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text" style="background:var(--gray-100);border-color:var(--gray-300);"><i class="fas fa-user" style="color:var(--gray-500);"></i></span>
                            <input type="text" class="form-control <?php echo !empty($errors['full_name']) ? 'is-invalid' : ''; ?>" 
                                   id="full_name" name="full_name" placeholder="Nhập họ và tên" 
                                   value="<?php echo e($old['full_name']); ?>" required>
                            <?php if (!empty($errors['full_name'])): ?>
                                <div class="invalid-feedback"><?php echo e($errors['full_name']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Email -->
                    <div class="mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text" style="background:var(--gray-100);border-color:var(--gray-300);"><i class="fas fa-envelope" style="color:var(--gray-500);"></i></span>
                            <input type="email" class="form-control <?php echo !empty($errors['email']) ? 'is-invalid' : ''; ?>" 
                                   id="email" name="email" placeholder="Nhập email" 
                                   value="<?php echo e($old['email']); ?>" required>
                            <?php if (!empty($errors['email'])): ?>
                                <div class="invalid-feedback"><?php echo e($errors['email']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Số điện thoại -->
                    <div class="mb-3">
                        <label for="phone" class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text" style="background:var(--gray-100);border-color:var(--gray-300);"><i class="fas fa-phone" style="color:var(--gray-500);"></i></span>
                            <input type="tel" class="form-control <?php echo !empty($errors['phone']) ? 'is-invalid' : ''; ?>" 
                                   id="phone" name="phone" placeholder="VD: 0912345678" 
                                   value="<?php echo e($old['phone']); ?>" required>
                            <?php if (!empty($errors['phone'])): ?>
                                <div class="invalid-feedback"><?php echo e($errors['phone']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Mật khẩu -->
                    <div class="mb-3">
                        <label for="password" class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text" style="background:var(--gray-100);border-color:var(--gray-300);"><i class="fas fa-lock" style="color:var(--gray-500);"></i></span>
                            <input type="password" class="form-control <?php echo !empty($errors['password']) ? 'is-invalid' : ''; ?>" 
                                   id="password" name="password" placeholder="Tối thiểu 6 ký tự" required>
                            <button type="button" class="input-group-text" style="background:var(--gray-100);border-color:var(--gray-300);cursor:pointer;" 
                                    onclick="togglePassword('password', this)">
                                <i class="fas fa-eye" style="color:var(--gray-500);"></i>
                            </button>
                            <?php if (!empty($errors['password'])): ?>
                                <div class="invalid-feedback"><?php echo e($errors['password']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Xác nhận mật khẩu -->
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Xác nhận mật khẩu <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text" style="background:var(--gray-100);border-color:var(--gray-300);"><i class="fas fa-lock" style="color:var(--gray-500);"></i></span>
                            <input type="password" class="form-control <?php echo !empty($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                   id="confirm_password" name="confirm_password" placeholder="Nhập lại mật khẩu" required>
                            <button type="button" class="input-group-text" style="background:var(--gray-100);border-color:var(--gray-300);cursor:pointer;" 
                                    onclick="togglePassword('confirm_password', this)">
                                <i class="fas fa-eye" style="color:var(--gray-500);"></i>
                            </button>
                            <?php if (!empty($errors['confirm_password'])): ?>
                                <div class="invalid-feedback"><?php echo e($errors['confirm_password']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Submit -->
                    <button type="submit" class="btn-wink">
                        <i class="fas fa-user-plus"></i> Đăng ký
                    </button>
                </form>
            </div>
            
            <!-- Footer -->
            <div class="auth-footer">
                Đã có tài khoản? <a href="<?php echo url('auth/login.php'); ?>">Đăng nhập</a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>
