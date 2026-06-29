<?php

$isLocal = in_array(
    $_SERVER['HTTP_HOST'] ?? '',
    ['localhost', '127.0.0.1']
);

if ($isLocal) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'admission_system');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    define('DB_HOST', 'sql308.infinityfree.com');
    define('DB_NAME', 'if0_42277694_diemchuan');
    define('DB_USER', 'if0_42277694');
    define('DB_PASS', 'MẬT_KHẨU_VISTAPANEL_CỦA_BẠN');
}

function getDB(): PDO
{
    static $p = null;

    if ($p === null) {
        try {
            $p = new PDO(
                'mysql:host=' . DB_HOST
                . ';dbname=' . DB_NAME
                . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());

            die(
                '<div style="
                    max-width:600px;
                    margin:60px auto;
                    padding:30px;
                    font-family:Arial,sans-serif;
                    color:#b91c1c;
                    background:#fef2f2;
                    border:1px solid #fecaca;
                    border-radius:12px;
                    text-align:center;
                ">
                    <h2>⚠️ Lỗi kết nối Database</h2>
                    <p>Website hiện không thể kết nối với cơ sở dữ liệu.</p>
                </div>'
            );
        }
    }

    return $p;
}