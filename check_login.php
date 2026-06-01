<?php
// ============================================================
//  check_login.php — Kiểm tra & đặt lại mật khẩu admin
//  Bỏ vào TRACUUDIEMCHUAN/ rồi truy cập file này
//  XÓA SAU KHI DÙNG!
// ============================================================

// Kết nối DB trực tiếp (không qua includes)
$host = 'localhost';
$db   = 'admission_system';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(Exception $e) {
    die('<h2 style="color:red">❌ Lỗi kết nối DB: '.$e->getMessage().'</h2>');
}

// Xử lý đặt lại mật khẩu
if (isset($_POST['reset'])) {
    $newPass = 'admin123';
    $hash    = md5($newPass);

    // Xóa hết user cũ
    $pdo->exec("DELETE FROM users");

    // Tạo lại
    $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)")
        ->execute(['admin', $hash, 'admin']);

    echo '<div style="background:#d1fae5;padding:20px;border-radius:10px;margin:20px">
        <h2>✅ Đặt lại thành công!</h2>
        <p>Username: <b>admin</b></p>
        <p>Password: <b>admin123</b></p>
        <a href="login.php" style="background:#1a56db;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none">→ Đăng nhập ngay</a>
    </div>';
}

// Hiển thị user hiện tại
$users = $pdo->query("SELECT user_id, username, password, role FROM users")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<title>Check Login</title>
<style>
  body{font-family:sans-serif;padding:30px;background:#f8fafc}
  table{border-collapse:collapse;width:100%;margin:20px 0}
  th,td{border:1px solid #e2e8f0;padding:10px 14px;text-align:left;font-size:13px}
  th{background:#f1f5f9;font-weight:600}
  .btn{background:#ef4444;color:#fff;border:none;padding:12px 28px;border-radius:8px;font-size:15px;cursor:pointer;font-weight:bold}
  h2{color:#1e293b}
</style>
</head>
<body>
<h2>🔍 Kiểm tra tài khoản trong Database</h2>

<table>
  <tr><th>ID</th><th>Username</th><th>Password (hash)</th><th>Role</th></tr>
  <?php foreach($users as $u): ?>
  <tr>
    <td><?= $u['user_id'] ?></td>
    <td><?= htmlspecialchars($u['username']) ?></td>
    <td style="font-size:11px;word-break:break-all"><?= htmlspecialchars($u['password']) ?></td>
    <td><?= htmlspecialchars($u['role']) ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if(empty($users)): ?>
  <tr><td colspan="4" style="color:red;text-align:center">⚠️ Bảng users TRỐNG!</td></tr>
  <?php endif; ?>
</table>

<hr>
<h2>🔧 Đặt lại mật khẩu admin = <code>admin123</code></h2>
<form method="POST">
  <button type="submit" name="reset" class="btn">
    ✅ Nhấn để đặt lại mật khẩu ngay
  </button>
</form>

<p style="color:#94a3b8;margin-top:30px;font-size:12px">⚠️ Xóa file này sau khi dùng xong!</p>
</body></html>
