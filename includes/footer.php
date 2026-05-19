<?php // includes/footer.php ?>
<footer class="mt-5 py-4 bg-white border-top">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-4">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="brand-icon" style="font-size:20px">🎓</span>
          <span class="fw-bold text-primary">DiemChuan.vn</span>
        </div>
        <p class="text-muted small">Hệ thống tra cứu điểm chuẩn đại học Việt Nam. Dữ liệu cập nhật hàng năm.</p>
      </div>
      <div class="col-md-2">
        <h6 class="fw-bold mb-3">Chức năng</h6>
        <ul class="list-unstyled small">
          <li class="mb-1"><a href="<?= url('search.php') ?>" class="text-muted text-decoration-none">Tra cứu điểm</a></li>
          <li class="mb-1"><a href="<?= url('compare.php') ?>" class="text-muted text-decoration-none">So sánh trường</a></li>
          <li class="mb-1"><a href="<?= url('ai_recommend.php') ?>" class="text-muted text-decoration-none">AI Gợi ý</a></li>
        </ul>
      </div>
      <div class="col-md-3">
        <h6 class="fw-bold mb-3">Công nghệ</h6>
        <p class="text-muted small mb-1">PHP thuần · MySQL · Bootstrap 5</p>
        <p class="text-muted small">Chạy trên XAMPP</p>
      </div>
      <div class="col-md-3">
        <h6 class="fw-bold mb-3">Admin</h6>
        <p class="text-muted small mb-1">Tài khoản: <code>admin</code> / <code>admin123</code></p>
        <a href="<?= url('login.php') ?>" class="btn btn-sm btn-outline-primary">Đăng nhập Admin</a>
      </div>
    </div>
    <hr class="my-3">
    <p class="text-center text-muted small mb-0">© <?= date('Y') ?> DiemChuan.vn</p>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="<?= url('assets/js/main.js') ?>"></script>
</body>
</html>
