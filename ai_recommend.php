<?php
$pageTitle = 'AI Gợi ý ngành & trường – DiemChuan.vn';
require_once 'includes/header.php';
$db      = getDB();
$combos  = $db->query("SELECT DISTINCT combination FROM admission_scores ORDER BY combination")->fetchAll(PDO::FETCH_COLUMN);
$provinces = getProvinces();
$result  = null; $score = 0; $combo = ''; $province = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $score    = (float)($_POST['score'] ?? 0);
    $combo    = trim($_POST['combination'] ?? '');
    $province = trim($_POST['province'] ?? '');
    if ($score < 1 || $score > 30) $err = 'Điểm không hợp lệ (phải từ 1 đến 30).';
    elseif (!$combo)                $err = 'Vui lòng chọn tổ hợp xét tuyển.';
    else {
        $result = aiSuggest($score, $combo, $province);
        $db->prepare("INSERT INTO ai_logs (user_score,combination,province,suggested_result,ip_address) VALUES (?,?,?,?,?)")
           ->execute([$score, $combo, $province, json_encode($result), $_SERVER['REMOTE_ADDR'] ?? '']);
    }
}
?>
<div class="container py-5">
<div class="row justify-content-center">
<div class="col-lg-7">

  <!-- Input card -->
  <div class="card shadow-sm mb-4">
    <div class="card-body p-4">
      <div class="d-flex align-items-center gap-3 mb-4">
        <div style="width:52px;height:52px;background:var(--primary-lt);border-radius:14px;
                    display:flex;align-items:center;justify-content:center;font-size:26px;flex-shrink:0">🤖</div>
        <div>
          <h2 class="fw-bold mb-0 fs-4">AI Gợi ý ngành &amp; trường</h2>
          <p class="text-muted small mb-0">Nhập điểm và tổ hợp để nhận gợi ý phù hợp</p>
        </div>
      </div>

      <?php if ($err): ?>
      <div class="alert alert-danger small py-2">
        <i class="bi bi-exclamation-triangle me-1"></i><?= e($err) ?>
      </div>
      <?php endif; ?>

      <form method="POST">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label fw-semibold small">Điểm thi <span class="text-danger">*</span></label>
            <input type="number" name="score" class="form-control"
                   placeholder="VD: 24.5" step=".25" min="0" max="30"
                   value="<?= $score ? e($score) : '' ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold small">Tổ hợp xét tuyển <span class="text-danger">*</span></label>
            <select name="combination" class="form-select" required>
              <option value="">-- Chọn tổ hợp --</option>
              <?php foreach ($combos as $c): ?>
              <option value="<?= e($c) ?>" <?= $combo === $c ? 'selected' : '' ?>><?= e($c) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold small">Tỉnh/Thành phố</label>
            <select name="province" class="form-select">
              <option value="">Tất cả</option>
              <?php foreach ($provinces as $p): ?>
              <option value="<?= e($p) ?>" <?= $province === $p ? 'selected' : '' ?>><?= e($p) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-primary w-100 fw-semibold py-2">
              <i class="bi bi-robot me-2"></i>Nhận gợi ý từ AI
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Result card -->
  <?php if ($result !== null):
    $total = count($result['safe']) + count($result['fit']) + count($result['try']);
    $groups = [
      'safe' => ['AN TOÀN',  'Điểm của bạn cao hơn ≥ 1.5 điểm', '#10b981', 'tag-safe'],
      'fit'  => ['PHÙ HỢP',  'Điểm trong khoảng ±1.0 điểm',       '#1a56db', 'tag-fit'],
      'try'  => ['THỬ SỨC',  'Điểm thấp hơn 0.5 – 2.0 điểm',      '#f59e0b', 'tag-try'],
    ];
  ?>
  <div class="card shadow-sm">
    <div class="card-header fw-semibold">
      <i class="bi bi-stars text-warning me-1"></i>
      Gợi ý cho điểm <strong class="text-primary"><?= number_format($score, 2) ?></strong>
      — <?= e($combo) ?>
      <?php if ($province): ?> — <?= e($province) ?><?php endif; ?>
      &nbsp;·&nbsp; <span class="text-primary"><?= $total ?></span> kết quả
    </div>
    <div class="card-body p-4">
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
          <div class="ai-item">
            <div>
              <div class="fw-semibold small"><?= e($r['major_name']) ?></div>
              <div class="text-muted" style="font-size:12px">
                <i class="bi bi-building me-1"></i><?= e($r['university_name']) ?>
                <span class="ms-2"><i class="bi bi-geo-alt me-1"></i><?= e($r['province']) ?></span>
              </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
              <span class="fw-bold text-primary"><?= number_format($r['cutoff'], 2) ?></span>
              <span class="tag <?= $tagCls ?>"><?= $label ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>

      <div class="alert alert-light small mb-0">
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
<?php require_once 'includes/footer.php'; ?>
