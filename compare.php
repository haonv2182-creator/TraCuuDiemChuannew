<?php
$pageTitle = 'So sánh điểm chuẩn – DiemChuan.vn';
require_once 'includes/header.php';

$db = getDB();

$u1               = (int)($_GET['uni1'] ?? 0);
$u2               = (int)($_GET['uni2'] ?? 0);
$majorId          = (int)($_GET['major'] ?? 0);
$method           = trim((string)($_GET['method'] ?? ''));
$compareRequested = isset($_GET['compare']);

/**
 * Đổi mã phương thức thành tên dễ đọc.
 */
function compare_method_label(string $method): string
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

/**
 * Rút gọn tên trường để hiển thị trên bảng và biểu đồ.
 */
function compare_short_name(string $name, int $length = 18): string
{
    if (mb_strlen($name, 'UTF-8') <= $length) {
        return $name;
    }

    return rtrim(mb_substr($name, 0, $length, 'UTF-8')) . '…';
}

/**
 * Hiển thị điểm, nếu không có dữ liệu thì hiện dấu gạch ngang.
 */
function compare_score($score): string
{
    if ($score === null || $score === '') {
        return '—';
    }

    return number_format((float)$score, 2);
}

/**
 * Kiểm tra một giá trị có tồn tại trong mảng dữ liệu hay không.
 */
function compare_contains_value(array $rows, string $key, $value): bool
{
    foreach ($rows as $row) {
        if ((string)($row[$key] ?? '') === (string)$value) {
            return true;
        }
    }

    return false;
}

