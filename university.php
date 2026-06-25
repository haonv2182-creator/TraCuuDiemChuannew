<?php
require_once 'includes/functions.php';

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    redirect('search.php');
}

$db = getDB();

$universityStmt = $db->prepare("
    SELECT *
    FROM universities
    WHERE university_id = ?
");
$universityStmt->execute([$id]);
$university = $universityStmt->fetch();

if (!$university) {
    http_response_code(404);
    $pageTitle = 'Không tìm thấy trường – DiemChuan.vn';
    require_once 'includes/header.php';
    echo '<div class="container py-5"><h2>Không tìm thấy trường</h2></div>';
    require_once 'includes/footer.php';
    exit;
}

$pageTitle = $university['university_name'] . ' – DiemChuan.vn';
require_once 'includes/header.php';

function university_method_label(string $method): string
{
    $labels = [
        'THPT'    => 'Thi THPT',
        'HocBa'   => 'Học bạ',
        'TongHop' => 'Tổng hợp',
        'DGNL'    => 'Đánh giá năng lực',
    ];

    return $labels[$method] ?? $method;
}

function university_method_color(string $method): string
{
    $colors = [
        'THPT'    => 'primary',
        'HocBa'   => 'success',
        'TongHop' => 'warning',
        'DGNL'    => 'info',
    ];

    return $colors[$method] ?? 'secondary';
}

// ── Dữ liệu cho bộ lọc ───────────────────────────────────────
$yearsStmt = $db->prepare("
    SELECT DISTINCT year
    FROM admission_scores
    WHERE university_id = ?
    ORDER BY year DESC
");
$yearsStmt->execute([$id]);
$years = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);

$methodsStmt = $db->prepare("
    SELECT DISTINCT method
    FROM admission_scores
    WHERE university_id = ?
      AND method IS NOT NULL
      AND TRIM(method) <> ''
    ORDER BY method
");
$methodsStmt->execute([$id]);
$methods = $methodsStmt->fetchAll(PDO::FETCH_COLUMN);

$combinationsStmt = $db->prepare("
    SELECT DISTINCT combination
    FROM admission_scores
    WHERE university_id = ?
      AND combination IS NOT NULL
      AND TRIM(combination) <> ''
    ORDER BY combination
");
$combinationsStmt->execute([$id]);
$combinations = $combinationsStmt->fetchAll(PDO::FETCH_COLUMN);

$filterYear = isset($_GET['year'])
    ? (int)$_GET['year']
    : (int)($years[0] ?? 0);

$filterMethod = trim((string)($_GET['method'] ?? ''));
$filterCombination = trim((string)($_GET['combination'] ?? ''));

// ── Lấy điểm theo bộ lọc ─────────────────────────────────────
$where = ['s.university_id = :university_id'];
$params = [':university_id' => $id];

if ($filterYear > 0) {
    $where[] = 's.year = :year';
    $params[':year'] = $filterYear;
}

if ($filterMethod !== '') {
    $where[] = 's.method = :method';
    $params[':method'] = $filterMethod;
}

if ($filterCombination !== '') {
    $where[] = 's.combination = :combination';
    $params[':combination'] = $filterCombination;
}

$scoresSql = "
    SELECT
        m.major_id,
        m.major_name,
        s.year,
        s.combination,
        s.method,
        s.score,
        s.quota
    FROM admission_scores s
    JOIN majors m
      ON s.major_id = m.major_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY s.score DESC, m.major_name ASC
";

$scoresStmt = $db->prepare($scoresSql);
$scoresStmt->execute($params);
$scores = $scoresStmt->fetchAll();

// ── Thống kê toàn trường ─────────────────────────────────────
$statsStmt = $db->prepare("
    SELECT
        COUNT(DISTINCT major_id) AS major_count,
        MAX(score) AS max_score,
        MIN(score) AS min_score,
        MAX(year) AS latest_year
    FROM admission_scores
    WHERE university_id = ?
");
$statsStmt->execute([$id]);
$stats = $statsStmt->fetch();
?>

<div class="container py-4">

  <nav class="mb-3" aria-label="breadcrumb">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item">
        <a href="<?= url('index.php') ?>">Trang chủ</a>
      </li>

      <li class="breadcrumb-item">
        <a href="<?= url('search.php') ?>">Tra cứu nâng cao</a>
      </li>

      <li class="breadcrumb-item active" aria-current="page">
        <?= e($university['university_name']) ?>
      </li>
    </ol>
  </nav>

  <!-- THÔNG TIN TRƯỜNG -->
  <div class="card mb-4 p-4">
    <div class="d-flex gap-4 flex-wrap align-items-start">

      <div
        class="uni-logo flex-shrink-0 d-flex align-items-center justify-content-center"
        style="width:80px;height:80px;font-size:18px;font-weight:700"
      >
        <?= e(
            $university['university_code']
            ?: mb_substr($university['university_name'], 0, 4, 'UTF-8')
        ) ?>
      </div>

      <div class="flex-grow-1">
        <h2 class="fw-bold mb-2">
          <?= e($university['university_name']) ?>
        </h2>

        <div class="d-flex flex-wrap gap-3 text-muted small mb-2">
          <?php if (!empty($university['province'])): ?>
            <span>
              <i class="bi bi-geo-alt me-1"></i>
              <?= e($university['province']) ?>
            </span>
          <?php endif; ?>

          <?php if (!empty($university['address'])): ?>
            <span>
              <i class="bi bi-signpost me-1"></i>
              <?= e($university['address']) ?>
            </span>
          <?php endif; ?>

          <span>
            <i class="bi bi-building me-1"></i>
            <?= e($university['school_type'] ?? '') ?>
          </span>
        </div>

        <?php if (!empty($university['description'])): ?>
          <p class="text-muted small mb-3">
            <?= e($university['description']) ?>
          </p>
        <?php endif; ?>

        <div class="d-flex flex-wrap gap-2">
          <?php if (!empty($university['website'])): ?>
            <a
              href="<?= e($university['website']) ?>"
              target="_blank"
              rel="noopener noreferrer"
              class="btn btn-sm btn-outline-primary"
            >
              <i class="bi bi-globe me-1"></i>
              Website trường
            </a>
          <?php endif; ?>

          <a
            href="<?= url('compare.php?uni1=' . $id) ?>"
            class="btn btn-sm btn-primary"
          >
            <i class="bi bi-bar-chart-line me-1"></i>
            Thêm vào so sánh
          </a>
        </div>
      </div>

    </div>
  </div>

  <div class="row g-4">

    <!-- BẢNG ĐIỂM -->
    <div class="col-lg-8">
      <div class="card">

        <div class="card-header">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

            <div>
              <div class="fw-semibold">
                <i class="bi bi-list-ul me-1"></i>
                Điểm chuẩn
                <?= $filterYear > 0 ? 'năm ' . e($filterYear) : 'tất cả các năm' ?>
              </div>

              <div class="text-muted small mt-1">
                <?= count($scores) ?> bản ghi phù hợp
              </div>
            </div>

            <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
              <input type="hidden" name="id" value="<?= $id ?>">

              <select
                name="method"
                class="form-select form-select-sm"
                style="width:auto;min-width:170px"
                onchange="this.form.submit()"
              >
                <option value="">Tất cả phương thức</option>

                <?php foreach ($methods as $methodValue): ?>
                  <option
                    value="<?= e($methodValue) ?>"
                    <?= $filterMethod === $methodValue ? 'selected' : '' ?>
                  >
                    <?= e(university_method_label($methodValue)) ?>
                  </option>
                <?php endforeach; ?>
              </select>

              <select
                name="combination"
                class="form-select form-select-sm"
                style="width:auto;min-width:130px"
                onchange="this.form.submit()"
              >
                <option value="">Tất cả tổ hợp</option>

                <?php foreach ($combinations as $combination): ?>
                  <option
                    value="<?= e($combination) ?>"
                    <?= $filterCombination === $combination ? 'selected' : '' ?>
                  >
                    <?= e($combination) ?>
                  </option>
                <?php endforeach; ?>
              </select>

              <select
                name="year"
                class="form-select form-select-sm"
                style="width:auto;min-width:110px"
                onchange="this.form.submit()"
              >
                <option value="0" <?= $filterYear === 0 ? 'selected' : '' ?>>
                  Tất cả năm
                </option>

                <?php foreach ($years as $year): ?>
                  <option
                    value="<?= e($year) ?>"
                    <?= $filterYear === (int)$year ? 'selected' : '' ?>
                  >
                    <?= e($year) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </form>

          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle small">
            <thead>
              <tr>
                <th>Ngành</th>
                <th>Năm</th>
                <th>Tổ hợp</th>
                <th>Phương thức</th>
                <th>Điểm chuẩn</th>
              </tr>
            </thead>

            <tbody>
              <?php if (empty($scores)): ?>
                <tr>
                  <td colspan="6" class="text-center text-muted py-5">
                    Không có dữ liệu phù hợp với bộ lọc hiện tại.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($scores as $row): ?>
                  <?php
                  $scoreClass = $row['score'] >= 27
                      ? 'sb-hi'
                      : ($row['score'] >= 23 ? 'sb-mid' : 'sb-lo');

                  $methodValue = (string)($row['method'] ?? '');
                  ?>

                  <tr>
                    <td>
                      <a
                        href="<?= url('major.php?id=' . $row['major_id']) ?>"
                        class="text-decoration-none fw-semibold"
                      >
                        <?= e($row['major_name']) ?>
                      </a>
                    </td>

                    <td>
                      <span class="chip"><?= e($row['year']) ?></span>
                    </td>

                    <td>
                      <span class="chip">
                        <?= !empty($row['combination'])
                            ? e($row['combination'])
                            : '—' ?>
                      </span>
                    </td>

                    <td>
                      <?php if ($methodValue !== ''): ?>
                        <span
                          class="badge text-bg-<?= e(university_method_color($methodValue)) ?> fw-normal"
                          style="font-size:10px;border-radius:20px"
                        >
                          <?= e(university_method_label($methodValue)) ?>
                        </span>
                      <?php else: ?>
                        <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>

                    <td>
                      <span class="score-badge <?= $scoreClass ?>">
                        <?= number_format((float)$row['score'], 2) ?>
                      </span>
                    </td>


                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>

    <!-- THỐNG KÊ -->
    <div class="col-lg-4">
      <div class="card p-4 mb-3">
        <h5 class="fw-bold mb-3">
          <i class="bi bi-info-circle me-1 text-primary"></i>
          Tổng quan
        </h5>

        <table class="table table-sm small mb-0">
          <tr>
            <td class="text-muted">Mã trường</td>
            <td class="fw-semibold">
              <?= e($university['university_code'] ?? '—') ?>
            </td>
          </tr>

          <tr>
            <td class="text-muted">Số ngành có dữ liệu</td>
            <td><?= number_format((int)($stats['major_count'] ?? 0)) ?></td>
          </tr>

          <tr>
            <td class="text-muted">Năm dữ liệu mới nhất</td>
            <td><?= e($stats['latest_year'] ?? '—') ?></td>
          </tr>

          <tr>
            <td class="text-muted">Điểm cao nhất</td>
            <td class="fw-bold text-primary">
              <?= $stats['max_score'] !== null
                  ? number_format((float)$stats['max_score'], 2)
                  : '—' ?>
            </td>
          </tr>

          <tr>
            <td class="text-muted">Điểm thấp nhất</td>
            <td>
              <?= $stats['min_score'] !== null
                  ? number_format((float)$stats['min_score'], 2)
                  : '—' ?>
            </td>
          </tr>
        </table>
      </div>

      <a
        href="<?= url('compare.php?uni1=' . $id) ?>"
        class="btn btn-primary w-100 mb-2"
      >
        <i class="bi bi-bar-chart-line me-1"></i>
        So sánh với trường khác
      </a>

      <a
        href="<?= url('ai_recommend.php?province=' . urlencode($university['province'] ?? '')) ?>"
        class="btn btn-outline-primary w-100"
      >
        <i class="bi bi-stars me-1"></i>
        Gợi ý theo điểm
      </a>
    </div>

  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
