<?php
define('DB_HOST','localhost'); define('DB_NAME','admission_system');
define('DB_USER','root');      define('DB_PASS','');
function getDB(): PDO {
    static $p=null;
    if(!$p) try {
        $p=new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",DB_USER,DB_PASS,
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    } catch(PDOException $e){
        die('<div style="font:16px sans-serif;padding:40px;color:#c00"><h2>⚠️ Lỗi kết nối Database</h2><p>'.$e->getMessage().'</p><p>Kiểm tra XAMPP đã bật MySQL và cấu hình <code>includes/db.php</code>.</p></div>');
    }
    return $p;
}
