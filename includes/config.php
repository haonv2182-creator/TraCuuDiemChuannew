<?php
// ============================================================
//  includes/config.php — Tự động tính BASE_URL theo subfolder
//  Chạy ở localhost/TRACUUDIEMCHUAN/ hay localhost/ đều OK
// ============================================================

$_root = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$_doc  = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
$_rel  = str_replace($_doc, '', $_root);

define('BASE_URL', rtrim(str_replace('\\','/',$_rel), '/'));

/** Tạo URL từ path tương đối trong project */
function url(string $path = ''): string {
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Redirect theo path project */
function redirect(string $path): void {
    header('Location: ' . url($path));
    exit;
}
