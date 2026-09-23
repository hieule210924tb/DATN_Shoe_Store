    </div><!-- /.content-wrapper -->
    
    <footer class="main-footer text-center">
        <strong>&copy; <?php echo date('Y'); ?> <a href="<?php echo url('index.php'); ?>">WinK Shoe Store</a></strong> | Quản trị hệ thống
    </footer>
</div><!-- ./wrapper -->

<!-- jQuery -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<?php if (!empty($extraAdminJS)): ?>
    <?php foreach ($extraAdminJS as $js): ?>
        <script src="<?php echo $js; ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
