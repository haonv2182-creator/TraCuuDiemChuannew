<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/db.php';

// ── Auth & Session (gộp vào đây để tránh lỗi thứ tự load) ──
function startSession():void {
    if (session_status() === PHP_SESSION_NONE) session_start();
}
function isAdmin():bool {
    startSession();
    return isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin';
}
function requireAdmin():void {
    if (!isAdmin()) { header('Location:'.url('login.php')); exit; }
}
function setFlash(string $t, string $m):void {
    startSession();
    $_SESSION['flash'] = compact('t','m');
}
function getFlash():?array {
    startSession();
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return ['type'=>$f['t'], 'msg'=>$f['m']];
    }
    return null;
}
function checkPassword(string $input, string $stored): bool {
    if (strlen($stored) === 60 && str_starts_with($stored, '$2')) {
        return password_verify($input, $stored);
    }
    return md5($input) === $stored;
}

function e(string $s):string{ return htmlspecialchars($s,ENT_QUOTES,'UTF-8'); }

function paginate(int $total,int $per,int $cur):array{
    $pages=max(1,(int)ceil($total/$per));
    $cur=max(1,min($cur,$pages));
    return ['total'=>$total,'per_page'=>$per,'current'=>$cur,'total_pages'=>$pages,'offset'=>($cur-1)*$per];
}

function uploadLogo(array $f):string|false{
    $ok=['image/jpeg','image/png','image/gif','image/webp'];
    if(!in_array($f['type'],$ok)||$f['size']>2*1024*1024) return false;
    $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
    $name='logo_'.uniqid().'.'.$ext;
    return move_uploaded_file($f['tmp_name'],__DIR__.'/../uploads/'.$name)?$name:false;
}

function getStats():array{
    $db=getDB();
    return [
        'universities'=>$db->query('SELECT COUNT(*) FROM universities')->fetchColumn(),
        'majors'      =>$db->query('SELECT COUNT(*) FROM majors')->fetchColumn(),
        'scores'      =>$db->query('SELECT COUNT(*) FROM admission_scores')->fetchColumn(),
        'ai_logs'     =>$db->query('SELECT COUNT(*) FROM ai_logs')->fetchColumn(),
    ];
}

function getProvinces():array{
    return ['TP. Hồ Chí Minh','Hà Nội','Đà Nẵng','Cần Thơ','Hải Phòng',
            'Bình Dương','Đồng Nai','Khánh Hòa','Thừa Thiên Huế','Nghệ An'];
}