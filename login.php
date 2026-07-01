<?php
// ============================================================
//  login.php — Đăng nhập Admin
// ============================================================
require_once 'includes/functions.php';

startSession();

if (isAdmin()) {
    redirect('admin/dashboard.php');
}

$err = '';
$styleFile = __DIR__ . '/assets/css/style.css';
$styleVersion = file_exists($styleFile) ? filemtime($styleFile) : time();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');

    if ($user !== '' && $pass !== '') {
        $db = getDB();

        $stmt = $db->prepare("
            SELECT user_id, username, password, role
            FROM users
            WHERE username = ?
            LIMIT 1
        ");
        $stmt->execute([$user]);
        $row = $stmt->fetch();

        if ($row && checkPassword($pass, $row['password'])) {
            $_SESSION['user_id']  = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role']     = $row['role'];

            redirect('admin/dashboard.php');
        }

        $err = 'Sai tài khoản hoặc mật khẩu!';
    } else {
        $err = 'Vui lòng điền đầy đủ!';
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
  <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>?v=<?= $styleVersion ?>">
</head>
<body class="bg-light">
<div class="min-vh-100 d-flex align-items-center justify-content-center p-3">
  <div class="card shadow" style="width:420px">
    <div class="card-body p-5">
      <div class="text-center mb-4">
        <div style="font-size:52px">🔐</div>
        <h4 class="fw-bold mt-2 mb-1">Đăng nhập Admin</h4>
        <p class="text-muted small">DiemChuan.vn</p>
      </div>

      <?php if ($err): ?>
      <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle me-1"></i><?= e($err) ?>
      </div>
      <?php endif; ?>

      <form method="POST">
        <div class="mb-3">
          <label class="form-label fw-semibold">Tên đăng nhập</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input
              type="text"
              name="username"
              class="form-control form-control-lg"
              autocomplete="username"
              required
              autofocus
            >
          </div>
        </div>

        <div class="mb-4">
          <label class="form-label fw-semibold">Mật khẩu</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input
              type="password"
              name="password"
              id="passInput"
              class="form-control form-control-lg"
              placeholder="••••••••"
              autocomplete="current-password"
              required
            >
            <button type="button" class="btn btn-outline-secondary"
              onclick="var p=document.getElementById('passInput');p.type=p.type==='password'?'text':'password'">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 fw-bold py-2 fs-5">
          <i class="bi bi-box-arrow-in-right me-1"></i>Đăng nhập
        </button>
      </form>

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