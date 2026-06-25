```php
<?php
$pageTitle = 'Import CSV – Admin';
require_once '../includes/header.php';
requireAdmin();

$db = getDB();

$preview = [];
$errors = [];
$success = 0;
$done = false;

$validMethods = ['THPT', 'HocBa', 'TongHop', 'DGNL'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $f = $_FILES['csv_file'];

    if ($f['error'] !== UPLOAD_ERR_OK) {
        setFlash('danger', 'Upload thất bại!');
    } elseif ($f['size'] > 10 * 1024 * 1024) {
        setFlash('danger', 'File quá lớn (tối đa 10MB)!');
    } elseif (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'csv') {
        setFlash('danger', 'Chỉ nhận file .csv!');
    } else {
        $h = fopen($f['tmp_name'], 'r');

        // Bỏ dòng header
        fgetcsv($h);

        $ins = $db->prepare("
            INSERT INTO admission_scores 
            (university_id, major_id, year, combination, method, score)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $uC = [];
        $mC = [];
        $row = 1;

        while (($cols = fgetcsv($h)) !== false) {
            $row++;

            if (count($cols) < 6) {
                $errors[] = "Dòng $row: Thiếu cột";
                continue;
            }

            [$un, $mn, $yr, $cb, $method, $sc] = $cols;

            $un     = trim($un);
            $mn     = trim($mn);
            $yr     = trim($yr);
            $cb     = trim($cb);
            $method = trim($method);
            $sc     = trim($sc);

            if ($un === '') {
                $errors[] = "Dòng $row: Tên trường không được để trống";
                continue;
            }

            if ($mn === '') {
                $errors[] = "Dòng $row: Tên ngành không được để trống";
                continue;
            }

            if (!preg_match('/^\d{4}$/', $yr) || (int)$yr < 2015 || (int)$yr > (int)date('Y')) {
                $errors[] = "Dòng $row: Năm '$yr' không hợp lệ";
                continue;
            }

            if (!in_array($method, $validMethods, true)) {
                $errors[] = "Dòng $row: Phương thức '$method' không hợp lệ";
                continue;
            }

            if ($method !== 'DGNL' && !preg_match('/^[A-Z]\d{2}$/', $cb)) {
                $errors[] = "Dòng $row: Tổ hợp '$cb' không hợp lệ";
                continue;
            }

            if ($method === 'DGNL') {
                $cb = '';
            }

            if (!is_numeric($sc)) {
                $errors[] = "Dòng $row: Điểm '$sc' không hợp lệ";
                continue;
            }

            $score = (float)$sc;

            if ($method === 'DGNL') {
                if ($score <= 0 || $score > 1200) {
                    $errors[] = "Dòng $row: Điểm ĐGNL '$sc' phải từ 1 đến 1200";
                    continue;
                }
            } else {
                if ($score < 0 || $score > 30) {
                    $errors[] = "Dòng $row: Điểm '$sc' phải từ 0 đến 30";
                    continue;
                }
            }

            // Tìm hoặc tạo trường
            if (!isset($uC[$un])) {
                $s = $db->prepare("
                    SELECT university_id 
                    FROM universities 
                    WHERE university_name = ?
                ");
                $s->execute([$un]);
                $uid = $s->fetchColumn();

                if (!$uid) {
                    $db->prepare("
                        INSERT INTO universities (university_name) 
                        VALUES (?)
                    ")->execute([$un]);

                    $uid = $db->lastInsertId();
                }

                $uC[$un] = $uid;
            }

            // Tìm hoặc tạo ngành
            if (!isset($mC[$mn])) {
                $s = $db->prepare("
                    SELECT major_id 
                    FROM majors 
                    WHERE major_name = ?
                ");
                $s->execute([$mn]);
                $mid = $s->fetchColumn();

                if (!$mid) {
                    $db->prepare("
                        INSERT INTO majors (major_name) 
                        VALUES (?)
                    ")->execute([$mn]);

                    $mid = $db->lastInsertId();
                }

                $mC[$mn] = $mid;
            }

            $ins->execute([
                $uC[$un],
                $mC[$mn],
                (int)$yr,
                $cb,
                $method,
                $score
            ]);

            $preview[] = [$un, $mn, $yr, $cb ?: '—', $method, $sc, '✅ OK'];
            $success++;
        }

        fclose($h);
        $done = true;
    }
}
?>

<div class="admin-wrapper">
    <?php require_once '../includes/sidebar.php'; ?>

    <div class="admin-content">
        <h2 class="fw-bold mb-4">
            <i class="bi bi-upload me-2"></i>
            Import dữ liệu CSV
        </h2>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header fw-semibold">
                        📂 Chọn file CSV
                    </div>

                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div 
                                class="upload-zone mb-3" 
                                onclick="document.getElementById('csvIn').click()"
                            >
                                <div style="font-size:40px">📂</div>

                                <p class="fw-semibold mb-1">
                                    Nhấn để chọn file
                                </p>

                                <p class="text-muted small mb-0">
                                    Chỉ nhận .csv · Tối đa 10MB
                                </p>

                                <input 
                                    type="file" 
                                    id="csvIn" 
                                    name="csv_file" 
                                    accept=".csv" 
                                    class="d-none"
                                    onchange="document.getElementById('fname').textContent=this.files[0].name; document.getElementById('btnImport').disabled=false"
                                >
                            </div>

                            <div 
                                id="fname" 
                                class="text-muted small text-center mb-3"
                            ></div>

                            <button 
                                type="submit" 
                                id="btnImport" 
                                class="btn btn-primary w-100" 
                                disabled
                            >
                                <i class="bi bi-upload me-1"></i>
                                Import ngay
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header fw-semibold">
                        📋 Định dạng CSV
                    </div>

                    <div class="card-body">
                        <p class="small text-muted mb-1">
                            Dòng đầu tiên, header bắt buộc:
                        </p>

                        <code class="d-block bg-light p-2 rounded mb-3 small">
                            university_name,major_name,year,combination,method,score
                        </code>

                        <p class="small text-muted mb-1">
                            Ví dụ dữ liệu:
                        </p>

                        <code 
                            class="d-block bg-light p-2 rounded small mb-2" 
                            style="word-break:break-all"
                        >
                            ĐH Bách Khoa TP.HCM,Công nghệ thông tin,2024,A00,THPT,27.20
                        </code>

                        <code 
                            class="d-block bg-light p-2 rounded small" 
                            style="word-break:break-all"
                        >
                            ĐH Bách Khoa TP.HCM,Công nghệ thông tin,2024,,DGNL,950
                        </code>

                        <ul class="small text-muted mt-3 mb-0">
                            <li><b>year</b>: từ 2015 đến <?= date('Y') ?></li>
                            <li><b>combination</b>: dạng A00, A01, B00...</li>
                            <li><b>DGNL</b>: để trống tổ hợp</li>
                            <li><b>method</b>: THPT, HocBa, TongHop, DGNL</li>
                            <li><b>score</b>: THPT/Học bạ/Tổng hợp từ 0 đến 30, DGNL từ 1 đến 1200</li>
                            <li>Trường/ngành chưa có → <b>tự động tạo</b></li>
                            <li>Không dùng cột <b>chỉ tiêu</b></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($done): ?>
            <div class="row g-3 mt-2 mb-4">
                <div class="col-md-3">
                    <div class="stat-card text-center">
                        <div class="s-num text-success">
                            <?= $success ?>
                        </div>

                        <div class="s-lbl">
                            Dòng thành công
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card text-center">
                        <div class="s-num text-danger">
                            <?= count($errors) ?>
                        </div>

                        <div class="s-lbl">
                            Dòng lỗi
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($errors): ?>
                <div class="card border-danger mb-4">
                    <div class="card-header text-danger">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Danh sách lỗi
                    </div>

                    <div class="card-body">
                        <ul class="small mb-0">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($preview): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-check-circle text-success me-1"></i>
                        Dữ liệu đã import
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm small mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Trường</th>
                                    <th>Ngành</th>
                                    <th>Năm</th>
                                    <th>Tổ hợp</th>
                                    <th>Phương thức</th>
                                    <th>Điểm</th>
                                    <th>Trạng thái</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach (array_slice($preview, 0, 50) as $i => $r): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>

                                        <?php foreach (array_slice($r, 0, 6) as $v): ?>
                                            <td><?= e($v) ?></td>
                                        <?php endforeach; ?>

                                        <td class="text-success">
                                            <?= $r[6] ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (count($preview) > 50): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">
                                            ... và <?= count($preview) - 50 ?> dòng khác
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
```
