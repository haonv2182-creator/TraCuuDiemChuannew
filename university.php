<?php
require_once 'includes/header.php';
$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('search.php');
$db  = getDB();

$uni = $db->prepare("SELECT * FROM universities WHERE university_id=?");
$uni->execute([$id]); $uni = $uni->fetch();
if (!$uni) { http_response_code(404); echo '<h2 class="p-5">Không tìm thấy trường</h2>'; require_once 'includes/footer.php'; exit; }
$pageTitle = e($uni['university_name']) . ' – DiemChuan.vn';

// Lọc theo năm và phương thức
$filterYear   = (int)($_GET['year']   ?? 0);
$filterMethod = trim($_GET['method'] ?? '');

// Lấy tất cả năm có dữ liệu của trường
$years = $db->prepare("SELECT DISTINCT year FROM admission_scores WHERE university_id=? ORDER BY year DESC");
$years->execute([$id]); $years = $years->fetchAll(PDO::FETCH_COLUMN);

// Lấy điểm theo năm lọc hoặc tất cả
if ($filterYear) {
    $wh2=[]; $p2=[$id,$filterYear];
    if($filterMethod){$wh2[]='AND s.method=?';$p2[]=$filterMethod;}
    $scores=$db->prepare("SELECT m.major_id,m.major_name,s.year,s.combination,s.method,s.score,s.quota
        FROM admission_scores s JOIN majors m ON s.major_id=m.major_id
        WHERE s.university_id=? AND s.year=? ".implode(' ',$wh2)." ORDER BY s.score DESC");
    $scores->execute($p2);
} else {
    $wh2=[]; $p2=[$id];
    if($filterMethod){$wh2[]='AND s.method=?';$p2[]=$filterMethod;}
    $scores=$db->prepare("SELECT m.major_id,m.major_name,s.year,s.combination,s.method,s.score,s.quota
        FROM admission_scores s JOIN majors m ON s.major_id=m.major_id
        WHERE s.university_id=? ".implode(' ',$wh2)." ORDER BY s.year DESC,s.score DESC");
    $scores->execute($p2);
}
$all = $scores->fetchAll();
$mcount = count(array_unique(array_column($all, 'major_name')));
$mx     = $all ? max(array_column($all, 'score')) : 0;
?>

<div class="container py-4">
  <nav class="mb-3"><ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="<?= url('index.php') ?>">Trang chủ</a></li>
    <li class="breadcrumb-item"><a href="<?= url('search.php') ?>">Tra cứu</a></li>
    <li class="breadcrumb-item active"><?= e($uni['university_name']) ?></li>
  </ol></nav>

  <!-- Header trường -->
  <div class="card mb-4 p-4">
    <div class="d-flex gap-4 flex-wrap align-items-start">
      <div class="uni-logo flex-shrink-0" style="width:80px;height:80px;font-size:20px">
        <?php if($uni['logo']): ?>
          <img src="<?= url('uploads/'.$uni['logo']) ?>" alt="">
        <?php else: ?>
          <?= e(substr($uni['university_code']??'?',0,4)) ?>
        <?php endif; ?>
      </div>
      <div class="flex-grow-1">
        <h2 class="fw-bold mb-2"><?= e($uni['university_name']) ?></h2>
        <div class="d-flex flex-wrap gap-3 text-muted small mb-2">
          <?php if($uni['province']): ?><span><i class="bi bi-geo-alt me-1"></i><?= e($uni['province']) ?></span><?php endif; ?>
          <?php if($uni['address']): ?><span><i class="bi bi-signpost me-1"></i><?= e($uni['address']) ?></span><?php endif; ?>
          <?php if($uni['website']): ?><a href="<?= e($uni['website']) ?>" target="_blank" class="text-decoration-none"><i class="bi bi-globe me-1"></i><?= e($uni['website']) ?></a><?php endif; ?>
          <span><i class="bi bi-building me-1"></i><?= e($uni['school_type']??'') ?></span>
        </div>
        <?php if($uni['description']): ?><p class="text-muted small mb-0"><?= e($uni['description']) ?></p><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-md-8">
      <div class="card">
        <!-- Header bảng + bộ lọc năm -->
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
          <span><i class="bi bi-list-ul me-1"></i>
            Điểm chuẩn
            <?php if($filterYear): ?>
              năm <strong class="text-primary"><?= $filterYear ?></strong>
            <?php else: ?>
              tất cả các năm
            <?php endif; ?>
            <span class="text-muted small ms-1">(<?= count($all) ?> bản ghi)</span>
          </span>

          <!-- Bộ lọc phương thức -->
          <div class="d-flex gap-1 flex-wrap mb-1">
            <?php foreach([''=> 'Tất cả PT','THPT'=>'Thi THPT','HocBa'=>'Học bạ','TongHop'=>'Tổng hợp','DGNL'=>'Đánh giá NL'] as $v=>$l): ?>
            <a href="?id=<?=$id?>&year=<?=$filterYear?>&method=<?=$v?>"
               class="btn btn-xs <?=$filterMethod===$v?'btn-primary':'btn-outline-secondary'?>"
               style="font-size:11px;padding:2px 10px;border-radius:20px"><?=$l?></a>
            <?php endforeach; ?>
          </div>
          <!-- Bộ lọc năm -->
          <div class="d-flex gap-1 flex-wrap">
            <a href="?id=<?= $id ?>"
               class="btn btn-sm <?= !$filterYear ? 'btn-primary' : 'btn-outline-secondary' ?>"
               style="border-radius:20px;font-size:12px;padding:3px 12px">
              Tất cả
            </a>
            <?php foreach($years as $y): ?>
            <a href="?id=<?= $id ?>&year=<?= $y ?>"
               class="btn btn-sm <?= $filterYear==$y ? 'btn-primary' : 'btn-outline-secondary' ?>"
               style="border-radius:20px;font-size:12px;padding:3px 12px">
              <?= $y ?>
            </a>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Bảng điểm -->
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle small">
            <thead>
              <tr><th>Ngành</th><th>Năm</th><th>Tổ hợp</th><th>Phương thức</th><th>Điểm chuẩn</th><th>Chỉ tiêu</th></tr>
            </thead>
            <tbody>
              <?php if(empty($all)): ?>
              <tr><td colspan="5" class="text-center text-muted py-4">Không có dữ liệu năm <?= $filterYear ?></td></tr>
              <?php else: ?>
              <?php foreach($all as $r): $c=$r['score']>=27?'sb-hi':($r['score']>=23?'sb-mid':'sb-lo'); ?>
              <tr>
                <td><a href="<?= url('major.php?id='.$r['major_id']) ?>" class="text-decoration-none fw-semibold"><?= e($r['major_name']) ?></a></td>
                <td><span class="chip"><?= $r['year'] ?></span></td>
                <td><span class="chip"><?= e($r['combination']) ?></span></td>
                <td><span class="score-badge <?= $c ?>"><?= number_format($r['score'],2) ?></span></td>
                <td class="text-muted"><?= number_format($r['quota']) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card">
        <div class="card-header"><i class="bi bi-info-circle me-1"></i>Thông tin tuyển sinh</div>
        <div class="card-body">
          <table class="table table-sm small mb-3">
            <tr><td class="text-muted">Mã trường</td><td class="fw-semibold"><?= e($uni['university_code']??'—') ?></td></tr>
            <tr><td class="text-muted">Loại hình</td><td><?= e($uni['school_type']??'—') ?></td></tr>
            <tr><td class="text-muted">Tỉnh/Thành</td><td><?= e($uni['province']??'—') ?></td></tr>
            <tr><td class="text-muted">Điểm cao nhất</td><td class="fw-bold text-primary"><?= $mx ?></td></tr>
            <tr><td class="text-muted">Số ngành</td><td><?= $mcount ?></td></tr>
          </table>
          <a href="<?= url('compare.php?uni1='.$id) ?>" class="btn btn-outline-primary btn-sm w-100">
            <i class="bi bi-bar-chart me-1"></i>So sánh trường này
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>