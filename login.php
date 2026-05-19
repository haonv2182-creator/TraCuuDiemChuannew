<?php
require_once 'includes/functions.php';
startSession();
if (isAdmin()) redirect('admin/dashboard.php');

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');
    if ($user && $pass) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$user]);
        $row  = $stmt->fetch();
        if ($row && password_verify($pass, $row['password'])) {
            $_SESSION['user_id']  = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role']     = $row['role'];
            redirect('admin/dashboard.php');
        }
        $err = 'Tên đăng nhập hoặc mật khẩu không đúng.';
    } else {
        $err = 'Vui lòng điền đầy đủ thông tin.';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Đăng nhập – DiemChuan.vn</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body class="bg-light">

<div class="min-vh-100 d-flex align-items-center justify-content-center p-3">
  <div class="card shadow" style="width:380px">
    <div class="card-body p-5">
      <div class="text-center mb-4">
        <div style="font-size:48px">🔐</div>
        <h4 class="fw-bold mt-2 mb-1">Đăng nhập Admin</h4>
        <p class="text-muted small mb-0">Hệ thống Tra cứu Điểm chuẩn ĐH</p>
      </div>

      <?php if ($err): ?>
      <div class="alert alert-danger small py-2">
        <i class="bi bi-exclamation-triangle me-1"></i><?= e($err) ?>
      </div>
      <?php endif; ?>

      <form method="POST">
        <div class="mb-3">
          <label class="form-label fw-semibold small">Tên đăng nhập</label>
          <input type="text" name="username" class="form-control"
                 placeholder="admin" required autofocus>
        </div>
        <div class="mb-4">
          <label class="form-label fw-semibold small">Mật khẩu</label>
          <input type="password" name="password" class="form-control"
                 placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn btn-primary w-100 fw-bold py-2">
          <i class="bi bi-box-arrow-in-right me-1"></i>Đăng nhập
        </button>
      </form>

      <div class="text-center mt-4">
        <p class="text-muted small mb-1">Tài khoản mặc định:</p>
        <code>admin</code> / <code>admin123</code>
      </div>
      <div class="text-center mt-3">
        <a href="<?= url('index.php') ?>" class="small text-muted">
          <i class="bi bi-arrow-left me-1"></i>Về trang chủ
        </a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
