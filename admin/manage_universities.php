<?php
require_once '../includes/functions.php';
requireAdmin();

$pageTitle = 'Quản lý trường - Admin';
require_once '../includes/header.php';

$db  = getDB();
$act = $_GET['action'] ?? 'list';
$id  = (int)($_GET['id'] ?? 0);
// Xóa
if ($act === 'delete' && $id) {
    $db->prepare("DELETE FROM universities WHERE university_id=?")->execute([$id]);
    setFlash('success', 'Đã xóa trường thành công!');
    redirect('admin/manage_universities.php');
}

// Lưu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['university_name'] ?? '');
    $code  = trim($_POST['university_code'] ?? '');
    $prov  = trim($_POST['province']        ?? '');
    $addr  = trim($_POST['address']         ?? '');
    $web   = trim($_POST['website']         ?? '');
    $desc  = trim($_POST['description']     ?? '');
    $stype = trim($_POST['school_type']     ?? 'Công lập');
    $feat  = isset($_POST['is_featured']) ? 1 : 0;
    $eid   = (int)($_POST['edit_id'] ?? 0);
    $logo  = null;
    if (!empty($_FILES['logo']['name'])) {
        $logo = uploadLogo($_FILES['logo']);
        if (!$logo) setFlash('danger', 'File logo không hợp lệ!');
    }
    if ($name) {
        if ($eid) {
            $sql = "UPDATE universities SET university_name=?,university_code=?,province=?,address=?,website=?,description=?,school_type=?,is_featured=?" . ($logo?",logo=?":"") . " WHERE university_id=?";
            $p   = $logo ? [$name,$code,$prov,$addr,$web,$desc,$stype,$feat,$logo,$eid] : [$name,$code,$prov,$addr,$web,$desc,$stype,$feat,$eid];
        } else {
            $sql = "INSERT INTO universities (university_name,university_code,province,address,website,description,school_type,is_featured,logo) VALUES (?,?,?,?,?,?,?,?,?)";
            $p   = [$name,$code,$prov,$addr,$web,$desc,$stype,$feat,$logo];
        }
        $db->prepare($sql)->execute($p);
        setFlash('success', $eid ? 'Cập nhật trường thành công!' : 'Thêm trường mới thành công!');
        redirect('admin/manage_universities.php');
    }
}

$editRow = null;
if ($act === 'edit' && $id) {
    $s = $db->prepare("SELECT * FROM universities WHERE university_id=?");
    $s->execute([$id]); $editRow = $s->fetch();
}

