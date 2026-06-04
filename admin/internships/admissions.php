<?php
/**
 * MG Education & Social Development Organization
 * Internship Admissions Management Console Dashboard
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

        $stmt = $db->prepare("DELETE FROM internship_admissions WHERE id = :id");
        $stmt->execute(['id' => $delete_id]);
        
        $success_message = "Internship admission record deleted successfully!";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Record Removed',
                    text: 'The internship admission file was successfully deleted from records.',
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

// Fetch dynamic internships for the filter dropdown
try {
    $intern_stmt = $db->query("SELECT id, name FROM internships ORDER BY name ASC");
    $internships_list = $intern_stmt->fetchAll();
} catch (Exception $e) {
    $internships_list = [];
}

// Fetch franchise centers for filter
try {
    $centers_stmt = $db->query("SELECT center_id, center_name FROM franchise_centers ORDER BY center_name ASC");
    $centers_list = $centers_stmt->fetchAll();
} catch (Exception $e) {
    $centers_list = [];
}

// 2. Setup Filter Parameters & Pagination
$search = trim($_GET['search'] ?? '');
$internship_filter = isset($_GET['internship_id']) ? intval($_GET['internship_id']) : 0;
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

    if ($internship_filter > 0) {
        $where_clauses[] = "a.internship_id = :internship_id";
        $params['internship_id'] = $internship_filter;
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
    $count_sql = "SELECT COUNT(*) FROM internship_admissions a" . $where_sql;
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = intval($count_stmt->fetchColumn());
    $total_pages = ceil($total_records / $limit);

    // Get Paginated Data
    $query = "
        SELECT a.*, i.name as internship_name, fc.center_name AS franchise_name
        FROM internship_admissions a
        LEFT JOIN internships i ON a.internship_id = i.id
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
        padding: 0;
    }
    .search-input {
        border: 1px solid #cbd5e1;
        border-radius: 50px;
        padding: 8px 18px;
        font-size: 13.5px;
        font-weight: 600;
        width: 240px;
        transition: all 0.3s ease;
    }
    .search-input:focus {
        border-color: #0d47a1;
        outline: none;
        box-shadow: 0 0 0 3px rgba(13, 71, 161, 0.08);
    }

    .select-premium {
        border: 1px solid #cbd5e1;
        border-radius: 50px;
        padding: 8px 18px;
        font-size: 13.5px;
        font-weight: 600;
        background-color: #ffffff;
        color: #2c3e50;
        outline: none;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .select-premium:focus {
        border-color: #0d47a1;
    }

    .table-premium {
        width: 100%;
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
    }
    .table-premium th {
        background-color: #f8fafc;
        color: #475569;
        font-weight: 700;
        font-size: 11.5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e2e8f0;
        padding: 16px 20px;
    }
    .table-premium td {
        padding: 16px 20px;
        vertical-align: middle;
        font-size: 13.5px;
        border-bottom: 1px solid #e2e8f0;
        color: #1e293b;
    }
    .table-premium tr:last-child td {
        border-bottom: none;
    }
    .table-premium tr:hover td {
        background-color: #f8fafc;
    }

    .badge-premium {
        border-radius: 50px;
        padding: 4px 12px;
        font-size: 10.5px;
        font-weight: 700;
        text-transform: uppercase;
        display: inline-block;
        letter-spacing: 0.3px;
    }
    .badge-paid {
        background-color: #ecfdf5;
        color: #059669;
        border: 1px solid #a7f3d0;
    }
    .badge-free {
        background-color: #eff6ff;
        color: #2563eb;
        border: 1px solid #bfdbfe;
    }
    .badge-pending {
        background-color: #fffbeb;
        color: #d97706;
        border: 1px solid #fde68a;
    }
    .badge-failed {
        background-color: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
    }

    .btn-action-circle {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        border: none;
        transition: all 0.2s ease;
        text-decoration: none;
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
        background-color: #3b82f6;
    }
    .btn-view-circle:hover {
        background-color: #1d4ed8;
        transform: scale(1.05);
    }
    .btn-edit-circle {
        background-color: #4b5563;
    }
    .btn-edit-circle:hover {
        background-color: #1f2937;
        transform: scale(1.05);
    }
    .btn-delete-circle {
        background-color: #ef4444;
    }
    .btn-delete-circle:hover {
        background-color: #b91c1c;
        transform: scale(1.05);
    }

    /* Premium pagination styles */
    .pagination-container {
        padding: 20px 25px;
        border-top: 1px solid #f0f0f1;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    .pagination-info {
        font-size: 13px;
        color: #64748b;
        font-weight: 600;
    }
    .pagination-list {
        display: flex;
        list-style: none;
        padding: 0;
        margin: 0;
        gap: 5px;
    }
    .page-link-premium {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        color: #475569;
        font-weight: 700;
        font-size: 13px;
        text-decoration: none;
        transition: all 0.3s;
    }
    .page-item.active .page-link-premium {
        background-color: #0d47a1;
        color: #ffffff;
        border-color: #0d47a1;
    }
    .page-link-premium:hover:not(.active) {
        background-color: #f1f5f9;
        border-color: #94a3b8;
    }
</style>

