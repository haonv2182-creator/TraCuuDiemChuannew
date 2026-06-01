<?php
// includes/header.php
require_once __DIR__.'/functions.php';
startSession();
$_cur   = basename($_SERVER['PHP_SELF'],'.php');
$_flash = getFlash();
?>
<!DOCTYPE html>
<html lang="vi" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $pageTitle ?? 'DiemChuan.vn – Tra cứu điểm chuẩn đại học' ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<?php if($_flash): ?>
<div class="alert alert-<?= $_flash['type']==='success'?'success':'danger' ?> alert-dismissible
     position-fixed top-0 start-50 translate-middle-x mt-3 shadow-lg"
     style="z-index:9999;min-width:320px;border-radius:12px" role="alert">
  <i class="bi bi-<?= $_flash['type']==='success'?'check-circle':'exclamation-triangle' ?> me-2"></i>
  <?= e($_flash['msg']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top shadow-sm">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-primary" href="<?= url('index.php') ?>">
      <span class="brand-icon">🎓</span>
      DiemChuan<span class="badge bg-primary ms-1" style="font-size:.58rem;vertical-align:middle">VN</span>
    </a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto gap-1 mb-2 mb-lg-0">
        <?php foreach([
          ['index',        'bi-house',          'Trang chủ'],
          ['search',       'bi-search',         'Tra cứu'],
          ['compare',      'bi-bar-chart-line', 'So sánh'],
          ['ai_recommend', 'bi-robot',          'AI Gợi ý'],
        ] as [$pg,$ic,$lb]): ?>
        <li class="nav-item">
          <a class="nav-link <?= $_cur===$pg?'active':'' ?>" href="<?= url($pg.'.php') ?>">
            <i class="bi <?= $ic ?> me-1"></i><?= $lb ?>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>

      <!-- Autocomplete search -->
      <div class="position-relative me-2 d-none d-lg-block" style="width:240px">
        <input type="text" id="navSearch" class="form-control form-control-sm"
               placeholder="Tìm trường, ngành..." autocomplete="off"
               data-apiurl="<?= url('api/search.php') ?>"
               data-uniurl="<?= url('university.php') ?>"
               data-majurl="<?= url('major.php') ?>">
        <i class="bi bi-search position-absolute top-50 end-0 translate-middle-y me-2 text-muted" style="pointer-events:none;font-size:12px"></i>
        <div id="navDrop" class="dropdown-menu w-100 p-0 shadow" style="display:none;max-height:300px;overflow-y:auto;border-radius:10px"></div>
      </div>

      <button id="darkBtn" class="btn btn-sm btn-light border me-2" title="Dark mode">
        <i class="bi bi-moon"></i>
      </button>

      <?php if(isAdmin()): ?>
        <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-sm btn-outline-primary me-2">
          <i class="bi bi-speedometer2 me-1"></i>Admin
        </a>
        <a href="<?= url('logout.php') ?>" class="btn btn-sm btn-outline-danger">
          <i class="bi bi-box-arrow-right me-1"></i>Đăng xuất
        </a>
      <?php else: ?>
        <a href="<?= url('login.php') ?>" class="btn btn-sm btn-primary px-3">
          <i class="bi bi-person-circle me-1"></i>Đăng nhập
        </a>
      <?php endif; ?>
    </div>
  </div>
</nav>