$list = $db->query("SELECT u.*, COUNT(DISTINCT s.major_id) AS mcnt FROM universities u LEFT JOIN admission_scores s ON u.university_id=s.university_id GROUP BY u.university_id ORDER BY u.university_name")->fetchAll();
$types = ['Công lập','Dân lập','Tư thục','Quốc tế'];
$provinces = getProvinces();
?>
<div class="admin-wrapper">
  <?php require_once '../includes/sidebar.php'; ?>
  <div class="admin-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="fw-bold mb-0"><i class="bi bi-building me-2"></i>Quản lý trường đại học</h2>
      <button class="btn btn-primary btn-sm" onclick="document.getElementById('fCard').classList.toggle('d-none')">
        <i class="bi bi-plus-lg me-1"></i>Thêm trường mới
      </button>
    </div>

    <!-- Form -->
    <div id="fCard" class="card mb-4 <?= $editRow ? '' : 'd-none' ?>">
      <div class="card-header"><?= $editRow ? '✏️ Sửa thông tin trường' : '➕ Thêm trường mới' ?></div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
          <?php if ($editRow): ?><input type="hidden" name="edit_id" value="<?= $editRow['university_id'] ?>"><?php endif; ?>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold small">Tên trường <span class="text-danger">*</span></label>
              <input type="text" name="university_name" class="form-control" required value="<?= e($editRow['university_name'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold small">Mã trường</label>
              <input type="text" name="university_code" class="form-control" placeholder="VD: BKU" value="<?= e($editRow['university_code'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold small">Loại trường</label>
              <select name="school_type" class="form-select">
                <?php foreach ($types as $t): ?>
                <option value="<?= $t ?>" <?= ($editRow['school_type'] ?? 'Công lập') === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold small">Tỉnh/Thành phố</label>
              <select name="province" class="form-select">
                <?php foreach ($provinces as $p): ?>
                <option value="<?= e($p) ?>" <?= ($editRow['province'] ?? '') === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold small">Địa chỉ</label>
              <input type="text" name="address" class="form-control" value="<?= e($editRow['address'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold small">Website</label>
              <input type="url" name="website" class="form-control" placeholder="https://..." value="<?= e($editRow['website'] ?? '') ?>">
            </div>
            <div class="col-md-9">
              <label class="form-label fw-semibold small">Mô tả</label>
              <input type="text" name="description" class="form-control" value="<?= e($editRow['description'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold small">Logo</label>
              <input type="file" name="logo" class="form-control" accept="image/*" onchange="previewLogo(this,'logoPreview')">
              <?php if (!empty($editRow['logo'])): ?>
              <img id="logoPreview" src="<?= url('uploads/'.$editRow['logo']) ?>" class="mt-2 rounded" style="height:44px">
              <?php else: ?>
              <img id="logoPreview" class="mt-2 rounded d-none" style="height:44px">
              <?php endif; ?>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input type="checkbox" name="is_featured" id="isFeat" class="form-check-input"
                       <?= ($editRow['is_featured'] ?? 0) ? 'checked' : '' ?>>
                <label for="isFeat" class="form-check-label small">Trường nổi bật (hiển thị ở trang chủ)</label>
              </div>
            </div>
          </div>
          <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Lưu</button>
            <a href="<?= url('admin/manage_universities.php') ?>" class="btn btn-outline-secondary btn-sm">Hủy</a>
          </div>
        </form>
      </div>
    </div>

    <!-- Table -->
    <div class="card">
      <div class="card-header"><i class="bi bi-table me-1"></i>Danh sách — <strong class="text-primary"><?= count($list) ?></strong> trường</div>
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle small">
          <thead><tr><th>#</th><th>Logo</th><th>Tên trường</th><th>Mã</th><th>Tỉnh/TP</th><th>Loại</th><th>Ngành</th><th>Nổi bật</th><th>Thao tác</th></tr></thead>
          <tbody>
          <?php foreach ($list as $i => $u): ?>
          <tr>
            <td class="text-muted"><?= $i+1 ?></td>
            <td>
              <?php if ($u['logo']): ?>
              <img src="<?= url('uploads/'.$u['logo']) ?>" class="rounded" style="width:34px;height:34px;object-fit:cover">
              <?php else: ?>
              <div class="uni-logo" style="width:34px;height:34px;font-size:11px"><?= e(substr($u['university_code']??'?',0,4)) ?></div>
              <?php endif; ?>
            </td>
            <td><strong><?= e($u['university_name']) ?></strong></td>
            <td><span class="chip"><?= e($u['university_code'] ?? '—') ?></span></td>
            <td class="text-muted"><?= e($u['province'] ?? '') ?></td>
            <td><span class="chip"><?= e($u['school_type'] ?? '') ?></span></td>
            <td><?= $u['mcnt'] ?></td>
            <td><?= $u['is_featured'] ? '<span class="badge bg-warning text-dark">⭐ Nổi bật</span>' : '<span class="text-muted">—</span>' ?></td>
            <td class="d-flex gap-1">
              <a href="<?= url('admin/manage_universities.php?action=edit&id='.$u['university_id']) ?>" class="btn btn-sm btn-outline-primary py-0 px-2">✏️</a>
              <button class="btn btn-sm btn-outline-danger py-0 px-2"
                onclick="confirmDelete('<?= url('admin/manage_universities.php?action=delete&id='.$u['university_id']) ?>','<?= e(addslashes($u['university_name'])) ?>')">🗑️</button>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>
