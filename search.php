<?php
$pageTitle = 'Tra cứu điểm chuẩn – DiemChuan.vn';
require_once 'includes/header.php';
$db=$db=getDB();

$q        =trim($_GET['q']??'');  $major=trim($_GET['major']??'');
$year     =(int)($_GET['year']??0); $combo=trim($_GET['combo']??'');
$province =trim($_GET['province']??''); $stype=trim($_GET['school_type']??'');
$min      =(float)($_GET['min']??0); $max=(float)($_GET['max']??0);
$page     =max(1,(int)($_GET['page']??1)); $limit=15;

$where=['1=1']; $params=[];
if($q)       { $where[]='u.university_name LIKE :q';    $params[':q']="%$q%"; }
if($major)   { $where[]='m.major_name LIKE :major';     $params[':major']="%$major%"; }
if($year)    { $where[]='s.year=:year';                  $params[':year']=$year; }
if($combo)   { $where[]='s.combination=:combo';          $params[':combo']=$combo; }
if($province){ $where[]='u.province LIKE :prov';         $params[':prov']="%$province%"; }
if($stype)   { $where[]='u.school_type=:stype';          $params[':stype']=$stype; }
if($min>0)   { $where[]='s.score>=:min';                 $params[':min']=$min; }
if($max>0)   { $where[]='s.score<=:max';                 $params[':max']=$max; }
$sql="FROM admission_scores s JOIN universities u ON s.university_id=u.university_id JOIN majors m ON s.major_id=m.major_id WHERE ".implode(' AND ',$where);

$cnt=$db->prepare("SELECT COUNT(*) $sql"); $cnt->execute($params); $total=(int)$cnt->fetchColumn();
$pg=paginate($total,$limit,$page);

$stmt=$db->prepare("SELECT s.*,u.university_id,u.university_name,u.province,u.school_type,m.major_id,m.major_name $sql ORDER BY s.year DESC,s.score DESC LIMIT :lim OFFSET :off");
foreach($params as $k=>$v) $stmt->bindValue($k,$v);
$stmt->bindValue(':lim',$pg['per_page'],PDO::PARAM_INT);
$stmt->bindValue(':off',$pg['offset'],PDO::PARAM_INT);
$stmt->execute(); $rows=$stmt->fetchAll();

