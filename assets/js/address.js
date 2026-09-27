/**
 * Address Selector - WinK Shoe Store
 * Dữ liệu: 34 tỉnh/thành + xã/phường theo Nghị quyết 202/2025/QH15
 * API: /ajax/address_api.php (serve từ file JSON local)
 */

const ADDRESS_API_BASE = BASE_URL + '/ajax/address_api.php';

/**
 * Khởi tạo bộ chọn địa chỉ 2 cấp
 * @param {Object} options
 *   provinceSelectId  - id <select> tỉnh/thành
 *   wardSelectId      - id <select> xã/phường
 *   provinceHiddenId  - id <input hidden> lưu tên tỉnh
 *   wardHiddenId      - id <input hidden> lưu tên xã
 *   defaultProvince   - tên tỉnh hiện tại (preselect)
 *   defaultWard       - tên xã hiện tại (preselect)
 */
function initAddressSelector(options = {}) {
    const {
        provinceSelectId = 'select_province',
        wardSelectId     = 'select_ward',
        provinceHiddenId = 'hidden_city',
        wardHiddenId     = 'hidden_ward',
        defaultProvince  = '',
        defaultWard      = '',
    } = options;

    const selProvince = document.getElementById(provinceSelectId);
    const selWard     = document.getElementById(wardSelectId);
    const hidProvince = document.getElementById(provinceHiddenId);
    const hidWard     = document.getElementById(wardHiddenId);

    if (!selProvince || !selWard) return;

    // ── Load 34 tỉnh/thành ──────────────────────────────
    selProvince.innerHTML = '<option value="">-- Đang tải... --</option>';
    selProvince.disabled = true;

    fetch(`${ADDRESS_API_BASE}?action=provinces`)
        .then(r => r.json())
        .then(provinces => {
            selProvince.innerHTML = '<option value="">-- Chọn Tỉnh/Thành phố --</option>';

            provinces.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.code;
                opt.textContent = p.name_with_type;
                opt.dataset.name = p.name_with_type;
                // Preselect nếu trùng tên (so sánh linh hoạt)
                if (defaultProvince && (
                    p.name_with_type === defaultProvince ||
                    p.name === defaultProvince
                )) {
                    opt.selected = true;
                }
                selProvince.appendChild(opt);
            });

            selProvince.disabled = false;

            // Nếu có default → load xã ngay
            const selectedOpt = selProvince.querySelector('option:checked');
            if (selectedOpt && selectedOpt.value) {
                if (hidProvince) hidProvince.value = selectedOpt.dataset.name;
                loadWards(selectedOpt.value, selWard, hidWard, defaultWard);
            }
        })
        .catch(() => {
            selProvince.innerHTML = '<option value="">Không tải được dữ liệu</option>';
            selProvince.disabled = false;
        });

    // ── Khi chọn tỉnh → load xã ─────────────────────────
    selProvince.addEventListener('change', function () {
        const code = this.value;
        const name = this.options[this.selectedIndex]?.dataset.name || '';

        if (hidProvince) hidProvince.value = name;
        if (hidWard)     hidWard.value = '';

        selWard.innerHTML = '<option value="">-- Đang tải... --</option>';
        selWard.disabled = true;

        if (!code) {
            selWard.innerHTML = '<option value="">-- Chọn Tỉnh trước --</option>';
            return;
        }

        loadWards(code, selWard, hidWard, '');
    });

    // ── Khi chọn xã ─────────────────────────────────────
    selWard.addEventListener('change', function () {
        const name = this.options[this.selectedIndex]?.dataset.name || '';
        if (hidWard) hidWard.value = name;
    });
}

/**
 * Load danh sách xã/phường theo mã tỉnh
 */
function loadWards(provinceCode, selWard, hidWard, defaultWard) {
    fetch(`${ADDRESS_API_BASE}?action=wards&code=${provinceCode}`)
        .then(r => r.json())
        .then(wards => {
            selWard.innerHTML = '<option value="">-- Chọn Xã/Phường/Thị trấn --</option>';

            wards.forEach(w => {
                const opt = document.createElement('option');
                opt.value = w.code;
                opt.textContent = w.name_with_type;
                opt.dataset.name = w.name_with_type;
                // Preselect nếu trùng tên
                if (defaultWard && (
                    w.name_with_type === defaultWard ||
                    w.name === defaultWard
                )) {
                    opt.selected = true;
                }
                selWard.appendChild(opt);
            });

            selWard.disabled = false;

            // Cập nhật hidden nếu có preselect
            if (defaultWard && hidWard) {
                const sel = selWard.querySelector('option:checked');
                if (sel && sel.value) hidWard.value = sel.dataset.name;
            }
        })
        .catch(() => {
            selWard.innerHTML = '<option value="">Không tải được dữ liệu</option>';
            selWard.disabled = false;
        });
}
