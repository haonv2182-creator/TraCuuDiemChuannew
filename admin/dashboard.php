<?php
$pageTitle = 'Dashboard – Admin DiemChuan.vn';
require_once '../includes/header.php';
requireAdmin();
$db    = getDB();
$stats = getStats();

$byYear  = $db->query("SELECT year, COUNT(*) AS cnt FROM admission_scores GROUP BY year ORDER BY year")->fetchAll();
$topUni  = $db->query("SELECT u.university_name, MAX(s.score) AS mx
                        FROM admission_scores s JOIN universities u ON s.university_id=u.university_id
                        WHERE s.year=(SELECT MAX(year) FROM admission_scores)
                        GROUP BY u.university_id ORDER BY mx DESC LIMIT 5")->fetchAll();
$byRegion= $db->query("SELECT province, COUNT(*) AS cnt FROM universities GROUP BY province ORDER BY cnt DESC LIMIT 6")->fetchAll();
$aiLogs  = $db->query("SELECT * FROM ai_logs ORDER BY created_at DESC LIMIT 6")->fetchAll();
?>
<div class="admin-wrapper">
  <?php require_once '../includes/sidebar.php'; ?>
  <div class="admin-content">
    <h2 class="fw-bold mb-4"><i class="bi bi-speedometer2 me-2"></i>Dashboard</h2>

    <!-- Stats -->
    <div class="row g-3 mb-4">
      <?php foreach ([
        ['building','universities','Trường đại học','primary'],
        ['book',    'majors',      'Ngành học',     'success'],
        ['graph-up','scores',      'Bản ghi điểm',  'warning'],
        ['robot',   'ai_logs',     'Lượt dùng AI',  'info'],
      ] as [$ic,$k,$lb,$cl]): ?>
      <div class="col-6 col-md-3">
        <div class="stat-card">
          <div class="s-icon"><i class="bi bi-<?= $ic ?> text-<?= $cl ?>"></i></div>
          <div class="s-num"><?= number_format($stats[$k]) ?></div>
          <div class="s-lbl"><?= $lb ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Charts -->
    <div class="row g-4 mb-4">
      <div class="col-md-7">
        <div class="card">
          <div class="card-header"><i class="bi bi-bar-chart me-1"></i>Bản ghi điểm theo năm</div>
          <div class="card-body p-3"><canvas id="cYear" height="160"></canvas></div>
        </div>
      </div>
      <div class="col-md-5">
        <div class="card">
          <div class="card-header"><i class="bi bi-pie-chart me-1"></i>Phân bố theo tỉnh/thành</div>
          <div class="card-body p-3"><canvas id="cRegion" height="160"></canvas></div>
        </div>
      </div>
    </div>

    <!-- Top + Logs -->
    <div class="row g-4">
      <div class="col-md-6">
        <div class="card">
          <div class="card-header"><i class="bi bi-trophy me-1 text-warning"></i>Top trường điểm cao nhất</div>
          <div class="card-body p-0">
            <?php foreach ($topUni as $i => $u): ?>
            <div class="d-flex align-items-center px-4 py-3 <?= $i < count($topUni)-1 ? 'border-bottom' : '' ?>">
              <span class="badge <?= $i===0?'bg-warning text-dark':($i===1?'bg-secondary':'bg-light text-dark') ?> me-3" style="width:24px"><?= $i+1 ?></span>
              <div class="flex-grow-1 small fw-semibold"><?= e($u['university_name']) ?></div>
              <span class="score-badge sb-hi"><?= number_format($u['mx'], 2) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card">
          <div class="card-header"><i class="bi bi-robot me-1"></i>Lượt dùng AI gần nhất</div>
          <div class="card-body p-0">
            <?php if (empty($aiLogs)): ?>
            <p class="text-muted small text-center py-4">Chưa có lượt sử dụng nào.</p>
            <?php else: ?>
            <?php foreach ($aiLogs as $log): ?>
            <div class="d-flex align-items-center px-4 py-2 border-bottom">
              <div class="flex-grow-1">
                <span class="fw-semibold small"><?= number_format($log['user_score'], 2) ?> điểm</span>
                <span class="chip ms-2"><?= e($log['combination']) ?></span>
                <?php if ($log['province']): ?><span class="chip ms-1"><?= e($log['province']) ?></span><?php endif; ?>
              </div>
              <span class="text-muted" style="font-size:11px"><?= date('d/m H:i', strtotime($log['created_at'])) ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /admin-content -->
</div>

<script>
chartBar('cYear',
  <?= json_encode(array_column($byYear, 'year')) ?>,
  <?= json_encode(array_column($byYear, 'cnt')) ?>,
  'Số bản ghi'
);
chartDoughnut('cRegion',
  <?= json_encode(array_column($byRegion, 'province'), JSON_UNESCAPED_UNICODE) ?>,
  <?= json_encode(array_column($byRegion, 'cnt')) ?>
);
</script>

<?php require_once '../includes/footer.php'; ?>
