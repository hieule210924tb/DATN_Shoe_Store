<?php
/** Footer - WinK Shoe Store */
?>
    </main>

    <!-- Footer -->
    <footer class="wink-footer">
        <div class="container">
            <div class="row g-4">
                <!-- Thông tin cửa hàng -->
                <div class="col-lg-4 col-md-6">
                    <a href="<?php echo url('index.php'); ?>" class="wink-footer-logo">Win<span>K</span></a>
                    <p class="mt-2">Cửa hàng giày trực tuyến uy tín hàng đầu Việt Nam. Cam kết sản phẩm chính hãng 100%, giao hàng nhanh chóng.</p>
                    <div class="wink-footer-social">
                        <a href="#" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" title="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" title="TikTok"><i class="fab fa-tiktok"></i></a>
                        <a href="#" title="YouTube"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                
                <!-- Liên kết nhanh -->
                <div class="col-lg-2 col-md-6">
                    <h5>Liên kết</h5>
                    <ul>
                        <li><a href="<?php echo url('index.php'); ?>">Trang chủ</a></li>
                        <li><a href="<?php echo url('pages/products.php'); ?>">Sản phẩm</a></li>
                        <li><a href="<?php echo url('pages/vouchers.php'); ?>">Khuyến mãi</a></li>
                        <li><a href="<?php echo url('pages/chat.php'); ?>">Liên hệ</a></li>
                    </ul>
                </div>
                
                <!-- Chính sách -->
                <div class="col-lg-3 col-md-6">
                    <h5>Chính sách</h5>
                    <ul>
                        <li><a href="#">Chính sách đổi trả</a></li>
                        <li><a href="#">Chính sách bảo mật</a></li>
                        <li><a href="#">Điều khoản dịch vụ</a></li>
                        <li><a href="#">Hướng dẫn mua hàng</a></li>
                        <li><a href="#">Hướng dẫn chọn size</a></li>
                    </ul>
                </div>
                
                <!-- Liên hệ -->
                <div class="col-lg-3 col-md-6">
                    <h5>Liên hệ</h5>
                    <ul class="wink-footer-contact">
                        <li>
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo STORE_ADDRESS; ?></span>
                        </li>
                        <li>
                            <i class="fas fa-phone-alt"></i>
                            <span><?php echo STORE_PHONE; ?></span>
                        </li>
                        <li>
                            <i class="fas fa-envelope"></i>
                            <span><?php echo STORE_EMAIL; ?></span>
                        </li>
                        <li>
                            <i class="fas fa-clock"></i>
                            <span>8:00 - 22:00 (T2 - CN)</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="wink-footer-bottom">
            <div class="container">
                <p>&copy; <?php echo date('Y'); ?> <strong>WinK Shoe Store</strong>. All rights reserved. | Đồ án tốt nghiệp CNTT</p>
            </div>
        </div>
    </footer>

    <!-- Back to Top -->
    <button class="back-to-top" id="backToTop" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">
        <i class="fas fa-chevron-up"></i>
    </button>

    <!-- Floating Chat Widget -->
    <?php if (isLoggedIn()): ?>
    <div class="chat-widget" id="chatWidget">
        <!-- Chat Button -->
        <button class="chat-widget-btn" id="chatWidgetBtn" onclick="toggleChatWidget()">
            <i class="fas fa-comments"></i>
            <span class="chat-widget-badge" id="chatUnreadBadge" style="display: none;">0</span>
        </button>
        
        <!-- Chat Window -->
        <div class="chat-widget-window" id="chatWidgetWindow">
            <div class="chat-widget-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="chat-widget-avatar">
                        <i class="fas fa-headset"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold">Hỗ trợ WinK</h6>
                        <small class="text-success">
                            <i class="fas fa-circle me-1" style="font-size: 6px;"></i>Online
                        </small>
                    </div>
                </div>
                <button class="chat-widget-close" onclick="toggleChatWidget()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="chat-widget-messages" id="chatWidgetMessages">
                <div class="text-center text-muted py-4">
                    <i class="fas fa-comments fa-2x mb-2"></i>
                    <p style="font-size: 13px;">Bắt đầu cuộc trò chuyện!</p>
                </div>
            </div>
            
            <div class="chat-widget-input">
                <div class="chat-widget-preview" id="chatWidgetPreview" style="display: none;">
                    <img id="chatWidgetPreviewImg" src="" alt="Ảnh xem trước">
                    <button type="button" id="chatWidgetPreviewRemove" aria-label="Bỏ ảnh"><i class="fas fa-times"></i></button>
                </div>
                <form id="chatWidgetForm" class="d-flex gap-2 align-items-center">
                    <input type="file" id="chatWidgetFile" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
                    <button type="button" class="chat-widget-attach" id="chatWidgetAttach" title="Gửi ảnh">
                        <i class="fas fa-image"></i>
                    </button>
                    <input type="text" id="chatWidgetInput" class="form-control form-control-sm" 
                           placeholder="Nhập tin nhắn..." autocomplete="off" maxlength="2000">
                    <button type="submit" class="btn-wink btn-wink-sm">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    

    <!-- Chat Widget JS -->
    <script src="<?php echo asset('js/chat.js') . ($_cacheVersion ?? '?v=' . time()); ?>"></script>
    <?php endif; ?>

    <!-- Chat CSS -->
    <link rel="stylesheet" href="<?php echo asset('css/chat.css') . ($_cacheVersion ?? '?v=' . time()); ?>">

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo asset('js/main.js') . ($_cacheVersion ?? '?v=' . time()); ?>"></script>
    
    <?php if (!empty($extraJS)): ?>
        <?php foreach ($extraJS as $js): ?>
            <script src="<?php echo asset('js/' . $js) . ($_cacheVersion ?? '?v=' . time()); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
