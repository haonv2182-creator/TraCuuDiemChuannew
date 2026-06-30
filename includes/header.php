<?php
// includes/header.php
require_once __DIR__ . '/functions.php';

startSession();

$_cur   = basename($_SERVER['PHP_SELF'], '.php');
$_flash = getFlash();
$isAdminPage = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;

$styleFile = __DIR__ . '/../assets/css/style.css';
$styleVersion = file_exists($styleFile)
    ? filemtime($styleFile)
    : time();
?>

<!DOCTYPE html>
<html lang="vi" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title><?= $pageTitle ?? 'DiemChuan.vn – Tra cứu điểm chuẩn đại học' ?></title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>?v=<?= $styleVersion ?>">

  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700&display=swap">
  <link rel="stylesheet" href="<?= url('assets/css/unified.css') ?>?v=<?= time() ?>">
</head>

<body>

<?php if ($_flash): ?>
  <div
    class="alert alert-<?= $_flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible position-fixed top-0 start-50 translate-middle-x mt-3 shadow-lg"
    style="z-index:9999;min-width:320px;border-radius:12px"
    role="alert"
  >
    <i class="bi bi-<?= $_flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
    <?= e($_flash['msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
  </div>
<?php endif; ?>

<?php if (!$isAdminPage): ?>
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top shadow-sm">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-primary" href="<?= url('index.php') ?>">
      <span class="brand-icon">🎓</span>
      <span>DiemChuan</span>
      <span class="badge bg-primary ms-1" style="font-size:.58rem;vertical-align:middle">VN</span>
    </a>

    <button
      class="navbar-toggler border-0"
      type="button"
      data-bs-toggle="collapse"
      data-bs-target="#navMain"
      aria-controls="navMain"
      aria-expanded="false"
      aria-label="Mở menu"
    >
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto gap-1 mb-2 mb-lg-0">
        <?php
        $menuItems = [
          ['index',        'bi-house',          'Trang chủ'],
          ['search',       'bi-funnel',         'Tra cứu nâng cao'],
          ['compare',      'bi-bar-chart-line', 'So sánh'],
          ['ai_recommend', 'bi-stars',          'Gợi ý theo điểm'],
        ];
        ?>

        <?php foreach ($menuItems as [$page, $icon, $label]): ?>
          <li class="nav-item">
            <a class="nav-link <?= $_cur === $page ? 'active' : '' ?>" href="<?= url($page . '.php') ?>">
              <i class="bi <?= $icon ?> me-1"></i>
              <?= e($label) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>

      <div class="d-flex align-items-center gap-2">
        <button
          id="darkBtn"
          class="btn btn-sm btn-light border"
          title="Chuyển chế độ sáng/tối"
          aria-label="Chuyển chế độ sáng/tối"
          type="button"
        >
          <i class="bi bi-moon"></i>
        </button>

        <?php if (isAdmin()): ?>
          <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-speedometer2 me-1"></i>
            Admin
          </a>

          <a href="<?= url('logout.php') ?>" class="btn btn-sm btn-outline-danger">
            <i class="bi bi-box-arrow-right me-1"></i>
            Đăng xuất
          </a>
        <?php else: ?>
          <a href="<?= url('login.php') ?>" class="btn btn-sm btn-primary px-3">
            <i class="bi bi-person-circle me-1"></i>
            Đăng nhập
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
<?php endif; ?>