// ── Danh sách trường ─────────────────────────────────────────
$allUnis = $db->query("
    SELECT university_id, university_name
    FROM universities
    ORDER BY university_name
")->fetchAll();

$errors   = [];
$warnings = [];

// Không cho so sánh cùng một trường.
if ($u1 && $u2 && $u1 === $u2) {
    $errors[] = 'Vui lòng chọn hai trường khác nhau để so sánh.';
}

// ── Các ngành có dữ liệu ở cả hai trường ─────────────────────
$commonMajors = [];

if ($u1 && $u2 && $u1 !== $u2) {
    $stmt = $db->prepare("
        SELECT
            m.major_id,
            m.major_name
        FROM majors m
        WHERE EXISTS (
            SELECT 1
            FROM admission_scores s1
            WHERE s1.major_id = m.major_id
              AND s1.university_id = ?
        )
        AND EXISTS (
            SELECT 1
            FROM admission_scores s2
            WHERE s2.major_id = m.major_id
              AND s2.university_id = ?
        )
        ORDER BY m.major_name
    ");

    $stmt->execute([$u1, $u2]);
    $commonMajors = $stmt->fetchAll();

    if (empty($commonMajors)) {
        $warnings[] = 'Hai trường hiện chưa có ngành chung trong dữ liệu để so sánh.';
    }
}

// Khi đổi trường, bỏ ngành cũ nếu không còn hợp lệ.
if (
    $majorId
    && !compare_contains_value($commonMajors, 'major_id', $majorId)
) {
    $majorId = 0;
    $method  = '';
}

// ── Các phương thức chung của hai trường ─────────────────────
$commonMethods = [];

if (
    $u1
    && $u2
    && $u1 !== $u2
    && $majorId
) {
    $stmt = $db->prepare("
        SELECT DISTINCT s1.method
        FROM admission_scores s1
        WHERE s1.university_id = ?
          AND s1.major_id = ?
          AND s1.method IS NOT NULL
          AND TRIM(s1.method) <> ''
          AND EXISTS (
              SELECT 1
              FROM admission_scores s2
              WHERE s2.university_id = ?
                AND s2.major_id = ?
                AND s2.method = s1.method
          )
        ORDER BY s1.method
    ");

    $stmt->execute([
        $u1,
        $majorId,
        $u2,
        $majorId
    ]);

    $commonMethods = $stmt->fetchAll();

    if (empty($commonMethods)) {
        $warnings[] = 'Ngành đã chọn chưa có phương thức xét tuyển chung ở cả hai trường.';
    }
}

// Nếu chỉ có một phương thức thì tự chọn.
if ($method === '' && count($commonMethods) === 1) {
    $method = (string)$commonMethods[0]['method'];
}

// Khi đổi ngành, bỏ phương thức cũ nếu không còn hợp lệ.
if (
    $method !== ''
    && !compare_contains_value($commonMethods, 'method', $method)
) {
    $method = '';
}

// ── Tên ngành đang chọn ──────────────────────────────────────
$majorName = '';

foreach ($commonMajors as $major) {
    if ((int)$major['major_id'] === $majorId) {
        $majorName = (string)$major['major_name'];
        break;
    }
}

// Chỉ cần cùng ngành và cùng phương thức, không bắt buộc cùng tổ hợp.
$readyToCompare =
    $u1
    && $u2
    && $u1 !== $u2
    && $majorId
    && $majorName !== ''
    && $method !== '';

if ($compareRequested && !$readyToCompare && empty($errors)) {
    $errors[] = 'Vui lòng chọn đầy đủ hai trường, ngành học và phương thức xét tuyển.';
}

// ── Dữ liệu kết quả ──────────────────────────────────────────
$dataA = [];
$dataB = [];
$nameA = null;
$nameB = null;

if ($readyToCompare && $compareRequested) {
    // Thông tin hai trường.
    $schoolStmt = $db->prepare("
        SELECT
            university_name,
            university_code,
            province,
            school_type,
            website
        FROM universities
        WHERE university_id = ?
    ");

    $schoolStmt->execute([$u1]);
    $nameA = $schoolStmt->fetch();

    $schoolStmt->execute([$u2]);
    $nameB = $schoolStmt->fetch();

    /*
     * Không lọc theo tổ hợp.
     * Nếu trong cùng một năm có nhiều tổ hợp, lấy mức điểm cao nhất
     * của ngành và phương thức đó để so sánh giữa hai trường.
     */
    $scoreSql = "
        SELECT
            year,
            ROUND(MAX(score), 2) AS score,
            MAX(quota) AS quota,
            COUNT(DISTINCT NULLIF(TRIM(combination), '')) AS combination_count
        FROM admission_scores
        WHERE university_id = ?
          AND major_id = ?
          AND method = ?
        GROUP BY year
        ORDER BY year
    ";

    $scoreStmt = $db->prepare($scoreSql);

    // Trường A.
    $scoreStmt->execute([
        $u1,
        $majorId,
        $method
    ]);
    $dataA = $scoreStmt->fetchAll();

    // Trường B.
    $scoreStmt->execute([
        $u2,
        $majorId,
        $method
    ]);
    $dataB = $scoreStmt->fetchAll();

    if (empty($dataA) && empty($dataB)) {
        $warnings[] = 'Chưa có dữ liệu điểm chuẩn phù hợp để so sánh.';
    } elseif (empty($dataA)) {
        $warnings[] = 'Trường A chưa có dữ liệu điểm chuẩn phù hợp.';
    } elseif (empty($dataB)) {
        $warnings[] = 'Trường B chưa có dữ liệu điểm chuẩn phù hợp.';
    }
}

// ── Ghép danh sách năm ───────────────────────────────────────
$years = array_values(array_unique(array_merge(
    array_column($dataA, 'year'),
    array_column($dataB, 'year')
)));

sort($years, SORT_NUMERIC);

// Chuyển dữ liệu thành dạng năm => dữ liệu.
$dataAByYear = [];
foreach ($dataA as $row) {
    $dataAByYear[(string)$row['year']] = $row;
}

$dataBByYear = [];
foreach ($dataB as $row) {
    $dataBByYear[(string)$row['year']] = $row;
}

// Các năm có dữ liệu ở cả hai trường.
$commonYears = array_values(array_intersect(
    array_keys($dataAByYear),
    array_keys($dataBByYear)
));

sort($commonYears, SORT_NUMERIC);

// Năm chung mới nhất.
$latestCommonYear = !empty($commonYears)
    ? (string)end($commonYears)
    : null;

// Điểm ở năm chung mới nhất.
$latestScoreA = $latestCommonYear !== null
    ? (float)$dataAByYear[$latestCommonYear]['score']
    : null;

$latestScoreB = $latestCommonYear !== null
    ? (float)$dataBByYear[$latestCommonYear]['score']
    : null;

$latestDifference = (
    $latestScoreA !== null
    && $latestScoreB !== null
)
    ? $latestScoreA - $latestScoreB
    : null;
?>

<div class="container py-5 compare-page">

    <!-- Tiêu đề -->
    <div class="compare-heading mb-4">
        <span class="compare-kicker"><i class="bi bi-stars"></i> Công cụ đối chiếu</span>
        <h2 class="fw-bold mb-2">
            <i class="bi bi-bar-chart-line me-2 text-primary"></i>
            So sánh điểm chuẩn
        </h2>

        <p class="text-muted mb-0">
            So sánh cùng ngành và cùng phương thức xét tuyển giữa hai trường
        </p>
    </div>

    <!-- Form chọn dữ liệu -->
    <div class="card mb-4 compare-control-card">
        <div class="card-body p-4 p-lg-5">
        <form method="GET" id="compareForm">

            <div class="row g-3">
                <!-- Trường A -->
                <div class="col-md-5">
                    <label class="form-label fw-semibold small" for="uni1">
                        Trường A
                    </label>

                    <select
                        name="uni1"
                        id="uni1"
                        class="form-select"
                        required
                        onchange="submitCompareFilter()"
                    >
                        <option value="">-- Chọn trường A --</option>

                        <?php foreach ($allUnis as $uni): ?>
                            <option
                                value="<?= (int)$uni['university_id'] ?>"
                                <?= $u1 === (int)$uni['university_id'] ? 'selected' : '' ?>
                            >
                                <?= e($uni['university_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 d-flex align-items-end justify-content-center">
                    <div class="compare-vs-badge" aria-hidden="true">VS</div>
                </div>

                <!-- Trường B -->
                <div class="col-md-5">
                    <label class="form-label fw-semibold small" for="uni2">
                        Trường B
                    </label>

                    <select
                        name="uni2"
                        id="uni2"
                        class="form-select"
                        required
                        onchange="submitCompareFilter()"
                    >
                        <option value="">-- Chọn trường B --</option>

                        <?php foreach ($allUnis as $uni): ?>
                            <option
                                value="<?= (int)$uni['university_id'] ?>"
                                <?= $u2 === (int)$uni['university_id'] ? 'selected' : '' ?>
                            >
                                <?= e($uni['university_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row g-3 mt-1 align-items-end">
                <!-- Ngành chung -->
                <div class="col-md-5">
                    <label class="form-label fw-semibold small" for="major">
                        Ngành học chung
                    </label>

                    <select
                        name="major"
                        id="major"
                        class="form-select"
                        <?= (
                            !$u1
                            || !$u2
                            || $u1 === $u2
                            || empty($commonMajors)
                        ) ? 'disabled' : '' ?>
                        required
                        onchange="submitCompareFilter()"
                    >
                        <option value="0">
                            <?= (!$u1 || !$u2)
                                ? '-- Chọn hai trường trước --'
                                : '-- Chọn ngành chung --' ?>
                        </option>

                        <?php foreach ($commonMajors as $major): ?>
                            <option
                                value="<?= (int)$major['major_id'] ?>"
                                <?= $majorId === (int)$major['major_id'] ? 'selected' : '' ?>
                            >
                                <?= e($major['major_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Phương thức -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold small" for="method">
                        Phương thức xét tuyển
                    </label>

                    <select
                        name="method"
                        id="method"
                        class="form-select"
                        <?= (!$majorId || empty($commonMethods)) ? 'disabled' : '' ?>
                        required
                    >
                        <option value="">-- Chọn phương thức --</option>

                        <?php foreach ($commonMethods as $methodRow): ?>
                            <?php $methodValue = (string)$methodRow['method']; ?>

                            <option
                                value="<?= e($methodValue) ?>"
                                <?= $method === $methodValue ? 'selected' : '' ?>
                            >
                                <?= e(compare_method_label($methodValue)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Nút so sánh -->
                <div class="col-md-3">
                    <button
                        type="submit"
                        name="compare"
                        value="1"
                        class="btn btn-primary w-100 fw-semibold"
                        <?= !$readyToCompare ? 'disabled' : '' ?>
                    >
                        <i class="bi bi-bar-chart-line me-1"></i>
                        So sánh
                    </button>
                </div>
            </div>

            <div class="mt-3 small text-muted">
                <i class="bi bi-info-circle me-1"></i>
                Danh sách ngành và phương thức chỉ hiển thị những lựa chọn có dữ liệu ở cả hai trường.
                Hệ thống không bắt buộc hai trường phải có cùng tổ hợp xét tuyển.
            </div>
        </form>
        </div>
    </div>

    <!-- Thông báo lỗi -->
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <?= e($error) ?>
        </div>
    <?php endforeach; ?>

    <!-- Cảnh báo -->
    <?php foreach ($warnings as $warning): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-circle me-1"></i>
            <?= e($warning) ?>
        </div>
    <?php endforeach; ?>

    <?php if (
        $readyToCompare
        && $compareRequested
        && $nameA
        && $nameB
        && !empty($years)
    ): ?>

        <!-- Thông tin lựa chọn -->
        <div class="card mb-4 compare-summary-card">
            <div class="card-body d-flex flex-wrap gap-2 align-items-center">
                <span class="chip">
                    <i class="bi bi-book me-1"></i>
                    <?= e($majorName) ?>
                </span>

                <span class="chip">
                    <i class="bi bi-check2-circle me-1"></i>
                    <?= e(compare_method_label($method)) ?>
                </span>

                <span class="small text-muted">
                    Mỗi năm lấy mức điểm cao nhất trong các tổ hợp của cùng ngành và phương thức.
                </span>
            </div>
        </div>

        <!-- Thống kê nhanh -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card h-100 compare-stat compare-stat-a">
                    <div class="small text-muted mb-2">
                        <?= e($nameA['university_name']) ?>
                    </div>

                    <div class="s-num">
                        <?= compare_score($latestScoreA) ?>
                    </div>

                    <div class="s-lbl">
                        <?= $latestCommonYear !== null
                            ? 'Điểm năm ' . e($latestCommonYear)
                            : 'Chưa có năm chung' ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card h-100 compare-stat compare-stat-b">
                    <div class="small text-muted mb-2">
                        <?= e($nameB['university_name']) ?>
                    </div>

                    <div class="s-num text-danger">
                        <?= compare_score($latestScoreB) ?>
                    </div>

                    <div class="s-lbl">
                        <?= $latestCommonYear !== null
                            ? 'Điểm năm ' . e($latestCommonYear)
                            : 'Chưa có năm chung' ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card h-100 compare-stat compare-stat-diff">
                    <div class="small text-muted mb-2">
                        Chênh lệch
                    </div>

                    <div class="s-num <?= (
                        $latestDifference !== null
                        && $latestDifference < 0
                    ) ? 'text-danger' : '' ?>">
                        <?php if ($latestDifference === null): ?>
                            —
                        <?php else: ?>
                            <?= $latestDifference > 0 ? '+' : '' ?>
                            <?= number_format($latestDifference, 2) ?>
                        <?php endif; ?>
                    </div>

                    <div class="s-lbl">
                        A trừ B
                        <?= $latestCommonYear !== null
                            ? ' · năm ' . e($latestCommonYear)
                            : '' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Biểu đồ và bảng -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="card h-100 compare-chart-card">
                    <div class="card-header">
                        <i class="bi bi-graph-up me-1 text-primary"></i>
                        Điểm chuẩn theo năm
                    </div>

                    <div class="card-body p-3">
                        <canvas id="cCmp" height="150"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card h-100 compare-table-card">
                    <div class="card-header">
                        <i class="bi bi-table me-1"></i>
                        Bảng so sánh
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm small mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th>Năm</th>
                                        <th
                                            class="text-primary"
                                            title="<?= e($nameA['university_name']) ?>"
                                        >
                                            <?= e(compare_short_name($nameA['university_name'], 12)) ?>
                                        </th>
                                        <th
                                            class="text-danger"
                                            title="<?= e($nameB['university_name']) ?>"
                                        >
                                            <?= e(compare_short_name($nameB['university_name'], 12)) ?>
                                        </th>
                                        <th>Chênh lệch</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($years as $year): ?>
                                        <?php
                                        $yearKey = (string)$year;

                                        $scoreA = isset($dataAByYear[$yearKey])
                                            ? (float)$dataAByYear[$yearKey]['score']
                                            : null;

                                        $scoreB = isset($dataBByYear[$yearKey])
                                            ? (float)$dataBByYear[$yearKey]['score']
                                            : null;

                                        $difference = (
                                            $scoreA !== null
                                            && $scoreB !== null
                                        ) ? $scoreA - $scoreB : null;
                                        ?>

                                        <tr>
                                            <td class="fw-semibold">
                                                <?= e((string)$year) ?>
                                            </td>

                                            <td class="<?= (
                                                $scoreA !== null
                                                && $scoreB !== null
                                                && $scoreA > $scoreB
                                            ) ? 'text-success fw-bold' : '' ?>">
                                                <?= compare_score($scoreA) ?>
                                            </td>

                                            <td class="<?= (
                                                $scoreA !== null
                                                && $scoreB !== null
                                                && $scoreB > $scoreA
                                            ) ? 'text-success fw-bold' : '' ?>">
                                                <?= compare_score($scoreB) ?>
                                            </td>

                                            <td>
                                                <?php if ($difference === null): ?>
                                                    —
                                                <?php else: ?>
                                                    <span class="<?= (
                                                        $difference > 0
                                                    ) ? 'text-primary' : (
                                                        $difference < 0
                                                            ? 'text-danger'
                                                            : ''
                                                    ) ?> fw-semibold">
                                                        <?= $difference > 0 ? '+' : '' ?>
                                                        <?= number_format($difference, 2) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card-footer small text-muted">
                        <span class="text-success fw-bold">Xanh đậm</span>
                        = trường có điểm cao hơn trong cùng năm
                    </div>
                </div>
            </div>
        </div>

        <!-- Thông tin hai trường -->
        <div class="row g-3">
            <?php
            $schoolCards = [
                [
                    'school' => $nameA,
                    'data'   => $dataA,
                    'color'  => '#1a56db',
                    'id'     => $u1,
                    'label'  => 'Trường A'
                ],
                [
                    'school' => $nameB,
                    'data'   => $dataB,
                    'color'  => '#ef4444',
                    'id'     => $u2,
                    'label'  => 'Trường B'
                ]
            ];
            ?>

            <?php foreach ($schoolCards as $card): ?>
                <?php
                $school     = $card['school'];
                $schoolData = $card['data'];
                $color      = $card['color'];
                $schoolId   = $card['id'];
                $schoolLabel = $card['label'];
                ?>

                <div class="col-md-6">
                    <div class="card p-4 h-100 compare-school-card">
                        <div class="small text-muted mb-1">
                            <?= e($schoolLabel) ?>
                        </div>

                        <h5 class="fw-bold mb-3" style="color:<?= e($color) ?>">
                            <a
                                href="<?= url('university.php?id=' . $schoolId) ?>"
                                class="text-decoration-none"
                                style="color:<?= e($color) ?>"
                            >
                                <?= e($school['university_name']) ?>
                            </a>
                        </h5>

                        <table class="table table-sm small mb-0">
                            <tr>
                                <td class="text-muted">Mã trường</td>
                                <td><?= e($school['university_code'] ?: '—') ?></td>
                            </tr>

                            <tr>
                                <td class="text-muted">Tỉnh/Thành</td>
                                <td><?= e($school['province'] ?: '—') ?></td>
                            </tr>

                            <tr>
                                <td class="text-muted">Loại hình</td>
                                <td><?= e($school['school_type'] ?: '—') ?></td>
                            </tr>

                            <tr>
                                <td class="text-muted">Ngành</td>
                                <td><?= e($majorName) ?></td>
                            </tr>

                            <tr>
                                <td class="text-muted">Phương thức</td>
                                <td><?= e(compare_method_label($method)) ?></td>
                            </tr>

                            <?php if (!empty($schoolData)): ?>
                                <?php
                                $highestScore = max(array_map(
                                    'floatval',
                                    array_column($schoolData, 'score')
                                ));
                                ?>

                                <tr>
                                    <td class="text-muted">Điểm cao nhất</td>
                                    <td class="fw-bold" style="color:<?= e($color) ?>">
                                        <?= number_format($highestScore, 2) ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php elseif ($readyToCompare && $compareRequested && empty($years)): ?>
        <div class="text-center py-5 text-muted">
            <div style="font-size:48px">📭</div>
            <h5 class="mt-3">Chưa có dữ liệu để so sánh</h5>
            <p class="mb-0">
                Hãy thử chọn ngành hoặc phương thức xét tuyển khác.
            </p>
        </div>
    <?php endif; ?>
</div>

<script>
function submitCompareFilter() {
    const form = document.getElementById('compareForm');

    if (!form) {
        return;
    }

    const compareButton = form.querySelector('button[name="compare"]');

    if (compareButton) {
        compareButton.disabled = true;
    }

    form.submit();
}

function syncUniversityOptions() {
    const selectA = document.getElementById('uni1');
    const selectB = document.getElementById('uni2');

    if (!selectA || !selectB) {
        return;
    }

    Array.from(selectA.options).forEach(option => {
        option.disabled = Boolean(
            option.value
            && option.value === selectB.value
        );
    });

    Array.from(selectB.options).forEach(option => {
        option.disabled = Boolean(
            option.value
            && option.value === selectA.value
        );
    });
}

document.addEventListener('DOMContentLoaded', function () {
    syncUniversityOptions();

    const methodSelect = document.getElementById('method');

    if (methodSelect) {
        methodSelect.addEventListener('change', function () {
            const compareButton = document.querySelector(
                '#compareForm button[name="compare"]'
            );

            if (compareButton) {
                compareButton.disabled = !methodSelect.value;
            }
        });
    }
});
</script>

<?php if (
    $readyToCompare
    && $compareRequested
    && $years
    && ($dataA || $dataB)
): ?>
    <script>
    window.addEventListener('load', function () {
        const rawA = <?= json_encode($dataA, JSON_UNESCAPED_UNICODE) ?>;
        const rawB = <?= json_encode($dataB, JSON_UNESCAPED_UNICODE) ?>;
        const years = <?= json_encode(array_values($years), JSON_UNESCAPED_UNICODE) ?>;

        const getValue = (rows, year) => {
            const row = rows.find(
                item => String(item.year) === String(year)
            );

            return row ? parseFloat(row.score) : null;
        };

        chartLine('cCmp', years, [
            {
                label: <?= json_encode($nameA['university_name'], JSON_UNESCAPED_UNICODE) ?>,
                data: years.map(year => getValue(rawA, year)),
                borderColor: '#1a56db',
                backgroundColor: 'rgba(26,86,219,.15)',
                tension: .4,
                fill: true,
                pointRadius: 5,
                spanGaps: true
            },
            {
                label: <?= json_encode($nameB['university_name'], JSON_UNESCAPED_UNICODE) ?>,
                data: years.map(year => getValue(rawB, year)),
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239,68,68,.15)',
                tension: .4,
                fill: true,
                pointRadius: 5,
                spanGaps: true
            }
        ]);
    });
    </script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
