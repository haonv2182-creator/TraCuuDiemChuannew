<?php
require_once '../includes/functions.php';
requireAdmin();

$pageTitle = 'Quản lý ngành - Admin';
require_once '../includes/header.php';

$db  = getDB();
$act = $_GET['action'] ?? 'list';
$id  = (int)($_GET['id'] ?? 0);

if ($act === 'delete' && $id) {
    $db->prepare("DELETE FROM majors WHERE major_id=?")->execute([$id]);
    setFlash('success', 'Đã xóa ngành!'); redirect('admin/manage_majors.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['major_name'] ?? ''); $code = trim($_POST['major_code'] ?? ''); $eid = (int)($_POST['edit_id'] ?? 0);
    if ($name) {
        if ($eid) $db->prepare("UPDATE majors SET major_name=?,major_code=? WHERE major_id=?")->execute([$name,$code,$eid]);
        else      $db->prepare("INSERT INTO majors (major_name,major_code) VALUES (?,?)")->execute([$name,$code]);
        setFlash('success', $eid ? 'Cập nhật ngành!' : 'Thêm ngành mới!'); redirect('admin/manage_majors.php');
    }
}
$editRow = null;
if ($act === 'edit' && $id) { $s=$db->prepare("SELECT * FROM majors WHERE major_id=?"); $s->execute([$id]); $editRow=$s->fetch(); }
$list = $db->query("SELECT m.*, COUNT(DISTINCT s.university_id) AS ucnt, COUNT(s.score_id) AS scnt FROM majors m LEFT JOIN admission_scores s ON m.major_id=s.major_id GROUP BY m.major_id ORDER BY m.major_name")->fetchAll();
?>
<div class="admin-wrapper">
  <?php require_once '../includes/sidebar.php'; ?>
  <div class="admin-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="fw-bold mb-0"><i class="bi bi-book me-2"></i>Quản lý ngành học</h2>
      <button class="btn btn-primary btn-sm" onclick="document.getElementById('fCard').classList.toggle('d-none')">
        <i class="bi bi-plus-lg me-1"></i>Thêm ngành mới
      </button>
    </div>
    <div id="fCard" class="card mb-4 <?= $editRow?'':'d-none' ?>">
      <div class="card-header"><?= $editRow?'✏️ Sửa':'➕ Thêm' ?> ngành học</div>
      <div class="card-body">
        <form method="POST">
          <?php if($editRow): ?><input type="hidden" name="edit_id" value="<?=$editRow['major_id']?>"><?php endif; ?>
          <div class="row g-3">
            <div class="col-md-7"><label class="form-label fw-semibold small">Tên ngành <span class="text-danger">*</span></label>
              <input type="text" name="major_name" class="form-control" required value="<?=e($editRow['major_name']??'')?>"></div>
            <div class="col-md-3"><label class="form-label fw-semibold small">Mã ngành</label>
              <input type="text" name="major_code" class="form-control" placeholder="7480201" value="<?=e($editRow['major_code']??'')?>"></div>
          </div>
          <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Lưu</button>
            <a href="<?=url('admin/manage_majors.php')?>" class="btn btn-outline-secondary btn-sm">Hủy</a>
          </div>
        </form>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><i class="bi bi-table me-1"></i>Danh sách — <strong class="text-primary"><?=count($list)?></strong> ngành</div>
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle small"><thead>
          <tr><th>#</th><th>Tên ngành</th><th>Mã ngành</th><th>Số trường</th><th>Số bản ghi</th><th>Thao tác</th></tr>
        </thead><tbody>
          <?php foreach($list as $i=>$m): ?>
          <tr>
            <td class="text-muted"><?=$i+1?></td>
            <td class="fw-semibold"><?=e($m['major_name'])?></td>
            <td><span class="chip"><?=e($m['major_code']??'—')?></span></td>
            <td><?=$m['ucnt']?></td><td><?=$m['scnt']?></td>
            <td class="d-flex gap-1">
              <a href="<?=url('admin/manage_majors.php?action=edit&id='.$m['major_id'])?>" class="btn btn-sm btn-outline-primary py-0 px-2">✏️</a>
              <button class="btn btn-sm btn-outline-danger py-0 px-2"
                onclick="confirmDelete('<?=url('admin/manage_majors.php?action=delete&id='.$m['major_id'])?>','<?=e(addslashes($m['major_name']))?>')">🗑️</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody></table>
      </div>
    </div>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>
