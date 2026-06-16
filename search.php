```php
<?php
$pageTitle = 'Tra cứu điểm chuẩn – DiemChuan.vn';
require_once 'includes/header.php';

$db = getDB();

// ── Filter params ─────────────────────────────────────────────
$q        = trim($_GET['q']          ?? '');
$major    = trim($_GET['major']      ?? '');
$year     = (int)($_GET['year']      ?? 0);
$combo    = trim($_GET['combo']      ?? '');
$province = trim($_GET['province']   ?? '');
$stype    = trim($_GET['school_type']?? '');
$method   = trim($_GET['method']    ?? '');
$min      = (float)($_GET['min']     ?? 0);
$max      = (float)($_GET['max']     ?? 0);
$page     = max(1,(int)($_GET['page']?? 1));
$limit    = 20;

// ── Build query ───────────────────────────────────────────────
$where = ['1=1']; 
$params = [];

if($q)       { $where[]='u.university_name LIKE :q';   $params[':q']="%$q%"; }
if($major)   { $where[]='m.major_name LIKE :major';    $params[':major']="%$major%"; }
if($year)    { $where[]='s.year=:year';                $params[':year']=$year; }
if($combo)   { $where[]='s.combination=:combo';        $params[':combo']=$combo; }
if($province){ $where[]='u.province LIKE :prov';       $params[':prov']="%$province%"; }
if($stype)   { $where[]='u.school_type=:stype';        $params[':stype']=$stype; }
if($method)  { $where[]='s.method=:method';            $params[':method']=$method; }
if($min>0)   { $where[]='s.score>=:min';               $params[':min']=$min; }
if($max>0)   { $where[]='s.score<=:max';               $params[':max']=$max; }

$wsql = "FROM admission_scores s
         JOIN universities u ON s.university_id=u.university_id
         JOIN majors m ON s.major_id=m.major_id
         WHERE ".implode(' AND ',$where);

$cnt = $db->prepare("SELECT COUNT(*) $wsql");
$cnt->execute($params);
$pg = paginate((int)$cnt->fetchColumn(), $limit, $page);

$stmt = $db->prepare("SELECT s.score_id, u.university_id, u.university_name,
                              u.university_code, u.province, u.school_type,
                              m.major_id, m.major_name,
                              s.year, s.combination, s.method, s.score, s.quota
                       $wsql ORDER BY s.year DESC, s.score DESC
                       LIMIT :lim OFFSET :off");

foreach($params as $k=>$v) {
  $stmt->bindValue($k,$v);
}

$stmt->bindValue(':lim', $pg['per_page'], PDO::PARAM_INT);
$stmt->bindValue(':off', $pg['offset'], PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

// ── Dropdown data ─────────────────────────────────────────────
$years     = $db->query("SELECT DISTINCT year FROM admission_scores ORDER BY year DESC")->fetchAll(PDO::FETCH_COLUMN);
$combos    = $db->query("SELECT DISTINCT combination FROM admission_scores ORDER BY combination")->fetchAll(PDO::FETCH_COLUMN);
$provinces = getProvinces();
$types     = ['Công lập','Dân lập','Tư thục','Quốc tế'];
$hasFilter = $q||$major||$year||$combo||$province||$stype||$method||$min||$max;
?>

<!-- Page header -->
<div style="background:#fff;border-bottom:1px solid var(--gray-200);padding:16px 0">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>
        <h4 class="fw-bold mb-0">
          <i class="bi bi-search me-2 text-primary"></i>Tra cứu điểm chuẩn
        </h4>
        <p class="text-muted small mb-0">Tìm kiếm và lọc điểm chuẩn theo nhiều tiêu chí</p>
      </div>

      <?php if($hasFilter): ?>
      <a href="<?= url('search.php') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-x-circle me-1"></i>Xóa tất cả bộ lọc
      </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="container py-4">
  <div class="row g-4">

    <!-- ══ CỘT TRÁI: BỘ LỌC CỐ ĐỊNH ══ -->
    <div class="col-md-3">
      <div style="position:sticky;top:76px">
        <form method="GET" id="filterForm">

          <!-- Kết quả count -->
          <div class="mb-3 p-3 rounded-3 text-center"
               style="background:var(--primary-lt);border:1px solid rgba(26,86,219,.2)">
            <div class="fw-bold text-primary" style="font-size:22px">
              <?= number_format($pg['total']) ?>
            </div>
            <div class="text-muted small">kết quả tìm thấy</div>
          </div>

          <!-- Tìm trường -->
          <div class="mb-3">
            <label class="form-label fw-semibold small text-uppercase text-muted" style="letter-spacing:.5px;font-size:10px">
              <i class="bi bi-building me-1"></i>Tên trường
            </label>
            <input type="text" 
                   name="q" 
                   value="<?= e($q) ?>"
                   placeholder="VD: Bách Khoa..."
                   class="form-control form-control-sm">
          </div>

          <!-- Tìm ngành -->
          <div class="mb-3">
            <label class="form-label fw-semibold small text-uppercase text-muted" style="letter-spacing:.5px;font-size:10px">
              <i class="bi bi-book me-1"></i>Ngành học
            </label>

            <?php 
            $allMajors = $db->query("SELECT major_name FROM majors ORDER BY major_name")->fetchAll(PDO::FETCH_COLUMN); 
            ?>

            <div style="position:relative" id="majorWrap">
              <!-- Ô hiển thị -->
              <div id="majorDisplay"
                   onclick="toggleMajorDrop()"
                   style="border:1px solid var(--gray-200);border-radius:8px;padding:6px 32px 6px 10px;
                          font-size:13px;cursor:pointer;background:#fff;position:relative;
                          color:<?= $major?'var(--gray-800)':'var(--gray-400)' ?>">
                <?= $major ? e($major) : 'Chọn ngành học...' ?>
                <i class="bi bi-chevron-down" 
                   style="position:absolute;right:10px;top:50%;transform:translateY(-50%);
                          font-size:11px;color:var(--gray-400)"></i>
              </div>

              <!-- Input ẩn gửi form -->
              <input type="hidden" name="major" id="majorHidden" value="<?= e($major) ?>">

              <!-- Dropdown -->
              <div id="majorDrop"
                   style="display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;
                          background:#fff;border:1px solid var(--gray-200);border-radius:10px;
                          box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:999;overflow:hidden">

                <!-- Tìm trong dropdown -->
                <div style="padding:8px">
                  <input type="text" 
                         id="majorSearch"
                         placeholder="🔍 Tìm ngành..."
                         oninput="filterMajors(this.value)"
                         style="width:100%;border:1px solid var(--gray-200);border-radius:6px;
                                padding:5px 10px;font-size:12px;outline:none">
                </div>

                <!-- Danh sách -->
                <div id="majorList" style="max-height:200px;overflow-y:auto">
                  <div onclick="selectMajor('')"
                       style="padding:7px 12px;font-size:12px;cursor:pointer;color:var(--gray-600);
                              border-bottom:1px solid var(--gray-100)"
                       onmouseover="this.style.background='var(--gray-50)'"
                       onmouseout="this.style.background=''">
                    — Tất cả ngành —
                  </div>

                  <?php foreach($allMajors as $mn): ?>
                  <div onclick="selectMajor('<?= e(addslashes($mn)) ?>')"
                       class="major-opt"
                       data-name="<?= e(strtolower($mn)) ?>"
                       style="padding:7px 12px;font-size:12px;cursor:pointer;
                              border-bottom:1px solid var(--gray-100);
                              <?= $major===$mn?'background:var(--primary-lt);color:var(--primary);font-weight:600':'' ?>"
                       onmouseover="this.style.background='var(--primary-lt)';this.style.color='var(--primary)'"
                       onmouseout="this.style.background='<?= $major===$mn?'var(--primary-lt)':'' ?>';this.style.color='<?= $major===$mn?'var(--primary)':'' ?>'">
                    <?= e($mn) ?>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>

          <script>
          function toggleMajorDrop(){
            const d = document.getElementById('majorDrop');
            const open = d.style.display === 'block';
            d.style.display = open ? 'none' : 'block';

            if(!open) {
              setTimeout(() => document.getElementById('majorSearch').focus(), 50);
            }
          }

          function selectMajor(val){
            document.getElementById('majorHidden').value = val;
            document.getElementById('majorDisplay').style.color = val ? 'var(--gray-800)' : 'var(--gray-400)';
            document.getElementById('majorDisplay').innerHTML =
              (val || 'Chọn ngành học...') +
              '<i class="bi bi-chevron-down" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:11px;color:var(--gray-400)"></i>';

            document.getElementById('majorDrop').style.display = 'none';
            document.getElementById('filterForm').submit();
          }

          function filterMajors(q){
            document.querySelectorAll('.major-opt').forEach(el => {
              el.style.display = el.dataset.name.includes(q.toLowerCase()) ? '' : 'none';
            });
          }

          document.addEventListener('click', e => {
            if(!document.getElementById('majorWrap').contains(e.target)) {
              document.getElementById('majorDrop').style.display = 'none';
            }
          });
          </script>

          <!-- Năm -->
          <div class="mb-3">
            <label class="form-label fw-semibold small text-uppercase text-muted" style="letter-spacing:.5px;font-size:10px">
              <i class="bi bi-calendar me-1"></i>Năm tuyển sinh
            </label>

            <select name="year"
                    class="form-select form-select-sm"
                    onchange="document.getElementById('filterForm').submit()">
              <option value="">Tất cả</option>
              <?php foreach($years as $y): ?>
              <option value="<?= e($y) ?>" <?= $year == $y ? 'selected' : '' ?>>
                <?= e($y) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Tổ hợp -->
          <div class="mb-3">
            <label class="form-label fw-semibold small text-uppercase text-muted" style="letter-spacing:.5px;font-size:10px">
              <i class="bi bi-grid me-1"></i>Tổ hợp xét tuyển
            </label>

            <select name="combo"
                    class="form-select form-select-sm"
                    onchange="document.getElementById('filterForm').submit()">
              <option value="">Tất cả</option>
              <?php foreach($combos as $c): ?>
                <?php if(trim($c) !== ''): ?>
                <option value="<?= e($c) ?>" <?= $combo === $c ? 'selected' : '' ?>>
                  <?= e($c) ?>
                </option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Khoảng điểm -->
          <div class="mb-3">
            <label class="form-label fw-semibold small text-uppercase text-muted" style="letter-spacing:.5px;font-size:10px">
              <i class="bi bi-sliders me-1"></i>Khoảng điểm
            </label>
            <div class="d-flex gap-2">
              <input type="number" 
                     name="min" 
                     value="<?= $min>0?$min:'' ?>"
                     placeholder="Từ" 
                     class="form-control form-control-sm"
                     min="0" 
                     max="30" 
                     step=".25" 
                     style="width:50%">

              <input type="number" 
                     name="max" 
                     value="<?= $max>0?$max:'' ?>"
                     placeholder="Đến" 
                     class="form-control form-control-sm"
                     min="0" 
                     max="30" 
                     step=".25" 
                     style="width:50%">
            </div>
          </div>

          <!-- Tỉnh/Thành -->
          <div class="mb-3">
            <label class="form-label fw-semibold small text-uppercase text-muted" style="letter-spacing:.5px;font-size:10px">
              <i class="bi bi-geo-alt me-1"></i>Tỉnh/Thành phố
            </label>

            <select name="province" class="form-select form-select-sm">
              <option value="">Tất cả</option>
              <?php foreach($provinces as $p): ?>
              <option value="<?= e($p) ?>" <?= $province===$p?'selected':'' ?>>
                <?= e($p) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Loại trường -->
          <div class="mb-4">
            <label class="form-label fw-semibold small text-uppercase text-muted" style="letter-spacing:.5px;font-size:10px">
              <i class="bi bi-building me-1"></i>Loại trường
            </label>

            <select name="school_type" class="form-select form-select-sm">
              <option value="">Tất cả</option>
              <?php foreach($types as $t): ?>
              <option value="<?= $t ?>" <?= $stype===$t?'selected':'' ?>>
                <?= $t ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Phương thức xét tuyển -->
          <div class="mb-4">
            <label class="form-label fw-semibold small text-uppercase text-muted" style="letter-spacing:.5px;font-size:10px">
              <i class="bi bi-card-checklist me-1"></i>Phương thức xét tuyển
            </label>

            <div class="d-flex flex-wrap gap-1">
              <?php foreach([
                ''        => 'Tất cả',
                'THPT'    => 'Thi THPT',
                'HocBa'   => 'Học bạ',
                'TongHop' => 'Tổng hợp',
                'DGNL'    => 'Đánh giá NL',
                'Thang'   => 'Xét thẳng',
              ] as $val=>$lbl): ?>
              <a href="?<?= http_build_query(array_merge($_GET,['method'=>$val,'page'=>1])) ?>"
                 class="btn btn-xs <?= $method===$val?'btn-primary':'btn-outline-secondary' ?>"
                 style="font-size:11px;padding:2px 10px;border-radius:20px">
                <?= $lbl ?>
              </a>
              <?php endforeach; ?>
            </div>
          </div>

          <button type="submit" class="btn btn-primary w-100 fw-semibold d-none">
  <i class="bi bi-search me-1"></i>Tìm kiếm
</button>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('filterForm');
  if (!form) return;

  let typingTimer;

  function autoSubmit() {
    form.submit();
  }

  // Select: chọn là lọc luôn
  form.querySelectorAll('select').forEach(function (select) {
    select.addEventListener('change', autoSubmit);
  });

  // Khoảng điểm: nhập xong đổi giá trị là lọc
  form.querySelectorAll('input[type="number"]').forEach(function (input) {
    input.addEventListener('change', autoSubmit);
  });

  // Ô tên trường: gõ xong 500ms tự lọc
  const qInput = form.querySelector('input[name="q"]');
  if (qInput) {
    qInput.addEventListener('input', function () {
      clearTimeout(typingTimer);
      typingTimer = setTimeout(autoSubmit, 500);
    });
  }
});
</script>
//fđf
        </form>
      </div>
    </div>

    <!-- ══ CỘT PHẢI: KẾT QUẢ ══ -->
    <div class="col-md-9">

      <!-- Active filters -->
      <?php
      $activeFilters = [];

      if($q)        $activeFilters[] = ['Trường: '.$q,       array_merge($_GET,['q'=>'','page'=>1])];
      if($major)    $activeFilters[] = ['Ngành: '.$major,    array_merge($_GET,['major'=>'','page'=>1])];
      if($year)     $activeFilters[] = ['Năm: '.$year,       array_merge($_GET,['year'=>'','page'=>1])];
      if($combo)    $activeFilters[] = ['Tổ hợp: '.$combo,   array_merge($_GET,['combo'=>'','page'=>1])];
      if($province) $activeFilters[] = ['Tỉnh: '.$province,  array_merge($_GET,['province'=>'','page'=>1])];
      if($stype)    $activeFilters[] = ['Loại: '.$stype,     array_merge($_GET,['school_type'=>'','page'=>1])];
      if($method)   $activeFilters[] = ['PT: '.$method,      array_merge($_GET,['method'=>'','page'=>1])];
      if($min>0)    $activeFilters[] = ['Điểm từ: '.$min,    array_merge($_GET,['min'=>'','page'=>1])];
      if($max>0)    $activeFilters[] = ['Điểm đến: '.$max,   array_merge($_GET,['max'=>'','page'=>1])];
      ?>

      <?php if($activeFilters): ?>
      <div class="d-flex flex-wrap gap-2 mb-3">
        <?php foreach($activeFilters as [$label,$params]): ?>
        <a href="?<?= http_build_query($params) ?>"
           class="badge text-bg-primary text-decoration-none d-flex align-items-center gap-1"
           style="border-radius:20px;font-size:12px;font-weight:500;padding:5px 12px">
          <?= e($label) ?> 
          <i class="bi bi-x-circle ms-1"></i>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Table -->
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span class="fw-semibold">
            <?php if($pg['total']>0): ?>
            Hiển thị <?= $pg['offset']+1 ?>–<?= min($pg['offset']+$pg['per_page'],$pg['total']) ?>
            trong <strong class="text-primary"><?= number_format($pg['total']) ?></strong> kết quả
            <?php else: ?>
            Không có kết quả
            <?php endif; ?>
          </span>

          <?php if($pg['total_pages']>1): ?>
          <small class="text-muted">
            Trang <?= $pg['current'] ?>/<?= $pg['total_pages'] ?>
          </small>
          <?php endif; ?>
        </div>

        <?php if(empty($rows)): ?>
        <div class="text-center py-5 text-muted">
          <i class="bi bi-inbox" style="font-size:48px;display:block;margin-bottom:12px"></i>
          <h6>Không tìm thấy kết quả phù hợp</h6>
          <p class="small">Thử thay đổi bộ lọc hoặc từ khóa tìm kiếm</p>
          <a href="<?= url('search.php') ?>" class="btn btn-outline-primary btn-sm">
            Xóa bộ lọc
          </a>
        </div>
        <?php else: ?>

        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead>
              <tr>
                <th>#</th>
                <th>Trường đại học</th>
                <th>Ngành học</th>
                <th>Năm</th>
                <th>Tổ hợp</th>
                <th>Phương thức</th>
                <th>Điểm chuẩn</th>
                <th>Chỉ tiêu</th>
                <th></th>
              </tr>
            </thead>

            <tbody>
            <?php foreach($rows as $i=>$r):
              $cls = $r['score']>=27 ? 'sb-hi' : ($r['score']>=23 ? 'sb-mid' : 'sb-lo');
            ?>
              <tr>
                <td class="text-muted small">
                  <?= $pg['offset']+$i+1 ?>
                </td>

                <td>
                  <a href="<?= url('university.php?id='.$r['university_id']) ?>"
                     class="fw-semibold text-decoration-none small d-block">
                    <?= e($r['university_name']) ?>
                  </a>

                  <span class="text-muted" style="font-size:11px">
                    <i class="bi bi-geo-alt me-1"></i><?= e($r['province']) ?>
                    · <span class="chip"><?= e($r['school_type']) ?></span>
                  </span>
                </td>

                <td>
                  <a href="<?= url('major.php?id='.$r['major_id']) ?>"
                     class="text-decoration-none text-reset small">
                    <?= e($r['major_name']) ?>
                  </a>
                </td>

                <td>
                  <span class="chip"><?= $r['year'] ?></span>
                </td>

                <td>
                  <span class="chip"><?= e($r['combination']) ?></span>
                </td>

                <td>
                  <?php
                  $mColors = [
                    'THPT'    => 'primary',
                    'HocBa'   => 'success',
                    'TongHop' => 'warning',
                    'DGNL'    => 'info',
                    'Thang'   => 'secondary'
                  ];

                  $mLabels = [
                    'THPT'    => 'Thi THPT',
                    'HocBa'   => 'Học bạ',
                    'TongHop' => 'Tổng hợp',
                    'DGNL'    => 'Đánh giá NL',
                    'Thang'   => 'Xét thẳng'
                  ];

                  $mc = $mColors[$r['method']] ?? 'secondary';
                  $ml = $mLabels[$r['method']] ?? $r['method'];
                  ?>

                  <span class="badge text-bg-<?= $mc ?> fw-normal" style="font-size:10px;border-radius:20px">
                    <?= $ml ?>
                  </span>
                </td>

                <td>
                  <span class="score-badge <?= $cls ?> fw-bold">
                    <?= number_format($r['score'],2) ?>
                  </span>
                </td>

                <td class="text-muted small">
                  <?= $r['quota'] ? number_format($r['quota']) : '—' ?>
                </td>

                <td>
                  <a href="<?= url('university.php?id='.$r['university_id']) ?>"
                     class="btn btn-sm btn-outline-primary py-0 px-2">
                    Chi tiết
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if($pg['total_pages']>1): ?>
        <div class="card-footer d-flex justify-content-center py-3">
          <nav>
            <ul class="pagination pagination-sm mb-0">

              <li class="page-item <?= $pg['current']<=1?'disabled':'' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$pg['current']-1])) ?>">
                  ‹ Trước
                </a>
              </li>

              <?php
              $start = max(1, $pg['current']-2);
              $end   = min($pg['total_pages'], $pg['current']+2);

              if($start>1): ?>
                <li class="page-item disabled">
                  <span class="page-link">...</span>
                </li>
              <?php endif; ?>

              <?php for($p=$start;$p<=$end;$p++): ?>
              <li class="page-item <?= $p==$pg['current']?'active':'' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$p])) ?>">
                  <?= $p ?>
                </a>
              </li>
              <?php endfor; ?>

              <?php if($end<$pg['total_pages']): ?>
                <li class="page-item disabled">
                  <span class="page-link">...</span>
                </li>
              <?php endif; ?>

              <li class="page-item <?= $pg['current']>=$pg['total_pages']?'disabled':'' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$pg['current']+1])) ?>">
                  Sau ›
                </a>
              </li>

            </ul>
          </nav>
        </div>
        <?php endif; ?>

        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
```
