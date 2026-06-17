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
// Các ngành có nhiều dữ liệu nhất để hiển thị tìm kiếm nhanh
$popularMajors = $db->query("
    SELECT 
        m.major_id,
        m.major_name,
        COUNT(s.score_id) AS total_scores
    FROM majors m
    LEFT JOIN admission_scores s ON m.major_id = s.major_id
    GROUP BY m.major_id, m.major_name
    ORDER BY total_scores DESC, m.major_name ASC
    LIMIT 5
")->fetchAll();

// ── Kết quả tìm trường ───────────────────────────────────────
$uniResults = [];
if ($q !== '') {
    $stmt = $db->prepare("
        SELECT u.university_id, u.university_name, u.university_code,
               u.province, u.school_type,
               COUNT(DISTINCT s.major_id) AS mcnt,
               MAX(s.score) AS top_score
        FROM universities u
        LEFT JOIN admission_scores s ON u.university_id=s.university_id
        WHERE u.university_name LIKE :q
        GROUP BY u.university_id 
        ORDER BY top_score DESC
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
               u.province, u.school_type,
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
               u.province, u.school_type,
               COUNT(DISTINCT s.major_id) AS mcnt,
               MAX(s.score) AS top_score
        FROM universities u
        LEFT JOIN admission_scores s ON u.university_id=s.university_id
        WHERE u.is_featured=1
        GROUP BY u.university_id 
        ORDER BY top_score DESC
    ")->fetchAll();
}

$stats = $db->query("
    SELECT
        (SELECT COUNT(*) FROM universities) AS unis,
        (SELECT COUNT(*) FROM majors) AS majors,
        (SELECT COUNT(*) FROM admission_scores) AS scores
")->fetch();

function uni_code_box($code, $name, $length = 4) {
    $code = trim((string)$code);
    if ($code !== '') {
        return $code;
    }

    return mb_substr(trim((string)$name), 0, $length, 'UTF-8');
}
?>

<!-- HERO -->
<section class="hero home-hero">
  <div class="container position-relative">

    <div class="text-center">
      <span class="home-hero-label">
        <i class="bi bi-mortarboard-fill me-1"></i>
        Dữ liệu tuyển sinh đại học Việt Nam
      </span>

      <h1 class="hero-title mt-3">
        Tra cứu điểm chuẩn<br>
        <span class="home-gradient-text">nhanh chóng và chính xác</span>
      </h1>

      <p class="hero-sub">
        Tìm trường, ngành học và phương thức xét tuyển phù hợp với bạn
      </p>

      <!-- Tab tìm kiếm -->
      <div class="d-flex justify-content-center gap-2 mb-3">
        <button type="button"
                onclick="switchTab('uni')"
                id="tab-uni"
                class="btn fw-semibold px-4 <?= $tab === 'uni' ? 'btn-light' : 'btn-outline-light' ?>"
                style="border-radius:30px">
          <i class="bi bi-building me-1"></i>
          Tìm theo trường
        </button>

        <button type="button"
                onclick="switchTab('major')"
                id="tab-major"
                class="btn fw-semibold px-4 <?= $tab === 'major' ? 'btn-light' : 'btn-outline-light' ?>"
                style="border-radius:30px">
          <i class="bi bi-book me-1"></i>
          Tìm theo ngành
        </button>
      </div>

      <!-- Form tìm trường -->
      <form action="<?= url('index.php') ?>"
            method="GET"
            id="form-uni"
            class="js-home-search-form <?= $tab === 'major' ? 'd-none' : '' ?>">

        <div class="search-hero mx-auto" style="max-width:650px">
          <i class="bi bi-building"></i>

          <input type="text"
                 name="q"
                 id="heroUniversityInput"
                 value="<?= e($q) ?>"
                 autocomplete="off"
                 placeholder="Nhập tên trường đại học...">

          <button type="submit"
                  class="btn btn-primary px-4 fw-semibold js-submit-btn">
            <i class="bi bi-search me-1"></i>
            Tìm kiếm
          </button>
        </div>
      </form>

      <!-- Form tìm ngành -->
      <form action="<?= url('index.php') ?>"
            method="GET"
            id="form-major"
            class="js-home-search-form <?= $tab === 'uni' ? 'd-none' : '' ?>">

        <div class="search-hero mx-auto" style="max-width:850px">
          <i class="bi bi-book"></i>

          <select name="major"
                  id="heroMajorSelect"
                  class="form-select border-0 bg-transparent"
                  style="flex:1;outline:none;font-size:14px;font-family:inherit"
                  onchange="this.form.submit()">

            <option value="0">-- Chọn ngành học --</option>

            <?php foreach ($allMajors as $m): ?>
              <option value="<?= $m['major_id'] ?>"
                      <?= $mid == $m['major_id'] ? 'selected' : '' ?>>
                <?= e($m['major_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <select name="method"
                  class="form-select border-0 bg-transparent"
                  style="max-width:200px;
                         outline:none;
                         font-size:14px;
                         font-family:inherit;
                         border-left:1px solid var(--gray-200)!important"
                  onchange="this.form.submit()">

            <option value="">Tất cả phương thức</option>

            <option value="THPT" <?= $method === 'THPT' ? 'selected' : '' ?>>
              Thi THPT
            </option>

            <option value="HocBa" <?= $method === 'HocBa' ? 'selected' : '' ?>>
              Học bạ
            </option>

            <option value="TongHop" <?= $method === 'TongHop' ? 'selected' : '' ?>>
              Tổng hợp
            </option>

            <option value="DGNL" <?= $method === 'DGNL' ? 'selected' : '' ?>>
              Đánh giá NL
            </option>

            <option value="Thang" <?= $method === 'Thang' ? 'selected' : '' ?>>
              Xét thẳng
            </option>
          </select>

          <button type="submit"
                  class="btn btn-primary px-4 fw-semibold js-submit-btn">
            <i class="bi bi-search me-1"></i>
            Xem điểm
          </button>
        </div>
      </form>

      <!-- Ngành tìm nhanh -->
      <?php if (!empty($popularMajors)): ?>
      <div class="popular-searches">
        <span class="popular-searches-label">Tìm nhanh:</span>

        <?php foreach ($popularMajors as $m): ?>
          <button type="button"
                  class="popular-search-btn"
                  data-major-id="<?= $m['major_id'] ?>">
            <?= e($m['major_name']) ?>
          </button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Thống kê -->
      <div class="home-hero-stats">

        <div class="home-hero-stat">
          <div class="home-stat-icon">
            <i class="bi bi-building"></i>
          </div>

          <div>
            <strong data-counter="<?= (int)$stats['unis'] ?>">
              <?= number_format($stats['unis']) ?>
            </strong>
            <span>Trường đại học</span>
          </div>
        </div>

        <div class="home-hero-stat">
          <div class="home-stat-icon">
            <i class="bi bi-book"></i>
          </div>

          <div>
            <strong data-counter="<?= (int)$stats['majors'] ?>">
              <?= number_format($stats['majors']) ?>
            </strong>
            <span>Ngành học</span>
          </div>
        </div>

        <div class="home-hero-stat">
          <div class="home-stat-icon">
            <i class="bi bi-graph-up-arrow"></i>
          </div>

          <div>
            <strong data-counter="<?= (int)$stats['scores'] ?>">
              <?= number_format($stats['scores']) ?>
            </strong>
            <span>Dữ liệu điểm chuẩn</span>
          </div>
        </div>

      </div>

    </div>
  </div>
</section>

<div class="container py-5">
  <!-- Chức năng nhanh -->
<section class="mb-5">
  <div class="section-heading text-center mb-4">
    <span class="section-label">Khám phá hệ thống</span>
    <h3 class="fw-bold mt-2 mb-2">Bạn đang cần tìm gì?</h3>
    <p class="text-muted mb-0">
      Sử dụng các công cụ hỗ trợ tra cứu và lựa chọn trường đại học
    </p>
  </div>

  <div class="row g-3">

    <div class="col-md-4 reveal-up">
      <a href="<?= url('search.php') ?>"
         class="home-action-card text-decoration-none">

        <div class="home-action-icon action-search">
          <i class="bi bi-search"></i>
        </div>

        <div>
          <h5>Tra cứu điểm chuẩn</h5>
          <p>
            Tìm điểm theo trường, ngành, tổ hợp, năm và phương thức xét tuyển.
          </p>

          <span class="home-action-link">
            Tra cứu ngay
            <i class="bi bi-arrow-right"></i>
          </span>
        </div>
      </a>
    </div>

    <div class="col-md-4 reveal-up">
      <a href="<?= url('compare.php') ?>"
         class="home-action-card text-decoration-none">

        <div class="home-action-icon action-compare">
          <i class="bi bi-bar-chart-line"></i>
        </div>

        <div>
          <h5>So sánh trường</h5>
          <p>
            So sánh điểm chuẩn giữa hai trường qua từng năm và từng ngành.
          </p>

          <span class="home-action-link">
            So sánh ngay
            <i class="bi bi-arrow-right"></i>
          </span>
        </div>
      </a>
    </div>

    <div class="col-md-4 reveal-up">
      <a href="<?= url('ai_recommend.php') ?>"
         class="home-action-card text-decoration-none">

        <div class="home-action-icon action-ai">
          <i class="bi bi-stars"></i>
        </div>

        <div>
          <h5>Gợi ý trường phù hợp</h5>
          <p>
            Nhập điểm của bạn để nhận gợi ý ngành và trường phù hợp.
          </p>

          <span class="home-action-link">
            Nhận gợi ý
            <i class="bi bi-arrow-right"></i>
          </span>
        </div>
      </a>
    </div>

  </div>
</section>

<!-- Lịch sử tìm kiếm -->
<section id="recentSearchesWrap"
         class="recent-search-section mb-5 d-none reveal-up">

  <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
    <div>
      <h5 class="fw-bold mb-1">
        <i class="bi bi-clock-history text-primary me-2"></i>
        Tìm kiếm gần đây
      </h5>

      <p class="text-muted small mb-0">
        Nhấn vào một mục để tìm lại nhanh chóng
      </p>
    </div>

    <button type="button"
            id="clearRecentSearches"
            class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-trash3 me-1"></i>
      Xóa lịch sử
    </button>
  </div>

  <div id="recentSearchList"
       class="d-flex flex-wrap gap-2">
  </div>
</section>

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
                <div class="uni-logo flex-shrink-0 d-flex align-items-center justify-content-center"
                     style="width:36px;height:36px;font-size:10px;font-weight:700">
                  <?= e(uni_code_box($r['university_code'] ?? '', $r['university_name'] ?? '', 3)) ?>
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
          <div class="uni-logo flex-shrink-0 d-flex align-items-center justify-content-center"
               style="width:48px;height:48px;font-size:12px;font-weight:700">
            <?= e(uni_code_box($u['university_code'] ?? '', $u['university_name'] ?? '', 4)) ?>
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
          <div class="uni-logo flex-shrink-0 d-flex align-items-center justify-content-center"
               style="width:48px;height:48px;font-size:12px;font-weight:700">
            <?= e(uni_code_box($u['university_code'] ?? '', $u['university_name'] ?? '', 4)) ?>
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