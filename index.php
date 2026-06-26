<?php
$pageTitle = 'DiemChuan.vn – Tra cứu điểm chuẩn đại học Việt Nam';
require_once 'includes/header.php';

$db      = getDB();
$q       = trim($_GET['q'] ?? '');
$mid     = (int)($_GET['major'] ?? 0);
$method  = trim($_GET['method'] ?? '');
$tab     = isset($_GET['major']) ? 'major' : 'uni';
$showAll = ($_GET['show_all'] ?? '') === '1';
$isHome  = $q === '' && $mid === 0;

$methods = [
    'THPT' => [
        'label' => 'Thi THPT',
        'color' => 'primary'
    ],
    'HocBa' => [
        'label' => 'Học bạ',
        'color' => 'success'
    ],
    'TongHop' => [
        'label' => 'Tổng hợp',
        'color' => 'warning'
    ],
    'DGNL' => [
        'label' => 'Đánh giá NL',
        'color' => 'info'
    ]
];

$allMajors = $db->query("
    SELECT major_id, major_name
    FROM majors
    ORDER BY major_name
")->fetchAll();

$popularMajors = $db->query("
    SELECT
        m.major_id,
        m.major_name,
        COUNT(s.score_id) AS total_scores
    FROM majors m
    LEFT JOIN admission_scores s
        ON m.major_id = s.major_id
    GROUP BY m.major_id, m.major_name
    ORDER BY total_scores DESC, m.major_name ASC
    LIMIT 5
")->fetchAll();

$uniResults = [];

if ($q !== '') {
    $stmt = $db->prepare("
        SELECT
            u.university_id,
            u.university_name,
            u.university_code,
            u.province,
            u.school_type,
            COUNT(DISTINCT s.major_id) AS mcnt
        FROM universities u
        LEFT JOIN admission_scores s
            ON u.university_id = s.university_id
        WHERE u.university_name LIKE :q
        GROUP BY
            u.university_id,
            u.university_name,
            u.university_code,
            u.province,
            u.school_type
        ORDER BY u.university_name ASC
    ");

    $stmt->execute([':q' => "%$q%"]);
    $uniResults = $stmt->fetchAll();
}

$majorResults = [];
$majorName    = '';

if ($mid > 0) {
    $stmt = $db->prepare("
        SELECT major_name
        FROM majors
        WHERE major_id = ?
    ");
    $stmt->execute([$mid]);
    $majorName = (string)$stmt->fetchColumn();

    $yearSql = "
        SELECT MAX(year)
        FROM admission_scores
        WHERE major_id = :mid
    ";
    $yearParams = [':mid' => $mid];

    if ($method !== '') {
        $yearSql .= ' AND method = :method';
        $yearParams[':method'] = $method;
    }

    $stmt = $db->prepare($yearSql);
    $stmt->execute($yearParams);
    $latestYear = (int)$stmt->fetchColumn();

    if ($latestYear > 0) {
        $majorWhere = "
            s.major_id = :mid
            AND s.year = :year
        ";
        $majorParams = [
            ':mid'  => $mid,
            ':year' => $latestYear
        ];

        if ($method !== '') {
            $majorWhere .= ' AND s.method = :method';
            $majorParams[':method'] = $method;
        }

        $stmt = $db->prepare("
            SELECT
                u.university_id,
                u.university_name,
                u.university_code,
                u.province,
                u.school_type,
                s.score,
                s.combination,
                s.method,
                s.year
            FROM admission_scores s
            JOIN universities u
                ON s.university_id = u.university_id
            WHERE $majorWhere
            ORDER BY s.score DESC
        ");

        $stmt->execute($majorParams);
        $majorResults = $stmt->fetchAll();
    }
}

$featuredUnis   = [];
$featuredMajors = [];

if ($isHome) {
    $featuredFilter = $showAll ? '' : 'WHERE u.is_featured = 1';

    $featuredUnis = $db->query("
        SELECT
            u.university_id,
            u.university_name,
            u.university_code,
            u.province,
            u.school_type,
            COUNT(DISTINCT s.major_id) AS mcnt
        FROM universities u
        LEFT JOIN admission_scores s
            ON u.university_id = s.university_id
        $featuredFilter
        GROUP BY
            u.university_id,
            u.university_name,
            u.university_code,
            u.province,
            u.school_type
        ORDER BY u.university_name ASC
    ")->fetchAll();

    $featuredMajors = $db->query("
        SELECT
            m.major_id,
            m.major_name,
            COUNT(DISTINCT s.university_id) AS university_count
        FROM majors m
        LEFT JOIN admission_scores s
            ON m.major_id = s.major_id
        GROUP BY m.major_id, m.major_name
        ORDER BY university_count DESC, m.major_name ASC
        LIMIT 8
    ")->fetchAll();
}

$stats = $db->query("
    SELECT
        (SELECT COUNT(*) FROM universities) AS unis,
        (SELECT COUNT(*) FROM majors) AS majors,
        (SELECT COUNT(*) FROM admission_scores) AS scores
")->fetch();

$statItems = [
    [
        'value' => (int)$stats['unis'],
        'label' => 'Trường đại học',
        'icon'  => 'bi-building'
    ],
    [
        'value' => (int)$stats['majors'],
        'label' => 'Ngành học',
        'icon'  => 'bi-book'
    ],
    [
        'value' => (int)$stats['scores'],
        'label' => 'Dữ liệu điểm chuẩn',
        'icon'  => 'bi-graph-up-arrow'
    ]
];

$actionItems = [
    [
        'url'         => 'search.php',
        'icon_class'  => 'action-search',
        'icon'        => 'bi-search',
        'title'       => 'Tra cứu điểm chuẩn',
        'description' => 'Tìm điểm theo trường, ngành, tổ hợp, năm và phương thức xét tuyển.',
        'link_text'   => 'Tra cứu ngay'
    ],
    [
        'url'         => 'compare.php',
        'icon_class'  => 'action-compare',
        'icon'        => 'bi-bar-chart-line',
        'title'       => 'So sánh trường',
        'description' => 'So sánh điểm chuẩn giữa hai trường qua từng năm và từng ngành.',
        'link_text'   => 'So sánh ngay'
    ],
    [
        'url'         => 'ai_recommend.php',
        'icon_class'  => 'action-ai',
        'icon'        => 'bi-stars',
        'title'       => 'Gợi ý trường phù hợp',
        'description' => 'Nhập điểm của bạn để nhận gợi ý ngành và trường phù hợp.',
        'link_text'   => 'Nhận gợi ý'
    ]
];

function uni_code_box($code, $name, $length = 4)
{
    $code = trim((string)$code);

    return $code !== ''
        ? $code
        : mb_substr(trim((string)$name), 0, $length, 'UTF-8');
}

function render_uni_card(array $university)
{
    ?>
    <div class="col-6 col-md-4 col-lg-3">
        <a
            href="<?= url('university.php?id=' . $university['university_id']) ?>"
            class="uni-card h-100 text-decoration-none"
        >
            <div class="d-flex align-items-center gap-3 mb-3">
                <div
                    class="uni-logo flex-shrink-0 d-flex align-items-center justify-content-center"
                    style="width:48px;height:48px;font-size:12px;font-weight:700"
                >
                    <?= e(uni_code_box(
                        $university['university_code'] ?? '',
                        $university['university_name'] ?? '',
                        4
                    )) ?>
                </div>

                <div class="overflow-hidden">
                    <div
                        class="fw-bold text-dark"
                        style="font-size:13px;line-height:1.3"
                    >
                        <?= e($university['university_name']) ?>
                    </div>

                    <div class="text-muted" style="font-size:11px">
                        <i class="bi bi-geo-alt me-1"></i>
                        <?= e($university['province']) ?>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <span class="chip">
                    <?= (int)$university['mcnt'] ?> ngành
                </span>

                <span class="chip">
                    <?= e($university['school_type']) ?>
                </span>
            </div>
        </a>
    </div>
    <?php
}
?>

<section class="hero home-hero">
    <div class="container position-relative">
        <div class="text-center">

            <span class="home-hero-label">
                <i class="bi bi-mortarboard-fill me-1"></i>
                Dữ liệu tuyển sinh đại học Việt Nam
            </span>

            <h1 class="hero-title mt-3">
                Tra cứu điểm chuẩn<br>
                <span class="home-gradient-text">
                    nhanh chóng và chính xác
                </span>
            </h1>

            <p class="hero-sub">
                Tìm trường, ngành học và phương thức xét tuyển phù hợp với bạn
            </p>

            <div class="d-flex justify-content-center gap-2 mb-3">
                <button
                    type="button"
                    onclick="switchHomeTab('uni')"
                    id="tab-uni"
                    class="btn fw-semibold px-4 <?= $tab === 'uni' ? 'btn-light' : 'btn-outline-light' ?>"
                    style="border-radius:30px"
                >
                    <i class="bi bi-building me-1"></i>
                    Tìm theo trường
                </button>

                <button
                    type="button"
                    onclick="switchHomeTab('major')"
                    id="tab-major"
                    class="btn fw-semibold px-4 <?= $tab === 'major' ? 'btn-light' : 'btn-outline-light' ?>"
                    style="border-radius:30px"
                >
                    <i class="bi bi-book me-1"></i>
                    Tìm theo ngành
                </button>
            </div>

            <form
                action="<?= url('index.php') ?>"
                method="GET"
                id="form-uni"
                class="js-home-search-form <?= $tab === 'major' ? 'd-none' : '' ?>"
            >
                <div class="search-hero mx-auto" style="max-width:650px">
                    <i class="bi bi-building"></i>

                    <input
                        type="text"
                        name="q"
                        id="heroUniversityInput"
                        value="<?= e($q) ?>"
                        autocomplete="off"
                        placeholder="Nhập tên trường đại học..."
                    >

                    <button
                        type="submit"
                        class="btn btn-primary px-4 fw-semibold js-submit-btn"
                    >
                        <i class="bi bi-search me-1"></i>
                        Tìm kiếm
                    </button>
                </div>
            </form>

            <form
                action="<?= url('index.php') ?>"
                method="GET"
                id="form-major"
                class="js-home-search-form <?= $tab === 'uni' ? 'd-none' : '' ?>"
            >
                <div class="search-hero mx-auto" style="max-width:850px">
                    <i class="bi bi-book"></i>

                    <select
                        name="major"
                        id="heroMajorSelect"
                        class="form-select border-0 bg-transparent"
                        style="flex:1;outline:none;font-size:14px;font-family:inherit"
                        onchange="this.form.submit()"
                    >
                        <option value="0">-- Chọn ngành học --</option>

                        <?php foreach ($allMajors as $major): ?>
                            <option
                                value="<?= (int)$major['major_id'] ?>"
                                <?= $mid === (int)$major['major_id'] ? 'selected' : '' ?>
                            >
                                <?= e($major['major_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select
                        name="method"
                        class="form-select border-0 bg-transparent"
                        style="max-width:200px;outline:none;font-size:14px;font-family:inherit;border-left:1px solid var(--gray-200)!important"
                        onchange="this.form.submit()"
                    >
                        <option value="">Tất cả phương thức</option>

                        <?php foreach ($methods as $value => $item): ?>
                            <option
                                value="<?= e($value) ?>"
                                <?= $method === $value ? 'selected' : '' ?>
                            >
                                <?= e($item['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button
                        type="submit"
                        class="btn btn-primary px-4 fw-semibold js-submit-btn"
                    >
                        <i class="bi bi-search me-1"></i>
                        Xem điểm
                    </button>
                </div>
            </form>

            <?php if (!empty($popularMajors)): ?>
                <div class="popular-searches">
                    <span class="popular-searches-label">Tìm nhanh:</span>

                    <?php foreach ($popularMajors as $major): ?>
                        <button
                            type="button"
                            class="popular-search-btn"
                            data-major-id="<?= (int)$major['major_id'] ?>"
                        >
                            <?= e($major['major_name']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="home-hero-stats">
                <?php foreach ($statItems as $item): ?>
                    <div class="home-hero-stat">
                        <div class="home-stat-icon">
                            <i class="bi <?= e($item['icon']) ?>"></i>
                        </div>

                        <div>
                            <strong data-counter="<?= $item['value'] ?>">
                                <?= number_format($item['value']) ?>
                            </strong>
                            <span><?= e($item['label']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </div>
</section>

<div class="container py-5">

    <?php if ($mid > 0 && $majorName !== ''): ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="bi bi-book me-2 text-primary"></i>
                    Điểm chuẩn ngành
                    <span class="text-primary"><?= e($majorName) ?></span>
                </h4>

                <p class="text-muted small mb-0">
                    <?= count($majorResults) ?> kết quả

                    <?php if (!empty($majorResults)): ?>
                        · Dữ liệu năm <?= e($majorResults[0]['year']) ?> mới nhất
                    <?php endif; ?>

                    <?php if ($method !== ''): ?>
                        · Phương thức:
                        <span class="text-primary">
                            <?= e($methods[$method]['label'] ?? $method) ?>
                        </span>
                    <?php endif; ?>
                </p>
            </div>

            <a href="<?= url('index.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-x me-1"></i>
                Xóa tìm kiếm
            </a>
        </div>

        <?php if (empty($majorResults)): ?>

            <div class="empty-state text-center py-5">
                <div class="empty-state-icon">
                    <i class="bi bi-inbox"></i>
                </div>

                <h5 class="mt-3">Chưa có dữ liệu ngành này</h5>

                <a
                    href="<?= url('index.php') ?>"
                    class="btn btn-outline-primary btn-sm mt-2"
                >
                    Quay lại
                </a>
            </div>

        <?php else: ?>

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Trường đại học</th>
                                <th>Tỉnh/TP</th>
                                <th>Loại trường</th>
                                <th>Tổ hợp</th>
                                <th>Phương thức</th>
                                <th>Điểm chuẩn</th>
                                <th></th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($majorResults as $index => $row): ?>
                                <?php
                                $scoreClass = $row['score'] >= 27
                                    ? 'sb-hi'
                                    : ($row['score'] >= 23 ? 'sb-mid' : 'sb-lo');

                                $methodItem  = $methods[$row['method']] ?? null;
                                $methodColor = $methodItem['color'] ?? 'secondary';
                                $methodLabel = $methodItem['label'] ?? $row['method'];
                                ?>

                                <tr>
                                    <td class="text-muted small">
                                        <?= $index + 1 ?>
                                    </td>

                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div
                                                class="uni-logo flex-shrink-0 d-flex align-items-center justify-content-center"
                                                style="width:36px;height:36px;font-size:10px;font-weight:700"
                                            >
                                                <?= e(uni_code_box(
                                                    $row['university_code'] ?? '',
                                                    $row['university_name'] ?? '',
                                                    3
                                                )) ?>
                                            </div>

                                            <div class="fw-semibold small">
                                                <?= e($row['university_name']) ?>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="text-muted small">
                                        <?= e($row['province']) ?>
                                    </td>

                                    <td>
                                        <span class="chip">
                                            <?= e($row['school_type']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="chip">
                                            <?= !empty($row['combination']) ? e($row['combination']) : '—' ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span
                                            class="badge text-bg-<?= e($methodColor) ?> fw-normal"
                                            style="font-size:10px;border-radius:20px"
                                        >
                                            <?= e($methodLabel) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="score-badge <?= e($scoreClass) ?> fw-bold">
                                            <?= number_format((float)$row['score'], 2) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <a
                                            href="<?= url('university.php?id=' . $row['university_id']) ?>"
                                            class="btn btn-sm btn-outline-primary py-0 px-2"
                                        >
                                            Chi tiết
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php endif; ?>

    <?php elseif ($q !== ''): ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="bi bi-search me-2 text-primary"></i>
                    Kết quả cho
                    "<span class="text-primary"><?= e($q) ?></span>"
                </h4>

                <p class="text-muted small mb-0">
                    Tìm thấy
                    <strong><?= count($uniResults) ?></strong>
                    trường
                </p>
            </div>

            <a href="<?= url('index.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-x me-1"></i>
                Xóa tìm kiếm
            </a>
        </div>

        <?php if (empty($uniResults)): ?>

            <div class="empty-state text-center py-5">
                <div class="empty-state-icon">
                    <i class="bi bi-search"></i>
                </div>

                <h5 class="mt-3">Không tìm thấy trường nào</h5>

                <a
                    href="<?= url('index.php') ?>"
                    class="btn btn-outline-primary btn-sm mt-2"
                >
                    Quay lại
                </a>
            </div>

        <?php else: ?>

            <div class="row g-3">
                <?php foreach ($uniResults as $university): ?>
                    <?php render_uni_card($university); ?>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    <?php else: ?>

        <div
            id="featured-universities"
            class="<?= $tab === 'major' ? 'd-none' : '' ?>"
        >
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-1">
                        <i class="bi bi-building me-2 text-primary"></i>
                        <?= $showAll ? 'Danh sách trường đại học' : 'Trường đại học nổi bật' ?>
                    </h4>

                    <p class="text-muted small mb-0">
                        <?= count($featuredUnis) ?>
                        <?= $showAll ? 'trường trong hệ thống' : 'trường được chọn lọc' ?>
                        · Nhấn để xem điểm chuẩn
                    </p>
                </div>

                <?php if ($showAll): ?>
                    <a
                        href="<?= url('index.php#featured-universities') ?>"
                        class="btn btn-outline-secondary btn-sm"
                    >
                        Thu gọn
                        <i class="bi bi-arrow-up ms-1"></i>
                    </a>
                <?php else: ?>
                    <a
                        href="<?= url('index.php?show_all=1#featured-universities') ?>"
                        class="btn btn-outline-primary btn-sm"
                    >
                        Xem tất cả
                        <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                <?php endif; ?>
            </div>

            <div class="row g-3">
                <?php foreach ($featuredUnis as $university): ?>
                    <?php render_uni_card($university); ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div
            id="featured-majors"
            class="<?= $tab === 'major' ? '' : 'd-none' ?>"
        >
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-1">
                        <i class="bi bi-book me-2 text-primary"></i>
                        Ngành học nổi bật
                    </h4>

                    <p class="text-muted small mb-0">
                        <?= count($featuredMajors) ?>
                        ngành có nhiều dữ liệu tuyển sinh · Nhấn để xem điểm chuẩn
                    </p>
                </div>

                <a
                    href="<?= url('search.php') ?>"
                    class="btn btn-outline-primary btn-sm"
                >
                    Xem tất cả
                    <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>

            <div class="row g-3">
                <?php foreach ($featuredMajors as $major): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a
                            href="<?= url('index.php?major=' . $major['major_id']) ?>"
                            class="uni-card h-100 text-decoration-none"
                        >
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div
                                    class="uni-logo flex-shrink-0 d-flex align-items-center justify-content-center"
                                    style="width:48px;height:48px;font-size:20px"
                                >
                                    <i class="bi bi-book"></i>
                                </div>

                                <div class="overflow-hidden">
                                    <div
                                        class="fw-bold text-dark"
                                        style="font-size:13px;line-height:1.3"
                                    >
                                        <?= e($major['major_name']) ?>
                                    </div>

                                    <div class="text-muted" style="font-size:11px">
                                        <i class="bi bi-building me-1"></i>
                                        <?= (int)$major['university_count'] ?>
                                        trường đào tạo
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex gap-2 flex-wrap">
                                <span class="chip">
                                    <?= (int)$major['university_count'] ?> trường
                                </span>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <section class="mt-5 mb-5">
            <div class="section-heading text-center mb-4">
                <span class="section-label">
                    Khám phá hệ thống
                </span>

                <h3 class="fw-bold mt-2 mb-2">
                    Bạn đang cần tìm gì?
                </h3>

                <p class="text-muted mb-0">
                    Sử dụng các công cụ hỗ trợ tra cứu và lựa chọn trường đại học
                </p>
            </div>

            <div class="row g-3">
                <?php foreach ($actionItems as $item): ?>
                    <div class="col-md-4 reveal-up">
                        <a
                            href="<?= url($item['url']) ?>"
                            class="home-action-card text-decoration-none"
                        >
                            <div class="home-action-icon <?= e($item['icon_class']) ?>">
                                <i class="bi <?= e($item['icon']) ?>"></i>
                            </div>

                            <div>
                                <h5><?= e($item['title']) ?></h5>

                                <p><?= e($item['description']) ?></p>

                                <span class="home-action-link">
                                    <?= e($item['link_text']) ?>
                                    <i class="bi bi-arrow-right"></i>
                                </span>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

    <?php endif; ?>

</div>

<style>
/* ===== Bổ sung CSS cải tiến cho trang chủ — đồng bộ 1 màu chủ đạo, bớt rực ===== */

:root{
    --dc-accent:#4f46e5;
    --dc-accent-dark:#3730a3;
    --dc-accent-soft:#eef2ff;
}

/* Hero: bỏ gradient nhiều màu, dùng 1 màu liền */
.home-hero{
    background:linear-gradient(180deg, var(--dc-accent) 0%, var(--dc-accent-dark) 100%);
}
.home-gradient-text{
    background:none;
    color:#fff;
}
.home-hero-label{
    background:rgba(255,255,255,.12);
    color:#fff;
    border:1px solid rgba(255,255,255,.18);
    border-radius:20px;
    padding:6px 14px;
    font-size:13px;
    display:inline-block;
}

/* Card trường đại học */
.uni-card{
    display:block;
    background:#fff;
    border:1px solid #e9ecf2;
    border-radius:14px;
    padding:16px;
    transition:border-color .15s ease, box-shadow .15s ease, transform .15s ease;
}
.uni-card:hover{
    border-color:var(--dc-accent);
    box-shadow:0 4px 14px rgba(79,70,229,.08);
    transform:translateY(-2px);
}
.uni-logo{
    background:var(--dc-accent-soft);
    color:var(--dc-accent-dark);
    border-radius:10px;
}

/* Chip mặc định */
.chip{
    font-size:11px;
    background:#f3f4f8;
    color:#555;
    border-radius:20px;
    padding:3px 10px;
    line-height:1.4;
}


/* Nút submit & tab dùng 1 màu chủ đạo, tránh lệch tông với hero */
.btn-primary{
    background:var(--dc-accent);
    border-color:var(--dc-accent);
}
.btn-primary:hover{
    background:var(--dc-accent-dark);
    border-color:var(--dc-accent-dark);
}
.btn-outline-primary{
    color:var(--dc-accent);
    border-color:var(--dc-accent);
}
.btn-outline-primary:hover{
    background:var(--dc-accent);
    border-color:var(--dc-accent);
}
.text-primary{
    color:var(--dc-accent) !important;
}

/* Empty state gọn, dùng icon thay emoji để nhất quán bộ icon */
.empty-state-icon{
    font-size:40px;
    color:#c7cbd4;
}

/* Popular search pill theo màu chủ đạo */
.popular-search-btn{
    background:var(--dc-accent-soft);
    color:var(--dc-accent-dark);
    border:none;
    border-radius:20px;
    padding:6px 14px;
    font-size:12px;
    font-weight:500;
}
.popular-search-btn:hover{
    background:var(--dc-accent);
    color:#fff;
}
</style>

<script>
function switchHomeTab(tab) {
    const isUniversityTab = tab === 'uni';
    const formUni = document.getElementById('form-uni');
    const formMajor = document.getElementById('form-major');
    const tabUni = document.getElementById('tab-uni');
    const tabMajor = document.getElementById('tab-major');
    const featuredUniversities = document.getElementById('featured-universities');
    const featuredMajors = document.getElementById('featured-majors');

    formUni.classList.toggle('d-none', !isUniversityTab);
    formMajor.classList.toggle('d-none', isUniversityTab);

    tabUni.classList.toggle('btn-light', isUniversityTab);
    tabUni.classList.toggle('btn-outline-light', !isUniversityTab);
    tabMajor.classList.toggle('btn-light', !isUniversityTab);
    tabMajor.classList.toggle('btn-outline-light', isUniversityTab);

    featuredUniversities?.classList.toggle('d-none', !isUniversityTab);
    featuredMajors?.classList.toggle('d-none', isUniversityTab);
}

<?php if ($mid > 0): ?>
switchHomeTab('major');
<?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>