$years   =$db->query("SELECT DISTINCT year FROM admission_scores ORDER BY year DESC")->fetchAll(PDO::FETCH_COLUMN);
$combos  =$db->query("SELECT DISTINCT combination FROM admission_scores ORDER BY combination")->fetchAll(PDO::FETCH_COLUMN);
$provinces=getProvinces(); $types=['Công lập','Dân lập','Tư thục','Quốc tế'];
?>
<div class="container py-4">
  <h2 class="fw-bold mb-1"><i class="bi bi-search me-2 text-primary"></i>Tra cứu điểm chuẩn</h2>
  <p class="text-muted mb-4">Lọc theo trường, ngành, năm, tổ hợp, tỉnh thành và khoảng điểm</p>

  <div class="filter-card mb-4">
    <form method="GET">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-4">
          <label class="form-label fw-semibold" style="font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:var(--gray-600)">Tên trường</label>
          <input type="text" name="q" value="<?= e($q) ?>" placeholder="VD: Bách Khoa..." class="form-control form-control-sm">
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label fw-semibold" style="font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:var(--gray-600)">Ngành học</label>
          <input type="text" name="major" value="<?= e($major) ?>" placeholder="VD: Công nghệ thông tin..." class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label fw-semibold" style="font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:var(--gray-600)">Năm</label>
          <select name="year" class="form-select form-select-sm">
            <option value="">Tất cả</option>
            <?php foreach($years as $y): ?><option value="<?=$y?>" <?=$year==$y?'selected':''?>><?=$y?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label fw-semibold" style="font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:var(--gray-600)">Tổ hợp</label>
          <select name="combo" class="form-select form-select-sm">
            <option value="">Tất cả</option>
            <?php foreach($combos as $c): ?><option value="<?=$c?>" <?=$combo===$c?'selected':''?>><?=$c?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label fw-semibold" style="font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:var(--gray-600)">Tỉnh/Thành</label>
          <select name="province" class="form-select form-select-sm">
            <option value="">Tất cả</option>
            <?php foreach($provinces as $p): ?><option value="<?=e($p)?>" <?=$province===$p?'selected':''?>><?=e($p)?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label fw-semibold" style="font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:var(--gray-600)">Loại trường</label>
          <select name="school_type" class="form-select form-select-sm">
            <option value="">Tất cả</option>
            <?php foreach($types as $t): ?><option value="<?=$t?>" <?=$stype===$t?'selected':''?>><?=$t?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label fw-semibold" style="font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:var(--gray-600)">Điểm từ</label>
          <input type="number" name="min" value="<?=$min>0?$min:''?>" placeholder="0" class="form-control form-control-sm" min="0" max="30" step=".25">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label fw-semibold" style="font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:var(--gray-600)">Điểm đến</label>
          <input type="number" name="max" value="<?=$max>0?$max:''?>" placeholder="30" class="form-control form-control-sm" min="0" max="30" step=".25">
        </div>
        <div class="col-12 d-flex gap-2 align-items-center">
          <button type="submit" class="btn btn-primary btn-sm px-4"><i class="bi bi-funnel me-1"></i>Lọc kết quả</button>
          <a href="<?= url('search.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x me-1"></i>Đặt lại</a>
          <span class="ms-auto text-muted small">Tìm thấy <strong class="text-primary"><?= number_format($total) ?></strong> kết quả</span>
        </div>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-table me-1"></i>Kết quả tra cứu</span>
      <?php if($pg['total_pages']>1): ?>
      <small class="text-muted">Trang <?=$pg['current']?>/<?=$pg['total_pages']?></small>
      <?php endif; ?>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead><tr>
          <th>#</th><th>Trường đại học</th><th>Ngành học</th>
          <th>Tỉnh/TP</th><th>Loại</th><th>Năm</th><th>Tổ hợp</th>
          <th>Điểm chuẩn</th><th>Chỉ tiêu</th><th></th>
        </tr></thead>
        <tbody>
        <?php if(empty($rows)): ?>
          <tr><td colspan="10" class="text-center py-5 text-muted">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            Không tìm thấy kết quả phù hợp.
            <a href="<?= url('search.php') ?>" class="d-block mt-2 btn btn-sm btn-outline-primary">Xóa bộ lọc</a>
          </td></tr>
        <?php else: ?>
        <?php foreach($rows as $i=>$r):
          $cls=$r['score']>=27?'sb-hi':($r['score']>=23?'sb-mid':'sb-lo');
        ?>
          <tr>
            <td class="text-muted small"><?=$pg['offset']+$i+1?></td>
            <td><a href="<?= url('university.php?id='.$r['university_id']) ?>" class="fw-semibold text-decoration-none small"><?=e($r['university_name'])?></a></td>
            <td><a href="<?= url('major.php?id='.$r['major_id']) ?>" class="text-decoration-none text-reset small"><?=e($r['major_name'])?></a></td>
            <td class="text-muted small"><?=e($r['province'])?></td>
            <td><span class="chip"><?=e($r['school_type'])?></span></td>
            <td><span class="chip"><?=$r['year']?></span></td>
            <td><span class="chip"><?=e($r['combination'])?></span></td>
            <td><span class="score-badge <?=$cls?>"><?=number_format($r['score'],2)?></span></td>
            <td class="small text-muted"><?=number_format($r['quota'])?></td>
            <td><a href="<?= url('university.php?id='.$r['university_id']) ?>" class="btn btn-sm btn-outline-primary py-0 px-2">Chi tiết</a></td>
          </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if($pg['total_pages']>1): ?>
    <div class="card-footer d-flex justify-content-center py-3">
      <nav><ul class="pagination pagination-sm mb-0">
        <li class="page-item <?=$pg['current']<=1?'disabled':''?>">
          <a class="page-link" href="?<?=http_build_query(array_merge($_GET,['page'=>$pg['current']-1]))?>">‹</a>
        </li>
        <?php for($p=max(1,$pg['current']-2);$p<=min($pg['total_pages'],$pg['current']+2);$p++): ?>
        <li class="page-item <?=$p==$pg['current']?'active':''?>">
          <a class="page-link" href="?<?=http_build_query(array_merge($_GET,['page'=>$p]))?>"><?=$p?></a>
        </li>
        <?php endfor; ?>
        <li class="page-item <?=$pg['current']>=$pg['total_pages']?'disabled':''?>">
          <a class="page-link" href="?<?=http_build_query(array_merge($_GET,['page'=>$pg['current']+1]))?>">›</a>
        </li>
      </ul></nav>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require_once 'includes/footer.php'; ?>
