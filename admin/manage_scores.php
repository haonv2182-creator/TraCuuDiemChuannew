<?php
require_once '../includes/functions.php';
requireAdmin();

$pageTitle = 'Quản lý dữ liệu tuyển sinh - Admin';
require_once '../includes/header.php';

$db = getDB();

$action       = $_GET['action'] ?? 'list';
$scoreId      = (int)($_GET['id'] ?? 0);
$universityId = (int)($_GET['uni'] ?? $_POST['university_id'] ?? 0);
$yearFilter   = (int)($_GET['year'] ?? 0);
$methodFilter = trim((string)($_GET['method'] ?? ''));
$page         = max(1, (int)($_GET['page'] ?? 1));

$methods = getAdmissionMethods();

$combinations = [
    '',
    'A00',
    'A01',
    'A02',
    'B00',
    'B01',
    'C00',
    'D01',
    'D07'
];

$years = range((int)date('Y'), 2015);


/* Danh sách trường */
$universities = $db->query("
    SELECT
        university_id,
        university_name,
        university_code,
        province,
        school_type
    FROM universities
    ORDER BY university_name
")->fetchAll();

/* Danh sách ngành */
$majors = $db->query("
    SELECT
        major_id,
        major_name
    FROM majors
    ORDER BY major_name
")->fetchAll();

/* Thông tin trường đang chọn */
$selectedUniversity = null;

if ($universityId > 0) {
    $stmt = $db->prepare("
        SELECT
            university_id,
            university_name,
            university_code,
            province,
            school_type
        FROM universities
        WHERE university_id = ?
    ");

    $stmt->execute([$universityId]);
    $selectedUniversity = $stmt->fetch();

    if (!$selectedUniversity) {
        $universityId = 0;
    }
}

/* Xóa điểm chuẩn */
if ($action === 'delete' && $scoreId > 0) {
    $stmt = $db->prepare("
        SELECT university_id
        FROM admission_scores
        WHERE score_id = ?
    ");

    $stmt->execute([$scoreId]);
    $deleteUniversityId = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("
        DELETE FROM admission_scores
        WHERE score_id = ?
    ");

    $stmt->execute([$scoreId]);

    setFlash('success', 'Đã xóa bản ghi điểm chuẩn.');

    redirect(
        'admin/manage_scores.php?uni=' . $deleteUniversityId
    );
}

/* Thêm hoặc sửa điểm chuẩn */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $universityId = (int)($_POST['university_id'] ?? 0);
    $majorId      = (int)($_POST['major_id'] ?? 0);
    $year         = (int)($_POST['year'] ?? 0);
    $combination  = trim((string)($_POST['combination'] ?? ''));
    $method       = trim((string)($_POST['method'] ?? 'THPT'));
    $score        = (float)($_POST['score'] ?? 0);
    $editId       = (int)($_POST['edit_id'] ?? 0);

    $maxScore = $method === 'DGNL' ? 1200 : 30;

    if (
        $universityId <= 0
        || $majorId <= 0
        || $year <= 0
        || $method === ''
    ) {
        setFlash(
            'error',
            'Vui lòng nhập đầy đủ thông tin bắt buộc.'
        );
    } elseif ($score < 0 || $score > $maxScore) {
        setFlash(
            'error',
            $method === 'DGNL'
                ? 'Điểm đánh giá năng lực phải từ 0 đến 1200.'
                : 'Điểm theo thang 30 phải từ 0 đến 30.'
        );
    } else {
        if ($editId > 0) {
            $stmt = $db->prepare("
                UPDATE admission_scores
                SET
                    university_id = ?,
                    major_id = ?,
                    year = ?,
                    combination = ?,
                    method = ?,
                    score = ?
                WHERE score_id = ?
            ");

            $stmt->execute([
                $universityId,
                $majorId,
                $year,
                $combination,
                $method,
                $score,
                $editId
            ]);

            setFlash(
                'success',
                'Đã cập nhật điểm chuẩn.'
            );
        } else {
            $stmt = $db->prepare("
                INSERT INTO admission_scores (
                    university_id,
                    major_id,
                    year,
                    combination,
                    method,
                    score
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $universityId,
                $majorId,
                $year,
                $combination,
                $method,
                $score
            ]);

            setFlash(
                'success',
                'Đã thêm điểm chuẩn.'
            );
        }

        redirect(
            'admin/manage_scores.php?uni=' . $universityId
        );
    }
}

/* Lấy dữ liệu khi sửa */
$editRow = null;

if ($action === 'edit' && $scoreId > 0) {
    $stmt = $db->prepare("
        SELECT *
        FROM admission_scores
        WHERE score_id = ?
    ");

    $stmt->execute([$scoreId]);
    $editRow = $stmt->fetch();

    if ($editRow) {
        $universityId = (int)$editRow['university_id'];

        $stmt = $db->prepare("
            SELECT
                university_id,
                university_name,
                university_code,
                province,
                school_type
            FROM universities
            WHERE university_id = ?
        ");

        $stmt->execute([$universityId]);
        $selectedUniversity = $stmt->fetch();
    }
}

/* Danh sách điểm chuẩn */
$list = [];

$pagination = [
    'total'       => 0,
    'per_page'    => 20,
    'offset'      => 0,
    'current'     => 1,
    'total_pages' => 0
];

if ($universityId > 0) {
    $where = [
        's.university_id = :university_id'
    ];

    $params = [
        ':university_id' => $universityId
    ];

    if ($yearFilter > 0) {
        $where[] = 's.year = :year';
        $params[':year'] = $yearFilter;
    }

    if ($methodFilter !== '') {
        $where[] = 's.method = :method';
        $params[':method'] = $methodFilter;
    }

    $whereSql = implode(' AND ', $where);

    $countStmt = $db->prepare("
        SELECT COUNT(*)
        FROM admission_scores s
        WHERE $whereSql
    ");

    $countStmt->execute($params);

    $pagination = paginate(
        (int)$countStmt->fetchColumn(),
        20,
        $page
    );

    $dataStmt = $db->prepare("
        SELECT
            s.score_id,
            s.university_id,
            s.major_id,
            s.year,
            s.combination,
            s.method,
            s.score,
            m.major_name
        FROM admission_scores s
        JOIN majors m
            ON s.major_id = m.major_id
        WHERE $whereSql
        ORDER BY
            m.major_name ASC,
            s.year DESC,
            s.score DESC
        LIMIT :limit
        OFFSET :offset
    ");

    foreach ($params as $key => $value) {
        $dataStmt->bindValue(
            $key,
            $value
        );
    }

    $dataStmt->bindValue(
        ':limit',
        $pagination['per_page'],
        PDO::PARAM_INT
    );

    $dataStmt->bindValue(
        ':offset',
        $pagination['offset'],
        PDO::PARAM_INT
    );

    $dataStmt->execute();
    $list = $dataStmt->fetchAll();
}
?>

<div class="admin-wrapper">

    <?php require_once '../includes/sidebar.php'; ?>

    <div class="admin-content">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">

            <div>
                <h2 class="fw-bold mb-1">
                    <i class="bi bi-database me-2"></i>
                    Quản lý dữ liệu tuyển sinh
                </h2>

                <p class="text-muted mb-0 small">
                    Chọn một trường để xem và chỉnh sửa ngành, năm và điểm chuẩn.
                </p>
            </div>

            <?php if ($universityId > 0): ?>
                <button
                    type="button"
                    class="btn btn-primary btn-sm"
                    onclick="
                        document
                            .getElementById('scoreFormCard')
                            .classList.toggle('d-none')
                    "
                >
                    <i class="bi bi-plus-lg me-1"></i>
                    Thêm điểm chuẩn
                </button>
            <?php endif; ?>

        </div>

        <!-- CHỌN TRƯỜNG -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">

                <form
                    method="GET"
                    id="universitySearchForm"
                >

                    <label
                        for="universitySearch"
                        class="form-label fw-semibold"
                    >
                        Tìm trường đại học
                    </label>

                    <div class="row g-2">

                        <div class="col-lg-9 position-relative">

                            <input
                                type="text"
                                id="universitySearch"
                                class="form-control"
                                list="universityList"
                                autocomplete="off"
                                placeholder="Nhập tên hoặc mã trường..."
                                value="<?= e(
                                    $selectedUniversity['university_name'] ?? ''
                                ) ?>"
                                required
                            >

                            <input
                                type="hidden"
                                name="uni"
                                id="universityId"
                                value="<?= $universityId ?>"
                            >

                            <datalist id="universityList">
                                <?php foreach ($universities as $university): ?>
                                    <option
                                        value="<?= e($university['university_name']) ?>"
                                        data-id="<?= (int)$university['university_id'] ?>"
                                    >
                                        <?= e($university['university_code'] ?? '') ?>
                                        <?= !empty($university['province'])
                                            ? ' - ' . e($university['province'])
                                            : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </datalist>

                        </div>

                        <div class="col-lg-3">
                            <button
                                type="submit"
                                class="btn btn-primary w-100"
                            >
                                <i class="bi bi-search me-1"></i>
                                Xem dữ liệu
                            </button>
                        </div>

                    </div>

                    <div
                        id="universitySearchError"
                        class="small text-danger mt-2 d-none"
                    >
                        Vui lòng chọn đúng một trường trong danh sách gợi ý.
                    </div>

                </form>

            </div>
        </div>

        <?php if ($universityId <= 0): ?>

            <!-- DANH SÁCH TRƯỜNG -->
            <div class="card shadow-sm">

                <div class="card-header d-flex justify-content-between align-items-center">

                    <div>
                        <i class="bi bi-building me-1"></i>
                        Danh sách trường đại học
                    </div>

                    <span class="badge bg-primary">
                        <?= count($universities) ?> trường
                    </span>

                </div>

                <div class="card-body">

                    <div class="row g-3">

                        <?php foreach ($universities as $university): ?>

                            <div class="col-md-6 col-xl-4">

                                <a
                                    href="<?= url(
                                        'admin/manage_scores.php?uni='
                                        . $university['university_id']
                                    ) ?>"
                                    class="card h-100 text-decoration-none university-admin-card"
                                >

                                    <div class="card-body">

                                        <div class="d-flex align-items-start gap-3">

                                            <div
                                                class="university-admin-logo"
                                            >
                                                <?= e(
                                                    !empty($university['university_code'])
                                                        ? $university['university_code']
                                                        : mb_substr(
                                                            $university['university_name'],
                                                            0,
                                                            2,
                                                            'UTF-8'
                                                        )
                                                ) ?>
                                            </div>

                                            <div class="flex-grow-1">

                                                <h6 class="fw-bold mb-1 text-dark">
                                                    <?= e($university['university_name']) ?>
                                                </h6>

                                                <div class="small text-muted">

                                                    <?= e(
                                                        $university['province'] ?? ''
                                                    ) ?>

                                                    <?php if (!empty($university['school_type'])): ?>
                                                        · <?= e($university['school_type']) ?>
                                                    <?php endif; ?>

                                                </div>

                                            </div>

                                            <i class="bi bi-chevron-right text-primary"></i>

                                        </div>

                                    </div>

                                </a>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            </div>

        <?php else: ?>

            <!-- THÔNG TIN TRƯỜNG -->
            <div class="card shadow-sm mb-4">

                <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">

                    <div>

                        <div class="small text-muted mb-1">
                            Trường đang quản lý
                        </div>

                        <h5 class="fw-bold mb-1">
                            <?= e(
                                $selectedUniversity['university_name'] ?? ''
                            ) ?>
                        </h5>

                        <div class="small text-muted">

                            <?= e(
                                $selectedUniversity['university_code'] ?? ''
                            ) ?>

                            <?php if (!empty($selectedUniversity['province'])): ?>
                                · <?= e($selectedUniversity['province']) ?>
                            <?php endif; ?>

                            <?php if (!empty($selectedUniversity['school_type'])): ?>
                                · <?= e($selectedUniversity['school_type']) ?>
                            <?php endif; ?>

                        </div>

                    </div>

                    <div class="d-flex gap-2 flex-wrap">

                        <a
                            href="<?= url(
                                'admin/manage_universities.php?action=edit&id='
                                . $universityId
                            ) ?>"
                            class="btn btn-outline-primary btn-sm"
                        >
                            <i class="bi bi-pencil-square me-1"></i>
                            Sửa thông tin trường
                        </a>

                        <a
                            href="<?= url('admin/manage_majors.php') ?>"
                            class="btn btn-outline-secondary btn-sm"
                        >
                            <i class="bi bi-book me-1"></i>
                            Quản lý danh mục ngành
                        </a>

                    </div>

                </div>

            </div>

            <!-- FORM THÊM / SỬA -->
            <div
                id="scoreFormCard"
                class="card shadow-sm mb-4 <?= $editRow ? '' : 'd-none' ?>"
            >

                <div class="card-header fw-semibold">
                    <?= $editRow
                        ? 'Sửa điểm chuẩn'
                        : 'Thêm điểm chuẩn' ?>
                </div>

                <div class="card-body">

                    <form method="POST">

                        <input
                            type="hidden"
                            name="university_id"
                            value="<?= $universityId ?>"
                        >

                        <?php if ($editRow): ?>
                            <input
                                type="hidden"
                                name="edit_id"
                                value="<?= (int)$editRow['score_id'] ?>"
                            >
                        <?php endif; ?>

                        <div class="row g-3">

                            <div class="col-md-5">

                                <label class="form-label fw-semibold small">
                                    Ngành học
                                    <span class="text-danger">*</span>
                                </label>

                                <select
                                    name="major_id"
                                    class="form-select"
                                    required
                                >

                                    <option value="">
                                        -- Chọn ngành --
                                    </option>

                                    <?php foreach ($majors as $major): ?>

                                        <option
                                            value="<?= (int)$major['major_id'] ?>"
                                            <?= (int)($editRow['major_id'] ?? 0)
                                                === (int)$major['major_id']
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= e($major['major_name']) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <div class="col-md-2">

                                <label class="form-label fw-semibold small">
                                    Năm
                                </label>

                                <select
                                    name="year"
                                    class="form-select"
                                    required
                                >

                                    <?php foreach ($years as $year): ?>

                                        <option
                                            value="<?= $year ?>"
                                            <?= (int)($editRow['year'] ?? date('Y'))
                                                === $year
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= $year ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <div class="col-md-2">

                                <label class="form-label fw-semibold small">
                                    Tổ hợp
                                </label>

                                <select
                                    name="combination"
                                    class="form-select"
                                >

                                    <?php foreach ($combinations as $combination): ?>

                                        <option
                                            value="<?= e($combination) ?>"
                                            <?= (string)($editRow['combination'] ?? '')
                                                === $combination
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= $combination !== ''
                                                ? e($combination)
                                                : 'Không có' ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <div class="col-md-3">

                                <label class="form-label fw-semibold small">
                                    Phương thức
                                    <span class="text-danger">*</span>
                                </label>

                                <select
                                    name="method"
                                    id="scoreMethod"
                                    class="form-select"
                                    required
                                >

                                    <?php foreach ($methods as $value => $methodItem): ?>

                                        <option
                                            value="<?= e($value) ?>"
                                            <?= (string)($editRow['method'] ?? 'THPT')
                                                === $value
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= e(methodLabel($value)) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <div class="col-md-3">

                                <label class="form-label fw-semibold small">
                                    Điểm chuẩn
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="number"
                                    name="score"
                                    id="scoreInput"
                                    class="form-control"
                                    step="0.01"
                                    min="0"
                                    max="30"
                                    required
                                    value="<?= e($editRow['score'] ?? '') ?>"
                                >

                                <small
                                    id="scoreHelp"
                                    class="text-muted"
                                >
                                    Thang điểm tối đa 30
                                </small>

                            </div>

                        </div>

                        <div class="d-flex gap-2 mt-3">

                            <button
                                type="submit"
                                class="btn btn-primary btn-sm"
                            >
                                <i class="bi bi-save me-1"></i>
                                Lưu
                            </button>

                            <a
                                href="<?= url(
                                    'admin/manage_scores.php?uni='
                                    . $universityId
                                ) ?>"
                                class="btn btn-outline-secondary btn-sm"
                            >
                                Hủy
                            </a>

                        </div>

                    </form>

                </div>

            </div>

            <!-- BỘ LỌC -->
            <div class="filter-card mb-3">

                <form
                    method="GET"
                    class="row g-2 align-items-end"
                >

                    <input
                        type="hidden"
                        name="uni"
                        value="<?= $universityId ?>"
                    >

                    <div class="col-md-4">

                        <label class="form-label fw-semibold small">
                            Lọc theo năm
                        </label>

                        <select
                            name="year"
                            class="form-select form-select-sm"
                        >

                            <option value="0">
                                Tất cả năm
                            </option>

                            <?php foreach ($years as $year): ?>

                                <option
                                    value="<?= $year ?>"
                                    <?= $yearFilter === $year
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= $year ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="col-md-4">

                        <label class="form-label fw-semibold small">
                            Lọc theo phương thức
                        </label>

                        <select
                            name="method"
                            class="form-select form-select-sm"
                        >

                            <option value="">
                                Tất cả phương thức
                            </option>

                            <?php foreach ($methods as $value => $methodItem): ?>

                                <option
                                    value="<?= e($value) ?>"
                                    <?= $methodFilter === $value
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= e(methodLabel($value)) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="col-auto">

                        <button
                            type="submit"
                            class="btn btn-sm btn-primary"
                        >
                            <i class="bi bi-funnel me-1"></i>
                            Lọc
                        </button>

                    </div>

                    <?php if ($yearFilter > 0 || $methodFilter !== ''): ?>

                        <div class="col-auto">

                            <a
                                href="<?= url(
                                    'admin/manage_scores.php?uni='
                                    . $universityId
                                ) ?>"
                                class="btn btn-sm btn-outline-secondary"
                            >
                                Xóa lọc
                            </a>

                        </div>

                    <?php endif; ?>

                </form>

            </div>

            <!-- BẢNG DỮ LIỆU -->
            <div class="card shadow-sm">

                <div class="card-header">

                    <i class="bi bi-table me-1"></i>

                    Tổng:

                    <strong class="text-primary">
                        <?= number_format($pagination['total']) ?>
                    </strong>

                    bản ghi

                </div>

                <?php if (empty($list)): ?>

                    <div class="card-body text-center py-5">

                        <i
                            class="bi bi-inbox text-muted"
                            style="font-size:44px"
                        ></i>

                        <h6 class="mt-3">
                            Trường này chưa có dữ liệu phù hợp
                        </h6>

                        <p class="text-muted small mb-0">
                            Bạn có thể thêm điểm chuẩn bằng nút phía trên.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="table-responsive">

                        <table class="table table-hover mb-0 align-middle small">

                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Ngành học</th>
                                    <th>Năm</th>
                                    <th>Tổ hợp</th>
                                    <th>Phương thức</th>
                                    <th>Điểm</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($list as $index => $row): ?>

                                    <?php
                                    $scoreClass = (float)$row['score'] >= 27
                                        ? 'sb-hi'
                                        : (
                                            (float)$row['score'] >= 23
                                                ? 'sb-mid'
                                                : 'sb-lo'
                                        );
                                    ?>

                                    <tr>

                                        <td class="text-muted">
                                            <?= $pagination['offset'] + $index + 1 ?>
                                        </td>

                                        <td class="fw-semibold">
                                            <?= e($row['major_name']) ?>
                                        </td>

                                        <td>
                                            <span class="chip">
                                                <?= e($row['year']) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="chip">
                                                <?= !empty($row['combination'])
                                                    ? e($row['combination'])
                                                    : '—' ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="chip">
                                                <?= e(methodLabel((string)$row['method'])) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="score-badge <?= $scoreClass ?>">
                                                <?= number_format(
                                                    (float)$row['score'],
                                                    2
                                                ) ?>
                                            </span>
                                        </td>

                                        <td>

                                            <div class="d-flex gap-1">

                                                <a
                                                    href="<?= url(
                                                        'admin/manage_scores.php?action=edit&id='
                                                        . $row['score_id']
                                                        . '&uni='
                                                        . $universityId
                                                    ) ?>"
                                                    class="btn btn-sm btn-outline-primary py-0 px-2"
                                                    title="Sửa"
                                                >
                                                    <i class="bi bi-pencil"></i>
                                                </a>

                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-danger py-0 px-2"
                                                    title="Xóa"
                                                    onclick="confirmDelete(
                                                        '<?= url(
                                                            'admin/manage_scores.php?action=delete&id='
                                                            . $row['score_id']
                                                            . '&uni='
                                                            . $universityId
                                                        ) ?>',
                                                        'bản ghi này'
                                                    )"
                                                >
                                                    <i class="bi bi-trash"></i>
                                                </button>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                    <?php if ($pagination['total_pages'] > 1): ?>

                        <div class="card-footer d-flex justify-content-center py-2">

                            <nav aria-label="Phân trang">

                                <ul class="pagination pagination-sm mb-0">

                                    <?php for (
                                        $p = 1;
                                        $p <= $pagination['total_pages'];
                                        $p++
                                    ): ?>

                                        <li
                                            class="page-item <?= $p === $pagination['current']
                                                ? 'active'
                                                : '' ?>"
                                        >

                                            <a
                                                class="page-link"
                                                href="?<?= e(
                                                    http_build_query([
                                                        'uni'    => $universityId,
                                                        'year'   => $yearFilter,
                                                        'method' => $methodFilter,
                                                        'page'   => $p
                                                    ])
                                                ) ?>"
                                            >
                                                <?= $p ?>
                                            </a>

                                        </li>

                                    <?php endfor; ?>

                                </ul>

                            </nav>

                        </div>

                    <?php endif; ?>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>
