<?php
$pageTitle = 'Gợi ý theo điểm – DiemChuan.vn';
require_once 'includes/header.php';

$db = getDB();

function recommend_major_name(array $majors, int $majorId): string
{
    foreach ($majors as $major) {
        if ((int)$major['major_id'] === $majorId) {
            return (string)$major['major_name'];
        }
    }

    return '';
}

function recommend_by_score(
    float $userScore,
    string $method,
    string $combination = '',
    string $province = '',
    int $majorId = 0
): array {
    $db = getDB();

    $where = [
        "s.year = (
            SELECT MAX(s2.year)
            FROM admission_scores s2
            WHERE s2.university_id = s.university_id
              AND s2.major_id = s.major_id
              AND COALESCE(s2.method, '') = COALESCE(s.method, '')
              AND COALESCE(s2.combination, '') = COALESCE(s.combination, '')
        )"
    ];

    $params = [];

    if ($method !== '') {
        $where[] = 's.method = :method';
        $params[':method'] = $method;
    }

    if ($combination !== '') {
        $where[] = 's.combination = :combination';
        $params[':combination'] = $combination;
    }

    if ($province !== '') {
        $where[] = 'u.province = :province';
        $params[':province'] = $province;
    }

    if ($majorId > 0) {
        $where[] = 's.major_id = :major_id';
        $params[':major_id'] = $majorId;
    }

    $sql = "
        SELECT
            u.university_id,
            u.university_name,
            u.province,
            u.school_type,
            m.major_id,
            m.major_name,
            s.year,
            s.combination,
            s.method,
            s.score AS cutoff
        FROM admission_scores s
        JOIN universities u
          ON s.university_id = u.university_id
        JOIN majors m
          ON s.major_id = m.major_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY s.score DESC, u.university_name ASC, m.major_name ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $groups = [
        'safe' => [],
        'fit'  => [],
        'try'  => []
    ];

    foreach ($rows as $row) {
        $difference = $userScore - (float)$row['cutoff'];
        $row['difference'] = $difference;

        if ($method === 'DGNL') {
            if ($difference >= 50) {
                $groups['safe'][] = $row;
            } elseif ($difference >= -50) {
                $groups['fit'][] = $row;
            } elseif ($difference >= -120) {
                $groups['try'][] = $row;
            }
        } else {
            if ($difference >= 1.5) {
                $groups['safe'][] = $row;
            } elseif ($difference >= -1.0) {
                $groups['fit'][] = $row;
            } elseif ($difference >= -2.0) {
                $groups['try'][] = $row;
            }
        }
    }

    return $groups;
}

