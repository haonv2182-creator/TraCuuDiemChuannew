<?php
require_once 'includes/header.php';
$id=(int)($_GET['id']??0);
if(!$id) redirect('search.php');
$db=getDB();
$uni=$db->prepare("SELECT * FROM universities WHERE university_id=?");
$uni->execute([$id]); $uni=$uni->fetch();
if(!$uni){http_response_code(404);echo '<h2 class="p-5">Không tìm thấy trường</h2>'; require_once 'includes/footer.php'; exit;}
$pageTitle=e($uni['university_name']).' – DiemChuan.vn';

$scores=$db->prepare("SELECT m.major_id,m.major_name,s.year,s.combination,s.score,s.quota FROM admission_scores s JOIN majors m ON s.major_id=m.major_id WHERE s.university_id=? ORDER BY s.year DESC,s.score DESC");
$scores->execute([$id]); $all=$scores->fetchAll();

$cd=$db->prepare("SELECT year,ROUND(AVG(score),2) AS avg,MAX(score) AS mx FROM admission_scores WHERE university_id=? GROUP BY year ORDER BY year");
$cd->execute([$id]); $cd=$cd->fetchAll();
?>
<div class="container py-4">
  <nav class="mb-3"><ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="<?=url('index.php')?>">Trang chủ</a></li>
    <li class="breadcrumb-item"><a href="<?=url('search.php')?>">Tra cứu</a></li>
    <li class="breadcrumb-item active"><?=e($uni['university_name'])?></li>
  </ol></nav>

  <!-- Header trường -->
  <div class="card mb-4 p-4">
    <div class="d-flex gap-4 flex-wrap align-items-start">
      <div class="uni-logo flex-shrink-0" style="width:80px;height:80px;font-size:20px">
        <?php if($uni['logo']): ?>
          <img src="<?=url('uploads/'.$uni['logo'])?>" alt="">
        <?php else: ?>
          <?=e(substr($uni['university_code']??'?',0,4))?>
        <?php endif; ?>
      </div>
      <div class="flex-grow-1">
        <h2 class="fw-bold mb-2"><?=e($uni['university_name'])?></h2>
        <div class="d-flex flex-wrap gap-3 text-muted small mb-2">
          <?php if($uni['province']): ?><span><i class="bi bi-geo-alt me-1"></i><?=e($uni['province'])?></span><?php endif; ?>
          <?php if($uni['address']): ?><span><i class="bi bi-signpost me-1"></i><?=e($uni['address'])?></span><?php endif; ?>
          <?php if($uni['website']): ?><a href="<?=e($uni['website'])?>" target="_blank" class="text-decoration-none"><i class="bi bi-globe me-1"></i><?=e($uni['website'])?></a><?php endif; ?>
          <span><i class="bi bi-building me-1"></i><?=e($uni['school_type']??'')?></span>
        </div>
        <?php if($uni['description']): ?><p class="text-muted small mb-0"><?=e($uni['description'])?></p><?php endif; ?>
      </div>
      <?php $mcount=count(array_unique(array_column($all,'major_name'))); $mx=$cd?max(array_column($cd,'mx')):0; ?>
      <div class="d-flex gap-3 flex-shrink-0 flex-wrap">
        <div class="text-center bg-light rounded-3 px-3 py-2">
          <div class="fw-bold text-primary fs-5"><?=$mcount?></div><div class="small text-muted">Ngành</div>
        </div>
        <div class="text-center bg-light rounded-3 px-3 py-2">
          <div class="fw-bold text-primary fs-5"><?=$mx?></div><div class="small text-muted">Điểm cao nhất</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-md-8">
      <div class="card mb-4">
        <div class="card-header"><i class="bi bi-graph-up me-1 text-primary"></i>Biểu đồ điểm chuẩn theo năm</div>
        <div class="card-body p-3"><canvas id="cDetail" height="150"></canvas></div>
      </div>
      <div class="card">
        <div class="card-header"><i class="bi bi-list-ul me-1"></i>Tất cả ngành tuyển sinh</div>
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle small"><thead>
            <tr><th>Ngành</th><th>Năm</th><th>Tổ hợp</th><th>Điểm chuẩn</th><th>Chỉ tiêu</th></tr>
          </thead><tbody>
            <?php foreach($all as $r): $c=$r['score']>=27?'sb-hi':($r['score']>=23?'sb-mid':'sb-lo'); ?>
            <tr>
              <td><a href="<?=url('major.php?id='.$r['major_id'])?>" class="text-decoration-none fw-semibold"><?=e($r['major_name'])?></a></td>
              <td><span class="chip"><?=$r['year']?></span></td>
              <td><span class="chip"><?=e($r['combination'])?></span></td>
              <td><span class="score-badge <?=$c?>"><?=number_format($r['score'],2)?></span></td>
              <td class="text-muted"><?=number_format($r['quota'])?></td>
            </tr>
            <?php endforeach; ?>
          </tbody></table>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <div class="card-header"><i class="bi bi-info-circle me-1"></i>Thông tin tuyển sinh</div>
        <div class="card-body">
          <table class="table table-sm small mb-3">
            <tr><td class="text-muted">Mã trường</td><td class="fw-semibold"><?=e($uni['university_code']??'—')?></td></tr>
            <tr><td class="text-muted">Loại hình</td><td><?=e($uni['school_type']??'—')?></td></tr>
            <tr><td class="text-muted">Tỉnh/Thành</td><td><?=e($uni['province']??'—')?></td></tr>
            <tr><td class="text-muted">Điểm cao nhất</td><td class="fw-bold text-primary"><?=$mx?></td></tr>
            <tr><td class="text-muted">Số ngành</td><td><?=$mcount?></td></tr>
          </table>
          <a href="<?=url('compare.php?uni1='.$id)?>" class="btn btn-outline-primary btn-sm w-100">
            <i class="bi bi-bar-chart me-1"></i>So sánh trường này
          </a>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
chartLine('cDetail',<?=json_encode(array_column($cd,'year'))?>,
  [{label:'Điểm TB',data:<?=json_encode(array_column($cd,'avg'))?>.map(Number),borderColor:'#1a56db',backgroundColor:'rgba(26,86,219,.1)',tension:.4,fill:true,pointRadius:5},
   {label:'Điểm cao nhất',data:<?=json_encode(array_column($cd,'mx'))?>.map(Number),borderColor:'#10b981',backgroundColor:'rgba(16,185,129,.07)',tension:.4,pointRadius:5}]);
</script>
<?php require_once 'includes/footer.php'; ?>
