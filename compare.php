<?php
$pageTitle = 'So sánh điểm chuẩn – DiemChuan.vn';
require_once 'includes/header.php';
$db = getDB();

$u1      = (int)($_GET['uni1']  ?? 0);
$u2      = (int)($_GET['uni2']  ?? 0);
$majorId = (int)($_GET['major'] ?? 0);

$allUnis  = $db->query("SELECT university_id,university_name FROM universities ORDER BY university_name")->fetchAll();
$allMajors= $db->query("SELECT major_id,major_name FROM majors ORDER BY major_name")->fetchAll();

$dataA = $dataB = [];
$nameA = $nameB = null;

if ($u1 && $u2) {
    // Nếu chọn ngành → lọc theo ngành cụ thể
    if ($majorId) {
        $sql = "SELECT s.year, s.score AS avg, s.score AS mx
                FROM admission_scores s
                WHERE s.university_id=? AND s.major_id=?
                ORDER BY s.year";
        $s = $db->prepare($sql);
        $s->execute([$u1, $majorId]); $dataA = $s->fetchAll();
        $s->execute([$u2, $majorId]); $dataB = $s->fetchAll();
    } else {
        // Không chọn ngành → lấy TB tất cả ngành
        $sql = "SELECT year, ROUND(AVG(score),2) AS avg, MAX(score) AS mx
                FROM admission_scores WHERE university_id=?
                GROUP BY year ORDER BY year";
        $s = $db->prepare($sql);
        $s->execute([$u1]); $dataA = $s->fetchAll();
        $s->execute([$u2]); $dataB = $s->fetchAll();
    }

    $n = $db->prepare("SELECT university_name,province,school_type FROM universities WHERE university_id=?");
    $n->execute([$u1]); $nameA = $n->fetch();
    $n->execute([$u2]); $nameB = $n->fetch();
}

$years = array_unique(array_merge(
    array_column($dataA, 'year'),
    array_column($dataB, 'year')
));
sort($years);

// Tên ngành đang chọn
$majorName = '';
if ($majorId) {
    foreach ($allMajors as $m) {
        if ($m['major_id'] == $majorId) { $majorName = $m['major_name']; break; }
    }
}
?>

