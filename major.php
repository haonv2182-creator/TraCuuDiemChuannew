<?php
require_once 'includes/functions.php';

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    redirect('search.php');
}

$db = getDB();

$majorStmt = $db->prepare("
    SELECT *
    FROM majors
    WHERE major_id = ?
");
$majorStmt->execute([$id]);
$major = $majorStmt->fetch();

if (!$major) {
    http_response_code(404);
    $pageTitle = 'Không tìm thấy ngành – DiemChuan.vn';
    require_once 'includes/header.php';
    echo '<div class="container py-5"><h2>Không tìm thấy ngành</h2></div>';
    require_once 'includes/footer.php';
    exit;
}

$pageTitle = $major['major_name'] . ' – DiemChuan.vn';
require_once 'includes/header.php';

function major_method_label(string $method): string
{
    $labels = [
        'THPT'    => 'Thi THPT',
        'HocBa'   => 'Học bạ',
        'TongHop' => 'Tổng hợp',
        'DGNL'    => 'Đánh giá năng lực',
        'Thang'   => 'Xét thẳng'
    ];

    return $labels[$method] ?? $method;
}

function major_method_color(string $method): string
{
    $colors = [
        'THPT'    => 'primary',
        'HocBa'   => 'success',
        'TongHop' => 'warning',
        'DGNL'    => 'info',
        'Thang'   => 'secondary'
    ];

    return $colors[$method] ?? 'secondary';
}

// ── Năm mới nhất ─────────────────────────────────────────────
$latestYearStmt = $db->prepare("
    SELECT MAX(year)
    FROM admission_scores
    WHERE major_id = ?
");
$latestYearStmt->execute([$id]);
$latestYear = (int)$latestYearStmt->fetchColumn();

// ── Bộ lọc ───────────────────────────────────────────────────
$filterMethod = trim((string)($_GET['method'] ?? ''));
$filterCombination = trim((string)($_GET['combination'] ?? ''));

$methodsStmt = $db->prepare("
    SELECT DISTINCT method
    FROM admission_scores
    WHERE major_id = ?
      AND year = ?
      AND method IS NOT NULL
      AND TRIM(method) <> ''
    ORDER BY method
");
$methodsStmt->execute([$id, $latestYear]);
$methods = $methodsStmt->fetchAll(PDO::FETCH_COLUMN);

$combinationsStmt = $db->prepare("
    SELECT DISTINCT combination
    FROM admission_scores
    WHERE major_id = ?
      AND year = ?
      AND combination IS NOT NULL
      AND TRIM(combination) <> ''
    ORDER BY combination
");
$combinationsStmt->execute([$id, $latestYear]);
$combinations = $combinationsStmt->fetchAll(PDO::FETCH_COLUMN);

// ── Danh sách trường ở năm mới nhất ─────────────────────────
$where = [
    's.major_id = :major_id',
    's.year = :year'
];

$params = [
    ':major_id' => $id,
    ':year' => $latestYear
];

if ($filterMethod !== '') {
    $where[] = 's.method = :method';
    $params[':method'] = $filterMethod;
}

if ($filterCombination !== '') {
    $where[] = 's.combination = :combination';
    $params[':combination'] = $filterCombination;
}

$universitiesSql = "
    SELECT
        u.university_id,
        u.university_name,
        u.university_code,
        u.province,
        u.school_type,
        s.combination,
        s.method,
        s.score,
        s.quota,
        s.year
    FROM admission_scores s
    JOIN universities u
      ON s.university_id = u.university_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY s.score DESC, u.university_name ASC
";

$universitiesStmt = $db->prepare($universitiesSql);
$universitiesStmt->execute($params);
$universities = $universitiesStmt->fetchAll();

// ── Thống kê toàn bộ ngành ───────────────────────────────────
$statsStmt = $db->prepare("
    SELECT
        COUNT(DISTINCT university_id) AS university_count,
        MAX(score) AS max_score,
        MIN(score) AS min_score,
        MAX(year) AS latest_year
    FROM admission_scores
    WHERE major_id = ?
");
$statsStmt->execute([$id]);
$stats = $statsStmt->fetch();

// ── Top 3 trường để vẽ xu hướng ──────────────────────────────
$topStmt = $db->prepare("
    SELECT university_id
    FROM admission_scores
    WHERE major_id = ?
      AND year = ?
    GROUP BY university_id
    ORDER BY MAX(score) DESC
    LIMIT 3
");
$topStmt->execute([$id, $latestYear]);
$topUniversityIds = array_map('intval', $topStmt->fetchAll(PDO::FETCH_COLUMN));

$trend = [];

if (!empty($topUniversityIds)) {
    $placeholders = implode(',', array_fill(0, count($topUniversityIds), '?'));

    $trendSql = "
        SELECT
            u.university_name,
            s.year,
            MAX(s.score) AS score
        FROM admission_scores s
        JOIN universities u
          ON s.university_id = u.university_id
        WHERE s.major_id = ?
          AND s.university_id IN ({$placeholders})
        GROUP BY u.university_id, u.university_name, s.year
        ORDER BY s.year ASC
    ";

    $trendStmt = $db->prepare($trendSql);
    $trendStmt->execute(array_merge([$id], $topUniversityIds));
    $trend = $trendStmt->fetchAll();
}

$trendYears = array_values(array_unique(array_column($trend, 'year')));
sort($trendYears);

$trendSchools = array_values(array_unique(array_column($trend, 'university_name')));
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
        <?= e($major['major_name']) ?>
      </li>
    </ol>
  </nav>

  <!-- THÔNG TIN NGÀNH -->
  <div class="card mb-4 p-4">
    <div class="d-flex gap-3 align-items-center flex-wrap">

      <div
        class="uni-logo"
        style="width:60px;height:60px;font-size:26px;flex-shrink:0"
      >
        📚
      </div>

      <div class="flex-grow-1">
        <h2 class="fw-bold mb-2">
          <?= e($major['major_name']) ?>
        </h2>

        <div class="d-flex flex-wrap gap-2">
          <?php if (!empty($major['major_code'])): ?>
            <span class="chip">
              Mã ngành: <?= e($major['major_code']) ?>
            </span>
          <?php endif; ?>

          <span class="chip">
            <?= number_format((int)($stats['university_count'] ?? 0)) ?> trường có dữ liệu
          </span>
        </div>
      </div>

      <div class="d-flex flex-wrap gap-2 ms-auto">
        <a
          href="<?= url('compare.php?major=' . $id) ?>"
          class="btn btn-primary btn-sm"
        >
          <i class="bi bi-bar-chart-line me-1"></i>
          So sánh trường
        </a>

        <a
          href="<?= url('ai_recommend.php?major_id=' . $id) ?>"
          class="btn btn-outline-primary btn-sm"
        >
          <i class="bi bi-stars me-1"></i>
          Gợi ý theo điểm
        </a>
      </div>

    </div>
  </div>

  <div class="row g-4">

    <div class="col-lg-8">

      <!-- BIỂU ĐỒ -->
      <div class="card mb-4">
        <div class="card-header">
          <i class="bi bi-graph-up me-1 text-primary"></i>
          Xu hướng điểm chuẩn của nhóm trường nổi bật
        </div>

        <div class="card-body p-3">
          <?php if (empty($trend)): ?>
            <div class="text-center text-muted py-4">
              Chưa đủ dữ liệu để vẽ biểu đồ xu hướng.
            </div>
          <?php else: ?>
            <canvas id="cMaj" height="160"></canvas>
          <?php endif; ?>
        </div>
      </div>

      <!-- DANH SÁCH TRƯỜNG -->
      <div class="card">
        <div class="card-header">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

            <div>
              <div class="fw-semibold">
                <i class="bi bi-building me-1"></i>
                Các trường đào tạo năm <?= e($latestYear) ?>
              </div>

              <div class="text-muted small mt-1">
                <?= count($universities) ?> kết quả phù hợp
              </div>
            </div>

            <form method="GET" class="d-flex flex-wrap gap-2">
              <input type="hidden" name="id" value="<?= $id ?>">

              <select
                name="method"
                class="form-select form-select-sm"
                style="width:auto;min-width:165px"
                onchange="this.form.submit()"
              >
                <option value="">Tất cả phương thức</option>

                <?php foreach ($methods as $methodValue): ?>
                  <option
                    value="<?= e($methodValue) ?>"
                    <?= $filterMethod === $methodValue ? 'selected' : '' ?>
                  >
                    <?= e(major_method_label($methodValue)) ?>
                  </option>
                <?php endforeach; ?>
              </select>

              <select
                name="combination"
                class="form-select form-select-sm"
                style="width:auto;min-width:125px"
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
            </form>

          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle small">
            <thead>
              <tr>
                <th>#</th>
                <th>Trường</th>
                <th>Tỉnh/TP</th>
                <th>Loại</th>
                <th>Tổ hợp</th>
                <th>Phương thức</th>
                <th>Điểm</th>
                <th>Chỉ tiêu</th>
                <th></th>
              </tr>
            </thead>

            <tbody>
              <?php if (empty($universities)): ?>
                <tr>
                  <td colspan="9" class="text-center text-muted py-5">
                    Không có dữ liệu phù hợp với bộ lọc hiện tại.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($universities as $index => $university): ?>
                  <?php
                  $scoreClass = $university['score'] >= 27
                      ? 'sb-hi'
                      : ($university['score'] >= 23 ? 'sb-mid' : 'sb-lo');

                  $methodValue = (string)($university['method'] ?? '');
                  ?>

                  <tr>
                    <td class="text-muted">
                      <?= $index + 1 ?>
                    </td>

                    <td>
                      <a
                        href="<?= url('university.php?id=' . $university['university_id']) ?>"
                        class="fw-semibold text-decoration-none"
                      >
                        <?= e($university['university_name']) ?>
                      </a>
                    </td>

                    <td class="text-muted">
                      <?= e($university['province']) ?>
                    </td>

                    <td>
                      <span class="chip">
                        <?= e($university['school_type']) ?>
                      </span>
                    </td>

                    <td>
                      <span class="chip">
                        <?= !empty($university['combination'])
                            ? e($university['combination'])
                            : '—' ?>
                      </span>
                    </td>

                    <td>
                      <?php if ($methodValue !== ''): ?>
                        <span
                          class="badge text-bg-<?= e(major_method_color($methodValue)) ?> fw-normal"
                          style="font-size:10px;border-radius:20px"
                        >
                          <?= e(major_method_label($methodValue)) ?>
                        </span>
                      <?php else: ?>
                        <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>

                    <td>
                      <span class="score-badge <?= $scoreClass ?>">
                        <?= number_format((float)$university['score'], 2) ?>
                      </span>
                    </td>

                    <td class="text-muted">
                      <?= !empty($university['quota'])
                          ? number_format((int)$university['quota'])
                          : '—' ?>
                    </td>

                    <td>
                      <div class="d-flex gap-1">
                        <a
                          href="<?= url('university.php?id=' . $university['university_id']) ?>"
                          class="btn btn-sm btn-outline-primary py-0 px-2"
                        >
                          Chi tiết
                        </a>

                        <a
                          href="<?= url(
                              'compare.php?uni1=' . $university['university_id']
                              . '&major=' . $id
                          ) ?>"
                          class="btn btn-sm btn-outline-secondary py-0 px-2"
                          title="Đưa vào trang so sánh"
                        >
                          <i class="bi bi-bar-chart-line"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- THÔNG TIN NGÀNH -->
    <div class="col-lg-4">
      <div class="card p-4">
        <h5 class="fw-bold mb-3">
          <i class="bi bi-info-circle me-1 text-primary"></i>
          Thông tin ngành
        </h5>

        <table class="table table-sm small mb-3">
          <tr>
            <td class="text-muted">Mã ngành</td>
            <td class="fw-semibold">
              <?= e($major['major_code'] ?? '—') ?>
            </td>
          </tr>

          <tr>
            <td class="text-muted">Số trường có dữ liệu</td>
            <td>
              <?= number_format((int)($stats['university_count'] ?? 0)) ?>
            </td>
          </tr>

          <tr>
            <td class="text-muted">Năm mới nhất</td>
            <td><?= e($stats['latest_year'] ?? '—') ?></td>
          </tr>
        </table>

        <?php if (!empty($major['description'])): ?>
          <p class="text-muted small">
            <?= e($major['description']) ?>
          </p>
        <?php endif; ?>

        <a
          href="<?= url('ai_recommend.php?major_id=' . $id) ?>"
          class="btn btn-primary btn-sm w-100 mb-2"
        >
          <i class="bi bi-stars me-1"></i>
          Gợi ý theo điểm
        </a>

        <a
          href="<?= url('compare.php?major=' . $id) ?>"
          class="btn btn-outline-primary btn-sm w-100"
        >
          <i class="bi bi-bar-chart-line me-1"></i>
          So sánh trường
        </a>
      </div>
    </div>

  </div>
</div>

<?php if (!empty($trend)): ?>
<script>
const trendRaw = <?= json_encode($trend, JSON_UNESCAPED_UNICODE) ?>;
const trendYears = <?= json_encode($trendYears, JSON_UNESCAPED_UNICODE) ?>;
const trendSchools = <?= json_encode($trendSchools, JSON_UNESCAPED_UNICODE) ?>;
const trendColors = ['#1a56db', '#10b981', '#f59e0b'];

chartLine(
  'cMaj',
  trendYears,
  trendSchools.map(function (schoolName, index) {
    return {
      label: schoolName,
      borderColor: trendColors[index % trendColors.length],
      backgroundColor: trendColors[index % trendColors.length] + '22',
      tension: .4,
      fill: true,
      pointRadius: 5,
      spanGaps: true,
      data: trendYears.map(function (year) {
        const row = trendRaw.find(function (item) {
          return item.university_name === schoolName
            && String(item.year) === String(year);
        });

        return row ? Number(row.score) : null;
      })
    };
  })
);
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
