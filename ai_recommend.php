<?php
$pageTitle = 'AI Gợi ý ngành & trường – DiemChuan.vn';
require_once 'includes/header.php';
$db        = getDB();
$combos    = $db->query("SELECT DISTINCT combination FROM admission_scores ORDER BY combination")->fetchAll(PDO::FETCH_COLUMN);
$provinces = getProvinces();
$majors    = $db->query("SELECT major_id, major_name FROM majors ORDER BY major_name")->fetchAll();

$result   = null; $score = 0; $combo = ''; $province = ''; $majorId = 0; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $score    = (float)($_POST['score']       ?? 0);
    $combo    = trim($_POST['combination']    ?? '');
    $province = trim($_POST['province']       ?? '');
    $majorId  = (int)($_POST['major_id']      ?? 0);
    $method   = trim($_POST['method']         ?? '');

    if ($score < 1 || $score > 30) $err = 'Điểm không hợp lệ (phải từ 1 đến 30).';
    elseif (!$combo)                $err = 'Vui lòng chọn tổ hợp xét tuyển.';
    else {
        $result = aiSuggestFull($score, $combo, $province, $majorId, $method);
        $db->prepare("INSERT INTO ai_logs (user_score,combination,province,suggested_result,ip_address) VALUES (?,?,?,?,?)")
           ->execute([$score, $combo, $province, json_encode($result), $_SERVER['REMOTE_ADDR'] ?? '']);
    }
}

