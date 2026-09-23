/**
 * Main JavaScript - WinK Shoe Store
 * Các hàm JS dùng chung cho toàn bộ website
 */

// Base URL (lấy từ meta tag hoặc hardcode)
const BASE_URL = document.querySelector('meta[name="base-url"]')?.content || '/DATN_Shoe_Store';

// =====================================================
// BACK TO TOP BUTTON
// =====================================================
window.addEventListener('scroll', function() {
    const backToTop = document.getElementById('backToTop');
    if (backToTop) {
        if (window.scrollY > 300) {
            backToTop.classList.add('show');
        } else {
            backToTop.classList.remove('show');
        }
    }
});

// =====================================================
// CART DRAWER
// =====================================================

/**
 * Mở Cart Drawer
 */
function openCartDrawer() {
    document.getElementById('cartDrawer')?.classList.add('active');
    document.getElementById('cartDrawerOverlay')?.classList.add('active');
    document.body.style.overflow = 'hidden';
    loadCartDrawer();
}

/**
 * Đóng Cart Drawer
 */
function closeCartDrawer() {
    document.getElementById('cartDrawer')?.classList.remove('active');
    document.getElementById('cartDrawerOverlay')?.classList.remove('active');
    document.body.style.overflow = '';
}

/**
 * Tải nội dung giỏ hàng vào Drawer
 */
function loadCartDrawer() {
    const body = document.getElementById('cartDrawerBody');
    const footer = document.getElementById('cartDrawerFooter');
    const countEl = document.getElementById('cartDrawerCount');
    
    if (!body) return;
    
    body.innerHTML = '<div class="wink-loading"><div class="wink-spinner"></div></div>';
    
    fetch(BASE_URL + '/ajax/cart_actions.php?action=get_cart')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.items && data.items.length > 0) {
                let html = '';
                data.items.forEach(item => {
                    html += `
                        <div class="cart-item" data-item-id="${item.id}">
                            <div class="cart-item-img">
                                <img src="${item.image}" alt="${item.name}">
                            </div>
                            <div class="cart-item-info">
                                <div class="cart-item-name">${item.name}</div>
                                <div class="cart-item-variant">Size: ${item.size} | Màu: ${item.color}</div>
                                <div class="cart-item-price">${formatPrice(item.price)}</div>
                                <div class="cart-item-qty">
                                    <button onclick="updateCartQuantity(${item.id}, ${item.quantity - 1})">−</button>
                                    <input type="number" value="${item.quantity}" min="1" max="${item.max_qty}" 
                                           onchange="updateCartQuantity(${item.id}, this.value)" readonly>
                                    <button onclick="updateCartQuantity(${item.id}, ${item.quantity + 1})">+</button>
                                </div>
                            </div>
                            <button class="cart-item-remove" onclick="removeCartItem(${item.id})" title="Xóa">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    `;
                });
                body.innerHTML = html;
                footer.style.display = 'block';
                countEl.textContent = data.total_items;
                document.getElementById('cartDrawerTotal').textContent = formatPrice(data.total_amount);
                
                // Cập nhật badge trên navbar
                updateCartBadge(data.total_items);
            } else {
                body.innerHTML = `
                    <div class="cart-drawer-empty">
                        <i class="fas fa-shopping-bag"></i>
                        <p>Giỏ hàng trống</p>
                        <a href="${BASE_URL}/pages/products.php" class="btn-wink btn-wink-sm mt-2">
                            Mua sắm ngay
                        </a>
                    </div>
                `;
                footer.style.display = 'none';
                countEl.textContent = '0';
                updateCartBadge(0);
            }
        })
        .catch(error => {
            console.error('Lỗi tải giỏ hàng:', error);
            body.innerHTML = '<p class="text-center text-muted p-4">Không thể tải giỏ hàng</p>';
        });
}

/**
 * Thêm sản phẩm vào giỏ hàng
 */
function addToCart(productId, variantId, quantity = 1) {
    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('product_id', productId);
    formData.append('variant_id', variantId);
    formData.append('quantity', quantity);
    
    fetch(BASE_URL + '/ajax/cart_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', data.message || 'Đã thêm vào giỏ hàng!');
            openCartDrawer();
        } else {
            showToast('error', data.message || 'Không thể thêm vào giỏ hàng.');
        }
    })
    .catch(error => {
        console.error('Lỗi:', error);
        showToast('error', 'Có lỗi xảy ra. Vui lòng thử lại.');
    });
}

/**
 * Cập nhật số lượng sản phẩm trong giỏ
 */
function updateCartQuantity(cartItemId, quantity) {
    if (quantity < 1) {
        removeCartItem(cartItemId);
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'update');
    formData.append('cart_item_id', cartItemId);
    formData.append('quantity', quantity);
    
    fetch(BASE_URL + '/ajax/cart_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadCartDrawer();
        } else {
            showToast('error', data.message || 'Không thể cập nhật số lượng.');
        }
    })
    .catch(error => {
        console.error('Lỗi:', error);
    });
}

