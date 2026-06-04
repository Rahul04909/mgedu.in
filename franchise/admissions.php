<?php
/**
 * MG Education & Social Development Organization
 * Franchise Portal - Student Admissions Registry (Courses & Internships)
 */

include './header.php';

$db = MG_GetDBConnection();
$center_id = $_SESSION['center_id'];
$error_message = '';

// Active Tab state tracker ('course' or 'internship')
$active_tab = trim($_GET['tab'] ?? 'course');
if ($active_tab !== 'course' && $active_tab !== 'internship') {
    $active_tab = 'course';
}

// Filters for Course Tab
$c_search = trim($_GET['c_search'] ?? '');
$c_course_filter = isset($_GET['c_course_id']) ? intval($_GET['c_course_id']) : 0;
$c_status_filter = trim($_GET['c_status'] ?? '');
$c_page = isset($_GET['c_page']) ? max(1, intval($_GET['c_page'])) : 1;
$limit = 10;
$c_offset = ($c_page - 1) * $limit;

// Filters for Internship Tab
$i_search = trim($_GET['i_search'] ?? '');
$i_internship_filter = isset($_GET['i_internship_id']) ? intval($_GET['i_internship_id']) : 0;
$i_status_filter = trim($_GET['i_status'] ?? '');
$i_page = isset($_GET['i_page']) ? max(1, intval($_GET['i_page'])) : 1;
$i_offset = ($i_page - 1) * $limit;

try {
    // 1. Fetch Lists for Dropdowns
    $courses_stmt = $db->query("SELECT id, name FROM courses ORDER BY name ASC");
    $courses_list = $courses_stmt->fetchAll();

    $internships_stmt = $db->query("SELECT id, name FROM internships ORDER BY name ASC");
    $internships_list = $internships_stmt->fetchAll();

    // 2. Fetch COURSE Admissions
    $c_where = ["a.added_by = :center_id"];
    $c_params = ['center_id' => $center_id];

    if (!empty($c_search)) {
        $c_where[] = "(a.student_name LIKE :c_search OR a.enrollment_number LIKE :c_search OR a.email LIKE :c_search OR a.mobile LIKE :c_search OR a.father_name LIKE :c_search)";
        $c_params['c_search'] = "%{$c_search}%";
    }
    if ($c_course_filter > 0) {
        $c_where[] = "a.course_id = :c_course_id";
        $c_params['c_course_id'] = $c_course_filter;
    }
    if (!empty($c_status_filter)) {
        $c_where[] = "a.payment_status = :c_status";
        $c_params['c_status'] = $c_status_filter;
    }

    $c_where_sql = " WHERE " . implode(" AND ", $c_where);
    
    // Count Course admissions
    $c_count_stmt = $db->prepare("SELECT COUNT(*) FROM admissions a" . $c_where_sql);
    $c_count_stmt->execute($c_params);
    $c_total_records = intval($c_count_stmt->fetchColumn());
    $c_total_pages = ceil($c_total_records / $limit);

    // Get Course records
    $c_query = "
        SELECT a.*, c.name as course_name 
        FROM admissions a
        LEFT JOIN courses c ON a.course_id = c.id
        " . $c_where_sql . "
        ORDER BY a.id DESC 
        LIMIT :limit OFFSET :offset
    ";
    $c_stmt = $db->prepare($c_query);
    foreach ($c_params as $key => $val) {
        $c_stmt->bindValue($key, $val);
    }
    $c_stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $c_stmt->bindValue('offset', $c_offset, PDO::PARAM_INT);
    $c_stmt->execute();
    $course_admissions = $c_stmt->fetchAll();

    // 3. Fetch INTERNSHIP Admissions
    $i_where = ["a.added_by = :center_id"];
    $i_params = ['center_id' => $center_id];

    if (!empty($i_search)) {
        $i_where[] = "(a.student_name LIKE :i_search OR a.enrollment_number LIKE :i_search OR a.email LIKE :i_search OR a.mobile LIKE :i_search OR a.father_name LIKE :i_search)";
        $i_params['i_search'] = "%{$i_search}%";
    }
    if ($i_internship_filter > 0) {
        $i_where[] = "a.internship_id = :i_internship_id";
        $i_params['i_internship_id'] = $i_internship_filter;
    }
    if (!empty($i_status_filter)) {
        $i_where[] = "a.payment_status = :i_status";
        $i_params['i_status'] = $i_status_filter;
    }

    $i_where_sql = " WHERE " . implode(" AND ", $i_where);

    // Count Internship admissions
    $i_count_stmt = $db->prepare("SELECT COUNT(*) FROM internship_admissions a" . $i_where_sql);
    $i_count_stmt->execute($i_params);
    $i_total_records = intval($i_count_stmt->fetchColumn());
    $i_total_pages = ceil($i_total_records / $limit);

    // Get Internship records
    $i_query = "
        SELECT a.*, i.name as internship_name 
        FROM internship_admissions a
        LEFT JOIN internships i ON a.internship_id = i.id
        " . $i_where_sql . "
        ORDER BY a.id DESC 
        LIMIT :limit OFFSET :offset
    ";
    $i_stmt = $db->prepare($i_query);
    foreach ($i_params as $key => $val) {
        $i_stmt->bindValue($key, $val);
    }
    $i_stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $i_stmt->bindValue('offset', $i_offset, PDO::PARAM_INT);
    $i_stmt->execute();
    $internship_admissions = $i_stmt->fetchAll();

} catch (Exception $e) {
    $error_message = $e->getMessage();
    $course_admissions = [];
    $internship_admissions = [];
    $c_total_records = 0;
    $i_total_records = 0;
    $c_total_pages = 0;
    $i_total_pages = 0;
}
?>

