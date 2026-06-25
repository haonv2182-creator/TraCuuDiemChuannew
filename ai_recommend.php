<?php
$pageTitle = 'Gợi ý theo điểm – DiemChuan.vn';
require_once 'includes/header.php';

$db = getDB();

/**
 * Đổi mã phương thức thành tên dễ đọc.
 */
function recommend_method_label(string $method): string
{
    $labels = [
        'THPT'    => 'Thi THPT',
        'HocBa'   => 'Học bạ',
        'TongHop' => 'Tổng hợp',
        'DGNL'    => 'Đánh giá năng lực',
    ];

    return $labels[$method] ?? $method;
}

/**
 * Tìm tên ngành theo ID.
 */
function recommend_major_name(array $majors, int $majorId): string
{
    foreach ($majors as $major) {
        if ((int)$major['major_id'] === $majorId) {
            return (string)$major['major_name'];
        }
    }

    return '';
}

/**
 * Lấy dữ liệu gợi ý mới nhất và chia thành ba nhóm.
 */
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
            s.score AS cutoff,
            s.quota
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

// ── Dữ liệu cho form ─────────────────────────────────────────
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

$majors = $db->query("
    SELECT major_id, major_name
    FROM majors
    ORDER BY major_name
")->fetchAll();

$provinces = getProvinces();

// ── Giá trị form ──────────────────────────────────────────────
$score       = (float)($_GET['score'] ?? 0);
$method      = trim((string)($_GET['method'] ?? ''));
$combination = trim((string)($_GET['combination'] ?? ''));
$province    = trim((string)($_GET['province'] ?? ''));
$majorId     = (int)($_GET['major_id'] ?? 0);
$error       = '';
$result      = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $score       = (float)($_POST['score'] ?? 0);
    $method      = trim((string)($_POST['method'] ?? ''));
    $combination = trim((string)($_POST['combination'] ?? ''));
    $province    = trim((string)($_POST['province'] ?? ''));
    $majorId     = (int)($_POST['major_id'] ?? 0);

    if ($method === '') {
        $error = 'Vui lòng chọn phương thức xét tuyển để xác định đúng thang điểm.';
    } elseif ($score <= 0) {
        $error = 'Điểm nhập vào phải lớn hơn 0.';
    } elseif ($method === 'DGNL' && $score > 1200) {
        $error = 'Điểm đánh giá năng lực phải nằm trong khoảng từ 1 đến 1200.';
    } elseif ($method !== 'DGNL' && $score > 30) {
        $error = 'Điểm theo thang 30 phải nằm trong khoảng từ 1 đến 30.';
    } elseif (!in_array($method, ['DGNL', 'Thang'], true) && $combination === '') {
        $error = 'Vui lòng chọn tổ hợp xét tuyển.';
    } else {
        $result = recommend_by_score(
            $score,
            $method,
            $combination,
            $province,
            $majorId
        );

        // Lưu lịch sử nếu bảng ai_logs đang tồn tại.
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
            // Không làm gián đoạn trang nếu bảng log chưa tồn tại.
        }
    }
}

