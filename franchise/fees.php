<?php
/**
 * MG Education & Social Development Organization
 * Franchise Portal - Student Fees Ledgers Dashboard
 */

include './header.php';

$db = MG_GetDBConnection();
$center_id = $_SESSION['center_id'];
$error_message = '';

// Setup filters
$search = trim($_GET['search'] ?? '');
$student_type_filter = trim($_GET['student_type'] ?? '');
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    $where_clauses = ["(a.added_by = :center_id OR ia.added_by = :center_id)"];
    $params = ['center_id' => $center_id];

    if (!empty($search)) {
        $where_clauses[] = "(f.enrollment_number LIKE :search OR a.student_name LIKE :search OR ia.student_name LIKE :search)";
        $params['search'] = "%{$search}%";
    }

    if (!empty($student_type_filter)) {
        $where_clauses[] = "f.student_type = :student_type";
        $params['student_type'] = $student_type_filter;
    }

    $where_sql = " WHERE " . implode(" AND ", $where_clauses);

    // Count Total Records
    $count_query = "
        SELECT COUNT(*) 
        FROM student_fees f
        LEFT JOIN admissions a ON f.student_id = a.id AND f.student_type = 'course'
        LEFT JOIN internship_admissions ia ON f.student_id = ia.id AND f.student_type = 'internship'
        " . $where_sql;

    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = intval($count_stmt->fetchColumn());
    $total_pages = ceil($total_records / $limit);

    // Fetch Paginated Ledger Records
    $query = "
        SELECT f.*, 
               COALESCE(a.student_name, ia.student_name) AS student_name,
               COALESCE(c.name, i.name) AS program_name
        FROM student_fees f
        LEFT JOIN admissions a ON f.student_id = a.id AND f.student_type = 'course'
        LEFT JOIN courses c ON a.course_id = c.id
        LEFT JOIN internship_admissions ia ON f.student_id = ia.id AND f.student_type = 'internship'
        LEFT JOIN internships i ON ia.internship_id = i.id
        " . $where_sql . "
        ORDER BY f.id DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $fees_ledger = $stmt->fetchAll();

} catch (Exception $e) {
    $error_message = $e->getMessage();
    $fees_ledger = [];
    $total_records = 0;
    $total_pages = 0;
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
    .card-header-premium {
        border-bottom: 1px solid #f1f5f9;
        padding: 20px 25px;
    }
    .card-header-premium h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
        color: #1e293b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
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
    .table-premium tr:hover td {
        background-color: #fcfdfd;
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
    .badge-type-course {
        background-color: rgba(13, 71, 161, 0.08);
        color: #0d47a1;
        border: 1px solid rgba(13, 71, 161, 0.15);
    }
    .badge-type-internship {
        background-color: rgba(111, 66, 193, 0.08);
        color: #6f42c1;
        border: 1px solid rgba(111, 66, 193, 0.15);
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
    .btn-receipt-circle {
        background-color: #17a2b8;
    }
    .btn-receipt-circle:hover {
        background-color: #138496;
        transform: scale(1.05);
    }
</style>

<div class="row pt-3">
    <div class="col-12">
        <div class="card card-premium">
            <div class="card-header-premium bg-white">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <h3>Candidate Financial Fees Ledgers</h3>
                    
                    <form method="GET" action="" class="mb-0 d-flex gap-2 align-items-center flex-wrap">
                        <select name="student_type" class="select-premium" onchange="this.form.submit()">
                            <option value="">All Admission Types</option>
                            <option value="course" <?= ($student_type_filter === 'course') ? 'selected' : '' ?>>Course Admissions</option>
                            <option value="internship" <?= ($student_type_filter === 'internship') ? 'selected' : '' ?>>Internship Admissions</option>
                        </select>
                        
                        <input type="text" name="search" class="search-input-premium" placeholder="Search enrollment / student name..." value="<?= htmlspecialchars($search) ?>">
                        
                        <button type="submit" class="btn btn-success rounded-pill px-4 font-weight-bold"><i class="fa-solid fa-magnifying-glass mr-1"></i> Filter</button>
                        
                        <?php if(!empty($search) || !empty($student_type_filter)): ?>
                            <a href="fees.php" class="btn btn-secondary rounded-pill px-3" title="Reset Filters"><i class="fa-solid fa-arrows-rotate"></i> Reset</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-premium">
                        <thead>
                            <tr>
                                <th>Transaction Date</th>
                                <th>Enrollment No</th>
                                <th>Student Details</th>
                                <th>Applied Program Domain</th>
                                <th>Category</th>
                                <th>Amount Paid</th>
                                <th class="text-center">Transaction ID</th>
                                <th class="text-center">Receipt PDF</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($fees_ledger)): ?>
                                <?php foreach ($fees_ledger as $row): ?>
                                    <tr>
                                        <td>
                                            <strong><?= date('d M Y', strtotime($row['created_at'])) ?></strong><br>
                                            <small class="text-muted"><?= date('h:i A', strtotime($row['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <code class="text-primary font-weight-bold" style="font-size: 13.5px;"><?= htmlspecialchars($row['enrollment_number']) ?></code>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['student_name']) ?></strong>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['program_name'] ?: 'N/A') ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge-premium badge-type-<?= $row['student_type'] ?>">
                                                <?= htmlspecialchars($row['student_type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="text-success" style="font-size: 14.5px;">₹<?= number_format($row['amount'], 2) ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <code class="text-dark font-weight-bold" style="font-size: 12px;"><?= htmlspecialchars($row['razorpay_payment_id'] ?? 'WALLET') ?></code>
                                        </td>
                                        <td class="text-center">
                                            <?php if (!empty($row['receipt_path'])): ?>
                                                <a href="../<?= htmlspecialchars($row['receipt_path']) ?>" target="_blank" class="btn-action-circle btn-receipt-circle" title="Download Tax Invoice Receipt">
                                                    <i class="fa-solid fa-file-pdf"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size:12px;">No Receipt</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">
                                        <i class="fa-solid fa-receipt mb-3" style="font-size: 40px; color: #cbd5e1; display: block;"></i>
                                        No fees ledger transaction entries found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Pagination Panel -->
            <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-white d-flex justify-content-between align-items-center px-4 py-3 border-top">
                    <span class="text-muted" style="font-size: 13px;">
                        Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $total_records) ?> of <?= $total_records ?> entries
                    </span>
                    <ul class="pagination pagination-sm m-0">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="fees.php?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&student_type=<?= urlencode($student_type_filter) ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                <a class="page-link" href="fees.php?page=<?= $i ?>&search=<?= urlencode($search) ?>&student_type=<?= urlencode($student_type_filter) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="fees.php?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&student_type=<?= urlencode($student_type_filter) ?>">Next</a>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include './footer.php'; ?>