<div class="row pt-3">
    <div class="col-12">
        <div class="admin-card">
            
            <div class="admin-header">
                <h3>Internship Admissions Registry</h3>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <form method="GET" action="" class="mb-0 d-flex gap-2 align-items-center flex-wrap">
                        <select name="internship_id" class="select-premium" onchange="this.form.submit()">
                            <option value="0">All Programs</option>
                            <?php foreach ($internships_list as $intern): ?>
                                <option value="<?= $intern['id'] ?>" <?= ($internship_filter == $intern['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($intern['name']) ?>
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
                            <option value="">All Payments</option>
                            <option value="pending" <?= ($status_filter === 'pending') ? 'selected' : '' ?>>Pending</option>
                            <option value="paid" <?= ($status_filter === 'paid') ? 'selected' : '' ?>>Paid</option>
                            <option value="free" <?= ($status_filter === 'free') ? 'selected' : '' ?>>Scholarship / Free</option>
                            <option value="failed" <?= ($status_filter === 'failed') ? 'selected' : '' ?>>Failed</option>
                        </select>

                        <input type="text" name="search" class="search-input" placeholder="Search candidate details..." value="<?= htmlspecialchars($search) ?>">
                        
                        <button type="submit" class="btn btn-primary rounded-pill px-4 font-weight-bold shadow-sm" style="background-color:#0d47a1; border-color:#0d47a1; font-size:13px; height:38px;">
                            <i class="fa-solid fa-magnifying-glass mr-1"></i> Filter
                        </button>
                        
                        <?php if(!empty($search) || $internship_filter > 0 || !empty($status_filter) || !empty($source_filter)): ?>
                            <a href="admissions.php" class="btn btn-secondary btn-sm rounded-pill px-3 py-2" title="Reset Filters"><i class="fa-solid fa-arrows-rotate"></i> Reset</a>
                        <?php endif; ?>
                    </form>
                    
                    <a href="enroll.php" class="btn btn-primary btn-sm rounded-pill px-4 py-2 font-weight-bold shadow-sm" style="background-color:#0d47a1; border-color:#0d47a1;"><i class="fa-solid fa-user-plus mr-1"></i> Enroll Student</a>
                </div>
            </div>
            
            <div class="admin-body">
                <div class="table-responsive">
                    <table class="table table-premium">
                        <thead>
                            <tr>
                                <th>Enrollment No</th>
                                <th>Candidate Details</th>
                                <th>Registered By</th>
                                <th>Internship Program</th>
                                <th>Mobile & Father</th>
                                <th class="text-center">Fees Status</th>
                                <th style="width: 130px;" class="text-center">Actions</th>
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
                                                <span class="text-muted font-italic" style="font-size: 12.5px;">Pending Approval</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($admission['student_name']) ?></div>
                                            <div class="text-muted" style="font-size: 12px;"><?= htmlspecialchars($admission['email']) ?></div>
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
                                        <td>
                                            <div style="font-weight: 600; font-size:13.5px;"><?= htmlspecialchars($admission['internship_name'] ?: 'Unknown Project') ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; font-size:13px;"><?= htmlspecialchars($admission['mobile']) ?></div>
                                            <div class="text-muted" style="font-size: 12px;">S/o: <?= htmlspecialchars($admission['father_name']) ?></div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge-premium badge-<?= $admission['payment_status'] ?>">
                                                <?= $admission['payment_status'] === 'free' ? 'Scholarship' : $admission['payment_status'] ?>
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
                                                <a href="javascript:void(0);" onclick="confirmDelete(<?= $admission['id'] ?>, '<?= htmlspecialchars($admission['student_name'], ENT_QUOTES) ?>')" class="btn-action-circle btn-delete-circle" title="Delete Candidate">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="fa-solid fa-users-slash mb-3" style="font-size: 40px; color: #cbd5e0; display: block;"></i>
                                        No internship admission profiles match your filter options.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination block -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">
                        Showing <?= $offset + 1 ?> to <?= min($total_records, $offset + $limit) ?> of <?= $total_records ?> applicants
                    </div>
                    
                    <ul class="pagination-list">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a href="?page=<?= $page - 1 ?>&internship_id=<?= $internship_filter ?>&payment_status=<?= $status_filter ?>&search=<?= urlencode($search) ?>&added_by=<?= urlencode($source_filter) ?>" class="page-link-premium">&laquo;</a>
                            </li>
                        <?php endif; ?>
 
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                <a href="?page=<?= $i ?>&internship_id=<?= $internship_filter ?>&payment_status=<?= $status_filter ?>&search=<?= urlencode($search) ?>&added_by=<?= urlencode($source_filter) ?>" class="page-link-premium <?= ($page == $i) ? 'active' : '' ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
 
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a href="?page=<?= $page + 1 ?>&internship_id=<?= $internship_filter ?>&payment_status=<?= $status_filter ?>&search=<?= urlencode($search) ?>&added_by=<?= urlencode($source_filter) ?>" class="page-link-premium">&raquo;</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
    function confirmDelete(id, name) {
        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to permanently delete the internship admission record for "${name}". This cannot be undone!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!'
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
                title: 'Database Alert',
                text: <?= json_encode($error_message) ?>,
                confirmButtonColor: '#0d47a1'
            });
        <?php endif; ?>
    });
</script>

<?php include '../footer.php'; ?>
