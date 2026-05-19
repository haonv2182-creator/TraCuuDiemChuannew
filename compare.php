<?php
$pageTitle='So sánh điểm chuẩn – DiemChuan.vn';
require_once 'includes/header.php';
$db=getDB();
$u1=(int)($_GET['uni1']??0); $u2=(int)($_GET['uni2']??0);
$allUnis=$db->query("SELECT university_id,university_name,province FROM universities ORDER BY university_name")->fetchAll();
$dataA=$dataB=[]; $nameA=$nameB=null;
if($u1&&$u2){
    $s=$db->prepare("SELECT year,ROUND(AVG(score),2) AS avg,MAX(score) AS mx FROM admission_scores WHERE university_id=? GROUP BY year ORDER BY year");
    $s->execute([$u1]); $dataA=$s->fetchAll();
    $s->execute([$u2]); $dataB=$s->fetchAll();
    $n=$db->prepare("SELECT university_name,province,school_type FROM universities WHERE university_id=?");
    $n->execute([$u1]); $nameA=$n->fetch();
    $n->execute([$u2]); $nameB=$n->fetch();
}
$years=array_unique(array_merge(array_column($dataA,'year'),array_column($dataB,'year'))); sort($years);
?>
<div class="container py-4">
  <h2 class="fw-bold mb-1"><i class="bi bi-bar-chart-line me-2 text-primary"></i>So sánh điểm chuẩn</h2>
  <p class="text-muted mb-4">Chọn 2 trường để so sánh điểm chuẩn trực quan</p>

  <div class="card mb-4 p-4">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-5">
        <label class="form-label fw-semibold small">Trường A</label>
        <select name="uni1" class="form-select" required>
          <option value="">-- Chọn trường A --</option>
          <?php foreach($allUnis as $u): ?><option value="<?=$u['university_id']?>" <?=$u1==$u['university_id']?'selected':''?>><?=e($u['university_name'])?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1 text-center pb-2 fw-bold fs-5 text-muted">VS</div>
      <div class="col-md-5">
        <label class="form-label fw-semibold small">Trường B</label>
        <select name="uni2" class="form-select" required>
          <option value="">-- Chọn trường B --</option>
          <?php foreach($allUnis as $u): ?><option value="<?=$u['university_id']?>" <?=$u2==$u['university_id']?'selected':''?>><?=e($u['university_name'])?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1"><button type="submit" class="btn btn-primary w-100">So sánh</button></div>
    </form>
  </div>

  <?php if($u1&&$u2&&($dataA||$dataB)): ?>
  <div class="row g-4 mb-4">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header"><i class="bi bi-graph-up me-1 text-primary"></i>Điểm chuẩn TB theo năm</div>
        <div class="card-body p-3"><canvas id="cCmp" height="150"></canvas></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header"><i class="bi bi-table me-1"></i>Bảng so sánh</div>
        <div class="card-body p-0">
          <table class="table table-sm small mb-0">
            <thead><tr>
              <th>Năm</th>
              <th class="text-primary"><?=e(substr($nameA['university_name'],0,14))?>...</th>
              <th class="text-danger"><?=e(substr($nameB['university_name'],0,14))?>...</th>
            </tr></thead>
            <tbody>
              <?php foreach($years as $y):
                $a=array_values(array_filter($dataA,fn($r)=>$r['year']==$y));
                $b=array_values(array_filter($dataB,fn($r)=>$r['year']==$y));
                $sa=$a?$a[0]['avg']:'—'; $sb=$b?$b[0]['avg']:'—';
              ?>
              <tr><td><?=$y?></td>
                <td class="fw-semibold <?=is_numeric($sa)&&is_numeric($sb)&&$sa>$sb?'text-success':''?>"><?=$sa?></td>
                <td class="fw-semibold <?=is_numeric($sa)&&is_numeric($sb)&&$sb>$sa?'text-success':''?>"><?=$sb?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer small text-muted"><span class="text-success fw-bold">Xanh</span> = điểm cao hơn</div>
      </div>
    </div>
  </div>
  <div class="row g-3">
    <?php foreach([[$nameA,$dataA,'#1a56db'],[$nameB,$dataB,'#ef4444']] as [$n,$d,$c]): ?>
    <div class="col-md-6">
      <div class="card p-4">
        <h5 class="fw-bold mb-3" style="color:<?=$c?>"><?=e($n['university_name'])?></h5>
        <table class="table table-sm small">
          <tr><td class="text-muted">Tỉnh/Thành</td><td><?=e($n['province'])?></td></tr>
          <tr><td class="text-muted">Loại hình</td><td><?=e($n['school_type'])?></td></tr>
          <?php $mx=$d?max(array_column($d,'mx')):0; ?>
          <tr><td class="text-muted">Điểm cao nhất</td><td class="fw-bold" style="color:<?=$c?>"><?=$mx?></td></tr>
        </table>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php if($u1&&$u2&&$years): ?>
<script>
chartLine('cCmp',<?=json_encode($years)?>,[
  {label:<?=json_encode($nameA['university_name'])?>,
   data:<?=json_encode(array_column($dataA,'avg'))?>.map(Number),
   borderColor:'#1a56db',backgroundColor:'rgba(26,86,219,.12)',tension:.4,fill:true},
  {label:<?=json_encode($nameB['university_name'])?>,
   data:<?=json_encode(array_column($dataB,'avg'))?>.map(Number),
   borderColor:'#ef4444',backgroundColor:'rgba(239,68,68,.12)',tension:.4,fill:true},
]);
</script>
<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>
