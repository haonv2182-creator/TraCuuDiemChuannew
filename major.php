<?php
// major.php
require_once 'includes/header.php';
$id=(int)($_GET['id']??0);
if(!$id) redirect('search.php');
$db=getDB();
$maj=$db->prepare("SELECT * FROM majors WHERE major_id=?");
$maj->execute([$id]); $maj=$maj->fetch();
if(!$maj){http_response_code(404);echo '<h2 class="p-5">Không tìm thấy ngành</h2>'; require_once 'includes/footer.php'; exit;}
$pageTitle=e($maj['major_name']).' – DiemChuan.vn';
$latestYear=(int)$db->query("SELECT MAX(year) FROM admission_scores WHERE major_id=$id")->fetchColumn();

$unis=$db->prepare("SELECT u.university_id,u.university_name,u.university_code,u.province,u.school_type,s.combination,s.score,s.quota FROM admission_scores s JOIN universities u ON s.university_id=u.university_id WHERE s.major_id=? AND s.year=? ORDER BY s.score DESC");
$unis->execute([$id,$latestYear]); $unis=$unis->fetchAll();

$trend=$db->prepare("SELECT u.university_name,s.year,s.score FROM admission_scores s JOIN universities u ON s.university_id=u.university_id WHERE s.major_id=? AND s.university_id IN (SELECT university_id FROM admission_scores WHERE major_id=? ORDER BY score DESC LIMIT 3) ORDER BY s.year");
$trend->execute([$id,$id]); $trend=$trend->fetchAll();
$tY=array_unique(array_column($trend,'year')); sort($tY);
$tS=array_unique(array_column($trend,'university_name'));
?>
<div class="container py-4">
  <nav class="mb-3"><ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="<?=url('index.php')?>">Trang chủ</a></li>
    <li class="breadcrumb-item"><a href="<?=url('search.php')?>">Tra cứu</a></li>
    <li class="breadcrumb-item active"><?=e($maj['major_name'])?></li>
  </ol></nav>

  <div class="card mb-4 p-4">
    <div class="d-flex gap-3 align-items-center">
      <div class="uni-logo" style="width:60px;height:60px;font-size:26px;flex-shrink:0">📚</div>
      <div>
        <h2 class="fw-bold mb-1"><?=e($maj['major_name'])?></h2>
        <?php if($maj['major_code']): ?><span class="chip me-2">Mã: <?=e($maj['major_code'])?></span><?php endif; ?>
        <span class="chip"><?=count($unis)?> trường đào tạo</span>
        <?php if($unis): ?><span class="score-badge sb-hi ms-2">Cao nhất: <?=number_format($unis[0]['score'],2)?></span><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-md-8">
      <div class="card mb-4">
        <div class="card-header"><i class="bi bi-graph-up me-1 text-primary"></i>Xu hướng điểm chuẩn (Top 3 trường)</div>
        <div class="card-body p-3"><canvas id="cMaj" height="160"></canvas></div>
      </div>
      <div class="card">
        <div class="card-header"><i class="bi bi-building me-1"></i>Các trường đào tạo năm <?=$latestYear?></div>
        <div class="table-responsive"><table class="table table-hover mb-0 align-middle small"><thead>
          <tr><th>#</th><th>Trường</th><th>Tỉnh/TP</th><th>Loại</th><th>Tổ hợp</th><th>Điểm</th><th>Chỉ tiêu</th><th></th></tr>
        </thead><tbody>
          <?php foreach($unis as $i=>$u): $c=$u['score']>=27?'sb-hi':($u['score']>=23?'sb-mid':'sb-lo'); ?>
          <tr>
            <td class="text-muted"><?=$i+1?></td>
            <td><a href="<?=url('university.php?id='.$u['university_id'])?>" class="fw-semibold text-decoration-none"><?=e($u['university_name'])?></a></td>
            <td class="text-muted"><?=e($u['province'])?></td>
            <td><span class="chip"><?=e($u['school_type'])?></span></td>
            <td><span class="chip"><?=e($u['combination'])?></span></td>
            <td><span class="score-badge <?=$c?>"><?=number_format($u['score'],2)?></span></td>
            <td class="text-muted"><?=number_format($u['quota'])?></td>
            <td><a href="<?=url('university.php?id='.$u['university_id'])?>" class="btn btn-sm btn-outline-primary py-0 px-2">Chi tiết</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody></table></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <div class="card-header"><i class="bi bi-info-circle me-1"></i>Thông tin ngành</div>
        <div class="card-body">
          <table class="table table-sm small mb-3">
            <tr><td class="text-muted">Mã ngành</td><td class="fw-semibold"><?=e($maj['major_code']??'—')?></td></tr>
            <tr><td class="text-muted">Số trường</td><td><?=count($unis)?></td></tr>
            <?php if($unis): ?>
            <tr><td class="text-muted">Điểm cao nhất</td><td class="fw-bold text-primary"><?=number_format($unis[0]['score'],2)?></td></tr>
            <tr><td class="text-muted">Điểm thấp nhất</td><td><?=number_format(end($unis)['score'],2)?></td></tr>
            <?php endif; ?>
          </table>
          <a href="<?=url('ai_recommend.php')?>" class="btn btn-primary btn-sm w-100 mb-2"><i class="bi bi-robot me-1"></i>AI gợi ý</a>
          <a href="<?=url('compare.php')?>" class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-bar-chart me-1"></i>So sánh trường</a>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
const tRaw=<?=json_encode($trend,JSON_UNESCAPED_UNICODE)?>;
const tY=<?=json_encode(array_values($tY))?>;
const tS=<?=json_encode(array_values($tS))?>;
const colors=['#1a56db','#10b981','#f59e0b'];
chartLine('cMaj',tY,tS.map((name,i)=>({
  label:name,borderColor:colors[i],backgroundColor:colors[i]+'22',tension:.4,fill:true,pointRadius:5,spanGaps:true,
  data:tY.map(y=>{const r=tRaw.find(r=>r.university_name===name&&r.year==y);return r?+r.score:null;})
})));
</script>
<?php require_once 'includes/footer.php'; ?>
