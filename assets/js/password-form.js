/**
 * WinK - Tiện ích cho form mật khẩu (đổi / quên mật khẩu)
 * - Hiện/ẩn mật khẩu (.cp-toggle[data-target])
 * - Đo độ mạnh mật khẩu (#new_password, #strengthBar, #strengthLabel, #pwRules)
 * - Kiểm tra khớp mật khẩu xác nhận (#confirm_password, #matchHint, #confirmField)
 * - Xóa lỗi khi gõ lại, trạng thái loading cho nút .cp-submit
 */
(function () {
    // Hiện / ẩn mật khẩu
    document.querySelectorAll('.cp-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.dataset.target);
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
            btn.setAttribute('aria-label', show ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
            input.focus();
        });
    });

    // Xóa trạng thái lỗi khi người dùng gõ lại
    document.querySelectorAll('.cp-field input').forEach(function (input) {
        input.addEventListener('input', function () {
            var field = input.closest('.cp-field');
            field.classList.remove('is-invalid');
            var err = field.querySelector('.cp-error');
            if (err) err.remove();
        });
    });

    // Loading khi submit
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var btn = e.submitter && e.submitter.classList.contains('cp-submit')
                ? e.submitter
                : form.querySelector('.cp-submit');
            if (!btn) return;
            // Trì hoãn disable để giá trị name/value của nút vẫn được gửi đi
            setTimeout(function () {
                if (e.defaultPrevented) return;
                btn.classList.add('loading');
                btn.disabled = true;
            }, 0);
        });
    });

    var newPw = document.getElementById('new_password');
    var confirmPw = document.getElementById('confirm_password');
    if (!newPw) return;

    var bar = document.getElementById('strengthBar');
    var label = document.getElementById('strengthLabel');
    var rules = document.querySelectorAll('#pwRules li');
    var matchHint = document.getElementById('matchHint');
    var confirmField = document.getElementById('confirmField');
    var levels = [
        { text: '—', color: '' },
        { text: 'Yếu', color: 'var(--danger)' },
        { text: 'Trung bình', color: 'var(--warning)' },
        { text: 'Khá', color: '#84cc16' },
        { text: 'Mạnh', color: 'var(--success)' }
    ];

    function checkStrength() {
        if (!bar) return;
        var v = newPw.value;
        var checks = {
            length: v.length >= 6,
            case: /[a-z]/.test(v) && /[A-Z]/.test(v),
            number: /\d/.test(v),
            special: /[^A-Za-z0-9]/.test(v)
        };
        var score = 0;
        rules.forEach(function (li) {
            var ok = checks[li.dataset.rule];
            li.classList.toggle('ok', ok);
            if (ok) score++;
        });
        if (v.length && !checks.length) score = 1;
        if (!v.length) score = 0;
        bar.dataset.level = score;
        label.textContent = levels[score].text;
        label.style.color = levels[score].color;
    }

    function checkMatch() {
        if (!matchHint || !confirmPw) return;
        if (!confirmPw.value) {
            matchHint.hidden = true;
            confirmField.classList.remove('is-valid');
            return;
        }
        var ok = confirmPw.value === newPw.value;
        matchHint.hidden = false;
        matchHint.className = 'cp-hint ' + (ok ? 'ok' : 'bad');
        matchHint.innerHTML = ok
            ? '<i class="fas fa-circle-check"></i>Mật khẩu xác nhận khớp'
            : '<i class="fas fa-circle-xmark"></i>Mật khẩu xác nhận chưa khớp';
        confirmField.classList.toggle('is-valid', ok);
    }

    newPw.addEventListener('input', function () { checkStrength(); checkMatch(); });
    if (confirmPw) confirmPw.addEventListener('input', checkMatch);
})();
