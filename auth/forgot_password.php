<?php
/**
 * Quên mật khẩu - WinK Shoe Store
 *
 * Quy trình 3 bước (trạng thái lưu trong $_SESSION['pw_reset']):
 *   1. email  : Nhập email -> sinh OTP 6 số, lưu hash vào users.reset_token, gửi qua Gmail SMTP
 *   2. otp    : Nhập OTP (tối đa OTP_MAX_ATTEMPTS lần, hiệu lực OTP_EXPIRE_MINUTES phút)
 *   3. reset  : Đặt mật khẩu mới
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . '/includes/mailer.php';

// Đã đăng nhập thì dùng chức năng đổi mật khẩu
if (isLoggedIn()) {
    redirect(url('auth/change_password.php'));
}

$pdo    = getDBConnection();
$self   = url('auth/forgot_password.php');
$errors = [];
$state  = $_SESSION['pw_reset'] ?? null;

/** Hủy quy trình, quay lại bước 1 */
function resetFlow() {
    unset($_SESSION['pw_reset']);
}

/** Che bớt email: nguyenvana@gmail.com -> ng*******a@gmail.com */
function maskEmail($email) {
    [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
    $len = mb_strlen($name);
    if ($len <= 2) {
        return mb_substr($name, 0, 1) . '*@' . $domain;
    }
    return mb_substr($name, 0, 2) . str_repeat('*', max(1, $len - 3)) . mb_substr($name, -1) . '@' . $domain;
}

/**
 * Sinh và gửi OTP cho user trong $state
 * - Nếu email không tồn tại / bị khóa: không gửi gì nhưng vẫn coi như thành công (tránh dò email)
 * - Nếu vừa gửi trong OTP_RESEND_SECONDS giây: giữ mã cũ, không gửi lại (chống spam)
 */
function issueOtp(PDO $pdo, array &$state, &$error) {
    if (!empty($state['user_id'])) {
        $recentSeconds = OTP_EXPIRE_MINUTES * 60 - OTP_RESEND_SECONDS;
        $stmt = $pdo->prepare("SELECT reset_token IS NOT NULL AND reset_token_expiry > DATE_ADD(NOW(), INTERVAL ? SECOND) AS recent FROM users WHERE id = ?");
        $stmt->execute([$recentSeconds, $state['user_id']]);
        $recent = (bool) $stmt->fetchColumn();

        if (!$recent) {
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?");
            $stmt->execute([password_hash($otp, PASSWORD_DEFAULT), OTP_EXPIRE_MINUTES, $state['user_id']]);

            $mailError = null;
            $sent = sendMail(
                $state['email'],
                $state['name'],
                'Mã xác thực đặt lại mật khẩu - ' . STORE_NAME,
                buildOtpEmail($state['name'], $otp),
                $mailError
            );

            if (!$sent) {
                $pdo->prepare("UPDATE users SET reset_token = NULL, reset_token_expiry = NULL WHERE id = ?")
                    ->execute([$state['user_id']]);
                $error = isMailConfigured()
                    ? 'Không thể gửi email lúc này. Vui lòng thử lại sau.'
                    : 'Hệ thống chưa cấu hình gửi email. Vui lòng liên hệ quản trị viên.';
                return false;
            }
        }
    }

    $state['last_sent'] = time();
    $state['attempts']  = 0;
    return true;
}

// ---------- Bắt đầu lại ----------
if (isset($_GET['restart'])) {
    resetFlow();
    redirect($self);
}

// ---------- Phiên đặt lại mật khẩu (bước 3) hết hạn ----------
if ($state && $state['step'] === 'reset' && time() - $state['verified_at'] > OTP_EXPIRE_MINUTES * 60) {
    resetFlow();
    setFlashMessage('error', 'Phiên đặt lại mật khẩu đã hết hạn. Vui lòng thực hiện lại.');
    redirect($self);
}

// ---------- Xử lý POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Phiên làm việc không hợp lệ, vui lòng thử lại.');
        redirect($self);
    }

    $action = $_POST['action'] ?? '';

    // Bước 1: gửi mã
    if ($action === 'send') {
        $email = trim($_POST['email'] ?? '');

        if ($email === '') {
            $errors['email'] = 'Vui lòng nhập email.';
        } elseif (!isValidEmail($email)) {
            $errors['email'] = 'Email không hợp lệ.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id, full_name, status FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            $newState = [
                'email'       => $email,
                'user_id'     => ($user && $user['status'] === 'active') ? (int) $user['id'] : null,
                'name'        => $user['full_name'] ?? '',
                'step'        => 'otp',
                'attempts'    => 0,
                'last_sent'   => 0,
                'verified_at' => 0,
            ];

            $mailError = null;
            if (issueOtp($pdo, $newState, $mailError)) {
                $_SESSION['pw_reset'] = $newState;
                redirect($self);
            }
            $errors['general'] = $mailError;
        }
    }

    // Bước 2: gửi lại mã
    elseif ($action === 'resend') {
        if (!$state || $state['step'] !== 'otp') {
            redirect($self);
        }
        $wait = OTP_RESEND_SECONDS - (time() - $state['last_sent']);
        if ($wait > 0) {
            $errors['otp'] = "Vui lòng đợi {$wait} giây trước khi gửi lại mã.";
        } else {
            $mailError = null;
            if (issueOtp($pdo, $state, $mailError)) {
                $_SESSION['pw_reset'] = $state;
                setFlashMessage('success', 'Đã gửi lại mã xác thực mới. Vui lòng kiểm tra email.');
                redirect($self);
            }
            $errors['general'] = $mailError;
        }
    }

    // Bước 2: xác thực mã
    elseif ($action === 'verify') {
        if (!$state || $state['step'] !== 'otp') {
            redirect($self);
        }
        $otp = preg_replace('/\D/', '', $_POST['otp'] ?? '');

        if (strlen($otp) !== 6) {
            $errors['otp'] = 'Vui lòng nhập đủ 6 chữ số.';
        } else {
            $state['attempts']++;
            $verified = false;
            $expired  = false;

            if (!empty($state['user_id'])) {
                $stmt = $pdo->prepare("SELECT reset_token, reset_token_expiry > NOW() AS valid FROM users WHERE id = ?");
                $stmt->execute([$state['user_id']]);
                $row = $stmt->fetch();

                if (!$row || empty($row['reset_token']) || !$row['valid']) {
                    $expired = true;
                } elseif (password_verify($otp, $row['reset_token'])) {
                    $verified = true;
                }
            }

            if ($verified) {
                // OTP chỉ dùng 1 lần
                $pdo->prepare("UPDATE users SET reset_token = NULL, reset_token_expiry = NULL WHERE id = ?")
                    ->execute([$state['user_id']]);
                session_regenerate_id(true);
                $state['step']        = 'reset';
                $state['verified_at'] = time();
                $_SESSION['pw_reset'] = $state;
                redirect($self);
            }

            if ($expired && !empty($state['user_id'])) {
                $state['attempts']--; // không tính lần thử khi mã đã hết hạn
                $errors['otp'] = 'Mã xác thực đã hết hạn. Vui lòng bấm "Gửi lại mã".';
            } elseif ($state['attempts'] >= OTP_MAX_ATTEMPTS) {
                if (!empty($state['user_id'])) {
                    $pdo->prepare("UPDATE users SET reset_token = NULL, reset_token_expiry = NULL WHERE id = ?")
                        ->execute([$state['user_id']]);
                }
                resetFlow();
                setFlashMessage('error', 'Bạn đã nhập sai mã quá ' . OTP_MAX_ATTEMPTS . ' lần. Vui lòng yêu cầu mã mới.');
                redirect($self);
            } else {
                $left = OTP_MAX_ATTEMPTS - $state['attempts'];
                $errors['otp'] = "Mã xác thực không đúng. Bạn còn {$left} lần thử.";
            }
            $_SESSION['pw_reset'] = $state;
        }
    }

    // Bước 3: đặt mật khẩu mới
    elseif ($action === 'reset') {
        if (!$state || $state['step'] !== 'reset' || empty($state['user_id'])) {
            resetFlow();
            redirect($self);
        }
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($newPassword === '') {
            $errors['new'] = 'Vui lòng nhập mật khẩu mới.';
        } elseif (!isValidPassword($newPassword)) {
            $errors['new'] = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
        }
        if ($confirmPassword === '') {
            $errors['confirm'] = 'Vui lòng xác nhận mật khẩu mới.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors['confirm'] = 'Mật khẩu xác nhận không khớp.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $state['user_id']]);

            resetFlow();
            setFlashMessage('success', 'Đặt lại mật khẩu thành công! Vui lòng đăng nhập bằng mật khẩu mới.');
            redirect(url('auth/login.php'));
        }
    }
}

