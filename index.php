<?php
$pageTitle = 'DiemChuan.vn – Tra cứu điểm chuẩn đại học Việt Nam';
require_once 'includes/header.php';

$db    = getDB();
$stats = getStats();

// Trường nổi bật
$featuredUnis = $db->query("
    SELECT u.university_id,u.university_name,u.university_code,u.province,u.logo,
           COUNT(DISTINCT s.major_id) AS mcnt, MAX(s.score) AS top_score
    FROM universities u
    LEFT JOIN admission_scores s ON u.university_id=s.university_id
    WHERE u.is_featured=1
    GROUP BY u.university_id ORDER BY top_score DESC LIMIT 6
")->fetchAll();

// Top ngành điểm cao (năm mới nhất)
$latestYear = (int)$db->query("SELECT MAX(year) FROM admission_scores")->fetchColumn();
$topMajors  = $db->query("
    SELECT m.major_id,m.major_name,MAX(s.score) AS max_score,COUNT(DISTINCT s.university_id) AS ucnt
    FROM admission_scores s JOIN majors m ON s.major_id=m.major_id
    WHERE s.year=$latestYear
    GROUP BY m.major_id ORDER BY max_score DESC LIMIT 6
")->fetchAll();

// Data chart trend (3 trường)
$chartRaw = $db->query("
    SELECT u.university_name,s.year,ROUND(AVG(s.score),2) AS avg
    FROM admission_scores s JOIN universities u ON s.university_id=u.university_id
    WHERE u.university_id IN (1,2,4)
    GROUP BY u.university_id,s.year ORDER BY s.year
")->fetchAll();

$years  = [2024,2023,2022,2021,2020];
$combos = ['A00','A01','A02','B00','B01','C00','D01','D07'];
$provinces  = getProvinces();
$schoolTypes= ['Công lập','Dân lập','Tư thục','Quốc tế'];
?>

<!-- HERO -->
<section class="hero">
  <div class="container position-relative">
    <div class="row align-items-center">
      <div class="col-lg-8">
        <h1 class="hero-title">Tra cứu điểm chuẩn<br>đại học Việt Nam</h1>
        <p class="hero-sub">Tổng hợp dữ liệu điểm chuẩn · AI gợi ý ngành học · Cập nhật liên tục</p>

        <!-- Tìm trường -->
        <form action="<?= url('search.php') ?>" method="GET" class="mb-3">
          <div class="search-hero">
            <i class="bi bi-building"></i>
            <input type="text" name="q" placeholder="Tìm tên trường đại học...">
            <select name="year">
              <option value="">Tất cả năm</option>
              <?php foreach($years as $y): ?><option><?= $y ?></option><?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm px-3 fw-semibold">Tìm trường</button>
          </div>
        </form>

        <!-- Tìm ngành -->
        <form action="<?= url('search.php') ?>" method="GET">
          <input type="hidden" name="type" value="major">
          <div class="search-hero">
            <i class="bi bi-book"></i>
            <input type="text" name="major" placeholder="Tìm tên ngành học...">
            <select name="combo">
              <option value="">Tất cả tổ hợp</option>
              <?php foreach($combos as $c): ?><option><?= $c ?></option><?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-light btn-sm px-3 fw-semibold border">Tìm ngành</button>
          </div>
        </form>

        <div class="hero-stats">
          <div class="hero-stat"><strong><?= number_format($stats['universities']) ?></strong><span>Trường đại học</span></div>
          <div class="hero-stat"><strong><?= number_format($stats['majors']) ?></strong><span>Ngành học</span></div>
          <div class="hero-stat"><strong><?= number_format($stats['scores']) ?></strong><span>Bản ghi điểm</span></div>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="container py-5">

  <!-- BỘ LỌC NHANH -->
  <div class="filter-card mb-5">
    <h5 class="fw-bold mb-3"><i class="bi bi-funnel me-2 text-primary"></i>Lọc dữ liệu nhanh</h5>
    <form action="<?= url('search.php') ?>" method="GET">
      <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
          <select name="year" class="form-select form-select-sm">
            <option value="">Năm tuyển sinh</option>
            <?php foreach($years as $y): ?><option><?= $y ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select name="combo" class="form-select form-select-sm">
            <option value="">Tổ hợp xét tuyển</option>
            <?php foreach($combos as $c): ?><option><?= $c ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <input type="number" name="min" class="form-control form-control-sm" placeholder="Điểm từ" min="0" max="30" step=".25">
        </div>
        <div class="col-6 col-md-2">
          <input type="number" name="max" class="form-control form-control-sm" placeholder="Điểm đến" min="0" max="30" step=".25">
        </div>
        <div class="col-6 col-md-2">
          <select name="province" class="form-select form-select-sm">
            <option value="">Tỉnh/Thành phố</option>
            <?php foreach($provinces as $p): ?><option><?= e($p) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select name="school_type" class="form-select form-select-sm">
            <option value="">Loại trường</option>
            <?php foreach($schoolTypes as $t): ?><option><?= $t ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 d-flex gap-2 justify-content-end">
          <button type="submit" class="btn btn-primary btn-sm px-4">
            <i class="bi bi-search me-1"></i>Tìm kiếm
          </button>
          <a href="<?= url('search.php') ?>" class="btn btn-outline-secondary btn-sm">Đặt lại</a>
        </div>
      </div>
    </form>
  </div>

  <!-- STAT CARDS -->
  <div class="row g-3 mb-5">
    <?php foreach([
      ['building','universities','Trường đại học','primary'],
      ['book',    'majors',      'Ngành học',     'success'],
      ['graph-up','scores',      'Bản ghi điểm',  'warning'],
      ['robot',   'ai_logs',     'Lượt dùng AI',  'info'],
    ] as [$ic,$key,$lb,$cl]): ?>
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="s-icon"><i class="bi bi-<?= $ic ?> text-<?= $cl ?>"></i></div>
        <div class="s-num"><?= number_format($stats[$key]) ?></div>
        <div class="s-lbl"><?= $lb ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- CHARTS -->
  <div class="row g-4 mb-5">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-graph-up me-2 text-primary"></i>Xu hướng điểm chuẩn theo năm</span>
          <button id="darkBtn" class="btn btn-sm btn-light border"><i class="bi bi-moon"></i></button>
        </div>
        <div class="card-body p-3"><canvas id="cTrend" height="120"></canvas></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <div class="card-header"><i class="bi bi-pie-chart me-2 text-primary"></i>Phân bố tổ hợp thi</div>
        <div class="card-body p-3"><canvas id="cCombo" height="200"></canvas></div>
      </div>
    </div>
  </div>

  <!-- TRƯỜNG NỔI BẬT -->
  <div class="d-flex justify-content-between align-items-end mb-3">
    <div>
      <h4 class="fw-bold mb-1"><i class="bi bi-building me-2 text-primary"></i>Trường đại học nổi bật</h4>
      <p class="text-muted small mb-0">Được chọn lọc từ dữ liệu điểm chuẩn cao nhất</p>
    </div>
    <a href="<?= url('search.php') ?>" class="btn btn-sm btn-outline-primary">Xem tất cả →</a>
  </div>
  <div class="row g-3 mb-5">
    <?php foreach($featuredUnis as $u): ?>
    <div class="col-6 col-md-4 col-lg-2">
      <a href="<?= url('university.php?id='.$u['university_id']) ?>" class="uni-card text-center">
        <div class="uni-logo mx-auto mb-2">
          <?php if($u['logo']): ?>
            <img src="<?= url('uploads/'.$u['logo']) ?>" alt="">
          <?php else: ?>
            <?= e(substr($u['university_code']??'?',0,4)) ?>
          <?php endif; ?>
        </div>
        <div class="fw-semibold small mb-1" style="font-size:12px;line-height:1.3"><?= e($u['university_name']) ?></div>
        <div class="text-muted mb-2" style="font-size:11px"><i class="bi bi-geo-alt me-1"></i><?= e($u['province']) ?></div>
        <div class="d-flex justify-content-center gap-1 flex-wrap">
          <?php if($u['top_score']): ?><span class="score-badge sb-mid"><?= number_format($u['top_score'],2) ?></span><?php endif; ?>
          <span class="chip"><?= $u['mcnt'] ?> ngành</span>
        </div>
      </a>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- TOP NGÀNH -->
  <div class="d-flex justify-content-between align-items-end mb-3">
    <div>
      <h4 class="fw-bold mb-1"><i class="bi bi-fire me-2 text-danger"></i>Top ngành điểm chuẩn cao</h4>
      <p class="text-muted small mb-0">Ngành có điểm chuẩn cao nhất năm <?= $latestYear ?></p>
    </div>
    <a href="<?= url('search.php') ?>" class="btn btn-sm btn-outline-primary">Xem tất cả →</a>
  </div>
  <div class="row g-3">
    <?php foreach($topMajors as $i=>$m): ?>
    <div class="col-6 col-md-4">
      <a href="<?= url('major.php?id='.$m['major_id']) ?>"
         class="card p-3 d-flex flex-row align-items-center gap-3 text-decoration-none text-reset">
        <span class="badge <?= $i===0?'bg-warning text-dark':($i===1?'bg-secondary':'bg-light text-dark') ?> px-2 py-2 fs-6"><?= $i+1 ?></span>
        <div class="flex-grow-1 overflow-hidden">
          <div class="fw-semibold text-truncate" style="font-size:13px"><?= e($m['major_name']) ?></div>
          <div class="text-muted" style="font-size:11px"><?= $m['ucnt'] ?> trường</div>
        </div>
        <span class="score-badge sb-hi fw-bold flex-shrink-0"><?= number_format($m['max_score'],2) ?></span>
      </a>
    </div>
    <?php endforeach; ?>
  </div>

</div>

<script>
// Chart trend
(function(){
  const raw    = <?= json_encode($chartRaw, JSON_UNESCAPED_UNICODE) ?>;
  const years  = [...new Set(raw.map(r=>r.year))].sort();
  const schools= [...new Set(raw.map(r=>r.university_name))];
  const colors = ['#1a56db','#10b981','#f59e0b'];
  const datasets = schools.map((name,i)=>({
    label: name,
    data : years.map(y=>{ const r=raw.find(r=>r.university_name===name&&r.year==y); return r?+r.avg:null; }),
    borderColor:colors[i], backgroundColor:colors[i]+'22',
    tension:.4, fill:true, pointRadius:4, spanGaps:true
  }));
  chartLine('cTrend', years, datasets);
  chartDoughnut('cCombo',['A00','A01','B00','D01','C00','Khác'],[34,24,12,19,7,4]);
})();
</script>

<?php require_once 'includes/footer.php'; ?>
