<?php
require_once 'includes/header.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('search.php');

$db = getDB();

$uni = $db->prepare("SELECT * FROM universities WHERE university_id=?");
$uni->execute([$id]);
$uni = $uni->fetch();

if (!$uni) {
    http_response_code(404);
    echo '<h2 class="p-5">Không tìm thấy trường</h2>';
    require_once 'includes/footer.php';
    exit;
}

$pageTitle = e($uni['university_name']) . ' – DiemChuan.vn';

// Lấy tất cả năm có dữ liệu của trường
$yearsStmt = $db->prepare("SELECT DISTINCT year FROM admission_scores WHERE university_id=? ORDER BY year DESC");
$yearsStmt->execute([$id]);
$years = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);

// Mặc định: năm mới nhất + phương thức Tổng hợp
$filterYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)($years[0] ?? 0);
$filterMethod = $_GET['method'] ?? 'TongHop';

// Danh sách phương thức
$methodLabels = [
    ''        => 'Tất cả phương thức',
    'THPT'    => 'Thi THPT',
    'HocBa'   => 'Học bạ',
    'TongHop' => 'Tổng hợp',
    'DGNL'    => 'Đánh giá năng lực',
    'Thang'   => 'Xét thẳng'
];

$methodColors = [
    'THPT'    => 'primary',
    'HocBa'   => 'success',
    'TongHop' => 'warning',
    'DGNL'    => 'info',
    'Thang'   => 'secondary'
];

// Lấy điểm theo bộ lọc
$where = ["s.university_id = ?"];
$params = [$id];

if ($filterYear) {
    $where[] = "s.year = ?";
    $params[] = $filterYear;
}

if ($filterMethod !== '') {
    $where[] = "s.method = ?";
    $params[] = $filterMethod;
}

$sql = "
    SELECT m.major_id, m.major_name,
           s.year, s.combination, s.method, s.score, s.quota
    FROM admission_scores s
    JOIN majors m ON s.major_id = m.major_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY s.score DESC
";

$scores = $db->prepare($sql);
$scores->execute($params);
$all = $scores->fetchAll();

// Thông tin thống kê của trường, không phụ thuộc bộ lọc hiện tại
$statStmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT major_id) AS major_count,
        MAX(score) AS max_score
    FROM admission_scores
    WHERE university_id = ?
");
$statStmt->execute([$id]);
$stat = $statStmt->fetch();

$mcount = (int)($stat['major_count'] ?? 0);
$mx = $stat['max_score'] ?? 0;

function method_label($method, $labels) {
    return $labels[$method] ?? $method;
}

function method_color($method, $colors) {
    return $colors[$method] ?? 'secondary';
}
?>