/**
 * Xóa sản phẩm khỏi giỏ hàng
 */
function removeCartItem(cartItemId) {
    const formData = new FormData();
    formData.append('action', 'remove');
    formData.append('cart_item_id', cartItemId);
    
    fetch(BASE_URL + '/ajax/cart_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Đã xóa sản phẩm khỏi giỏ hàng.');
            loadCartDrawer();
        }
    })
    .catch(error => {
        console.error('Lỗi:', error);
    });
}

/**
 * Cập nhật badge số lượng giỏ hàng trên navbar
 */
function updateCartBadge(count) {
    const badges = document.querySelectorAll('.cart-count-badge');
    badges.forEach(badge => {
        badge.textContent = count;
        badge.style.display = count > 0 ? 'flex' : 'none';
    });
}

// =====================================================
// WISHLIST
// =====================================================

/**
 * Toggle Wishlist
 */
function toggleWishlist(productId, btn) {
    const formData = new FormData();
    formData.append('action', 'toggle');
    formData.append('product_id', productId);
    
    fetch(BASE_URL + '/ajax/wishlist_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.action === 'added') {
                btn.classList.add('wishlist-active');
                showToast('success', 'Đã thêm vào danh sách yêu thích!');
            } else {
                btn.classList.remove('wishlist-active');
                showToast('info', 'Đã xóa khỏi danh sách yêu thích.');
            }
            // Cập nhật badge wishlist
            const wishlistBadge = document.querySelector('#navWishlist .badge');
            if (wishlistBadge) {
                wishlistBadge.textContent = data.count;
                wishlistBadge.style.display = data.count > 0 ? 'flex' : 'none';
            }
        } else {
            if (data.login_required) {
                showToast('warning', 'Vui lòng đăng nhập để sử dụng tính năng này.');
                setTimeout(() => {
                    window.location.href = BASE_URL + '/auth/login.php';
                }, 1500);
            } else {
                showToast('error', data.message || 'Có lỗi xảy ra.');
            }
        }
    })
    .catch(error => {
        console.error('Lỗi:', error);
    });
}

// =====================================================
// TOAST NOTIFICATION
// =====================================================

/**
 * Hiển thị toast notification
 * @param {string} type - success, error, warning, info
 * @param {string} message - Nội dung thông báo
 */
function showToast(type, message) {
    // Xóa toast cũ
    const existingToasts = document.querySelectorAll('.wink-toast');
    existingToasts.forEach(t => t.remove());
    
    const icons = {
        success: '<i class="fas fa-check-circle text-success"></i>',
        error: '<i class="fas fa-exclamation-circle text-danger"></i>',
        warning: '<i class="fas fa-exclamation-triangle text-warning"></i>',
        info: '<i class="fas fa-info-circle text-info"></i>'
    };
    
    const bgColors = {
        success: '#d4edda',
        error: '#f8d7da',
        warning: '#fff3cd',
        info: '#d1ecf1'
    };
    
    const toastHtml = `
        <div class="wink-toast" style="position:fixed;top:80px;right:20px;z-index:9999;">
            <div class="toast show" style="background:${bgColors[type] || bgColors.info};border:none;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                <div class="toast-body d-flex align-items-center gap-2" style="padding:14px 18px;font-size:14px;font-weight:500;">
                    ${icons[type] || icons.info}
                    <span>${message}</span>
                    <button type="button" class="btn-close ms-auto" style="font-size:10px;" onclick="this.closest('.wink-toast').remove()"></button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', toastHtml);
    
    // Tự động ẩn sau 3 giây
    setTimeout(() => {
        const toast = document.querySelector('.wink-toast');
        if (toast) {
            toast.style.transition = 'opacity 0.3s ease';
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }
    }, 3000);
}

// =====================================================
// FORMAT HELPERS
// =====================================================

/**
 * Format số tiền VNĐ
 */
function formatPrice(amount) {
    return new Intl.NumberFormat('vi-VN').format(amount) + 'đ';
}

// =====================================================
// CONFIRM DELETE
// =====================================================

/**
 * Xác nhận trước khi xóa
 */
function confirmDelete(message = 'Bạn có chắc chắn muốn xóa?') {
    return confirm(message);
}

// =====================================================
// STAR RATING DISPLAY
// =====================================================

/**
 * Tạo HTML hiển thị sao đánh giá
 */
function renderStars(rating, size = '14px') {
    let html = '';
    for (let i = 1; i <= 5; i++) {
        if (i <= Math.floor(rating)) {
            html += `<i class="fas fa-star" style="color:#ffc107;font-size:${size}"></i>`;
        } else if (i - 0.5 <= rating) {
            html += `<i class="fas fa-star-half-alt" style="color:#ffc107;font-size:${size}"></i>`;
        } else {
            html += `<i class="far fa-star" style="color:#ddd;font-size:${size}"></i>`;
        }
    }
    return html;
}

// =====================================================
// INIT
// =====================================================
document.addEventListener('DOMContentLoaded', function() {
    // Đóng cart drawer khi nhấn Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeCartDrawer();
        }
    });
});
