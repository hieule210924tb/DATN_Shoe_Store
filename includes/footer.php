<?php
/**
 * Footer - WinK Shoe Store
 */
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

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo asset('js/main.js'); ?>"></script>
    
    <?php if (!empty($extraJS)): ?>
        <?php foreach ($extraJS as $js): ?>
            <script src="<?php echo asset('js/' . $js); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
