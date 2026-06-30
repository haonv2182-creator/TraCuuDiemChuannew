<?php
$pageTitle = 'Tra cứu nâng cao – DiemChuan.vn';
require_once 'includes/header.php';
<section class="sub-page-hero">
  <div class="container text-center">
    <span class="sub-page-label">
      <i class="bi bi-funnel"></i>
      Tra cứu nâng cao
    </span>

    <h1 class="sub-page-title">Tìm kiếm điểm chuẩn</h1>

    <p class="sub-page-subtitle">
      Lọc dữ liệu theo trường, ngành, năm, tổ hợp, phương thức xét tuyển và mức điểm.
    </p>
  </div>
</section>

$db = getDB();

$q          = trim((string)($_GET['q'] ?? ''));
$major      = trim((string)($_GET['major'] ?? ''));
$year       = (int)($_GET['year'] ?? 0);
$combo      = trim((string)($_GET['combo'] ?? ''));
$province   = trim((string)($_GET['province'] ?? ''));
$schoolType = trim((string)($_GET['school_type'] ?? ''));
$method     = trim((string)($_GET['method'] ?? ''));
$minScore   = (float)($_GET['min'] ?? 0);
$maxScore   = (float)($_GET['max'] ?? 0);
$sort       = trim((string)($_GET['sort'] ?? 'year_desc'));
$latestOnly = isset($_GET['latest']) && $_GET['latest'] === '1';
$page       = max(1, (int)($_GET['page'] ?? 1));
$limit      = 20;

/**
 * Tên hiển thị của phương thức xét tuyển.
 */
function search_method_label(string $value): string
{
    $labels = [
        'THPT'    => 'Thi THPT',
        'HocBa'   => 'Học bạ',
        'TongHop' => 'Tổng hợp',
        'DGNL'    => 'Đánh giá năng lực',
        'Thang'   => 'Xét thẳng'
    ];

    return $labels[$value] ?? $value;
}

/**
 * Màu badge phương thức.
 */
function search_method_color(string $value): string
{
    $colors = [
        'THPT'    => 'primary',
        'HocBa'   => 'success',
        'TongHop' => 'warning',
        'DGNL'    => 'info',
        'Thang'   => 'secondary'
    ];

    return $colors[$value] ?? 'secondary';
}

$sortOptions = [
    'year_desc'  => [
        'label' => 'Năm mới nhất',
        'sql'   => 's.year DESC, s.score DESC'
    ],
    'score_desc' => [
        'label' => 'Điểm cao đến thấp',
        'sql'   => 's.score DESC, s.year DESC'
    ],
    'score_asc'  => [
        'label' => 'Điểm thấp đến cao',
        'sql'   => 's.score ASC, s.year DESC'
    ],
    'school_asc' => [
        'label' => 'Tên trường A–Z',
        'sql'   => 'u.university_name ASC, s.year DESC, s.score DESC'
    ]
];

if (!isset($sortOptions[$sort])) {
    $sort = 'year_desc';
}

$orderSql = $sortOptions[$sort]['sql'];

$where  = ['1 = 1'];
$params = [];

if ($q !== '') {
    $where[] = 'u.university_name LIKE :q';
    $params[':q'] = "%{$q}%";
}

if ($major !== '') {
    $where[] = 'm.major_name = :major';
    $params[':major'] = $major;
}

if ($year > 0) {
    $where[] = 's.year = :year';
    $params[':year'] = $year;
}

if ($combo !== '') {
    $where[] = 's.combination = :combo';
    $params[':combo'] = $combo;
}

if ($province !== '') {
    $where[] = 'u.province = :province';
    $params[':province'] = $province;
}

if ($schoolType !== '') {
    $where[] = 'u.school_type = :school_type';
    $params[':school_type'] = $schoolType;
}

if ($method !== '') {
    $where[] = 's.method = :method';
    $params[':method'] = $method;
}

if ($minScore > 0) {
    $where[] = 's.score >= :min_score';
    $params[':min_score'] = $minScore;
}

if ($maxScore > 0) {
    $where[] = 's.score <= :max_score';
    $params[':max_score'] = $maxScore;
}

/*
 * Chỉ lấy bản ghi mới nhất của cùng:
 * trường + ngành + tổ hợp + phương thức.
 * Nếu người dùng chọn một năm cụ thể thì bộ lọc năm được ưu tiên.
 */
if ($latestOnly && !$year) {
    $where[] = "s.year = (
        SELECT MAX(s2.year)
        FROM admission_scores s2
        WHERE s2.university_id = s.university_id
          AND s2.major_id = s.major_id
          AND COALESCE(s2.combination, '') = COALESCE(s.combination, '')
          AND COALESCE(s2.method, '') = COALESCE(s.method, '')
    )";
}

