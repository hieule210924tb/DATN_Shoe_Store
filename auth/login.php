<?php
/**
 * Trang Đăng Nhập - WinK Shoe Store
 */
require_once dirname(__DIR__) . '/config/config.php';

// Nếu đã đăng nhập thì chuyển hướng
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect(url('admin/index.php'));
    } else {
        redirect(url('index.php'));
    }
}

$pageTitle = 'Đăng nhập - WinK Shoe Store';
$errors = [];
$email = '';

// Xử lý form đăng nhập
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate
    if (empty($email)) {
        $errors['email'] = 'Vui lòng nhập email.';
    } elseif (!isValidEmail($email)) {
        $errors['email'] = 'Email không hợp lệ.';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Vui lòng nhập mật khẩu.';
    }
    
    if (empty($errors)) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Kiểm tra tài khoản bị khóa
            if ($user['status'] === 'locked') {
                $errors['general'] = 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.';
            } else {
                // Tạo session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_avatar'] = $user['avatar'];
                
                // Chuyển hướng theo role
                if ($user['role'] === 'admin') {
                    setFlashMessage('success', 'Chào mừng Admin ' . $user['full_name'] . '!');
                    redirect(url('admin/index.php'));
                } else {
                    setFlashMessage('success', 'Đăng nhập thành công! Chào mừng ' . $user['full_name'] . '.');
                    redirect(url('index.php'));
                }
            }
        } else {
            $errors['general'] = 'Email hoặc mật khẩu không chính xác.';
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
        <div class="auth-card">
            <!-- Header -->
            <div class="auth-header">
                <div class="auth-logo">Win<span>K</span></div>
                <p>Đăng nhập vào tài khoản của bạn</p>
            </div>
            
            <!-- Body -->
            <div class="auth-body">
                <?php echo displayFlashMessage(); ?>
                
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger py-2 px-3" style="font-size: 14px;">
                        <i class="fas fa-exclamation-circle me-1"></i> <?php echo e($errors['general']); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="loginForm" novalidate>
                    <!-- Email -->
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text" style="background:var(--gray-100);border-color:var(--gray-300);"><i class="fas fa-envelope" style="color:var(--gray-500);"></i></span>
                            <input type="email" class="form-control <?php echo !empty($errors['email']) ? 'is-invalid' : ''; ?>" 
                                   id="email" name="email" placeholder="Nhập email" 
                                   value="<?php echo e($email); ?>" required>
                            <?php if (!empty($errors['email'])): ?>
                                <div class="invalid-feedback"><?php echo e($errors['email']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Password -->
                    <div class="mb-3">
                        <label for="password" class="form-label">Mật khẩu</label>
                        <div class="input-group">
                            <span class="input-group-text" style="background:var(--gray-100);border-color:var(--gray-300);"><i class="fas fa-lock" style="color:var(--gray-500);"></i></span>
                            <input type="password" class="form-control <?php echo !empty($errors['password']) ? 'is-invalid' : ''; ?>" 
                                   id="password" name="password" placeholder="Nhập mật khẩu" required>
                            <button type="button" class="input-group-text" style="background:var(--gray-100);border-color:var(--gray-300);cursor:pointer;" 
                                    onclick="togglePassword('password', this)">
                                <i class="fas fa-eye" style="color:var(--gray-500);"></i>
                            </button>
                            <?php if (!empty($errors['password'])): ?>
                                <div class="invalid-feedback"><?php echo e($errors['password']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Remember & Forgot -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember" name="remember">
                            <label class="form-check-label" for="remember" style="font-size:13px;">Ghi nhớ đăng nhập</label>
                        </div>
                        <a href="<?php echo url('auth/forgot_password.php'); ?>" style="font-size:13px;">Quên mật khẩu?</a>
                    </div>
                    
                    <!-- Submit -->
                    <button type="submit" class="btn-wink">
                        <i class="fas fa-sign-in-alt"></i> Đăng nhập
                    </button>
                </form>
            </div>
            
            <!-- Footer -->
            <div class="auth-footer">
                Chưa có tài khoản? <a href="<?php echo url('auth/register.php'); ?>">Đăng ký ngay</a>
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