$majorName = recommend_major_name($majors, $majorId);
?>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-xl-10">

      <!-- GIỚI THIỆU -->
      <div class="text-center mb-4">
        <span class="section-label">
          Gợi ý tham khảo
        </span>

        <h2 class="fw-bold mt-2 mb-2">
          <i class="bi bi-stars text-primary me-2"></i>
          Gợi ý trường theo điểm
        </h2>

        <p class="text-muted mb-0">
          Nhập điểm và tiêu chí của bạn để xem các lựa chọn an toàn, phù hợp và thử sức
        </p>
      </div>

      <!-- FORM -->
      <div class="card shadow-sm mb-4">
        <div class="card-body p-4">

          <?php if ($error !== ''): ?>
            <div class="alert alert-danger small py-2">
              <i class="bi bi-exclamation-triangle me-1"></i>
              <?= e($error) ?>
            </div>
          <?php endif; ?>

          <form method="POST" id="recommendForm">
            <div class="row g-3 align-items-end">

              <!-- Phương thức -->
              <div class="col-md-4">
                <label class="form-label fw-semibold small">
                  Phương thức xét tuyển
                  <span class="text-danger">*</span>
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
                      <?= e(recommend_method_label($methodValue)) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- Điểm -->
              <div class="col-md-4">
                <label class="form-label fw-semibold small">
                  Điểm của bạn
                  <span class="text-danger">*</span>
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

              <!-- Tổ hợp -->
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
                  Phương thức này không bắt buộc chọn tổ hợp
                </small>
              </div>

              <!-- Ngành -->
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

              <!-- Tỉnh thành -->
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
                <button
                  type="submit"
                  class="btn btn-primary w-100 fw-semibold py-2"
                >
                  <i class="bi bi-stars me-2"></i>
                  Xem gợi ý phù hợp
                </button>
              </div>

            </div>
          </form>

          <div class="alert alert-light border small text-muted mt-3 mb-0">
            <i class="bi bi-info-circle me-1"></i>
            Kết quả dựa trên dữ liệu điểm chuẩn gần nhất và chỉ mang tính tham khảo,
            không phải dự đoán chắc chắn kết quả trúng tuyển.
          </div>

        </div>
      </div>

      <!-- KẾT QUẢ -->
      <?php if ($result !== null): ?>
        <?php
        $groupSettings = [
            'safe' => [
                'title'       => 'AN TOÀN',
                'description' => $method === 'DGNL'
                    ? 'Điểm của bạn cao hơn điểm chuẩn từ 50 điểm trở lên'
                    : 'Điểm của bạn cao hơn điểm chuẩn từ 1,5 điểm trở lên',
                'icon'        => 'bi-shield-check',
                'class'       => 'success'
            ],
            'fit' => [
                'title'       => 'PHÙ HỢP',
                'description' => $method === 'DGNL'
                    ? 'Điểm của bạn nằm trong khoảng ±50 điểm so với điểm chuẩn'
                    : 'Điểm của bạn nằm trong khoảng gần điểm chuẩn',
                'icon'        => 'bi-check-circle',
                'class'       => 'primary'
            ],
            'try' => [
                'title'       => 'THỬ SỨC',
                'description' => $method === 'DGNL'
                    ? 'Điểm của bạn thấp hơn điểm chuẩn tối đa 120 điểm'
                    : 'Điểm của bạn thấp hơn điểm chuẩn tối đa 2 điểm',
                'icon'        => 'bi-lightning-charge',
                'class'       => 'warning'
            ]
        ];

        $totalResults =
            count($result['safe'])
            + count($result['fit'])
            + count($result['try']);
        ?>

        <div class="card shadow-sm mb-4">
          <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <span class="fw-semibold">
              <i class="bi bi-list-check text-primary me-1"></i>
              Kết quả gợi ý
            </span>

            <span class="chip">
              Điểm: <?= number_format($score, 2) ?>
            </span>

            <span class="chip">
              <?= e(recommend_method_label($method)) ?>
            </span>

            <?php if ($combination !== ''): ?>
              <span class="chip">
                Tổ hợp: <?= e($combination) ?>
              </span>
            <?php endif; ?>

            <?php if ($majorName !== ''): ?>
              <span class="chip">
                Ngành: <?= e($majorName) ?>
              </span>
            <?php endif; ?>

            <?php if ($province !== ''): ?>
              <span class="chip">
                <?= e($province) ?>
              </span>
            <?php endif; ?>

            <strong class="text-primary ms-auto">
              <?= number_format($totalResults) ?> kết quả
            </strong>
          </div>
        </div>

        <?php if ($totalResults === 0): ?>
          <div class="text-center py-5 text-muted">
            <i
              class="bi bi-inbox"
              style="display:block;font-size:48px;margin-bottom:12px"
            ></i>

            <h5>Chưa tìm thấy lựa chọn phù hợp</h5>

            <p class="small mb-0">
              Thử bỏ bớt bộ lọc ngành hoặc tỉnh/thành để mở rộng kết quả.
            </p>
          </div>
        <?php else: ?>

          <?php foreach ($groupSettings as $groupKey => $setting): ?>
            <?php $groupRows = $result[$groupKey]; ?>

            <section class="mb-4">
              <div class="d-flex justify-content-between align-items-end mb-3 flex-wrap gap-2">
                <div>
                  <h5 class="fw-bold mb-1 text-<?= e($setting['class']) ?>">
                    <i class="bi <?= e($setting['icon']) ?> me-1"></i>
                    <?= e($setting['title']) ?>
                  </h5>

                  <p class="text-muted small mb-0">
                    <?= e($setting['description']) ?>
                  </p>
                </div>

                <span class="badge text-bg-<?= e($setting['class']) ?>">
                  <?= count($groupRows) ?> kết quả
                </span>
              </div>

              <?php if (empty($groupRows)): ?>
                <div class="card p-3 text-muted small">
                  Chưa có kết quả trong nhóm này.
                </div>
              <?php else: ?>
                <div class="row g-3">
                  <?php foreach (array_slice($groupRows, 0, 12) as $row): ?>
                    <div class="col-md-6">
                      <div class="card h-100 p-3">

                        <div class="d-flex justify-content-between gap-3 mb-2">
                          <div>
                            <a
                              href="<?= url('university.php?id=' . $row['university_id']) ?>"
                              class="fw-bold text-decoration-none d-block"
                            >
                              <?= e($row['university_name']) ?>
                            </a>

                            <a
                              href="<?= url('major.php?id=' . $row['major_id']) ?>"
                              class="text-decoration-none small"
                            >
                              <?= e($row['major_name']) ?>
                            </a>
                          </div>

                          <span class="score-badge sb-hi align-self-start">
                            <?= number_format((float)$row['cutoff'], 2) ?>
                          </span>
                        </div>

                        <div class="d-flex flex-wrap gap-2 text-muted small mb-3">
                          <span>
                            <i class="bi bi-calendar me-1"></i>
                            <?= e($row['year']) ?>
                          </span>

                          <?php if (!empty($row['combination'])): ?>
                            <span>
                              <i class="bi bi-grid me-1"></i>
                              <?= e($row['combination']) ?>
                            </span>
                          <?php endif; ?>

                          <span>
                            <i class="bi bi-geo-alt me-1"></i>
                            <?= e($row['province']) ?>
                          </span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center gap-2 mt-auto">
                          <span class="small">
                            Chênh lệch:
                            <strong class="<?= $row['difference'] >= 0 ? 'text-success' : 'text-warning' ?>">
                              <?= $row['difference'] > 0 ? '+' : '' ?>
                              <?= number_format((float)$row['difference'], 2) ?>
                            </strong>
                          </span>

                          <div class="d-flex gap-1">
                            <a
                              href="<?= url('university.php?id=' . $row['university_id']) ?>"
                              class="btn btn-sm btn-outline-primary"
                            >
                              Chi tiết
                            </a>

                            <a
                              href="<?= url(
                                  'compare.php?uni1=' . $row['university_id']
                                  . '&major=' . $row['major_id']
                              ) ?>"
                              class="btn btn-sm btn-outline-secondary"
                              title="Đưa vào trang so sánh"
                            >
                              <i class="bi bi-bar-chart-line"></i>
                            </a>
                          </div>
                        </div>

                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>

                <?php if (count($groupRows) > 12): ?>
                  <p class="text-muted small mt-2 mb-0">
                    Đang hiển thị 12 trong <?= count($groupRows) ?> kết quả của nhóm.
                  </p>
                <?php endif; ?>
              <?php endif; ?>
            </section>
          <?php endforeach; ?>

        <?php endif; ?>
      <?php endif; ?>

    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const methodSelect = document.getElementById('methodSelect');
  const scoreInput = document.getElementById('scoreInput');
  const scoreHelp = document.getElementById('scoreHelp');
  const combinationSelect = document.getElementById('combinationSelect');
  const combinationRequired = document.getElementById('combinationRequired');
  const combinationHelp = document.getElementById('combinationHelp');

  function updateScoreForm() {
    const method = methodSelect.value;
    const combinationOptional = method === 'DGNL' || method === 'Thang';

    if (method === 'DGNL') {
      scoreInput.max = '1200';
      scoreInput.placeholder = 'Ví dụ: 850';
      scoreHelp.textContent = 'Thang điểm đánh giá năng lực, tối đa 1200';
    } else {
      scoreInput.max = '30';
      scoreInput.placeholder = 'Ví dụ: 24.5';
      scoreHelp.textContent = 'Thang điểm tối đa 30';
    }

    combinationRequired.classList.toggle('d-none', combinationOptional);
    combinationHelp.classList.toggle('d-none', !combinationOptional);
    combinationSelect.required = !combinationOptional;

    if (combinationOptional) {
      combinationSelect.value = '';
    }
  }

  methodSelect.addEventListener('change', updateScoreForm);
  updateScoreForm();
});
</script>

<?php require_once 'includes/footer.php'; ?>