$state = $_SESSION['pw_reset'] ?? null;
$step  = $state['step'] ?? 'email';
$stepIndex = ['email' => 1, 'otp' => 2, 'reset' => 3][$step];

$otpRemaining    = $state ? max(0, $state['last_sent'] + OTP_EXPIRE_MINUTES * 60 - time()) : 0;
$resendRemaining = $state ? max(0, $state['last_sent'] + OTP_RESEND_SECONDS - time()) : 0;

$fieldClass = function ($key) use ($errors) {
    return !empty($errors[$key]) ? ' is-invalid' : '';
};

$stepMeta = [
    1 => ['icon' => 'fa-envelope-open-text', 'title' => 'Quên mật khẩu?',   'lead' => 'Đừng lo! Nhập email đã đăng ký, chúng tôi sẽ gửi mã xác thực để bạn đặt lại mật khẩu.'],
    2 => ['icon' => 'fa-shield-halved',     'title' => 'Xác thực email',    'lead' => 'Nhập mã gồm 6 chữ số vừa được gửi tới hộp thư của bạn.'],
    3 => ['icon' => 'fa-key',               'title' => 'Mật khẩu mới',      'lead' => 'Gần xong rồi! Hãy tạo một mật khẩu mạnh mà bạn chưa từng sử dụng.'],
];
$meta = $stepMeta[$stepIndex];

