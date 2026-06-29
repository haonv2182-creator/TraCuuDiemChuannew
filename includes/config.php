<?php

// ============================================================
// includes/config.php
// Tự động xác định BASE_URL cho cả localhost và hosting
// ============================================================

$documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
$projectRoot  = realpath(__DIR__ . '/..');

$documentRoot = $documentRoot
    ? str_replace('\\', '/', $documentRoot)
    : '';

$projectRoot = $projectRoot
    ? str_replace('\\', '/', $projectRoot)
    : '';

$baseUrl = '';

if ($documentRoot !== '' && $projectRoot !== '') {
    if (str_starts_with($projectRoot, $documentRoot)) {
        $baseUrl = substr(
            $projectRoot,
            strlen($documentRoot)
        );
    }
}

$baseUrl = str_replace('\\', '/', $baseUrl);
$baseUrl = '/' . trim($baseUrl, '/');

/*
|--------------------------------------------------------------------------
| Nếu project nằm trực tiếp trong htdocs/public_html
|--------------------------------------------------------------------------
| BASE_URL sẽ là chuỗi rỗng.
|
| Ví dụ:
| https://tracuudiemchuan.infinityfree.me/
|--------------------------------------------------------------------------
*/

if ($baseUrl === '/') {
    $baseUrl = '';
}

define('BASE_URL', $baseUrl);

/**
 * Tạo URL từ đường dẫn tương đối trong project.
 *
 * Ví dụ:
 * url('assets/css/style.css')
 * url('admin/dashboard.php')
 */
function url(string $path = ''): string
{
    $path = ltrim($path, '/');

    if ($path === '') {
        return BASE_URL !== ''
            ? BASE_URL . '/'
            : '/';
    }

    return BASE_URL . '/' . $path;
}

/**
 * Chuyển hướng tới đường dẫn trong project.
 *
 * Ví dụ:
 * redirect('index.php');
 * redirect('admin/dashboard.php');
 */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