</div>

<style>
.university-admin-card {
    color: inherit;
    border: 1px solid #e2e8f0;
    transition:
        transform .2s ease,
        box-shadow .2s ease,
        border-color .2s ease;
}

.university-admin-card:hover {
    color: inherit;
    transform: translateY(-4px);
    border-color: rgba(79, 70, 229, .35);
    box-shadow: 0 14px 30px rgba(37, 99, 235, .12);
}

.university-admin-logo {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    flex-shrink: 0;
    padding: 5px;
    color: #ffffff;
    border-radius: 14px;
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    font-size: 11px;
    font-weight: 700;
    text-align: center;
}

html[data-theme="dark"] .university-admin-card {
    background: #1e293b !important;
    border-color: #334155 !important;
}

html[data-theme="dark"] .university-admin-card .text-dark {
    color: #f8fafc !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const methodSelect = document.getElementById('scoreMethod');
    const scoreInput = document.getElementById('scoreInput');
    const scoreHelp = document.getElementById('scoreHelp');

    if (!methodSelect || !scoreInput || !scoreHelp) {
        return;
    }

    function updateScoreScale() {
        const isDgnl = methodSelect.value === 'DGNL';

        scoreInput.max = isDgnl ? '1200' : '30';

        scoreInput.placeholder = isDgnl
            ? 'Ví dụ: 850'
            : 'Ví dụ: 25.50';

        scoreHelp.textContent = isDgnl
            ? 'Thang điểm tối đa 1200'
            : 'Thang điểm tối đa 30';
    }

    methodSelect.addEventListener(
        'change',
        updateScoreScale
    );

    updateScoreScale();
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('universitySearchForm');
    const input = document.getElementById('universitySearch');
    const hiddenId = document.getElementById('universityId');
    const error = document.getElementById('universitySearchError');
    const options = document.querySelectorAll(
        '#universityList option'
    );

    if (!form || !input || !hiddenId) {
        return;
    }

    function updateUniversityId() {
        const enteredName = input.value.trim();
        let matchedId = '';

        options.forEach(function (option) {
            if (option.value.trim() === enteredName) {
                matchedId = option.dataset.id || '';
            }
        });

        hiddenId.value = matchedId;

        if (error) {
            error.classList.toggle(
                'd-none',
                matchedId !== ''
            );
        }

        return matchedId !== '';
    }

    input.addEventListener('input', updateUniversityId);
    input.addEventListener('change', updateUniversityId);

    form.addEventListener('submit', function (event) {
        if (!updateUniversityId()) {
            event.preventDefault();
            input.focus();
        }
    });
});
</script>
<?php require_once '../includes/footer.php'; ?>