// AI suggest có thêm lọc ngành
function aiSuggestFull(float $score, string $combo, string $province='', int $majorId=0, string $method=''): array {
    $db  = getDB();
    $sql = "SELECT u.university_id, u.university_name, u.province,
                   m.major_id, m.major_name, s.score AS cutoff
            FROM admission_scores s
            JOIN universities u ON s.university_id = u.university_id
            JOIN majors m ON s.major_id = m.major_id
            WHERE s.combination = :c
              AND s.year = (SELECT MAX(year) FROM admission_scores)";
    if ($majorId) $sql .= " AND s.major_id = :mid";
    if ($method)  $sql .= " AND s.method = :method";
    $sql .= " ORDER BY s.score DESC";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':c', $combo);
    if ($majorId) $stmt->bindValue(':mid', $majorId, PDO::PARAM_INT);
    if ($method)  $stmt->bindValue(':method', $method);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    if ($province) {
        $rows = array_values(array_filter($rows, fn($r) => stripos($r['province'], $province) !== false));
    }

    $safe = $fit = $try = [];
    foreach ($rows as $r) {
        $d = $score - (float)$r['cutoff'];
        if      ($d >= 1.5)  $safe[] = $r;
        elseif  ($d >= -0.99) $fit[]  = $r;
        elseif  ($d >= -2.0)  $try[]  = $r;
    }
    return compact('safe','fit','try');
}
?>
<div class="container py-5">
<div class="row justify-content-center">
<div class="col-lg-8">

  <!-- Input card -->
  <div class="card shadow-sm mb-4">
    <div class="card-body p-4">
      <div class="d-flex align-items-center gap-3 mb-4">
        <div style="width:52px;height:52px;background:var(--primary-lt);border-radius:14px;
                    display:flex;align-items:center;justify-content:center;font-size:26px;flex-shrink:0">🤖</div>
        <div>
          <h2 class="fw-bold mb-0 fs-4">AI Gợi ý ngành &amp; trường</h2>
          <p class="text-muted small mb-0">Nhập điểm, tổ hợp và ngành yêu thích để nhận gợi ý phù hợp</p>
        </div>
      </div>

      <?php if ($err): ?>
      <div class="alert alert-danger small py-2">
        <i class="bi bi-exclamation-triangle me-1"></i><?= e($err) ?>
      </div>
      <?php endif; ?>

      <form method="POST">
        <div class="row g-3">

          <!-- Điểm thi -->
          <div class="col-6 col-md-3">
            <label class="form-label fw-semibold small">Điểm thi <span class="text-danger">*</span></label>
            <input type="number" name="score" class="form-control"
                   placeholder="VD: 24.6" step="any" min="0" max="30"
                   value="<?= $score ? e($score) : '' ?>" required>
          </div>

          <!-- Tổ hợp -->
          <div class="col-6 col-md-3">
            <label class="form-label fw-semibold small">Tổ hợp <span class="text-danger">*</span></label>
            <select name="combination" class="form-select" required>
              <option value="">-- Chọn --</option>
              <?php foreach ($combos as $c): ?>
              <option value="<?= e($c) ?>" <?= $combo === $c ? 'selected' : '' ?>><?= e($c) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Ngành muốn học -->
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold small">
              Ngành muốn học
              <span class="text-muted fw-normal">(không bắt buộc)</span>
            </label>
            <select name="major_id" class="form-select">
              <option value="0">🔍 Tất cả ngành</option>
              <?php foreach ($majors as $m): ?>
              <option value="<?= $m['major_id'] ?>" <?= $majorId == $m['major_id'] ? 'selected' : '' ?>>
                <?= e($m['major_name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Tỉnh/Thành -->
          <div class="col-12 col-md-6">
            <label class="form-label fw-semibold small">Tỉnh/Thành phố</label>
            <select name="province" class="form-select">
              <option value="">🌏 Tất cả tỉnh thành</option>
              <?php foreach ($provinces as $p): ?>
              <option value="<?= e($p) ?>" <?= $province === $p ? 'selected' : '' ?>><?= e($p) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Submit -->
          <div class="col-md-6">
            <label class="form-label fw-semibold small">Phương thức xét tuyển</label>
            <select name="method" class="form-select">
              <option value="">Tất cả phương thức</option>
              <option value="THPT"    <?=($method??'')==='THPT'   ?'selected':''?>>📝 Thi THPT Quốc gia</option>
              <option value="HocBa"   <?=($method??'')==='HocBa'  ?'selected':''?>>📋 Xét học bạ</option>
              <option value="TongHop" <?=($method??'')==='TongHop'?'selected':''?>>📊 Điểm tổng hợp (2025)</option>
              <option value="DGNL"    <?=($method??'')==='DGNL'   ?'selected':''?>>🧠 Đánh giá năng lực</option>
              <option value="Thang"   <?=($method??'')==='Thang'  ?'selected':''?>>🏆 Xét thẳng</option>
            </select>
          </div>

          <div class="col-12">
            <button type="submit" class="btn btn-primary w-100 fw-semibold py-2 fs-6">
              <i class="bi bi-robot me-2"></i>Nhận gợi ý từ AI
            </button>
          </div>

        </div>
      </form>
    </div>
  </div>

  <!-- Result -->
  <?php if ($result !== null):
    $total  = count($result['safe']) + count($result['fit']) + count($result['try']);
    $majName= $majorId ? collect_major_name($majors, $majorId) : '';
    $groups = [
      'safe' => ['AN TOÀN', 'Điểm của bạn cao hơn ≥ 1.5 điểm', '#10b981', 'tag-safe'],
      'fit'  => ['PHÙ HỢP', 'Điểm trong khoảng ±1.0 điểm',      '#1a56db', 'tag-fit'],
      'try'  => ['THỬ SỨC', 'Điểm thấp hơn 0.5 – 2.0 điểm',     '#f59e0b', 'tag-try'],
    ];
  ?>
  <div class="card shadow-sm">
    <div class="card-header fw-semibold d-flex flex-wrap gap-2 align-items-center">
      <span><i class="bi bi-stars text-warning me-1"></i>
        Gợi ý cho điểm <strong class="text-primary"><?= number_format($score, 2) ?></strong>
        — <strong><?= e($combo) ?></strong>
      </span>
      <?php if ($majName): ?>
        <span class="chip">📚 <?= e($majName) ?></span>
      <?php endif; ?>
      <?php if ($province): ?>
        <span class="chip">📍 <?= e($province) ?></span>
      <?php endif; ?>
      <span class="ms-auto text-primary fw-bold"><?= $total ?> kết quả</span>
    </div>
    <div class="card-body p-4">

      <?php if ($total === 0): ?>
      <div class="text-center py-4 text-muted">
        <div style="font-size:40px">😔</div>
        <p class="mt-2">Không tìm thấy kết quả phù hợp.<br>
        Thử bỏ lọc ngành hoặc tỉnh thành để xem thêm.</p>
      </div>
      <?php else: ?>

      <?php foreach ($groups as $key => [$label, $desc, $dot, $tagCls]): ?>
      <div class="mb-4">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span style="width:10px;height:10px;border-radius:50%;background:<?= $dot ?>;flex-shrink:0;display:inline-block"></span>
          <span class="fw-bold small text-uppercase" style="letter-spacing:.5px"><?= $label ?></span>
          <span class="text-muted small">— <?= $desc ?></span>
        </div>
        <?php if (empty($result[$key])): ?>
          <p class="text-muted small fst-italic ms-3 mb-0">Không có kết quả.</p>
        <?php else: ?>
          <?php foreach ($result[$key] as $r): ?>
          <a href="<?= url('university.php?id='.$r['university_id']) ?>" class="ai-item text-decoration-none">
            <div>
              <div class="fw-semibold small text-dark"><?= e($r['major_name']) ?></div>
              <div class="text-muted" style="font-size:12px">
                <i class="bi bi-building me-1"></i><?= e($r['university_name']) ?>
                <span class="ms-2"><i class="bi bi-geo-alt me-1"></i><?= e($r['province']) ?></span>
              </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
              <span class="fw-bold text-primary fs-6"><?= number_format($r['cutoff'], 2) ?></span>
              <span class="tag <?= $tagCls ?>"><?= $label ?></span>
            </div>
          </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>

      <?php endif; ?>

      <div class="alert alert-light small mb-0 mt-2">
        <i class="bi bi-info-circle me-1"></i>
        Kết quả dựa trên dữ liệu năm gần nhất và mang tính tham khảo.
        Điểm chuẩn thực tế thay đổi hàng năm.
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>
</div>
</div>

<?php
function collect_major_name(array $majors, int $id): string {
    foreach ($majors as $m) {
        if ($m['major_id'] == $id) return $m['major_name'];
    }
    return '';
}
require_once 'includes/footer.php';
?>