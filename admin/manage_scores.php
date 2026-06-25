<?php
$pageTitle = 'Quản lý điểm chuẩn – Admin';
require_once '../includes/header.php';
requireAdmin();
$db = getDB(); $act = $_GET['action'] ?? 'list'; $id = (int)($_GET['id'] ?? 0);

if ($act === 'delete' && $id) {
    $db->prepare("DELETE FROM admission_scores WHERE score_id=?")->execute([$id]);
    setFlash('success','Đã xóa bản ghi!'); redirect('admin/manage_scores.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid=(int)$_POST['university_id']; $mid=(int)$_POST['major_id'];
    $yr=(int)$_POST['year']; $cb=trim($_POST['combination']);
    $sc=(float)$_POST['score']; $qt=(int)($_POST['quota']??0);
    $eid=(int)($_POST['edit_id']??0);
    if ($uid&&$mid&&$yr&&$cb&&$sc) {
        if ($eid) $db->prepare("UPDATE admission_scores SET university_id=?,major_id=?,year=?,combination=?,score=?,quota=? WHERE score_id=?")->execute([$uid,$mid,$yr,$cb,$sc,$qt,$eid]);
        else      $db->prepare("INSERT INTO admission_scores (university_id,major_id,year,combination,score,quota) VALUES (?,?,?,?,?,?)")->execute([$uid,$mid,$yr,$cb,$sc,$qt]);
        setFlash('success',$eid?'Cập nhật điểm!':'Thêm điểm chuẩn!'); redirect('admin/manage_scores.php');
    }
}
$editRow=null;
if ($act==='edit'&&$id){ $s=$db->prepare("SELECT * FROM admission_scores WHERE score_id=?"); $s->execute([$id]); $editRow=$s->fetch(); }

// Filter
$fU=(int)($_GET['uni']??0); $fY=(int)($_GET['year']??0);
$wh=['1=1']; $pr=[];
if($fU){$wh[]='s.university_id=:u';$pr[':u']=$fU;}
if($fY){$wh[]='s.year=:y';$pr[':y']=$fY;}
$ws=implode(' AND ',$wh);
$pg=paginate((int)$db->prepare("SELECT COUNT(*) FROM admission_scores s WHERE $ws")->execute($pr)||(0)||(function()use($db,$ws,$pr){$s=$db->prepare("SELECT COUNT(*) FROM admission_scores s WHERE $ws");$s->execute($pr);return(int)$s->fetchColumn();})(),20,(int)($_GET['page']??1));
// simple fix:
$cntS=$db->prepare("SELECT COUNT(*) FROM admission_scores s WHERE $ws"); $cntS->execute($pr); $pg=paginate((int)$cntS->fetchColumn(),20,(int)($_GET['page']??1));
$dataS=$db->prepare("SELECT s.*,u.university_name,m.major_name FROM admission_scores s JOIN universities u ON s.university_id=u.university_id JOIN majors m ON s.major_id=m.major_id WHERE $ws ORDER BY s.year DESC,s.score DESC LIMIT :lim OFFSET :off");
foreach($pr as $k=>$v) $dataS->bindValue($k,$v);
$dataS->bindValue(':lim',$pg['per_page'],PDO::PARAM_INT); $dataS->bindValue(':off',$pg['offset'],PDO::PARAM_INT);
$dataS->execute(); $list=$dataS->fetchAll();

$unis=$db->query("SELECT university_id,university_name FROM universities ORDER BY university_name")->fetchAll();
$majs=$db->query("SELECT major_id,major_name FROM majors ORDER BY major_name")->fetchAll();
$years=range((int)date('Y'),2015); $combos=['A00','A01','A02','B00','B01','C00','D01','D07'];
?>
<div class="admin-wrapper">
  <?php require_once '../includes/sidebar.php'; ?>
  <div class="admin-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="fw-bold mb-0"><i class="bi bi-graph-up me-2"></i>Quản lý điểm chuẩn</h2>
      <button class="btn btn-primary btn-sm" onclick="document.getElementById('fCard').classList.toggle('d-none')">
        <i class="bi bi-plus-lg me-1"></i>Thêm điểm chuẩn
      </button>
    </div>
    <div id="fCard" class="card mb-4 <?=$editRow?'':'d-none'?>">
      <div class="card-header"><?=$editRow?'✏️ Sửa':'➕ Thêm'?> điểm chuẩn</div>
      <div class="card-body">
        <form method="POST">
          <?php if($editRow): ?><input type="hidden" name="edit_id" value="<?=$editRow['score_id']?>"><?php endif; ?>
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label fw-semibold small">Trường <span class="text-danger">*</span></label>
              <select name="university_id" class="form-select" required>
                <option value="">-- Chọn trường --</option>
                <?php foreach($unis as $u): ?><option value="<?=$u['university_id']?>" <?=($editRow['university_id']??0)==$u['university_id']?'selected':''?>><?=e($u['university_name'])?></option><?php endforeach; ?>
              </select></div>
            <div class="col-md-4"><label class="form-label fw-semibold small">Ngành <span class="text-danger">*</span></label>
              <select name="major_id" class="form-select" required>
                <option value="">-- Chọn ngành --</option>
                <?php foreach($majs as $m): ?><option value="<?=$m['major_id']?>" <?=($editRow['major_id']??0)==$m['major_id']?'selected':''?>><?=e($m['major_name'])?></option><?php endforeach; ?>
              </select></div>
            <div class="col-md-2"><label class="form-label fw-semibold small">Năm</label>
              <select name="year" class="form-select">
                <?php foreach($years as $y): ?><option value="<?=$y?>" <?=($editRow['year']??(int)date('Y'))==$y?'selected':''?>><?=$y?></option><?php endforeach; ?>
              </select></div>
            <div class="col-md-2"><label class="form-label fw-semibold small">Tổ hợp</label>
              <select name="combination" class="form-select">
                <?php foreach($combos as $c): ?><option value="<?=$c?>" <?=($editRow['combination']??'')===$c?'selected':''?>><?=$c?></option><?php endforeach; ?>
              </select></div>

          <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Lưu</button>
            <a href="<?=url('admin/manage_scores.php')?>" class="btn btn-outline-secondary btn-sm">Hủy</a>
          </div>
        </form>
      </div>
    </div>
    <!-- Filter -->
    <div class="filter-card mb-3">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-5"><select name="uni" class="form-select form-select-sm">
          <option value="">Tất cả trường</option>
          <?php foreach($unis as $u): ?><option value="<?=$u['university_id']?>" <?=$fU==$u['university_id']?'selected':''?>><?=e($u['university_name'])?></option><?php endforeach; ?>
        </select></div>
        <div class="col-md-3"><select name="year" class="form-select form-select-sm">
          <option value="">Tất cả năm</option>
          <?php foreach($years as $y): ?><option value="<?=$y?>" <?=$fY==$y?'selected':''?>><?=$y?></option><?php endforeach; ?>
        </select></div>
        <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Lọc</button></div>
        <div class="col-auto"><a href="<?=url('admin/manage_scores.php')?>" class="btn btn-sm btn-outline-secondary">Xóa lọc</a></div>
      </form>
    </div>
    <div class="card">
      <div class="card-header"><i class="bi bi-table me-1"></i>Tổng: <strong class="text-primary"><?=number_format($pg['total'])?></strong> bản ghi</div>
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle small"><thead>
          <tr><th>#</th><th>Trường</th><th>Ngành</th><th>Năm</th><th>Tổ hợp</th><th>Điểm</th><th>Thao tác</th></tr>
        </thead><tbody>
          <?php foreach($list as $i=>$r): $c=$r['score']>=27?'sb-hi':($r['score']>=23?'sb-mid':'sb-lo'); ?>
          <tr>
            <td><?=e($r['university_name'])?></td><td><?=e($r['major_name'])?></td>
            <td><span class="chip"><?=$r['year']?></span></td>
            <td><span class="chip"><?=e($r['combination'])?></span></td>
            <td><span class="score-badge <?=$c?>"><?=number_format($r['score'],2)?></span></td>
            <td class="text-muted"><?=number_format($r['quota'])?></td>
            <td class="d-flex gap-1">
              <a href="<?=url('admin/manage_scores.php?action=edit&id='.$r['score_id'])?>" class="btn btn-sm btn-outline-primary py-0 px-2">✏️</a>
              <button class="btn btn-sm btn-outline-danger py-0 px-2"
                onclick="confirmDelete('<?=url('admin/manage_scores.php?action=delete&id='.$r['score_id'])?>','bản ghi này')">🗑️</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody></table>
      </div>
      <?php if($pg['total_pages']>1): ?>
      <div class="card-footer d-flex justify-content-center py-2">
        <nav><ul class="pagination pagination-sm mb-0">
          <?php for($p=1;$p<=$pg['total_pages'];$p++): ?>
          <li class="page-item <?=$p==$pg['current']?'active':''?>">
            <a class="page-link" href="?<?=http_build_query(array_merge($_GET,['page'=>$p]))?>"><?=$p?></a>
          </li>
          <?php endfor; ?>
        </ul></nav>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>