<div class="container py-4">
  <nav class="mb-3">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="<?= url('index.php') ?>">Trang chủ</a></li>
      <li class="breadcrumb-item"><a href="<?= url('search.php') ?>">Tra cứu</a></li>
      <li class="breadcrumb-item active"><?= e($uni['university_name']) ?></li>
    </ol>
  </nav>

  <!-- Header trường -->
  <div class="card mb-4 p-4">
    <div class="d-flex gap-4 flex-wrap align-items-start">
      <div class="uni-logo flex-shrink-0 d-flex align-items-center justify-content-center"
           style="width:80px;height:80px;font-size:20px;font-weight:700">
        <?= e($uni['university_code'] ?: substr($uni['university_name'], 0, 4)) ?>
      </div>

      <div class="flex-grow-1">
        <h2 class="fw-bold mb-2"><?= e($uni['university_name']) ?></h2>

        <div class="d-flex flex-wrap gap-3 text-muted small mb-2">
          <?php if($uni['province']): ?>
            <span><i class="bi bi-geo-alt me-1"></i><?= e($uni['province']) ?></span>
          <?php endif; ?>

          <?php if($uni['address']): ?>
            <span><i class="bi bi-signpost me-1"></i><?= e($uni['address']) ?></span>
          <?php endif; ?>

          <?php if($uni['website']): ?>
            <a href="<?= e($uni['website']) ?>" target="_blank" class="text-decoration-none">
              <i class="bi bi-globe me-1"></i><?= e($uni['website']) ?>
            </a>
          <?php endif; ?>

          <span><i class="bi bi-building me-1"></i><?= e($uni['school_type'] ?? '') ?></span>
        </div>

        <?php if($uni['description']): ?>
          <p class="text-muted small mb-0"><?= e($uni['description']) ?></p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-md-8">
      <div class="card">

        <!-- Header bảng + bộ chọn -->
        <div class="card-header">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
              <div class="fw-semibold">
                <i class="bi bi-list-ul me-1"></i>
                Điểm chuẩn
                <?php if($filterYear): ?>
                  năm <strong class="text-primary"><?= $filterYear ?></strong>
                <?php else: ?>
                  tất cả các năm
                <?php endif; ?>
              </div>

              <div class="text-muted small mt-1">
                <?= count($all) ?> bản ghi
                <?php if($filterMethod !== ''): ?>
                  · Phương thức:
                  <span class="text-primary fw-semibold">
                    <?= e(method_label($filterMethod, $methodLabels)) ?>
                  </span>
                <?php endif; ?>
              </div>
            </div>

            <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
              <input type="hidden" name="id" value="<?= $id ?>">

              <select name="method"
                      class="form-select form-select-sm"
                      style="width:auto;min-width:170px"
                      onchange="this.form.submit()">
                <?php foreach($methodLabels as $value => $label): ?>
                  <option value="<?= e($value) ?>" <?= $filterMethod === $value ? 'selected' : '' ?>>
                    <?= e($label) ?>
                  </option>
                <?php endforeach; ?>
              </select>

              <select name="year"
                      class="form-select form-select-sm"
                      style="width:auto;min-width:110px"
                      onchange="this.form.submit()">
                <option value="0" <?= !$filterYear ? 'selected' : '' ?>>Tất cả năm</option>
                <?php foreach($years as $y): ?>
                  <option value="<?= e($y) ?>" <?= $filterYear == $y ? 'selected' : '' ?>>
                    <?= e($y) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </form>
          </div>
        </div>

        <!-- Bảng điểm -->
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle small">
            <thead>
              <tr>
                <th>Ngành</th>
                <th>Năm</th>
                <th>Tổ hợp</th>
                <th>Phương thức</th>
                <th>Điểm chuẩn</th>
                <th>Chỉ tiêu</th>
              </tr>
            </thead>

            <tbody>
              <?php if(empty($all)): ?>
                <tr>
                  <td colspan="6" class="text-center text-muted py-4">
                    Không có dữ liệu phù hợp với bộ lọc hiện tại
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach($all as $r): ?>
                  <?php
                    $c = $r['score'] >= 27 ? 'sb-hi' : ($r['score'] >= 23 ? 'sb-mid' : 'sb-lo');
                    $mColor = method_color($r['method'], $methodColors);
                    $mLabel = method_label($r['method'], $methodLabels);
                  ?>

                  <tr>
                    <td>
                      <a href="<?= url('major.php?id='.$r['major_id']) ?>"
                         class="text-decoration-none fw-semibold">
                        <?= e($r['major_name']) ?>
                      </a>
                    </td>

                    <td>
                      <span class="chip"><?= e($r['year']) ?></span>
                    </td>

                    <td>
                      <span class="chip">
                        <?= $r['combination'] ? e($r['combination']) : '—' ?>
                      </span>
                    </td>

                    <td>
                      <span class="badge text-bg-<?= $mColor ?> fw-normal"
                            style="font-size:10px;border-radius:20px">
                        <?= e($mLabel) ?>
                      </span>
                    </td>

                    <td>
                      <span class="score-badge <?= $c ?>">
                        <?= number_format($r['score'], 2) ?>
                      </span>
                    </td>

                    <td class="text-muted">
                      <?= number_format($r['quota']) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card">
        <div class="card-header">
          <i class="bi bi-info-circle me-1"></i>Thông tin tuyển sinh
        </div>

        <div class="card-body">
          <table class="table table-sm small mb-3">
            <tr>
              <td class="text-muted">Mã trường</td>
              <td class="fw-semibold"><?= e($uni['university_code'] ?? '—') ?></td>
            </tr>

            <tr>
              <td class="text-muted">Loại hình</td>
              <td><?= e($uni['school_type'] ?? '—') ?></td>
            </tr>

            <tr>
              <td class="text-muted">Tỉnh/Thành</td>
              <td><?= e($uni['province'] ?? '—') ?></td>
            </tr>

            <tr>
              <td class="text-muted">Điểm cao nhất</td>
              <td class="fw-bold text-primary"><?= number_format((float)$mx, 2) ?></td>
            </tr>

            <tr>
              <td class="text-muted">Số ngành</td>
              <td><?= $mcount ?></td>
            </tr>
          </table>

          <a href="<?= url('compare.php?uni1='.$id) ?>" class="btn btn-outline-primary btn-sm w-100">
            <i class="bi bi-bar-chart me-1"></i>So sánh trường này
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>