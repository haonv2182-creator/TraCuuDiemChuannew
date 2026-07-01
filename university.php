<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$db = getDB();

$id = max(0, (int)($_GET['id'] ?? 0));

if ($id <= 0) {
    redirect('search.php');
}

$stmt = $db->prepare('SELECT * FROM universities WHERE university_id = ?');
$stmt->execute([$id]);
$university = $stmt->fetch();

if (!$university) {
    http_response_code(404);
    $pageTitle = 'Không tìm thấy trường';
    require __DIR__ . '/includes/header.php';

    echo '<div class="container py-5">
            <div class="card empty-state">
                <h2>Không tìm thấy trường</h2>
                <a class="btn btn-primary mx-auto" href="' . e(url('search.php')) . '">Quay lại tra cứu</a>
            </div>
          </div>';

    require __DIR__ . '/includes/footer.php';
    exit;
}

$yearsStmt = $db->prepare('
    SELECT DISTINCT year
    FROM admission_scores
    WHERE university_id = ?
    ORDER BY year DESC
');
$yearsStmt->execute([$id]);
$years = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);

$methodsStmt = $db->prepare('
    SELECT DISTINCT method
    FROM admission_scores
    WHERE university_id = ?
    ORDER BY method
');
$methodsStmt->execute([$id]);
$methods = $methodsStmt->fetchAll(PDO::FETCH_COLUMN);

$combinationsStmt = $db->prepare("
    SELECT DISTINCT combination
    FROM admission_scores
    WHERE university_id = ?
      AND TRIM(combination) <> ''
    ORDER BY combination
");
$combinationsStmt->execute([$id]);
$combinations = $combinationsStmt->fetchAll(PDO::FETCH_COLUMN);

$filterYear = isset($_GET['year'])
    ? max(0, (int)$_GET['year'])
    : (int)($years[0] ?? 0);

$filterMethod = trim((string)($_GET['method'] ?? ''));
$filterCombination = trim((string)($_GET['combination'] ?? ''));

$where = ['s.university_id = :id'];
$params = [':id' => $id];

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

$sql = '
    SELECT
        s.*,
        m.major_name,
        m.major_code
    FROM admission_scores s
    JOIN majors m
      ON m.major_id = s.major_id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY
        CASE
            WHEN s.method = "DGNL" THEN s.score / 40
            ELSE s.score
        END DESC,
        m.major_name
';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$scores = $stmt->fetchAll();

$statsStmt = $db->prepare('
    SELECT
        COUNT(DISTINCT major_id) AS major_count,
        MAX(year) AS latest_year,
        COUNT(*) AS score_count
    FROM admission_scores
    WHERE university_id = ?
');
$statsStmt->execute([$id]);
$stats = $statsStmt->fetch();

$trendStmt = $db->prepare("
    SELECT
        s.year,
        ROUND(
            AVG(
                CASE
                    WHEN s.method = 'DGNL' THEN s.score / 40
                    WHEN s.method = 'Thang' THEN NULL
                    ELSE s.score
                END
            ),
            2
        ) AS avg_score
    FROM admission_scores s
    WHERE s.university_id = ?
    GROUP BY s.year
    ORDER BY s.year
");
$trendStmt->execute([$id]);
$trend = $trendStmt->fetchAll();

$pageTitle = $university['university_name'] . ' – DiemChuan.vn';

require __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item">
                <a href="<?= e(url('index.php')) ?>">Trang chủ</a>
            </li>
            <li class="breadcrumb-item">
                <a href="<?= e(url('search.php')) ?>">Tra cứu</a>
            </li>
            <li class="breadcrumb-item active">
                <?= e($university['university_name']) ?>
            </li>
        </ol>
    </nav>

    <div class="card p-4 mb-4">
        <div class="d-flex gap-4 flex-wrap align-items-start">
            <div class="uni-logo" style="width:82px;height:82px;font-size:18px">
                <?php if (!empty($university['logo'])): ?>
                    <img src="<?= e(url('uploads/' . $university['logo'])) ?>" alt="Logo">
                <?php else: ?>
                    <?= e($university['university_code'] ?: mb_substr($university['university_name'], 0, 4)) ?>
                <?php endif; ?>
            </div>

            <div class="flex-grow-1">
                <span class="section-label">
                    <?= e($university['university_code'] ?: 'Trường đại học') ?>
                </span>

                <h2 class="fw-bold mb-2">
                    <?= e($university['university_name']) ?>
                </h2>

                <div class="d-flex flex-wrap gap-3 text-muted small mb-3">
                    <span>
                        <i class="bi bi-geo-alt"></i>
                        <?= e($university['province']) ?>
                    </span>

                    <span>
                        <i class="bi bi-building"></i>
                        <?= e($university['school_type']) ?>
                    </span>

                    <?php if (!empty($university['address'])): ?>
                        <span>
                            <i class="bi bi-signpost"></i>
                            <?= e($university['address']) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($university['description'])): ?>
                    <p class="text-muted mb-3">
                        <?= e($university['description']) ?>
                    </p>
                <?php endif; ?>

                <div class="d-flex gap-2 flex-wrap">
                    <?php if (!empty($university['website'])): ?>
                        <a
                            class="btn btn-sm btn-outline-primary"
                            target="_blank"
                            rel="noopener noreferrer"
                            href="<?= e($university['website']) ?>"
                        >
                            <i class="bi bi-globe me-1"></i>
                            Website trường
                        </a>
                    <?php endif; ?>

                    <a
                        class="btn btn-sm btn-primary"
                        href="<?= e(url('compare.php?uni1=' . $id)) ?>"
                    >
                        <i class="bi bi-bar-chart-line me-1"></i>
                        Đưa vào so sánh
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="value">
                    <?= number_format((int)$stats['major_count']) ?>
                </div>
                <div class="text-muted">Ngành có dữ liệu</div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <div class="value">
                    <?= e((string)($stats['latest_year'] ?: '—')) ?>
                </div>
                <div class="text-muted">Năm cập nhật mới nhất</div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <div class="value">
                    <?= number_format((int)$stats['score_count']) ?>
                </div>
                <div class="text-muted">Bản ghi điểm chuẩn</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card overflow-hidden">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span>Điểm chuẩn của trường</span>

                    <form class="d-flex gap-2 flex-wrap" method="get">
                        <input type="hidden" name="id" value="<?= $id ?>">

                        <select class="form-select form-select-sm" name="year" onchange="this.form.submit()">
                            <option value="0">Tất cả năm</option>

                            <?php foreach ($years as $item): ?>
                                <option
                                    value="<?= e($item) ?>"
                                    <?= $filterYear === (int)$item ? 'selected' : '' ?>
                                >
                                    <?= e($item) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select class="form-select form-select-sm" name="method" onchange="this.form.submit()">
                            <option value="">Tất cả phương thức</option>

                            <?php foreach ($methods as $item): ?>
                                <option
                                    value="<?= e($item) ?>"
                                    <?= $filterMethod === $item ? 'selected' : '' ?>
                                >
                                    <?= e(methodLabel($item)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select class="form-select form-select-sm" name="combination" onchange="this.form.submit()">
                            <option value="">Tất cả tổ hợp</option>

                            <?php foreach ($combinations as $item): ?>
                                <option
                                    value="<?= e($item) ?>"
                                    <?= $filterCombination === $item ? 'selected' : '' ?>
                                >
                                    <?= e($item) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Ngành</th>
                                <th>Năm</th>
                                <th>Tổ hợp</th>
                                <th>Phương thức</th>
                                <th>Điểm</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($scores as $row): ?>
                                <tr>
                                    <td>
                                        <a
                                            class="fw-semibold text-decoration-none"
                                            href="<?= e(url('major.php?id=' . $row['major_id'])) ?>"
                                        >
                                            <?= e($row['major_name']) ?>
                                        </a>

                                        <div class="small text-muted">
                                            <?= e($row['major_code'] ?: '') ?>
                                        </div>
                                    </td>

                                    <td><?= e($row['year']) ?></td>

                                    <td><?= e($row['combination'] ?: '—') ?></td>

                                    <td>
                                        <span class="badge text-bg-<?= e(methodColor($row['method'])) ?>">
                                            <?= e(methodLabel($row['method'])) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="score-badge <?= $row['method'] === 'DGNL' ? 'dgnl' : ($row['method'] === 'Thang' ? 'direct' : '') ?>">
                                            <?= e(scoreText($row['score'], $row['method'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$scores): ?>
                                <tr>
                                    <td colspan="5" class="empty-state">
                                        Không có dữ liệu theo bộ lọc đã chọn.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card p-3">
                <h5 class="fw-bold">Xu hướng điểm trung bình</h5>
                <p class="text-muted small">
                    Quy đổi ĐGNL về thang 30 để biểu đồ dễ so sánh.
                </p>
                <canvas id="trendChart" height="220"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
makeLineChart(
    'trendChart',
    <?= json_encode(array_map('strval', array_column($trend, 'year')), JSON_UNESCAPED_UNICODE) ?>,
    [
        {
            label: 'Điểm trung bình',
            data: <?= json_encode(array_map('floatval', array_column($trend, 'avg_score'))) ?>,
            borderColor: '#1a56db',
            backgroundColor: 'rgba(26,86,219,.12)',
            tension: .3,
            fill: true
        }
    ],
    30
);
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>