<div class="container py-4">
  <h2 class="fw-bold mb-1"><i class="bi bi-bar-chart-line me-2 text-primary"></i>So sánh điểm chuẩn</h2>
  <p class="text-muted mb-4">Chọn 2 trường và ngành học để so sánh điểm chuẩn trực quan</p>

  <!-- Form chọn -->
  <div class="card mb-4 p-4">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label fw-semibold small">Trường A</label>
        <select name="uni1" class="form-select" required>
          <option value="">-- Chọn trường A --</option>
          <?php foreach($allUnis as $u): ?>
          <option value="<?= $u['university_id'] ?>" <?= $u1==$u['university_id']?'selected':'' ?>><?= e($u['university_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold small">Trường B</label>
        <select name="uni2" class="form-select" required>
          <option value="">-- Chọn trường B --</option>
          <?php foreach($allUnis as $u): ?>
          <option value="<?= $u['university_id'] ?>" <?= $u2==$u['university_id']?'selected':'' ?>><?= e($u['university_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold small">Ngành học <span class="text-muted fw-normal">(không bắt buộc)</span></label>
        <select name="major" class="form-select">
          <option value="0">-- Tất cả ngành (TB) --</option>
          <?php foreach($allMajors as $m): ?>
          <option value="<?= $m['major_id'] ?>" <?= $majorId==$m['major_id']?'selected':'' ?>><?= e($m['major_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1">
        <button type="submit" class="btn btn-primary w-100 fw-semibold">So sánh</button>
      </div>
    </form>
  </div>

  <?php if ($u1 && $u2 && $nameA && $nameB): ?>

  <!-- Thông báo đang so sánh -->
  <div class="alert alert-primary d-flex align-items-center gap-2 mb-4 py-2">
    <i class="bi bi-info-circle"></i>
    <span>Đang so sánh <?= $majorName ? 'ngành <strong>'.$majorName.'</strong>' : '<strong>điểm TB tất cả ngành</strong>' ?> giữa 2 trường</span>
  </div>

  <?php if (empty($dataA) && empty($dataB)): ?>
  <div class="text-center py-5 text-muted">
    <div style="font-size:48px">📭</div>
    <h5 class="mt-3">Không có dữ liệu để so sánh</h5>
    <p class="small">Ngành này chưa có dữ liệu ở một hoặc cả hai trường. Thử chọn ngành khác.</p>
  </div>

  <?php else: ?>

  <div class="row g-4 mb-4">
    <!-- Biểu đồ -->
    <div class="col-md-8">
      <div class="card">
        <div class="card-header"><i class="bi bi-graph-up me-1 text-primary"></i>
          Điểm chuẩn <?= $majorName ?: 'trung bình' ?> theo năm
        </div>
        <div class="card-body p-3">
          <canvas id="cCmp" height="150"></canvas>
        </div>
      </div>
    </div>

    <!-- Bảng so sánh -->
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header"><i class="bi bi-table me-1"></i>Bảng so sánh</div>
        <div class="card-body p-0">
          <table class="table table-sm small mb-0">
            <thead><tr>
              <th>Năm</th>
              <th class="text-primary" title="<?= e($nameA['university_name']) ?>"><?= e(substr($nameA['university_name'],0,12)) ?>...</th>
              <th class="text-danger"  title="<?= e($nameB['university_name']) ?>"><?= e(substr($nameB['university_name'],0,12)) ?>...</th>
            </tr></thead>
            <tbody>
              <?php foreach($years as $y):
                $a = array_values(array_filter($dataA, fn($r)=>$r['year']==$y));
                $b = array_values(array_filter($dataB, fn($r)=>$r['year']==$y));
                $sa = $a ? $a[0]['avg'] : null;
                $sb = $b ? $b[0]['avg'] : null;
              ?>
              <tr>
                <td class="fw-semibold"><?= $y ?></td>
                <td class="<?= $sa&&$sb&&$sa>$sb?'text-success fw-bold':'' ?>"><?= $sa ?? '—' ?></td>
                <td class="<?= $sa&&$sb&&$sb>$sa?'text-success fw-bold':'' ?>"><?= $sb ?? '—' ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer small text-muted">
          <span class="text-success fw-bold">Xanh đậm</span> = điểm cao hơn
        </div>
      </div>
    </div>
  </div>

  <!-- Thông tin 2 trường -->
  <div class="row g-3">
    <?php foreach ([[$nameA,$dataA,'#1a56db',$u1],[$nameB,$dataB,'#ef4444',$u2]] as [$n,$d,$c,$uid]): ?>
    <div class="col-md-6">
      <div class="card p-4">
        <h5 class="fw-bold mb-3" style="color:<?= $c ?>">
          <a href="<?= url('university.php?id='.$uid) ?>" class="text-decoration-none" style="color:<?= $c ?>">
            <?= e($n['university_name']) ?>
          </a>
        </h5>
        <table class="table table-sm small mb-0">
          <tr><td class="text-muted">Tỉnh/Thành</td><td><?= e($n['province']) ?></td></tr>
          <tr><td class="text-muted">Loại hình</td><td><?= e($n['school_type']) ?></td></tr>
          <?php if($d): $mx=max(array_column($d,'mx')); ?>
          <tr><td class="text-muted">Điểm cao nhất</td><td class="fw-bold" style="color:<?= $c ?>"><?= $mx ?></td></tr>
          <?php endif; ?>
        </table>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php endif; ?>
  <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>

<?php if ($u1 && $u2 && $years && ($dataA || $dataB)): ?>
<script>
window.addEventListener('load', function(){
  const rawA  = <?= json_encode($dataA, JSON_UNESCAPED_UNICODE) ?>;
  const rawB  = <?= json_encode($dataB, JSON_UNESCAPED_UNICODE) ?>;
  const years = <?= json_encode(array_values($years)) ?>;
  const getVal = (raw, y) => {
    const r = raw.find(r => String(r.year) === String(y));
    return r ? parseFloat(r.avg) : null;
  };
  chartLine('cCmp', years, [
    {
      label: <?= json_encode($nameA['university_name'], JSON_UNESCAPED_UNICODE) ?>,
      data: years.map(y => getVal(rawA, y)),
      borderColor: '#1a56db', backgroundColor: 'rgba(26,86,219,.15)',
      tension: .4, fill: true, pointRadius: 5, spanGaps: true
    },
    {
      label: <?= json_encode($nameB['university_name'], JSON_UNESCAPED_UNICODE) ?>,
      data: years.map(y => getVal(rawB, y)),
      borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,.15)',
      tension: .4, fill: true, pointRadius: 5, spanGaps: true
    },
  ]);
});
</script>
<?php endif; ?>