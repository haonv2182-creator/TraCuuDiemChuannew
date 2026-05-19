# 🎓 DiemChuan.vn — Hệ thống Tra cứu Điểm chuẩn Đại học

## 📁 Cấu trúc thư mục

```
TRACUUDIEMCHUAN/          ← Đặt trong htdocs/
├── index.php             ← Trang chủ
├── search.php            ← Tra cứu điểm chuẩn
├── university.php        ← Chi tiết trường
├── major.php             ← Chi tiết ngành
├── compare.php           ← So sánh 2 trường
├── ai_recommend.php      ← AI gợi ý
├── login.php             ← Đăng nhập admin
├── logout.php
│
├── admin/
│   ├── dashboard.php
│   ├── manage_universities.php
│   ├── manage_majors.php
│   ├── manage_scores.php
│   └── import_csv.php
│
├── includes/
│   ├── config.php        ← BASE_URL tự động ✨
│   ├── db.php            ← Kết nối MySQL
│   ├── auth.php          ← Session & xác thực
│   ├── functions.php     ← Helper functions
│   ├── header.php        ← HTML head + navbar
│   ├── footer.php        ← Footer + scripts
│   └── sidebar.php       ← Sidebar admin
│
├── assets/
│   ├── css/style.css
│   └── js/main.js
│
├── api/
│   └── search.php        ← AJAX autocomplete
│
├── uploads/              ← Logo trường (tự tạo)
└── database/
    └── admission_system.sql
```

## 🚀 Hướng dẫn cài đặt XAMPP

### Bước 1 — Khởi động XAMPP
- Mở **XAMPP Control Panel**
- Bật **Apache** và **MySQL**

### Bước 2 — Copy project
```
C:\xampp\htdocs\TRACUUDIEMCHUAN\
```
> Tên thư mục tùy chọn, có thể dùng tên khác

### Bước 3 — Tạo Database
1. Mở trình duyệt → `http://localhost/phpmyadmin`
2. Nhấn **New** → tạo database tên `admission_system`
3. Chọn database vừa tạo → tab **Import**
4. Chọn file `database/admission_system.sql` → nhấn **Go**

### Bước 4 — Chạy website
```
http://localhost/TRACUUDIEMCHUAN/
```

### Bước 5 — Đăng nhập Admin
```
URL      : http://localhost/TRACUUDIEMCHUAN/login.php
Username : admin
Password : admin123
```

## ⚙️ Cấu hình Database

Nếu XAMPP của bạn dùng password MySQL, sửa file `includes/db.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'admission_system');
define('DB_USER', 'root');
define('DB_PASS', '');  // ← Sửa password ở đây nếu có
```

## 🔑 Tính năng BASE_URL tự động

File `includes/config.php` tự động nhận diện project đang chạy ở subfolder nào.
- Chạy ở `localhost/TRACUUDIEMCHUAN/` → tự tính đúng
- Chạy ở `localhost/` → tự tính đúng
- **Không cần sửa gì cả!**

## 📋 Chức năng

| Trang | Mô tả |
|-------|-------|
| Trang chủ | Tìm kiếm, lọc nhanh, biểu đồ, trường nổi bật |
| Tra cứu | Lọc đầy đủ 7 tiêu chí, phân trang |
| Chi tiết trường | Biểu đồ điểm, danh sách ngành |
| Chi tiết ngành | Xu hướng điểm, so sánh trường |
| So sánh | So sánh 2 trường với biểu đồ |
| AI Gợi ý | Rule-based: An toàn / Phù hợp / Thử sức |
| Admin Dashboard | Thống kê, biểu đồ Chart.js |
| Quản lý CRUD | Trường, ngành, điểm chuẩn |
| Import CSV | Upload file CSV hàng loạt |

## 🛠️ Công nghệ

- **Frontend**: HTML5, CSS3, Bootstrap 5, JavaScript, Chart.js
- **Backend**: PHP 8.0+ thuần (không framework)
- **Database**: MySQL (PDO + Prepared Statement)
- **Môi trường**: XAMPP

## 📝 Tài khoản mặc định

| Field | Value |
|-------|-------|
| Username | `admin` |
| Password | `admin123` |
