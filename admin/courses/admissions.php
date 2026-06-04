<?php
/**
 * MG Education & Social Development Organization
 * Admissions Management Console Dashboard
 */

include '../header.php';

$db = MG_GetDBConnection();
$error_message = '';
$success_message = '';

// CSRF Token generation if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 1. Handle Record Deletion
if (isset($_GET['delete_id'])) {
    try {
        $delete_id = intval($_GET['delete_id']);
        
        // CSRF verification on delete
        if (!isset($_GET['token']) || !hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
            throw new Exception("Security mismatch: CSRF validation failed.");
        }

        $stmt = $db->prepare("DELETE FROM admissions WHERE id = :id");
        $stmt->execute(['id' => $delete_id]);
        
        $success_message = "Admission record deleted successfully!";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Record Removed',
                    text: 'The student admission file was successfully deleted from records.',
                    confirmButtonColor: '#0d47a1'
                }).then(() => {
                    window.location.href = 'admissions.php';
                });
            });
        </script>";
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}



// Fetch dynamic courses for the filter dropdown
try {
    $course_stmt = $db->query("SELECT id, name FROM courses ORDER BY name ASC");
    $courses_list = $course_stmt->fetchAll();
} catch (Exception $e) {
    $courses_list = [];
}

// Fetch franchise centers for filter
try {
    $centers_stmt = $db->query("SELECT center_id, center_name FROM franchise_centers ORDER BY center_name ASC");
    $centers_list = $centers_stmt->fetchAll();
} catch (Exception $e) {
    $centers_list = [];
}

// 3. Setup Filter Parameters & Pagination
$search = trim($_GET['search'] ?? '');
$course_filter = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$status_filter = trim($_GET['payment_status'] ?? '');
$source_filter = trim($_GET['added_by'] ?? '');

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // Build queries dynamically
    $where_clauses = [];
    $params = [];

    if (!empty($search)) {
        $where_clauses[] = "(a.student_name LIKE :search OR a.enrollment_number LIKE :search OR a.email LIKE :search OR a.mobile LIKE :search OR a.father_name LIKE :search)";
        $params['search'] = "%{$search}%";
    }

    if ($course_filter > 0) {
        $where_clauses[] = "a.course_id = :course_id";
        $params['course_id'] = $course_filter;
    }

    if (!empty($status_filter)) {
        $where_clauses[] = "a.payment_status = :payment_status";
        $params['payment_status'] = $status_filter;
    }

    if (!empty($source_filter)) {
        $where_clauses[] = "a.added_by = :added_by";
        $params['added_by'] = $source_filter;
    }

    $where_sql = "";
    if (!empty($where_clauses)) {
        $where_sql = " WHERE " . implode(" AND ", $where_clauses);
    }

    // Get Total Count for Pagination
    $count_sql = "SELECT COUNT(*) FROM admissions a" . $where_sql;
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = intval($count_stmt->fetchColumn());
    $total_pages = ceil($total_records / $limit);

    // Get Paginated Data
    $query = "
        SELECT a.*, c.name as course_name, fc.center_name AS franchise_name
        FROM admissions a
        LEFT JOIN courses c ON a.course_id = c.id
        LEFT JOIN franchise_centers fc ON a.added_by = fc.center_id
        " . $where_sql . " 
        ORDER BY a.id DESC 
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $admissions = $stmt->fetchAll();

} catch (Exception $e) {
    $error_message = $e->getMessage();
    $admissions = [];
    $total_records = 0;
    $total_pages = 0;
}
?>

