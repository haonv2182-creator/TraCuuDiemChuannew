<?php
// includes/sidebar.php
$_ap = basename($_SERVER['PHP_SELF'], '.php');
?>

<aside class="admin-sidebar bg-dark">
    <div class="p-3 border-bottom border-secondary">
        <div class="d-flex align-items-center gap-2 mb-1">
            <span style="font-size:20px">🎓</span>
            <span class="fw-bold text-white small">
                DiemChuan.vn
            </span>
        </div>

        <div class="text-muted" style="font-size:11px">
            Xin chào,
            <strong class="text-white">
                <?= e($_SESSION['username'] ?? 'Admin') ?>
            </strong>
        </div>
    </div>

    <nav class="p-2 pt-3">

        <div class="sb-label">
            TỔNG QUAN
        </div>

        <a
            href="<?= url('admin/dashboard.php') ?>"
            class="sb-link <?= $_ap === 'dashboard' ? 'active' : '' ?>"
        >
            <i class="bi bi-speedometer2"></i>
            Dashboard
        </a>

        <div class="sb-label">
            QUẢN LÝ
        </div>

        <a
            href="<?= url('admin/manage_scores.php') ?>"
            class="sb-link <?= $_ap === 'manage_scores' ? 'active' : '' ?>"
        >
            <i class="bi bi-database"></i>
            Dữ liệu tuyển sinh
        </a>

        <a
            href="<?= url('admin/import_csv.php') ?>"
            class="sb-link <?= $_ap === 'import_csv' ? 'active' : '' ?>"
        >
            <i class="bi bi-upload"></i>
            Import CSV
        </a>

        <div class="sb-label">
            HỆ THỐNG
        </div>

        <a
            href="<?= url('index.php') ?>"
            target="_blank"
            class="sb-link"
        >
            <i class="bi bi-globe"></i>
            Xem website
        </a>

        <a
            href="<?= url('logout.php') ?>"
            class="sb-link"
            style="color:#f87171"
        >
            <i class="bi bi-box-arrow-right"></i>
            Đăng xuất
        </a>

    </nav>
</aside>