$combinations = $db->query("
    SELECT DISTINCT combination
    FROM admission_scores
    WHERE combination IS NOT NULL
      AND TRIM(combination) <> ''
    ORDER BY combination
")->fetchAll(PDO::FETCH_COLUMN);

$validMethods = array_keys(getAdmissionMethods());
$methods = $validMethods;

$majors = $db->query("
    SELECT major_id, major_name
    FROM majors
    ORDER BY major_name
")->fetchAll();

$provinces = getProvinces();

$score       = 0;
$method      = '';
$combination = '';
$province    = trim((string)($_GET['province'] ?? ''));
$majorId     = 0;
$error       = '';
$result      = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $score       = (float)($_POST['score'] ?? 0);
    $method      = trim((string)($_POST['method'] ?? ''));
    $combination = trim((string)($_POST['combination'] ?? ''));
    $province    = trim((string)($_POST['province'] ?? ''));
    $majorId     = (int)($_POST['major_id'] ?? 0);

    if ($method !== '' && !in_array($method, $validMethods, true)) {
        $method = '';
    }

    if ($method === '') {
        $error = 'Vui lòng chọn phương thức xét tuyển.';
    } elseif ($score <= 0) {
        $error = 'Điểm nhập vào phải lớn hơn 0.';
    } elseif ($method === 'DGNL' && $score > 1200) {
        $error = 'Điểm đánh giá năng lực phải nằm trong khoảng từ 1 đến 1200.';
    } elseif ($method !== 'DGNL' && $score > 30) {
        $error = 'Điểm theo thang 30 phải nằm trong khoảng từ 1 đến 30.';
    } elseif ($method !== 'DGNL' && $combination === '') {
        $error = 'Vui lòng chọn tổ hợp xét tuyển.';
    } else {
        $result = recommend_by_score(
            $score,
            $method,
            $combination,
            $province,
            $majorId
        );

        try {
            $db->prepare("
                INSERT INTO ai_logs (
                    user_score,
                    combination,
                    province,
                    suggested_result,
                    ip_address
                ) VALUES (?, ?, ?, ?, ?)
            ")->execute([
                $score,
                $combination,
                $province,
                json_encode($result, JSON_UNESCAPED_UNICODE),
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
        } catch (PDOException $exception) {
            // Bỏ qua nếu bảng log chưa tồn tại
        }
    }
}

$majorName = recommend_major_name($majors, $majorId);
?>
<section class="sub-page-hero ai-page-hero">
  <div class="container">
    <div class="ai-hero-content text-center">
      <h1 class="sub-page-title">
        <i class="bi bi-stars me-2"></i>
        Gợi ý trường theo điểm
      </h1>

      <p class="sub-page-subtitle">
        Nhập điểm xét tuyển để hệ thống đề xuất các lựa chọn an toàn, phù hợp và thử sức
      </p>
    </div>
  </div>
</section>

<div class="container py-5 ai-page sub-page-content">
    <div class="row justify-content-center">
        <div class="col-xl-10">

            <div class="card shadow-sm mb-4">
                <div class="card-body p-4">

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger small py-2">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            <?= e($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="recommendForm">
                        <div class="row g-3 align-items-start">

                            <div class="col-md-4">
                                <label class="form-label fw-semibold small">
                                    Phương thức xét tuyển <span class="text-danger">*</span>
                                </label>

                                <select
                                    name="method"
                                    id="methodSelect"
                                    class="form-select"
                                    required
                                >
                                    <option value="">-- Chọn phương thức --</option>

                                    <?php foreach ($methods as $methodValue): ?>
                                        <option
                                            value="<?= e($methodValue) ?>"
                                            <?= $method === $methodValue ? 'selected' : '' ?>
                                        >
                                            <?= e(methodLabel($methodValue)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold small">
                                    Điểm của bạn <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="number"
                                    name="score"
                                    id="scoreInput"
                                    class="form-control"
                                    placeholder="Ví dụ: 24.5"
                                    step="0.01"
                                    min="0"
                                    max="1200"
                                    value="<?= $score > 0 ? e($score) : '' ?>"
                                    required
                                >

                                <small id="scoreHelp" class="text-muted">
                                    Chọn phương thức để xác định thang điểm
                                </small>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold small">
                                    Tổ hợp xét tuyển
                                    <span id="combinationRequired" class="text-danger">*</span>
                                </label>

                                <select
                                    name="combination"
                                    id="combinationSelect"
                                    class="form-select"
                                >
                                    <option value="">-- Chọn tổ hợp --</option>

                                    <?php foreach ($combinations as $combinationValue): ?>
                                        <option
                                            value="<?= e($combinationValue) ?>"
                                            <?= $combination === $combinationValue ? 'selected' : '' ?>
                                        >
                                            <?= e($combinationValue) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <small id="combinationHelp" class="text-muted d-none">
                                    Đánh giá năng lực không cần chọn tổ hợp
                                </small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">
                                    Ngành muốn học
                                    <span class="text-muted fw-normal">(không bắt buộc)</span>
                                </label>

                                <select name="major_id" class="form-select">
                                    <option value="0">Tất cả ngành</option>

                                    <?php foreach ($majors as $major): ?>
                                        <option
                                            value="<?= (int)$major['major_id'] ?>"
                                            <?= $majorId === (int)$major['major_id'] ? 'selected' : '' ?>
                                        >
                                            <?= e($major['major_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">
                                    Tỉnh/Thành phố
                                    <span class="text-muted fw-normal">(không bắt buộc)</span>
                                </label>

                                <select name="province" class="form-select">
                                    <option value="">Tất cả tỉnh thành</option>

                                    <?php foreach ($provinces as $provinceValue): ?>
                                        <option
                                            value="<?= e($provinceValue) ?>"
                                            <?= $province === $provinceValue ? 'selected' : '' ?>
                                        >
                                            <?= e($provinceValue) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary w-100 fw-semibold py-2">
                                    <i class="bi bi-stars me-1"></i>
                                    Xem gợi ý
                                </button>
                            </div>

                        </div>
                    </form>

                </div>
            </div>

            <?php if ($result !== null): ?>
                <?php
                $total = count($result['safe']) + count($result['fit']) + count($result['try']);

                if ($method === 'DGNL') {
                    $groupInfo = [
                        'safe' => ['An toàn', 'Điểm của bạn cao hơn điểm chuẩn từ 50 điểm trở lên', 'success'],
                        'fit'  => ['Phù hợp', 'Điểm của bạn nằm trong khoảng ±50 điểm', 'primary'],
                        'try'  => ['Thử sức', 'Điểm của bạn thấp hơn điểm chuẩn tối đa 120 điểm', 'warning']
                    ];
                } else {
                    $groupInfo = [
                        'safe' => ['An toàn', 'Điểm của bạn cao hơn điểm chuẩn từ 1.5 điểm trở lên', 'success'],
                        'fit'  => ['Phù hợp', 'Điểm của bạn nằm trong khoảng ±1 điểm', 'primary'],
                        'try'  => ['Thử sức', 'Điểm của bạn thấp hơn điểm chuẩn tối đa 2 điểm', 'warning']
                    ];
                }
                ?>

                <div class="card shadow-sm">
                    <div class="card-header">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div class="fw-semibold">
                                <i class="bi bi-list-stars text-primary me-1"></i>
                                Kết quả gợi ý cho điểm
                                <span class="text-primary">
                                    <?= number_format($score, 2) ?>
                                </span>

                                <?php if ($method): ?>
                                    · <?= e(methodLabel($method)) ?>
                                <?php endif; ?>

                                <?php if ($combination): ?>
                                    · <?= e($combination) ?>
                                <?php endif; ?>

                                <?php if ($majorName): ?>
                                    · <?= e($majorName) ?>
                                <?php endif; ?>

                                <?php if ($province): ?>
                                    · <?= e($province) ?>
                                <?php endif; ?>
                            </div>

                            <span class="badge text-bg-primary">
                                <?= $total ?> kết quả
                            </span>
                        </div>
                    </div>

                    <div class="card-body p-4">
                        <?php if ($total === 0): ?>
                            <div class="text-center text-muted py-5">
                                <div style="font-size:48px">📭</div>
                                <h5 class="mt-3">Chưa tìm thấy gợi ý phù hợp</h5>
                                <p class="small mb-0">
                                    Hãy thử đổi phương thức, ngành hoặc tỉnh thành.
                                </p>
                            </div>
                        <?php else: ?>

                            <?php foreach (['safe', 'fit', 'try'] as $groupKey): ?>
                                <?php
                                $items = $result[$groupKey];
                                [$title, $desc, $color] = $groupInfo[$groupKey];
                                ?>

                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <h5 class="fw-bold mb-1 text-<?= e($color) ?>">
                                                <?= e($title) ?>
                                            </h5>

                                            <p class="text-muted small mb-0">
                                                <?= e($desc) ?>
                                            </p>
                                        </div>

                                        <span class="badge text-bg-<?= e($color) ?>">
                                            <?= count($items) ?>
                                        </span>
                                    </div>

                                    <?php if (empty($items)): ?>
                                        <div class="text-muted small border rounded-3 p-3">
                                            Không có kết quả trong nhóm này.
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle small mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Trường</th>
                                                        <th>Ngành</th>
                                                        <th>Tỉnh/TP</th>
                                                        <th>Phương thức</th>
                                                        <th>Tổ hợp</th>
                                                        <th>Điểm chuẩn</th>
                                                        <th>Chênh lệch</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>

                                                <tbody>
                                                    <?php foreach (array_slice($items, 0, 20) as $row): ?>
                                                        <tr>
                                                            <td class="fw-semibold">
                                                                <?= e($row['university_name']) ?>
                                                            </td>

                                                            <td>
                                                                <?= e($row['major_name']) ?>
                                                            </td>

                                                            <td class="text-muted">
                                                                <?= e($row['province']) ?>
                                                            </td>

                                                            <td>
                                                                <span class="badge text-bg-<?= e(methodColor($row['method'])) ?> fw-normal">
                                                                    <?= e(methodLabel($row['method'])) ?>
                                                                </span>
                                                            </td>

                                                            <td>
                                                                <span class="chip">
                                                                    <?= !empty($row['combination']) ? e($row['combination']) : '—' ?>
                                                                </span>
                                                            </td>

                                                            <td>
                                                                <span class="score-badge">
                                                                    <?= number_format((float)$row['cutoff'], 2) ?>
                                                                </span>
                                                            </td>

                                                            <td>
                                                                <?php $diff = (float)$row['difference']; ?>

                                                                <span class="<?= $diff >= 0 ? 'text-success' : 'text-danger' ?> fw-semibold">
                                                                    <?= $diff >= 0 ? '+' : '' ?><?= number_format($diff, 2) ?>
                                                                </span>
                                                            </td>

                                                            <td>
                                                                <a
                                                                    href="<?= url('university.php?id=' . $row['university_id']) ?>"
                                                                    class="btn btn-sm btn-outline-primary py-0 px-2"
                                                                >
                                                                    Chi tiết
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <?php if (count($items) > 20): ?>
                                            <p class="text-muted small mt-2 mb-0">
                                                Đang hiển thị 20 kết quả đầu tiên trong nhóm này.
                                            </p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>

                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>
<style>
#recommendForm .form-label {
    min-height: 22px;
}

#recommendForm .form-control,
#recommendForm .form-select {
    height: 46px;
}
</style>
<script>
function updateRecommendForm() {
    const methodSelect = document.getElementById('methodSelect');
    const scoreInput = document.getElementById('scoreInput');
    const scoreHelp = document.getElementById('scoreHelp');
    const combinationSelect = document.getElementById('combinationSelect');
    const combinationRequired = document.getElementById('combinationRequired');
    const combinationHelp = document.getElementById('combinationHelp');

    if (!methodSelect || !scoreInput || !combinationSelect) {
        return;
    }

    const method = methodSelect.value;

    if (method === 'DGNL') {
        scoreInput.max = '1200';
        scoreInput.placeholder = 'Ví dụ: 850';
        scoreHelp.textContent = 'Đánh giá năng lực dùng thang điểm đến 1200';

        combinationSelect.value = '';
        combinationSelect.disabled = true;
        combinationSelect.required = false;

        combinationRequired.classList.add('d-none');
        combinationHelp.classList.remove('d-none');
    } else {
        scoreInput.max = '30';
        scoreInput.placeholder = 'Ví dụ: 24.5';
        scoreHelp.textContent = 'Phương thức này dùng thang điểm 30';

        combinationSelect.disabled = false;
        combinationSelect.required = true;

        combinationRequired.classList.remove('d-none');
        combinationHelp.classList.add('d-none');
    }
}

document.getElementById('methodSelect')?.addEventListener('change', updateRecommendForm);
updateRecommendForm();
</script>

<?php require_once 'includes/footer.php'; ?>