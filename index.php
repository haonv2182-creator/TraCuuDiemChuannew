<?php
$pageTitle = 'DiemChuan.vn – Tra cứu điểm chuẩn đại học Việt Nam';
require_once 'includes/header.php';

$db  = getDB();
$q      = trim($_GET['q']       ?? '');  // tìm trường
$mid    = (int)($_GET['major']  ?? 0);   // tìm theo ngành
$method = trim($_GET['method']  ?? '');  // lọc phương thức xét tuyển
$tab    = isset($_GET['major']) ? 'major' : 'uni';

// ── Tất cả ngành (cho dropdown) ──────────────────────────────
$allMajors = $db->query("SELECT major_id, major_name FROM majors ORDER BY major_name")->fetchAll();

// ── Kết quả tìm trường ───────────────────────────────────────
$uniResults = [];
if ($q !== '') {
    $stmt = $db->prepare("
        SELECT u.university_id, u.university_name, u.university_code,
               u.province, u.logo, u.school_type,
               COUNT(DISTINCT s.major_id) AS mcnt,
               MAX(s.score) AS top_score
        FROM universities u
        LEFT JOIN admission_scores s ON u.university_id=s.university_id
        WHERE u.university_name LIKE :q
        GROUP BY u.university_id ORDER BY top_score DESC
    ");
    $stmt->execute([':q' => "%$q%"]);
    $uniResults = $stmt->fetchAll();
}

// ── Kết quả tìm theo ngành ───────────────────────────────────
$majorResults = [];
$majorName    = '';

if ($mid) {
    $mn = $db->prepare("SELECT major_name FROM majors WHERE major_id=?");
    $mn->execute([$mid]);
    $majorName = $mn->fetchColumn();

    $yearSql = "SELECT MAX(year) FROM admission_scores WHERE major_id = :mid";
    $yearParams = [':mid' => $mid];

    if ($method !== '') {
        $yearSql .= " AND method = :method";
        $yearParams[':method'] = $method;
    }

    $yearStmt = $db->prepare($yearSql);
    $yearStmt->execute($yearParams);
    $latestYear = (int)$yearStmt->fetchColumn();

    $majorWhere = "s.major_id = :mid AND s.year = :yr";
    $majorParams = [
        ':mid' => $mid,
        ':yr'  => $latestYear
    ];

    if ($method !== '') {
        $majorWhere .= " AND s.method = :method";
        $majorParams[':method'] = $method;
    }

    $stmt = $db->prepare("
        SELECT u.university_id, u.university_name, u.university_code,
               u.province, u.logo, u.school_type,
               s.score, s.combination, s.method, s.quota, s.year
        FROM admission_scores s
        JOIN universities u ON s.university_id = u.university_id
        WHERE $majorWhere
        ORDER BY s.score DESC
    ");
    $stmt->execute($majorParams);
    $majorResults = $stmt->fetchAll();
}

// ── Trường nổi bật ────────────────────────────────────────────
$featuredUnis = [];

if (!$q && !$mid) {
    $featuredUnis = $db->query("
        SELECT u.university_id, u.university_name, u.university_code,
               u.province, u.logo, u.school_type,
               COUNT(DISTINCT s.major_id) AS mcnt,
               MAX(s.score) AS top_score
        FROM universities u
        LEFT JOIN admission_scores s ON u.university_id=s.university_id
        WHERE u.is_featured=1
        GROUP BY u.university_id ORDER BY top_score DESC
    ")->fetchAll();
}

$stats = $db->query("SELECT
    (SELECT COUNT(*) FROM universities) AS unis,
    (SELECT COUNT(*) FROM admission_scores) AS scores")->fetch();
?>

<!-- HERO -->
<section class="hero">
  <div class="container">
    <div class="text-center mb-2">
      <h1 class="hero-title">Tra cứu điểm chuẩn<br>đại học Việt Nam</h1>
      <p class="hero-sub">
        Tổng hợp dữ liệu tuyển sinh từ <?= $stats['unis'] ?> trường · <?= number_format($stats['scores']) ?> bản ghi
      </p>

      <!-- Tab chọn kiểu tìm -->
      <div class="d-flex justify-content-center gap-2 mb-3">
        <button onclick="switchTab('uni')" id="tab-uni"
                class="btn fw-semibold px-4 <?= $tab==='uni'?'btn-light':'btn-outline-light' ?>"
                style="border-radius:30px">
          <i class="bi bi-building me-1"></i>Tìm theo trường
        </button>

        <button onclick="switchTab('major')" id="tab-major"
                class="btn fw-semibold px-4 <?= $tab==='major'?'btn-light':'btn-outline-light' ?>"
                style="border-radius:30px">
          <i class="bi bi-book me-1"></i>Tìm theo ngành
        </button>
      </div>

      <!-- Form tìm trường -->
      <form action="<?= url('index.php') ?>" method="GET" id="form-uni"
            class="<?= $tab==='major'?'d-none':'' ?>">
        <div class="search-hero mx-auto" style="max-width:580px">
          <i class="bi bi-building"></i>
          <input type="text" name="q" value="<?= e($q) ?>"
                 placeholder="Nhập tên trường đại học...">
          <button type="submit" class="btn btn-primary px-4 fw-semibold">Tìm kiếm</button>
        </div>
      </form>

      <!-- Form tìm ngành -->
      <form action="<?= url('index.php') ?>" method="GET" id="form-major"
            class="<?= $tab==='uni'?'d-none':'' ?>">
        <div class="search-hero mx-auto" style="max-width:820px">
          <i class="bi bi-book"></i>

          <select name="major"
                  class="form-select border-0 bg-transparent"
                  style="flex:1;outline:none;font-size:14px;font-family:inherit"
                  onchange="this.form.submit()">
            <option value="0">-- Chọn ngành học --</option>
            <?php foreach($allMajors as $m): ?>
            <option value="<?= $m['major_id'] ?>" <?= $mid==$m['major_id']?'selected':'' ?>>
              <?= e($m['major_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>

          <select name="method"
                  class="form-select border-0 bg-transparent"
                  style="max-width:190px;outline:none;font-size:14px;font-family:inherit;border-left:1px solid var(--gray-200)!important"
                  onchange="this.form.submit()">
            <option value="">Tất cả phương thức</option>
            <option value="THPT" <?= $method==='THPT'?'selected':'' ?>>Thi THPT</option>
            <option value="HocBa" <?= $method==='HocBa'?'selected':'' ?>>Học bạ</option>
            <option value="TongHop" <?= $method==='TongHop'?'selected':'' ?>>Tổng hợp</option>
            <option value="DGNL" <?= $method==='DGNL'?'selected':'' ?>>Đánh giá NL</option>
            <option value="Thang" <?= $method==='Thang'?'selected':'' ?>>Xét thẳng</option>
          </select>

          <button type="submit" class="btn btn-primary px-4 fw-semibold">
            Xem điểm
          </button>
        </div>
      </form>

    </div>
  </div>
</section>

<div class="container py-5">

<?php if ($mid && $majorName): ?>
<!-- ══ KẾT QUẢ TÌM THEO NGÀNH ══ -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="fw-bold mb-1">
        <i class="bi bi-book me-2 text-primary"></i>
        Điểm chuẩn ngành <span class="text-primary"><?= e($majorName) ?></span>
      </h4>

      <p class="text-muted small mb-0">
        <?= count($majorResults) ?> kết quả · Dữ liệu năm <?= $majorResults ? $majorResults[0]['year'] : '' ?> mới nhất

        <?php if($method): ?>
          · Phương thức:
          <span class="text-primary">
            <?php
              $methodLabelsTitle = [
                'THPT'    => 'Thi THPT',
                'HocBa'   => 'Học bạ',
                'TongHop' => 'Tổng hợp',
                'DGNL'    => 'Đánh giá năng lực',
                'Thang'   => 'Xét thẳng'
              ];
              echo e($methodLabelsTitle[$method] ?? $method);
            ?>
          </span>
        <?php endif; ?>
      </p>
    </div>

    <a href="<?= url('index.php') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-x me-1"></i>Xóa tìm kiếm
    </a>
  </div>

  <?php if(empty($majorResults)): ?>
  <div class="text-center py-5 text-muted">
    <div style="font-size:48px">📭</div>
    <h5 class="mt-3">Chưa có dữ liệu ngành này</h5>
    <a href="<?= url('index.php') ?>" class="btn btn-outline-primary btn-sm mt-2">Quay lại</a>
  </div>
  <?php else: ?>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th>#</th>
            <th>Trường đại học</th>
            <th>Tỉnh/TP</th>
            <th>Loại trường</th>
            <th>Tổ hợp</th>
            <th>Phương thức</th>
            <th>Điểm chuẩn</th>
            <th>Chỉ tiêu</th>
            <th></th>
          </tr>
        </thead>

        <tbody>
          <?php foreach($majorResults as $i=>$r):
            $cls = $r['score']>=27 ? 'sb-hi' : ($r['score']>=23 ? 'sb-mid' : 'sb-lo');
          ?>
          <tr>
            <td class="text-muted small"><?= $i+1 ?></td>

            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="uni-logo flex-shrink-0" style="width:36px;height:36px;font-size:11px">
                  <?php if($r['logo']??null): ?>
                    <img src="<?= url('uploads/'.$r['logo']) ?>" alt="">
                  <?php else: ?>
                    <?= e(substr($r['university_code']??'?',0,3)) ?>
                  <?php endif; ?>
                </div>

                <div>
                  <div class="fw-semibold small">
                    <?= e($r['university_name']) ?>
                  </div>
                </div>
              </div>
            </td>

            <td class="text-muted small">
              <?= e($r['province']) ?>
            </td>

            <td>
              <span class="chip"><?= e($r['school_type']) ?></span>
            </td>

            <td>
              <span class="chip"><?= e($r['combination']) ?></span>
            </td>

            <td>
              <?php
                $methodColors = [
                  'THPT'    => 'primary',
                  'HocBa'   => 'success',
                  'TongHop' => 'warning',
                  'DGNL'    => 'info',
                  'Thang'   => 'secondary'
                ];

                $methodLabels = [
                  'THPT'    => 'Thi THPT',
                  'HocBa'   => 'Học bạ',
                  'TongHop' => 'Tổng hợp',
                  'DGNL'    => 'Đánh giá NL',
                  'Thang'   => 'Xét thẳng'
                ];

                $methodColor = $methodColors[$r['method']] ?? 'secondary';
                $methodLabel = $methodLabels[$r['method']] ?? $r['method'];
              ?>

              <span class="badge text-bg-<?= $methodColor ?> fw-normal"
                    style="font-size:10px;border-radius:20px">
                <?= e($methodLabel) ?>
              </span>
            </td>

            <td>
              <span class="score-badge <?= $cls ?> fw-bold">
                <?= number_format($r['score'],2) ?>
              </span>
            </td>

            <td class="text-muted small">
              <?= number_format($r['quota']) ?>
            </td>

            <td>
              <a href="<?= url('university.php?id='.$r['university_id']) ?>"
                 class="btn btn-sm btn-outline-primary py-0 px-2">
                Chi tiết
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

<?php elseif ($q !== ''): ?>
<!-- ══ KẾT QUẢ TÌM THEO TRƯỜNG ══ -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="fw-bold mb-1">
        <i class="bi bi-search me-2 text-primary"></i>
        Kết quả cho "<span class="text-primary"><?= e($q) ?></span>"
      </h4>
      <p class="text-muted small mb-0">
        Tìm thấy <strong><?= count($uniResults) ?></strong> trường
      </p>
    </div>

    <a href="<?= url('index.php') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-x me-1"></i>Xóa tìm kiếm
    </a>
  </div>

  <?php if(empty($uniResults)): ?>
  <div class="text-center py-5 text-muted">
    <div style="font-size:48px">🔍</div>
    <h5 class="mt-3">Không tìm thấy trường nào</h5>
    <a href="<?= url('index.php') ?>" class="btn btn-outline-primary btn-sm mt-2">Quay lại</a>
  </div>
  <?php else: ?>

  <div class="row g-3">
    <?php foreach($uniResults as $u): ?>
    <div class="col-6 col-md-4 col-lg-3">
      <a href="<?= url('university.php?id='.$u['university_id']) ?>"
         class="uni-card h-100 text-decoration-none">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="uni-logo flex-shrink-0" style="width:48px;height:48px;font-size:13px">
            <?php if($u['logo']): ?>
              <img src="<?= url('uploads/'.$u['logo']) ?>" alt="">
            <?php else: ?>
              <?= e(substr($u['university_code']??'?',0,4)) ?>
            <?php endif; ?>
          </div>

          <div class="overflow-hidden">
            <div class="fw-bold text-dark" style="font-size:13px;line-height:1.3">
              <?= e($u['university_name']) ?>
            </div>
            <div class="text-muted" style="font-size:11px">
              <i class="bi bi-geo-alt me-1"></i><?= e($u['province']) ?>
            </div>
          </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
          <?php if($u['top_score']): ?>
          <span class="score-badge sb-hi fw-bold">
            <i class="bi bi-star-fill me-1" style="font-size:10px"></i>
            <?= number_format($u['top_score'],2) ?>
          </span>
          <?php endif; ?>

          <span class="chip"><?= $u['mcnt'] ?> ngành</span>
          <span class="chip"><?= e($u['school_type']) ?></span>
        </div>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

<?php else: ?>
<!-- ══ TRANG CHỦ: TRƯỜNG NỔI BẬT ══ -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="fw-bold mb-1">
        <i class="bi bi-building me-2 text-primary"></i>Trường đại học nổi bật
      </h4>
      <p class="text-muted small mb-0">
        <?= count($featuredUnis) ?> trường được chọn lọc · Nhấn để xem điểm chuẩn
      </p>
    </div>

    <a href="<?= url('search.php') ?>" class="btn btn-outline-primary btn-sm">
      Xem tất cả <i class="bi bi-arrow-right ms-1"></i>
    </a>
  </div>

  <div class="row g-3">
    <?php foreach($featuredUnis as $u): ?>
    <div class="col-6 col-md-4 col-lg-3">
      <a href="<?= url('university.php?id='.$u['university_id']) ?>"
         class="uni-card h-100 text-decoration-none">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="uni-logo flex-shrink-0" style="width:48px;height:48px;font-size:13px">
            <?php if($u['logo']): ?>
              <img src="<?= url('uploads/'.$u['logo']) ?>" alt="">
            <?php else: ?>
              <?= e(substr($u['university_code']??'?',0,4)) ?>
            <?php endif; ?>
          </div>

          <div class="overflow-hidden">
            <div class="fw-bold text-dark" style="font-size:13px;line-height:1.3">
              <?= e($u['university_name']) ?>
            </div>
            <div class="text-muted" style="font-size:11px">
              <i class="bi bi-geo-alt me-1"></i><?= e($u['province']) ?>
            </div>
          </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
          <?php if($u['top_score']): ?>
          <span class="score-badge sb-hi fw-bold">
            <i class="bi bi-star-fill me-1" style="font-size:10px"></i>
            <?= number_format($u['top_score'],2) ?>
          </span>
          <?php endif; ?>

          <span class="chip"><?= $u['mcnt'] ?> ngành</span>
          <span class="chip"><?= e($u['school_type']) ?></span>
        </div>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

</div>

<script>
function switchTab(tab) {
  const formUni   = document.getElementById('form-uni');
  const formMajor = document.getElementById('form-major');
  const tabUni    = document.getElementById('tab-uni');
  const tabMajor  = document.getElementById('tab-major');

  if (tab === 'uni') {
    formUni.classList.remove('d-none');
    formMajor.classList.add('d-none');
    tabUni.classList.replace('btn-outline-light','btn-light');
    tabMajor.classList.replace('btn-light','btn-outline-light');
  } else {
    formMajor.classList.remove('d-none');
    formUni.classList.add('d-none');
    tabMajor.classList.replace('btn-outline-light','btn-light');
    tabUni.classList.replace('btn-light','btn-outline-light');
  }
}

// Tự mở đúng tab khi có kết quả ngành
<?php if($mid): ?> switchTab('major'); <?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>