$flash = getFlashMessage();
$_v = '?v=' . time();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <script>
        (function () {
            var t = localStorage.getItem('wink-theme');
            if (t === 'dark' || (!t && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Khôi phục mật khẩu tài khoản WinK Shoe Store bằng mã xác thực gửi qua email.">
    <meta name="robots" content="noindex">
    <title>Quên mật khẩu - WinK Shoe Store</title>
    <link rel="icon" type="image/x-icon" href="<?php echo asset('images/logo/favicon.ico'); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="<?php echo asset('css/style.css') . $_v; ?>" rel="stylesheet">
    <link href="<?php echo asset('css/change_password.css') . $_v; ?>" rel="stylesheet">
    <link href="<?php echo asset('css/forgot_password.css') . $_v; ?>" rel="stylesheet">
</head>
<body class="fp-body">
<main class="cp-section fp-page">
    <div class="container">
        <div class="cp-card">
            <!-- Panel trái -->
            <aside class="cp-aside">
                <a href="<?php echo url('index.php'); ?>" class="fp-logo">Win<span>K</span></a>

                <div class="cp-shield"><i class="fas <?php echo $meta['icon']; ?>"></i></div>
                <h1><?php echo $meta['title']; ?></h1>
                <p class="cp-lead"><?php echo $meta['lead']; ?></p>

                <ol class="fp-steps">
                    <?php
                    $steps = [1 => ['Nhập email', 'Email đã đăng ký'], 2 => ['Xác thực OTP', 'Mã 6 số qua email'], 3 => ['Mật khẩu mới', 'Tạo mật khẩu an toàn']];
                    foreach ($steps as $i => [$title, $desc]):
                        $cls = $i < $stepIndex ? 'done' : ($i === $stepIndex ? 'active' : '');
                    ?>
                        <li class="<?php echo $cls; ?>">
                            <span class="fp-step-dot"><?php echo $i < $stepIndex ? '<i class="fas fa-check"></i>' : $i; ?></span>
                            <span><strong><?php echo $title; ?></strong><small><?php echo $desc; ?></small></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </aside>

            <!-- Nội dung -->
            <div class="cp-main">
                <?php if ($flash): ?>
                    <?php $ft = $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>
                    <div class="fp-alert <?php echo e($ft); ?>">
                        <i class="fas <?php echo $ft === 'success' ? 'fa-circle-check' : ($ft === 'danger' ? 'fa-circle-exclamation' : 'fa-circle-info'); ?>"></i>
                        <span><?php echo e($flash['message']); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors['general'])): ?>
                    <div class="fp-alert danger">
                        <i class="fas fa-circle-exclamation"></i><span><?php echo e($errors['general']); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($step === 'email'): ?>
                    <!-- ========== BƯỚC 1: NHẬP EMAIL ========== -->
                    <h2>Khôi phục tài khoản</h2>
                    <p class="cp-sub">Nhập địa chỉ email bạn đã dùng để đăng ký tài khoản WinK.</p>

                    <form method="POST" id="fpEmailForm" novalidate>
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="send">

                        <div class="cp-field<?php echo $fieldClass('email'); ?>">
                            <label class="cp-label" for="email">Email</label>
                            <div class="cp-input">
                                <i class="fas fa-envelope cp-icon"></i>
                                <input type="email" id="email" name="email" placeholder="vidu@gmail.com"
                                       value="<?php echo e($_POST['email'] ?? ''); ?>" autocomplete="email" autofocus required>
                            </div>
                            <?php if (!empty($errors['email'])): ?>
                                <div class="cp-error"><i class="fas fa-circle-exclamation"></i><?php echo e($errors['email']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="cp-actions">
                            <button type="submit" class="cp-submit" id="fpSendBtn">
                                <span class="cp-spinner"></span>
                                <i class="fas fa-paper-plane cp-submit-icon"></i>
                                Gửi mã xác thực
                            </button>
                        </div>
                    </form>

                <?php elseif ($step === 'otp'): ?>
                    <!-- ========== BƯỚC 2: NHẬP OTP ========== -->
                    <h2>Nhập mã xác thực</h2>
                    <p class="cp-sub">Không thấy email? Hãy kiểm tra cả thư mục <strong>Spam / Quảng cáo</strong>.</p>

                    <div class="fp-sent">
                        <div class="fp-sent-icon"><i class="fas fa-envelope"></i></div>
                        <div>
                            <p>Mã đã được gửi tới</p>
                            <strong><?php echo e(maskEmail($state['email'])); ?></strong>
                        </div>
                        <a href="<?php echo $self; ?>?restart=1" id="fpChangeEmail">Đổi email</a>
                    </div>

                    <form method="POST" id="fpVerifyForm" novalidate>
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="verify">
                        <input type="hidden" name="otp" id="otpValue">

                        <div class="cp-field<?php echo $fieldClass('otp'); ?>">
                            <label class="cp-label">Mã OTP</label>
                            <div class="fp-otp" id="otpBoxes">
                                <?php for ($i = 0; $i < 6; $i++): ?>
                                    <input type="text" inputmode="numeric" maxlength="1" pattern="\d*"
                                           autocomplete="<?php echo $i === 0 ? 'one-time-code' : 'off'; ?>"
                                           aria-label="Chữ số thứ <?php echo $i + 1; ?>" id="otp<?php echo $i; ?>">
                                <?php endfor; ?>
                            </div>
                            <?php if (!empty($errors['otp'])): ?>
                                <div class="cp-error"><i class="fas fa-circle-exclamation"></i><?php echo e($errors['otp']); ?></div>
                            <?php endif; ?>

                            <div class="fp-otp-meta">
                                <span class="fp-timer" id="otpTimer" data-remaining="<?php echo $otpRemaining; ?>">
                                    <i class="far fa-clock me-1"></i>Mã hết hạn sau <b>--:--</b>
                                </span>
                                <button type="submit" form="fpResendForm" class="fp-resend" id="fpResendBtn"
                                        data-remaining="<?php echo $resendRemaining; ?>">Gửi lại mã</button>
                            </div>
                        </div>

                        <div class="cp-actions">
                            <button type="submit" class="cp-submit" id="fpVerifyBtn">
                                <span class="cp-spinner"></span>
                                <i class="fas fa-shield-halved cp-submit-icon"></i>
                                Xác nhận
                            </button>
                        </div>
                    </form>

                    <form method="POST" id="fpResendForm">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="resend">
                    </form>

                <?php else: ?>
                    <!-- ========== BƯỚC 3: MẬT KHẨU MỚI ========== -->
                    <h2>Tạo mật khẩu mới</h2>
                    <p class="cp-sub">Đặt lại mật khẩu cho <strong><?php echo e(maskEmail($state['email'])); ?></strong></p>

                    <form method="POST" id="fpResetForm" novalidate>
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="reset">
                        <input type="email" name="username" value="<?php echo e($state['email']); ?>" autocomplete="username" hidden>

                        <div class="cp-field<?php echo $fieldClass('new'); ?>" id="newField">
                            <label class="cp-label" for="new_password">Mật khẩu mới</label>
                            <div class="cp-input">
                                <i class="fas fa-key cp-icon"></i>
                                <input type="password" id="new_password" name="new_password"
                                       placeholder="Tối thiểu 6 ký tự" autocomplete="new-password" minlength="6" autofocus required>
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
                            <a href="<?php echo $self; ?>?restart=1" class="cp-cancel" id="fpCancelBtn">Hủy</a>
                            <button type="submit" class="cp-submit" id="fpResetBtn">
                                <span class="cp-spinner"></span>
                                <i class="fas fa-check cp-submit-icon"></i>
                                Đặt lại mật khẩu
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

                <p class="fp-alt">
                    <i class="fas fa-arrow-left me-1"></i>
                    Nhớ ra mật khẩu? <a href="<?php echo url('auth/login.php'); ?>" id="fpLoginLink">Quay lại đăng nhập</a>
                </p>
            </div>
        </div>
    </div>
</main>

<script src="<?php echo asset('js/password-form.js') . $_v; ?>"></script>
<?php if ($step === 'otp'): ?>
<script>
(function () {
    var boxes = Array.prototype.slice.call(document.querySelectorAll('#otpBoxes input'));
    var hidden = document.getElementById('otpValue');
    var form = document.getElementById('fpVerifyForm');

    function sync() {
        var v = boxes.map(function (b) { return b.value; }).join('');
        hidden.value = v;
        boxes.forEach(function (b) { b.classList.toggle('filled', b.value !== ''); });
        return v;
    }

    function fillFrom(index, text) {
        var digits = text.replace(/\D/g, '').split('');
        for (var i = index; i < boxes.length && digits.length; i++) {
            boxes[i].value = digits.shift();
        }
        var next = boxes.find(function (b) { return b.value === ''; }) || boxes[boxes.length - 1];
        next.focus();
        if (sync().length === 6) form.requestSubmit();
    }

    boxes.forEach(function (box, i) {
        box.addEventListener('input', function () {
            var v = box.value.replace(/\D/g, '');
            if (v.length > 1) { fillFrom(i, v); return; }
            box.value = v;
            if (v && i < boxes.length - 1) boxes[i + 1].focus();
            if (sync().length === 6) form.requestSubmit();
        });
        box.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && !box.value && i > 0) {
                boxes[i - 1].value = '';
                boxes[i - 1].focus();
                sync();
                e.preventDefault();
            } else if (e.key === 'ArrowLeft' && i > 0) {
                boxes[i - 1].focus();
            } else if (e.key === 'ArrowRight' && i < boxes.length - 1) {
                boxes[i + 1].focus();
            }
        });
        box.addEventListener('focus', function () { box.select(); });
        box.addEventListener('paste', function (e) {
            e.preventDefault();
            fillFrom(i, (e.clipboardData || window.clipboardData).getData('text'));
        });
    });
    boxes[0].focus();

    form.addEventListener('submit', function (e) {
        if (sync().length !== 6) {
            e.preventDefault();
            var empty = boxes.find(function (b) { return b.value === ''; });
            if (empty) empty.focus();
        }
    });

    // Đếm ngược thời gian hết hạn mã
    var timer = document.getElementById('otpTimer');
    var expire = Date.now() + parseInt(timer.dataset.remaining, 10) * 1000;
    // Đếm ngược nút gửi lại
    var resend = document.getElementById('fpResendBtn');
    var resendAt = Date.now() + parseInt(resend.dataset.remaining, 10) * 1000;

    function fmt(s) {
        return String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
    }

    function tick() {
        var left = Math.max(0, Math.round((expire - Date.now()) / 1000));
        if (left > 0) {
            timer.innerHTML = '<i class="far fa-clock me-1"></i>Mã hết hạn sau <b>' + fmt(left) + '</b>';
        } else {
            timer.classList.add('expired');
            timer.innerHTML = '<i class="far fa-clock me-1"></i><b>Mã đã hết hạn</b>';
        }

        var wait = Math.max(0, Math.round((resendAt - Date.now()) / 1000));
        resend.disabled = wait > 0;
        resend.textContent = wait > 0 ? 'Gửi lại mã (' + wait + 's)' : 'Gửi lại mã';
    }
    tick();
    setInterval(tick, 1000);
})();
</script>
<?php endif; ?>
</body>
</html>