<style>
    .card-premium {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        transition: all 0.3s ease;
        overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    .nav-pills-premium .nav-link {
        color: #475569;
        font-weight: 600;
        font-size: 14px;
        padding: 10px 20px;
        border-radius: 8px;
        transition: all 0.2s;
    }
    .nav-pills-premium .nav-link.active {
        background-color: #28a745 !important;
        color: #ffffff;
    }
    .search-input-premium {
        border: 1px solid #cbd5e1;
        border-radius: 50px;
        padding: 8px 18px;
        font-size: 13.5px;
        transition: all 0.3s ease;
    }
    .search-input-premium:focus {
        border-color: #28a745;
        outline: none;
        box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.15);
    }
    .select-premium {
        border: 1px solid #cbd5e1;
        border-radius: 50px;
        padding: 8px 16px;
        font-size: 13.5px;
        background-color: #fff;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    .select-premium:focus {
        border-color: #28a745;
        outline: none;
    }
    .table-premium th {
        background-color: #f8fafc;
        color: #1e293b;
        font-weight: 700;
        font-size: 11.5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e2e8f0;
        padding: 14px 16px;
    }
    .table-premium td {
        padding: 14px 16px;
        vertical-align: middle;
        font-size: 13.5px;
        border-bottom: 1px solid #e2e8f0;
        color: #334155;
    }
    .badge-premium {
        border-radius: 50px;
        padding: 4px 12px;
        font-size: 10.5px;
        font-weight: 700;
        text-transform: uppercase;
        display: inline-block;
    }
    .badge-paid {
        background-color: rgba(40, 167, 69, 0.1);
        color: #28a745;
        border: 1px solid rgba(40, 167, 69, 0.2);
    }
    .badge-pending {
        background-color: rgba(255, 193, 7, 0.1);
        color: #d97706;
        border: 1px solid rgba(255, 193, 7, 0.25);
    }
    .badge-failed {
        background-color: rgba(220, 53, 69, 0.08);
        color: #dc3545;
        border: 1px solid rgba(220, 53, 69, 0.15);
    }
    .btn-action-circle {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #ffffff !important;
        border: none;
        transition: all 0.2s ease;
        text-decoration: none;
        cursor: pointer;
    }
    .btn-view-circle {
        background-color: #28a745;
    }
    .btn-view-circle:hover {
        background-color: #218838;
        transform: scale(1.05);
    }
    .btn-receipt-circle {
        background-color: #17a2b8;
    }
    .btn-receipt-circle:hover {
        background-color: #138496;
        transform: scale(1.05);
    }
    .modal-premium {
        border-radius: 12px;
        overflow: hidden;
        border: none;
    }
    .modal-premium .modal-header {
        background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        color: #ffffff;
        border-bottom: none;
        padding: 16px 24px;
    }
    .modal-premium .modal-title {
        font-weight: 700;
        font-size: 15px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .modal-premium .modal-body {
        padding: 24px;
    }
    .profile-section-title {
        font-size: 12.5px;
        font-weight: 700;
        color: #28a745;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e8f5e9;
        padding-bottom: 6px;
        margin-bottom: 16px;
        margin-top: 24px;
    }
    .profile-section-title:first-of-type {
        margin-top: 0;
    }
    .profile-detail-row {
        margin-bottom: 12px;
    }
    .profile-detail-label {
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        margin-bottom: 2px;
    }
    .profile-detail-value {
        font-size: 13px;
        color: #1e293b;
        font-weight: 600;
    }
    .doc-link-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background-color: #f1f5f9;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        color: #475569 !important;
        font-weight: 700;
        font-size: 10px;
        text-transform: uppercase;
        text-decoration: none;
        transition: all 0.2s;
    }
    .doc-link-badge:hover {
        background-color: #e2e8f0;
        border-color: #94a3b8;
    }