<style>
    .admin-card {
        background: #ffffff;
        border: 1px solid #d1d7dc;
        border-radius: 12px;
        transition: all 0.3s ease;
        overflow: hidden;
    }
    .admin-card:hover {
        border-color: #8c9096;
        box-shadow: 0 6px 16px rgba(0,0,0,0.04);
    }
    .admin-header {
        border-bottom: 1px solid #f0f0f1;
        padding: 20px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    .admin-header h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
        color: #2c3e50;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .admin-body {
        padding: 25px;
    }
    .search-input {
        border: 1px solid #d1d7dc;
        border-radius: 50px;
        padding: 8px 18px;
        font-size: 14px;
        width: 220px;
        transition: all 0.3s ease;
    }
    .search-input:focus {
        border-color: #0d47a1;
        outline: none;
        box-shadow: 0 0 0 3px rgba(13, 71, 161, 0.1);
    }
    .select-premium {
        border: 1px solid #d1d7dc;
        border-radius: 50px;
        padding: 8px 16px;
        font-size: 14px;
        background-color: #fff;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    .select-premium:focus {
        border-color: #0d47a1;
        outline: none;
    }

    .table-premium {
        width: 100%;
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
    }
    .table-premium th {
        background-color: #f8f9fa;
        color: #2c3e50;
        font-weight: 600;
        font-size: 12px;
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
        color: #2c3e50;
    }
    .table-premium tr:hover td {
        background-color: #fcfdfd;
    }

    .badge-premium {
        border-radius: 50px;
        padding: 4px 12px;
        font-size: 10.5px;
        font-weight: 600;
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
        color: #fd7e14;
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
        color: #fff !important;
        border: none;
        transition: all 0.2s ease;
        text-decoration: none;
        cursor: pointer;
    }
    .action-buttons-group {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 6px;
        flex-wrap: nowrap;
        white-space: nowrap;
    }
    .btn-view-circle {
        background-color: #0d47a1;
    }
    .btn-view-circle:hover {
        background-color: #0a3b84;
        transform: scale(1.05);
    }
    .btn-edit-circle {
        background-color: #4a5568;
    }
    .btn-edit-circle:hover {
        background-color: #2d3748;
        transform: scale(1.05);
    }
    .btn-delete-circle {
        background-color: #dc3545;
    }
    .btn-delete-circle:hover {
        background-color: #bd2130;
        transform: scale(1.05);
    }

    /* Modal Styling Adjustments */
    .modal-premium {
        border-radius: 14px;
        overflow: hidden;
        border: none;
    }
    .modal-premium .modal-header {
        background: linear-gradient(135deg, #0d47a1 0%, #1a73e8 100%);
        color: #ffffff;
        border-bottom: none;
        padding: 16px 24px;
    }
    .modal-premium .modal-title {
        font-weight: 700;
        font-size: 16px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .modal-premium .modal-body {
        padding: 24px;
    }
    .modal-premium .close-btn {
        color: #ffffff;
        background: none;
        border: none;
        font-size: 20px;
        cursor: pointer;
        opacity: 0.8;
        transition: opacity 0.2s;
    }
    .modal-premium .close-btn:hover {
        opacity: 1;
    }
    .profile-section-title {
        font-size: 13px;
        font-weight: 700;
        color: #0d47a1;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e7f1ff;
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
        font-size: 11.5px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        margin-bottom: 2px;
    }
    .profile-detail-value {
        font-size: 13.5px;
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
        font-size: 11px;
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
        <div class="admin-card">
            <div class="admin-header">
                <h3>Student Admissions Registry</h3>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <form method="GET" action="" class="mb-0 d-flex gap-2 align-items-center flex-wrap">
                        <select name="course_id" class="select-premium" onchange="this.form.submit()">
                            <option value="0">All Courses</option>
                            <?php foreach ($courses_list as $crs): ?>
                                <option value="<?= $crs['id'] ?>" <?= ($course_filter == $crs['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($crs['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="added_by" class="select-premium" onchange="this.form.submit()">
                            <option value="">All Sources</option>
                            <option value="direct" <?= ($source_filter === 'direct') ? 'selected' : '' ?>>Direct (Online)</option>
                            <option value="admin" <?= ($source_filter === 'admin') ? 'selected' : '' ?>>Admin Direct</option>
                            <?php foreach ($centers_list as $ctr): ?>
                                <option value="<?= htmlspecialchars($ctr['center_id']) ?>" <?= ($source_filter === $ctr['center_id']) ? 'selected' : '' ?>><?= htmlspecialchars($ctr['center_name']) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="payment_status" class="select-premium" onchange="this.form.submit()">
                            <option value="">All Fee Statuses</option>
                            <option value="paid" <?= ($status_filter === 'paid') ? 'selected' : '' ?>>Paid</option>
                            <option value="pending" <?= ($status_filter === 'pending') ? 'selected' : '' ?>>Pending</option>
                            <option value="failed" <?= ($status_filter === 'failed') ? 'selected' : '' ?>>Failed</option>
                        </select>
                        
                        <input type="text" name="search" class="search-input" placeholder="Search candidate..." value="<?= htmlspecialchars($search) ?>">
                        
                        <button type="submit" class="btn btn-dark btn-sm rounded-pill px-3 py-2"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
                        
                        <?php if(!empty($search) || $course_filter > 0 || !empty($status_filter) || !empty($source_filter)): ?>
                            <a href="admissions.php" class="btn btn-secondary btn-sm rounded-pill px-3 py-2" title="Reset Filters"><i class="fa-solid fa-arrows-rotate"></i> Reset</a>
                        <?php endif; ?>
                    </form>
                    
                    <a href="enroll.php" class="btn btn-primary btn-sm rounded-pill px-4 py-2 font-weight-bold shadow-sm"><i class="fa-solid fa-user-plus mr-1"></i> Enroll Student</a>
                </div>
            </div>
            
            <div class="admin-body p-0">
                <div class="table-responsive">
                    <table class="table table-premium">
                        <thead>
                            <tr>
                                <th>Enrollment No</th>
                                <th>Student Name</th>
                                <th>Registered By</th>
                                <th>Father's Name</th>
                                <th>Contact Information</th>
                                <th>Applied Course</th>
                                <th class="text-center">Fees Status</th>
                                <th style="width: 150px;" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($admissions)): ?>
                                <?php foreach ($admissions as $admission): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($admission['enrollment_number'])): ?>
                                                <code class="text-primary font-weight-bold" style="font-size: 13.5px;"><?= htmlspecialchars($admission['enrollment_number']) ?></code>
                                            <?php else: ?>
                                                <span class="badge badge-warning text-dark px-2 py-1 rounded">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($admission['student_name']) ?></strong><br>
                                            <small class="text-muted">DOB: <?= date('d M Y', strtotime($admission['dob'])) ?></small>
                                        </td>
                                        <td>
                                            <?php if ($admission['added_by'] === 'direct' || empty($admission['added_by'])): ?>
                                                <span class="badge badge-secondary px-2 py-1 rounded" style="font-size: 10px;">Direct (Online)</span>
                                            <?php elseif ($admission['added_by'] === 'admin'): ?>
                                                <span class="badge badge-primary px-2 py-1 rounded" style="font-size: 10px;">Administrator</span>
                                            <?php else: ?>
                                                <span class="badge badge-success px-2 py-1 rounded" style="font-size: 10px;" title="<?= htmlspecialchars($admission['franchise_name'] ?? '') ?>">Franchise</span>
                                                <small class="d-block text-muted mt-1" style="font-size:9px; font-weight: 600;"><?= htmlspecialchars($admission['franchise_name'] ?? $admission['added_by']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($admission['father_name']) ?></td>
                                        <td>
                                            <span class="d-block" style="font-size:12px;"><i class="fa-solid fa-envelope text-secondary mr-1"></i> <?= htmlspecialchars($admission['email']) ?></span>
                                            <span class="d-block" style="font-size:12px;"><i class="fa-solid fa-phone text-secondary mr-1"></i> <?= htmlspecialchars($admission['mobile']) ?></span>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($admission['course_name'] ?: 'Deleted Course') ?></strong>
                                            <?php if (!empty($admission['session_name'])): ?>
                                                <br><small class="text-muted">Session: <?= htmlspecialchars($admission['session_name']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge-premium badge-<?= strtolower($admission['payment_status']) ?>">
                                                <?= htmlspecialchars($admission['payment_status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="action-buttons-group">
                                                <a href="view_admission.php?id=<?= $admission['id'] ?>" class="btn-action-circle btn-view-circle" title="View Student Profile">
                                                    <i class="fa-solid fa-eye"></i>
                                                </a>
                                                <a href="edit_admission.php?id=<?= $admission['id'] ?>" class="btn-action-circle btn-edit-circle" title="Edit Admission">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </a>
                                                <button class="btn-action-circle btn-delete-circle" onclick="confirmDelete(<?= $admission['id'] ?>, '<?= htmlspecialchars($admission['student_name'], ENT_QUOTES) ?>')" title="Delete Record">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">
                                        <i class="fa-solid fa-file-invoice mb-3" style="font-size: 40px; color: #cbd5e0; display: block;"></i>
                                        No student admissions found matching the filtered parameters.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Pagination Panel -->
            <?php if ($total_pages > 1): ?>
                <div class="card-footer clearfix bg-white d-flex justify-content-between align-items-center px-4 py-3 border-top">
                    <span class="text-muted" style="font-size: 13.5px;">
                        Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $total_records) ?> of <?= $total_records ?> entries
                    </span>
                    <ul class="pagination pagination-sm m-0 float-right">
                        <!-- Previous Page -->
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link px-3 py-2" href="admissions.php?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&course_id=<?= $course_filter ?>&payment_status=<?= urlencode($status_filter) ?>&added_by=<?= urlencode($source_filter) ?>">Previous</a>
                        </li>
                        
                        <!-- Page Numbers -->
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                <a class="page-link px-3 py-2" href="admissions.php?page=<?= $i ?>&search=<?= urlencode($search) ?>&course_id=<?= $course_filter ?>&payment_status=<?= urlencode($status_filter) ?>&added_by=<?= urlencode($source_filter) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
 
                        <!-- Next Page -->
                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link px-3 py-2" href="admissions.php?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&course_id=<?= $course_filter ?>&payment_status=<?= urlencode($status_filter) ?>&added_by=<?= urlencode($source_filter) ?>">Next</a>
                        </li>
                    </ul>
                </div>
            <?php elseif($total_records > 0): ?>
                <div class="card-footer bg-white px-4 py-3 border-top">
                    <span class="text-muted" style="font-size: 13.5px;">
                        Showing 1 to <?= $total_records ?> of <?= $total_records ?> entries
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Secure sweet alert delete prompt
    function confirmDelete(id, name) {
        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to permanently delete the admission file of student "${name}". This cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete record!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `admissions.php?delete_id=${id}&token=<?= $_SESSION['csrf_token'] ?? '' ?>`;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($error_message)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Operation Failed',
                text: <?= json_encode($error_message) ?>,
                confirmButtonColor: '#0d47a1'
            });
        <?php endif; ?>
    });
</script>

<?php include '../footer.php'; ?>