$whereSql = implode(' AND ', $where);

$fromSql = "
    FROM admission_scores s
    JOIN universities u
      ON s.university_id = u.university_id
    JOIN majors m
      ON s.major_id = m.major_id
    WHERE {$whereSql}
";

$countStmt = $db->prepare("SELECT COUNT(*) {$fromSql}");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();

$pagination = paginate($totalRows, $limit, $page);

$dataSql = "
    SELECT
        s.score_id,
        s.year,
        s.combination,
        s.method,
        s.score,
        s.quota,
        u.university_id,
        u.university_name,
        u.university_code,
        u.province,
        u.school_type,
        m.major_id,
        m.major_name
    {$fromSql}
    ORDER BY {$orderSql}
    LIMIT :limit OFFSET :offset
";

$stmt = $db->prepare($dataSql);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();

$rows = $stmt->fetchAll();

$allMajors = $db->query("
    SELECT major_name
    FROM majors
    ORDER BY major_name
")->fetchAll(PDO::FETCH_COLUMN);

$years = $db->query("
    SELECT DISTINCT year
    FROM admission_scores
    ORDER BY year DESC
")->fetchAll(PDO::FETCH_COLUMN);

$combinations = $db->query("
    SELECT DISTINCT combination
    FROM admission_scores
    WHERE combination IS NOT NULL
      AND TRIM(combination) <> ''
    ORDER BY combination
")->fetchAll(PDO::FETCH_COLUMN);

$methods = $db->query("
    SELECT DISTINCT method
    FROM admission_scores
    WHERE method IS NOT NULL
      AND TRIM(method) <> ''
    ORDER BY method
")->fetchAll(PDO::FETCH_COLUMN);

$provinces  = getProvinces();
$schoolTypes = ['Công lập', 'Dân lập', 'Tư thục', 'Quốc tế'];

$hasFilter =
    $q !== ''
    || $major !== ''
    || $year > 0
    || $combo !== ''
    || $province !== ''
    || $schoolType !== ''
    || $method !== ''
    || $minScore > 0
    || $maxScore > 0
    || $latestOnly
    || $sort !== 'year_desc';

$activeFilters = [];

if ($q !== '') {
    $activeFilters[] = [
        'Trường: ' . $q,
        array_merge($_GET, ['q' => '', 'page' => 1])
    ];
}

if ($major !== '') {
    $activeFilters[] = [
        'Ngành: ' . $major,
        array_merge($_GET, ['major' => '', 'page' => 1])
    ];
}

if ($year > 0) {
    $activeFilters[] = [
        'Năm: ' . $year,
        array_merge($_GET, ['year' => '', 'page' => 1])
    ];
}

if ($combo !== '') {
    $activeFilters[] = [
        'Tổ hợp: ' . $combo,
        array_merge($_GET, ['combo' => '', 'page' => 1])
    ];
}

if ($province !== '') {
    $activeFilters[] = [
        'Tỉnh/TP: ' . $province,
        array_merge($_GET, ['province' => '', 'page' => 1])
    ];
}

if ($schoolType !== '') {
    $activeFilters[] = [
        'Loại trường: ' . $schoolType,
        array_merge($_GET, ['school_type' => '', 'page' => 1])
    ];
}

if ($method !== '') {
    $activeFilters[] = [
        'Phương thức: ' . search_method_label($method),
        array_merge($_GET, ['method' => '', 'page' => 1])
    ];
}

if ($minScore > 0) {
    $activeFilters[] = [
        'Điểm từ: ' . $minScore,
        array_merge($_GET, ['min' => '', 'page' => 1])
    ];
}

if ($maxScore > 0) {
    $activeFilters[] = [
        'Điểm đến: ' . $maxScore,
        array_merge($_GET, ['max' => '', 'page' => 1])
    ];
}

if ($latestOnly) {
    $activeFilters[] = [
        'Chỉ dữ liệu mới nhất',
        array_merge($_GET, ['latest' => '', 'page' => 1])
    ];
}
?>

<!-- TIÊU ĐỀ TRANG -->
<div
  style="background:var(--gray-50);border-bottom:1px solid var(--gray-200);padding:18px 0"
>
  <div class="container">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">

      <div>
        <h4 class="fw-bold mb-1">
          <i class="bi bi-funnel me-2 text-primary"></i>
          Tra cứu nâng cao
        </h4>

        <p class="text-muted small mb-0">
          Lọc điểm chuẩn theo trường, ngành, năm và phương thức xét tuyển
        </p>
      </div>

    </div>
  </div>
</div>

<div class="container py-4 sub-page-content">
  <div class="row g-4">
    <div class="col-lg-3">
      <div style="position:sticky;top:76px">

        <form method="GET" id="filterForm">

          <div
            class="mb-3 p-3 rounded-3 text-center"
            style="background:var(--primary-lt);border:1px solid rgba(26,86,219,.2)"
          >
            <div class="fw-bold text-primary" style="font-size:22px">
              <?= number_format($pagination['total']) ?>
            </div>

            <div class="text-muted small">
              kết quả tìm thấy
            </div>
          </div>

          <!-- Tên trường -->
          <div class="mb-3">
            <label class="form-label fw-semibold small text-uppercase text-muted filter-label">
              <i class="bi bi-building me-1"></i>
              Tên trường
            </label>

            <input
              type="text"
              name="q"
              value="<?= e($q) ?>"
              placeholder="Ví dụ: Bách Khoa..."
              class="form-control form-control-sm"
            >
          </div>

          <!-- Ngành học -->
          <div class="mb-3">
            <label class="form-label fw-semibold small text-uppercase text-muted filter-label">
              <i class="bi bi-book me-1"></i>
              Ngành học
            </label>

            <select
              name="major"
              class="form-select form-select-sm"
            >
              <option value="">Tất cả ngành</option>

              <?php foreach ($allMajors as $majorName): ?>
                <option
                  value="<?= e($majorName) ?>"
                  <?= $major === $majorName ? 'selected' : '' ?>
                >
                  <?= e($majorName) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Năm -->
          <div class="mb-3">
            <label class="form-label fw-semibold small text-uppercase text-muted filter-label">
              <i class="bi bi-calendar me-1"></i>
              Năm tuyển sinh
            </label>

            <select
              name="year"
              class="form-select form-select-sm"
            >
              <option value="0">Tất cả năm</option>

              <?php foreach ($years as $itemYear): ?>
                <option
                  value="<?= e($itemYear) ?>"
                  <?= $year === (int)$itemYear ? 'selected' : '' ?>
                >
                  <?= e($itemYear) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Chỉ dữ liệu mới nhất -->
          <div class="form-check form-switch mb-3">
            <input
              class="form-check-input"
              type="checkbox"
              role="switch"
              id="latestOnly"
              name="latest"
              value="1"
              <?= $latestOnly ? 'checked' : '' ?>
              <?= $year > 0 ? 'disabled' : '' ?>
            >

            <label class="form-check-label small" for="latestOnly">
              Chỉ xem dữ liệu mới nhất
            </label>

            <?php if ($year > 0): ?>
              <div class="text-muted" style="font-size:10px">
                Đang ưu tiên năm đã chọn
              </div>
            <?php endif; ?>
          </div>

          <!-- Tổ hợp -->
          <div class="mb-3">
            <label class="form-label fw-semibold small text-uppercase text-muted filter-label">
              <i class="bi bi-grid me-1"></i>
              Tổ hợp xét tuyển
            </label>

            <select
              name="combo"
              class="form-select form-select-sm"
            >
              <option value="">Tất cả tổ hợp</option>

              <?php foreach ($combinations as $combination): ?>
                <option
                  value="<?= e($combination) ?>"
                  <?= $combo === $combination ? 'selected' : '' ?>
                >
                  <?= e($combination) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Phương thức -->
          <div class="mb-3">
            <label class="form-label fw-semibold small text-uppercase text-muted filter-label">
              <i class="bi bi-card-checklist me-1"></i>
              Phương thức xét tuyển
            </label>

            <select
              name="method"
              class="form-select form-select-sm"
            >
              <option value="">Tất cả phương thức</option>

              <?php foreach ($methods as $methodValue): ?>
                <option
                  value="<?= e($methodValue) ?>"
                  <?= $method === $methodValue ? 'selected' : '' ?>
                >
                  <?= e(search_method_label($methodValue)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Khoảng điểm -->
          <div class="mb-3">
            <label class="form-label fw-semibold small text-uppercase text-muted filter-label">
              <i class="bi bi-sliders me-1"></i>
              Khoảng điểm
            </label>

            <div class="d-flex gap-2">
              <input
                type="number"
                name="min"
                value="<?= $minScore > 0 ? e($minScore) : '' ?>"
                placeholder="Từ"
                class="form-control form-control-sm"
                min="0"
                max="1200"
                step="0.01"
              >

              <input
                type="number"
                name="max"
                value="<?= $maxScore > 0 ? e($maxScore) : '' ?>"
                placeholder="Đến"
                class="form-control form-control-sm"
                min="0"
                max="1200"
                step="0.01"
              >
            </div>
          </div>

          <!-- Tỉnh thành -->
          <div class="mb-3">
            <label class="form-label fw-semibold small text-uppercase text-muted filter-label">
              <i class="bi bi-geo-alt me-1"></i>
              Tỉnh/Thành phố
            </label>

            <select
              name="province"
              class="form-select form-select-sm"
            >
              <option value="">Tất cả tỉnh thành</option>

              <?php foreach ($provinces as $provinceItem): ?>
                <option
                  value="<?= e($provinceItem) ?>"
                  <?= $province === $provinceItem ? 'selected' : '' ?>
                >
                  <?= e($provinceItem) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Loại trường -->
          <div class="mb-3">
            <label class="form-label fw-semibold small text-uppercase text-muted filter-label">
              <i class="bi bi-bank me-1"></i>
              Loại trường
            </label>

            <select
              name="school_type"
              class="form-select form-select-sm"
            >
              <option value="">Tất cả loại trường</option>

              <?php foreach ($schoolTypes as $type): ?>
                <option
                  value="<?= e($type) ?>"
                  <?= $schoolType === $type ? 'selected' : '' ?>
                >
                  <?= e($type) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Sắp xếp -->
          <div class="mb-4">
            <label class="form-label fw-semibold small text-uppercase text-muted filter-label">
              <i class="bi bi-sort-down me-1"></i>
              Sắp xếp kết quả
            </label>

            <select
              name="sort"
              class="form-select form-select-sm"
            >
              <?php foreach ($sortOptions as $sortValue => $sortItem): ?>
                <option
                  value="<?= e($sortValue) ?>"
                  <?= $sort === $sortValue ? 'selected' : '' ?>
                >
                  <?= e($sortItem['label']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <button
            type="submit"
            class="btn btn-primary w-100 fw-semibold"
          >
            <i class="bi bi-funnel me-1"></i>
            Tìm kiếm
          </button>

          <?php if ($hasFilter): ?>
            <a
              href="<?= url('search.php') ?>"
              class="btn btn-outline-secondary w-100 mt-2"
            >
              Xóa bộ lọc
            </a>
          <?php endif; ?>

        </form>
      </div>
    </div>
    <div class="col-lg-9">

      <?php if (!empty($activeFilters)): ?>
        <div class="d-flex flex-wrap gap-2 mb-3">
          <?php foreach ($activeFilters as [$label, $queryParams]): ?>
            <a
              href="?<?= e(http_build_query($queryParams)) ?>"
              class="badge text-bg-primary text-decoration-none d-flex align-items-center gap-1"
              style="border-radius:20px;font-size:11px;font-weight:500;padding:6px 11px"
            >
              <?= e($label) ?>
              <i class="bi bi-x-circle ms-1"></i>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="card">

        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
          <span class="fw-semibold">
            <?php if ($pagination['total'] > 0): ?>
              Hiển thị
              <?= $pagination['offset'] + 1 ?>–<?= min(
                  $pagination['offset'] + $pagination['per_page'],
                  $pagination['total']
              ) ?>
              trong
              <strong class="text-primary">
                <?= number_format($pagination['total']) ?>
              </strong>
              kết quả
            <?php else: ?>
              Không có kết quả
            <?php endif; ?>
          </span>

          <span class="text-muted small">
            <?= e($sortOptions[$sort]['label']) ?>
          </span>
        </div>

        <?php if (empty($rows)): ?>
          <div class="text-center py-5 text-muted">
            <i
              class="bi bi-inbox"
              style="font-size:48px;display:block;margin-bottom:12px"
            ></i>

            <h6>Không tìm thấy kết quả phù hợp</h6>

            <p class="small">
              Thử bỏ bớt một số bộ lọc hoặc chọn tiêu chí khác.
            </p>

            <a
              href="<?= url('search.php') ?>"
              class="btn btn-outline-primary btn-sm"
            >
              Xóa bộ lọc
            </a>
          </div>
        <?php else: ?>

          <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Trường đại học</th>
                  <th>Ngành học</th>
                  <th>Năm</th>
                  <th>Tổ hợp</th>
                  <th>Phương thức</th>
                  <th>Điểm chuẩn</th>
                  <th>Chỉ tiêu</th>
                  <th style="min-width:145px">Thao tác</th>
                </tr>
              </thead>

              <tbody>
                <?php foreach ($rows as $index => $row): ?>
                  <?php
                  $scoreClass = $row['score'] >= 27
                      ? 'sb-hi'
                      : ($row['score'] >= 23 ? 'sb-mid' : 'sb-lo');

                  $methodValue = (string)($row['method'] ?? '');
                  ?>

                  <tr>
                    <td class="text-muted small">
                      <?= $pagination['offset'] + $index + 1 ?>
                    </td>

                    <td>
                      <a
                        href="<?= url('university.php?id=' . $row['university_id']) ?>"
                        class="fw-semibold text-decoration-none small d-block"
                      >
                        <?= e($row['university_name']) ?>
                      </a>

                      <span class="text-muted" style="font-size:11px">
                        <i class="bi bi-geo-alt me-1"></i>
                        <?= e($row['province']) ?>

                        <?php if (!empty($row['school_type'])): ?>
                          · <?= e($row['school_type']) ?>
                        <?php endif; ?>
                      </span>
                    </td>

                    <td>
                      <a
                        href="<?= url('major.php?id=' . $row['major_id']) ?>"
                        class="text-decoration-none text-reset small"
                      >
                        <?= e($row['major_name']) ?>
                      </a>
                    </td>

                    <td>
                      <span class="chip">
                        <?= e($row['year']) ?>
                      </span>
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
                          class="badge text-bg-<?= e(search_method_color($methodValue)) ?> fw-normal"
                          style="font-size:10px;border-radius:20px"
                        >
                          <?= e(search_method_label($methodValue)) ?>
                        </span>
                      <?php else: ?>
                        <span class="text-muted small">—</span>
                      <?php endif; ?>
                    </td>

                    <td>
                      <span class="score-badge <?= $scoreClass ?> fw-bold">
                        <?= number_format((float)$row['score'], 2) ?>
                      </span>
                    </td>

                    <td class="text-muted small">
                      <?= !empty($row['quota'])
                          ? number_format((int)$row['quota'])
                          : '—' ?>
                    </td>

                    <td>
                      <div class="d-flex flex-wrap gap-1">
                        <a
                          href="<?= url('university.php?id=' . $row['university_id']) ?>"
                          class="btn btn-sm btn-outline-primary py-0 px-2"
                        >
                          Chi tiết
                        </a>

                        <a
                          href="<?= url(
                              'compare.php?uni1=' . $row['university_id']
                              . '&major=' . $row['major_id']
                          ) ?>"
                          class="btn btn-sm btn-outline-secondary py-0 px-2"
                          title="Đưa trường và ngành này vào trang so sánh"
                        >
                          <i class="bi bi-bar-chart-line"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <?php if ($pagination['total_pages'] > 1): ?>
            <div class="card-footer d-flex justify-content-center py-3">
              <nav aria-label="Phân trang kết quả">
                <ul class="pagination pagination-sm mb-0">

                  <li class="page-item <?= $pagination['current'] <= 1 ? 'disabled' : '' ?>">
                    <a
                      class="page-link"
                      href="?<?= e(http_build_query(array_merge(
                          $_GET,
                          ['page' => $pagination['current'] - 1]
                      ))) ?>"
                    >
                      ‹ Trước
                    </a>
                  </li>

                  <?php
                  $startPage = max(1, $pagination['current'] - 2);
                  $endPage = min(
                      $pagination['total_pages'],
                      $pagination['current'] + 2
                  );
                  ?>

                  <?php if ($startPage > 1): ?>
                    <li class="page-item disabled">
                      <span class="page-link">…</span>
                    </li>
                  <?php endif; ?>

                  <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                    <li class="page-item <?= $p === $pagination['current'] ? 'active' : '' ?>">
                      <a
                        class="page-link"
                        href="?<?= e(http_build_query(array_merge(
                            $_GET,
                            ['page' => $p]
                        ))) ?>"
                      >
                        <?= $p ?>
                      </a>
                    </li>
                  <?php endfor; ?>

                  <?php if ($endPage < $pagination['total_pages']): ?>
                    <li class="page-item disabled">
                      <span class="page-link">…</span>
                    </li>
                  <?php endif; ?>

                  <li class="page-item <?= $pagination['current'] >= $pagination['total_pages'] ? 'disabled' : '' ?>">
                    <a
                      class="page-link"
                      href="?<?= e(http_build_query(array_merge(
                          $_GET,
                          ['page' => $pagination['current'] + 1]
                      ))) ?>"
                    >
                      Sau ›
                    </a>
                  </li>

                </ul>
              </nav>
            </div>
          <?php endif; ?>

        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<style>
.filter-label {
  letter-spacing: .5px;
  font-size: 10px;
}

[data-theme="dark"] .form-check-label {
  color: #cbd5e1;
}
</style>

<?php require_once 'includes/footer.php'; ?>