</style>

<div class="row pt-3">
    <div class="col-12">
        <div class="card card-premium">
            <div class="card-header bg-white p-3 border-0">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <ul class="nav nav-pills nav-pills-premium" id="pills-tab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?= ($active_tab === 'course') ? 'active' : '' ?>" href="admissions.php?tab=course">
                                <i class="fa-solid fa-graduation-cap mr-2"></i> Course Admissions
                                <span class="badge ml-2 <?= ($active_tab === 'course') ? 'badge-light text-success' : 'badge-success' ?>"><?= $c_total_records ?></span>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?= ($active_tab === 'internship') ? 'active' : '' ?>" href="admissions.php?tab=internship">
                                <i class="fa-solid fa-briefcase mr-2"></i> Internship Admissions
                                <span class="badge ml-2 <?= ($active_tab === 'internship') ? 'badge-light text-success' : 'badge-success' ?>"><?= $i_total_records ?></span>
                            </a>
                        </li>
                    </ul>
                    
                    <div>
                        <a href="<?= ($active_tab === 'course') ? 'enroll-course.php' : 'enroll-internship.php' ?>" class="btn btn-success px-4 rounded-pill font-weight-bold shadow-sm">
                            <i class="fa-solid fa-user-plus mr-1"></i> Enroll Student
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="tab-content" id="pills-tabContent">
                    
                    <!-- TAB 1: COURSE ADMISSIONS -->
                    <div class="tab-pane fade show active" id="pills-course" role="tabpanel">
                        <?php if ($active_tab === 'course'): ?>
                            <div class="p-3 bg-light border-top border-bottom">
                                <form method="GET" action="" class="mb-0 row g-2 align-items-center">
                                    <input type="hidden" name="tab" value="course">
                                    <div class="col-md-3">
                                        <select name="c_course_id" class="select-premium w-100" onchange="this.form.submit()">
                                            <option value="0">All Courses</option>
                                            <?php foreach ($courses_list as $crs): ?>
                                                <option value="<?= $crs['id'] ?>" <?= ($c_course_filter == $crs['id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($crs['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="c_status" class="select-premium w-100" onchange="this.form.submit()">
                                            <option value="">All Fee Statuses</option>
                                            <option value="paid" <?= ($c_status_filter === 'paid') ? 'selected' : '' ?>>Paid</option>
                                            <option value="pending" <?= ($c_status_filter === 'pending') ? 'selected' : '' ?>>Pending</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <input type="text" name="c_search" class="search-input-premium w-100" placeholder="Search candidate..." value="<?= htmlspecialchars($c_search) ?>">
                                    </div>
                                    <div class="col-md-2 d-flex gap-2">
                                        <button type="submit" class="btn btn-success rounded-pill px-3 py-2 w-100"><i class="fa-solid fa-magnifying-glass mr-1"></i> Search</button>
                                        <?php if(!empty($c_search) || $c_course_filter > 0 || !empty($c_status_filter)): ?>
                                            <a href="admissions.php?tab=course" class="btn btn-secondary rounded-pill px-3 py-2" title="Reset Filters"><i class="fa-solid fa-arrows-rotate"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-premium">
                                    <thead>
                                        <tr>
                                            <th>Enrollment No</th>
                                            <th>Student Details</th>
                                            <th>Applied Course</th>
                                            <th>Parentage</th>
                                            <th class="text-center">Fee Status</th>
                                            <th class="text-center" style="width: 120px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($course_admissions)): ?>
                                            <?php foreach ($course_admissions as $row): ?>
                                                <tr>
                                                    <td>
                                                        <code class="text-primary font-weight-bold" style="font-size: 13.5px;"><?= htmlspecialchars($row['enrollment_number']) ?></code>
                                                    </td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($row['student_name']) ?></strong><br>
                                                        <span class="text-muted" style="font-size:12px;"><i class="fa-solid fa-envelope mr-1"></i> <?= htmlspecialchars($row['email']) ?></span>
                                                    </td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($row['course_name'] ?: 'N/A') ?></strong><br>
                                                        <small class="text-muted">Session: <?= htmlspecialchars($row['session_name']) ?></small>
                                                    </td>
                                                    <td><?= htmlspecialchars($row['father_name']) ?></td>
                                                    <td class="text-center">
                                                        <span class="badge-premium badge-<?= strtolower($row['payment_status']) ?>">
                                                            <?= htmlspecialchars($row['payment_status']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="d-flex justify-content-center gap-2">
                                                            <button class="btn-action-circle btn-view-circle" onclick="viewCourseDetails(<?= htmlspecialchars(json_encode($row)) ?>)" title="View Full Profile">
                                                                <i class="fa-solid fa-eye"></i>
                                                            </button>
                                                            <?php 
                                                            $receiptPath = '../assets/uploads/admissions/receipts/receipt_' . $row['id'] . '.pdf';
                                                            if (file_exists(dirname(__DIR__) . '/' . str_replace('../', '', $row['student_photo'] ?? ''))): 
                                                            ?>
                                                            <a href="../assets/uploads/admissions/receipts/receipt_<?= $row['id'] ?>.pdf" target="_blank" class="btn-action-circle btn-receipt-circle" title="Download Receipt">
                                                                <i class="fa-solid fa-file-pdf"></i>
                                                            </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-5">
                                                    <i class="fa-solid fa-folder-open mb-3" style="font-size: 40px; color: #cbd5e1; display: block;"></i>
                                                    No course admissions found enroled by this center.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($c_total_pages > 1): ?>
                                <div class="card-footer bg-white d-flex justify-content-between align-items-center px-4 py-3 border-top">
                                    <span class="text-muted" style="font-size: 13px;">
                                        Showing <?= $c_offset + 1 ?> to <?= min($c_offset + $limit, $c_total_records) ?> of <?= $c_total_records ?> entries
                                    </span>
                                    <ul class="pagination pagination-sm m-0">
                                        <li class="page-item <?= ($c_page <= 1) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="admissions.php?tab=course&c_page=<?= $c_page - 1 ?>&c_search=<?= urlencode($c_search) ?>&c_course_id=<?= $c_course_filter ?>&c_status=<?= urlencode($c_status_filter) ?>">Previous</a>
                                        </li>
                                        <?php for ($i = 1; $i <= $c_total_pages; $i++): ?>
                                            <li class="page-item <?= ($c_page == $i) ? 'active' : '' ?>">
                                                <a class="page-link" href="admissions.php?tab=course&c_page=<?= $i ?>&c_search=<?= urlencode($c_search) ?>&c_course_id=<?= $c_course_filter ?>&c_status=<?= urlencode($c_status_filter) ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= ($c_page >= $c_total_pages) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="admissions.php?tab=course&c_page=<?= $c_page + 1 ?>&c_search=<?= urlencode($c_search) ?>&c_course_id=<?= $c_course_filter ?>&c_status=<?= urlencode($c_status_filter) ?>">Next</a>
                                        </li>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- TAB 2: INTERNSHIP ADMISSIONS -->
                    <div class="tab-pane fade show active" id="pills-internship" role="tabpanel">
                        <?php if ($active_tab === 'internship'): ?>
                            <div class="p-3 bg-light border-top border-bottom">
                                <form method="GET" action="" class="mb-0 row g-2 align-items-center">
                                    <input type="hidden" name="tab" value="internship">
                                    <div class="col-md-3">
                                        <select name="i_internship_id" class="select-premium w-100" onchange="this.form.submit()">
                                            <option value="0">All Internships</option>
                                            <?php foreach ($internships_list as $int): ?>
                                                <option value="<?= $int['id'] ?>" <?= ($i_internship_filter == $int['id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($int['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select name="i_status" class="select-premium w-100" onchange="this.form.submit()">
                                            <option value="">All Fee Statuses</option>
                                            <option value="paid" <?= ($i_status_filter === 'paid') ? 'selected' : '' ?>>Paid</option>
                                            <option value="pending" <?= ($i_status_filter === 'pending') ? 'selected' : '' ?>>Pending</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <input type="text" name="i_search" class="search-input-premium w-100" placeholder="Search candidate..." value="<?= htmlspecialchars($i_search) ?>">
                                    </div>
                                    <div class="col-md-2 d-flex gap-2">
                                        <button type="submit" class="btn btn-success rounded-pill px-3 py-2 w-100"><i class="fa-solid fa-magnifying-glass mr-1"></i> Search</button>
                                        <?php if(!empty($i_search) || $i_internship_filter > 0 || !empty($i_status_filter)): ?>
                                            <a href="admissions.php?tab=internship" class="btn btn-secondary rounded-pill px-3 py-2" title="Reset Filters"><i class="fa-solid fa-arrows-rotate"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-premium">
                                    <thead>
                                        <tr>
                                            <th>Enrollment No</th>
                                            <th>Student Details</th>
                                            <th>Internship Domain</th>
                                            <th>Parentage</th>
                                            <th class="text-center">Fee Status</th>
                                            <th class="text-center" style="width: 120px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($internship_admissions)): ?>
                                            <?php foreach ($internship_admissions as $row): ?>
                                                <tr>
                                                    <td>
                                                        <code class="text-primary font-weight-bold" style="font-size: 13.5px;"><?= htmlspecialchars($row['enrollment_number']) ?></code>
                                                    </td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($row['student_name']) ?></strong><br>
                                                        <span class="text-muted" style="font-size:12px;"><i class="fa-solid fa-envelope mr-1"></i> <?= htmlspecialchars($row['email']) ?></span>
                                                    </td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($row['internship_name'] ?: 'N/A') ?></strong><br>
                                                        <small class="text-muted">Session: <?= htmlspecialchars($row['session_name']) ?></small>
                                                    </td>
                                                    <td><?= htmlspecialchars($row['father_name']) ?></td>
                                                    <td class="text-center">
                                                        <span class="badge-premium badge-<?= strtolower($row['payment_status']) ?>">
                                                            <?= htmlspecialchars($row['payment_status']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="d-flex justify-content-center gap-2">
                                                            <button class="btn-action-circle btn-view-circle" onclick="viewInternshipDetails(<?= htmlspecialchars(json_encode($row)) ?>)" title="View Full Profile">
                                                                <i class="fa-solid fa-eye"></i>
                                                            </button>
                                                            <a href="../assets/uploads/admissions/receipts/receipt_<?= $row['id'] ?>.pdf" target="_blank" class="btn-action-circle btn-receipt-circle" title="Download Receipt">
                                                                <i class="fa-solid fa-file-pdf"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-5">
                                                    <i class="fa-solid fa-folder-open mb-3" style="font-size: 40px; color: #cbd5e1; display: block;"></i>
                                                    No internship admissions found enroled by this center.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($i_total_pages > 1): ?>
                                <div class="card-footer bg-white d-flex justify-content-between align-items-center px-4 py-3 border-top">
                                    <span class="text-muted" style="font-size: 13px;">
                                        Showing <?= $i_offset + 1 ?> to <?= min($i_offset + $limit, $i_total_records) ?> of <?= $i_total_records ?> entries
                                    </span>
                                    <ul class="pagination pagination-sm m-0">
                                        <li class="page-item <?= ($i_page <= 1) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="admissions.php?tab=internship&i_page=<?= $i_page - 1 ?>&i_search=<?= urlencode($i_search) ?>&i_internship_id=<?= $i_internship_filter ?>&i_status=<?= urlencode($i_status_filter) ?>">Previous</a>
                                        </li>
                                        <?php for ($k = 1; $k <= $i_total_pages; $k++): ?>
                                            <li class="page-item <?= ($i_page == $k) ? 'active' : '' ?>">
                                                <a class="page-link" href="admissions.php?tab=internship&i_page=<?= $k ?>&i_search=<?= urlencode($i_search) ?>&i_internship_id=<?= $i_internship_filter ?>&i_status=<?= urlencode($i_status_filter) ?>"><?= $k ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= ($i_page >= $i_total_pages) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="admissions.php?tab=internship&i_page=<?= $i_page + 1 ?>&i_search=<?= urlencode($i_search) ?>&i_internship_id=<?= $i_internship_filter ?>&i_status=<?= urlencode($i_status_filter) ?>">Next</a>
                                        </li>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<!-- PROFILE DETAILS MODAL -->
<div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-premium">
            <div class="modal-header">
                <h5 class="modal-title" id="profileModalLabel"><i class="fa-solid fa-user-graduate mr-2"></i> Student Candidate Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4 text-center border-right">
                        <img id="m_photo" src="" alt="Student Portrait" class="img-thumbnail rounded-circle mb-3 shadow-sm" style="width: 150px; height: 150px; object-fit: cover;">
                        <h4 class="font-weight-bold mb-1" id="m_name" style="color: #2c3e50; font-size: 18px;"></h4>
                        <code class="text-primary font-weight-bold d-block mb-3" id="m_enrollment" style="font-size: 14px;"></code>
                        
                        <div class="profile-detail-row text-center mt-4">
                            <span class="profile-detail-label">Signature Scan</span>
                            <div class="p-2 border rounded bg-light mt-1">
                                <img id="m_signature" src="" alt="Student Signature" style="max-width: 150px; max-height: 60px; object-fit: contain;">
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8 pl-4">
                        <div class="profile-section-title">Program Specifications</div>
                        <div class="row">
                            <div class="col-6 profile-detail-row">
                                <div class="profile-detail-label" id="m_program_label">Target Course / Division</div>
                                <div class="profile-detail-value" id="m_program"></div>
                            </div>
                            <div class="col-6 profile-detail-row">
                                <div class="profile-detail-label">Academic Session</div>
                                <div class="profile-detail-value" id="m_session"></div>
                            </div>
                        </div>

                        <div class="profile-section-title">Biographical & Parental Details</div>
                        <div class="row">
                            <div class="col-6 profile-detail-row">
                                <div class="profile-detail-label">Gender</div>
                                <div class="profile-detail-value" id="m_gender"></div>
                            </div>
                            <div class="col-6 profile-detail-row">
                                <div class="profile-detail-label">Date of Birth</div>
                                <div class="profile-detail-value" id="m_dob"></div>
                            </div>
                            <div class="col-6 profile-detail-row mt-2">
                                <div class="profile-detail-label">Father's Name</div>
                                <div class="profile-detail-value" id="m_father"></div>
                            </div>
                            <div class="col-6 profile-detail-row mt-2">
                                <div class="profile-detail-label">Mother's Name</div>
                                <div class="profile-detail-value" id="m_mother"></div>
                            </div>
                        </div>

                        <div class="profile-section-title">Contact & Geographic Location</div>
                        <div class="row">
                            <div class="col-6 profile-detail-row">
                                <div class="profile-detail-label">Mobile Number</div>
                                <div class="profile-detail-value" id="m_mobile"></div>
                            </div>
                            <div class="col-6 profile-detail-row">
                                <div class="profile-detail-label">Email Address</div>
                                <div class="profile-detail-value" id="m_email"></div>
                            </div>
                            <div class="col-12 profile-detail-row mt-2">
                                <div class="profile-detail-label">Permanent Address</div>
                                <div class="profile-detail-value" id="m_address"></div>
                            </div>
                        </div>

                        <div class="profile-section-title">Academic Qualifications & Scanned Files</div>
                        <div class="row">
                            <div class="col-6 profile-detail-row">
                                <div class="profile-detail-label">Highest Standard</div>
                                <div class="profile-detail-value" id="m_qualification"></div>
                            </div>
                            <div class="col-6 profile-detail-row">
                                <div class="profile-detail-label">Aadhaar Card No</div>
                                <div class="profile-detail-value" id="m_aadhaar"></div>
                            </div>
                        </div>
                        
                        <div class="d-flex flex-wrap gap-2 mt-3" id="m_documents_container">
                            <!-- Document badges will be rendered here dynamically -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary px-4 rounded-pill font-weight-bold" data-bs-dismiss="modal">Close Profile</button>
            </div>
        </div>
    </div>
</div>

<script>
    function openProfileModal(data, type) {
        // Map details
        document.getElementById('m_photo').src = '../' + data.student_photo;
        document.getElementById('m_signature').src = '../' + data.student_signature;
        document.getElementById('m_name').innerText = data.student_name;
        document.getElementById('m_enrollment').innerText = data.enrollment_number;
        
        if (type === 'course') {
            document.getElementById('m_program_label').innerText = 'Target Course Division';
            document.getElementById('m_program').innerText = data.course_name;
        } else {
            document.getElementById('m_program_label').innerText = 'Internship Domain';
            document.getElementById('m_program').innerText = data.internship_name;
        }
        
        document.getElementById('m_session').innerText = data.session_name;
        document.getElementById('m_gender').innerText = data.gender;
        document.getElementById('m_dob').innerText = data.dob;
        document.getElementById('m_father').innerText = data.father_name;
        document.getElementById('m_mother').innerText = data.mother_name;
        document.getElementById('m_mobile').innerText = data.mobile;
        document.getElementById('m_email').innerText = data.email;
        document.getElementById('m_address').innerText = `${data.address}, ${data.city}, ${data.district}, ${data.state} - ${data.pincode}`;
        document.getElementById('m_qualification').innerText = formatQualification(data.highest_qualification);
        document.getElementById('m_aadhaar').innerText = data.aadhaar_number;
        
        // Build document downloads list
        const docCont = document.getElementById('m_documents_container');
        docCont.innerHTML = '';
        
        if (data.aadhaar_card) {
            docCont.innerHTML += `<a href="../${data.aadhaar_card}" target="_blank" class="doc-link-badge"><i class="fa-solid fa-address-card mr-1"></i> Aadhaar PDF</a>`;
        }
        if (data.class_10_marksheet) {
            docCont.innerHTML += `<a href="../${data.class_10_marksheet}" target="_blank" class="doc-link-badge"><i class="fa-solid fa-graduation-cap mr-1"></i> Class 10th PDF</a>`;
        }
        if (data.class_12_marksheet) {
            docCont.innerHTML += `<a href="../${data.class_12_marksheet}" target="_blank" class="doc-link-badge"><i class="fa-solid fa-school mr-1"></i> Class 12th PDF</a>`;
        }
        if (data.college_marksheet) {
            docCont.innerHTML += `<a href="../${data.college_marksheet}" target="_blank" class="doc-link-badge"><i class="fa-solid fa-university mr-1"></i> Collegiate PDF</a>`;
        }
        
        // Show modal
        var myModal = new bootstrap.Modal(document.getElementById('profileModal'), {});
        myModal.show();
    }
    
    function viewCourseDetails(data) {
        openProfileModal(data, 'course');
    }
    
    function viewInternshipDetails(data) {
        openProfileModal(data, 'internship');
    }
    
    function formatQualification(q) {
        const mappings = {
            'below_10th_5': 'Below 10th (5th Standard)',
            'below_10th_8': 'Below 10th (8th Standard)',
            'class_10th': 'Matriculation (10th Standard)',
            'class_12th': 'Senior Secondary (12th Standard)',
            'graduation': 'Undergraduate (Graduation)',
            'post_graduation': "Postgraduate (Master's)",
            'phd': 'Doctorate (Ph.D.)'
        };
        return mappings[q] || q;
    }
</script>

<?php include './footer.php'; ?>
