<?php
/**
 * MG Education & Social Development Organization
 * Franchise Center Management Registry Console
 */

include '../header.php';

$db = MG_GetDBConnection();
$error_message = '';
$success_message = '';

// CSRF Token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 1. Self-Healing Database: Create franchise_centers table automatically if not exists
try {
    $db->query("SELECT 1 FROM `franchise_centers` LIMIT 1");
} catch (Exception $e) {
    try {
        $createSQL = "
            CREATE TABLE IF NOT EXISTS `franchise_centers` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `center_id` VARCHAR(50) NOT NULL UNIQUE,
                `password` VARCHAR(255) NOT NULL,
                `center_name` VARCHAR(255) NOT NULL,
                `email` VARCHAR(255) NOT NULL,
                `mobile` VARCHAR(20) NOT NULL,
                `pincode` VARCHAR(10) NOT NULL,
                `city` VARCHAR(100) NOT NULL,
                `state` VARCHAR(100) NOT NULL,
                `full_address` TEXT NOT NULL,
                `center_logo` VARCHAR(255) NULL,
                `owner_image` VARCHAR(255) NULL,
                `auth_signatory` VARCHAR(255) NULL,
                
                -- Infrastructure Details
                `classrooms` INT NOT NULL DEFAULT 0,
                `computers` INT NOT NULL DEFAULT 0,
                `total_staff` INT NOT NULL DEFAULT 0,
                `lab_type` VARCHAR(50) NOT NULL DEFAULT 'basic',
                `working_hours_from` TIME NULL,
                `working_hours_to` TIME NULL,
                `amenities` TEXT NULL,
                `working_days_from` VARCHAR(50) NULL,
                `working_days_to` VARCHAR(50) NULL,
                
                -- Documentation
                `gst_number` VARCHAR(50) NULL,
                `aadhaar_number` VARCHAR(20) NOT NULL,
                `aadhaar_card_file` VARCHAR(255) NULL,
                `pan_number` VARCHAR(20) NULL,
                `pan_card_file` VARCHAR(255) NULL,
                `msme_file` VARCHAR(255) NULL,
                
                -- Fees Structure
                `franchise_fees` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `royalty_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                
                `status` VARCHAR(50) DEFAULT 'active',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $db->exec($createSQL);
    } catch (Exception $ex) {
        $error_message = "Failed to establish franchise database tables: " . $ex->getMessage();
    }
}

// 2. Handle Record Deletion
if (isset($_GET['delete_id'])) {
    try {
        $delete_id = intval($_GET['delete_id']);
        
        if (!isset($_GET['token']) || !hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
            throw new Exception("Security mismatch: CSRF validation failed.");
        }

        // Fetch files to clean from disk
        $stmtFile = $db->prepare("SELECT center_logo, owner_image, auth_signatory, aadhaar_card_file, pan_card_file, msme_file FROM franchise_centers WHERE id = :id");
        $stmtFile->execute(['id' => $delete_id]);
        $files = $stmtFile->fetch();

        if ($files) {
            $root = dirname(dirname(__DIR__));
            $fileKeys = ['center_logo', 'owner_image', 'auth_signatory', 'aadhaar_card_file', 'pan_card_file', 'msme_file'];
            foreach ($fileKeys as $key) {
                if (!empty($files[$key])) {
                    $targetPath = $root . '/' . $files[$key];
                    if (file_exists($targetPath)) { @unlink($targetPath); }
                }
            }
        }

        $stmt = $db->prepare("DELETE FROM franchise_centers WHERE id = :id");
        $stmt->execute(['id' => $delete_id]);
        
        $success_message = "Franchise Center record removed successfully!";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Center Removed',
                    text: 'The franchise center profile and files were successfully deleted.',
                    confirmButtonColor: '#0d47a1'
                }).then(() => {
                    window.location.href = 'index.php';
                });
            });
        </script>";
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// 3. Search and Pagination Logic
$search = trim($_GET['search'] ?? '');
$lab_filter = trim($_GET['lab_type'] ?? '');
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    $where_clauses = [];
    $params = [];

    if (!empty($search)) {
        $where_clauses[] = "(center_id LIKE :search OR center_name LIKE :search OR email LIKE :search OR mobile LIKE :search OR city LIKE :search OR state LIKE :search)";
        $params['search'] = "%{$search}%";
    }

    if (!empty($lab_filter)) {
        $where_clauses[] = "lab_type = :lab_type";
        $params['lab_type'] = $lab_filter;
    }

    $where_sql = "";
    if (!empty($where_clauses)) {
        $where_sql = " WHERE " . implode(" AND ", $where_clauses);
    }

    // Get Total Count
    $count_sql = "SELECT COUNT(*) FROM franchise_centers" . $where_sql;
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = intval($count_stmt->fetchColumn());
    $total_pages = ceil($total_records / $limit);

    // Get Paginated Data
    $query = "SELECT * FROM franchise_centers" . $where_sql . " ORDER BY id DESC LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $centers = $stmt->fetchAll();

} catch (Exception $e) {
    $error_message = $e->getMessage();
    $centers = [];
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
    .logo-preview {
        width: 42px;
        height: 42px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
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
    .btn-view-circle { background-color: #3b82f6; }
    .btn-view-circle:hover { background-color: #1d4ed8; transform: scale(1.05); }
    .btn-edit-circle { background-color: #4b5563; }
    .btn-edit-circle:hover { background-color: #1f2937; transform: scale(1.05); }
    .btn-delete-circle { background-color: #ef4444; }
    .btn-delete-circle:hover { background-color: #b91c1c; transform: scale(1.05); }
    
    .action-buttons-group {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 6px;
        flex-wrap: nowrap;
        white-space: nowrap;
    }
    .btn-green-premium {
        background-color: #28a745;
        color: #ffffff;
        font-weight: 700;
        border-radius: 50px;
        padding: 9px 22px;
        font-size: 13.5px;
        text-decoration: none;
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.15);
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .btn-green-premium:hover {
        background-color: #218838;
        color: #ffffff;
        box-shadow: 0 6px 16px rgba(40, 167, 69, 0.25);
        transform: translateY(-1px);
    }
    .badge-premium {
        border-radius: 50px;
        padding: 4px 12px;
        font-size: 10.5px;
        font-weight: 700;
        text-transform: uppercase;
        display: inline-block;
    }
    .badge-basic {
        background-color: #eff6ff;
        color: #2563eb;
        border: 1px solid #bfdbfe;
    }
    .badge-advance {
        background-color: #f5f3ff;
        color: #7c3aed;
        border: 1px solid #ddd6fe;
    }
</style>

<div class="row pt-3">
    <div class="col-12">
        <div class="admin-card">
            
            <div class="admin-header">
                <h3>Franchise Centers Management</h3>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <form method="GET" action="" class="mb-0 d-flex gap-2 align-items-center flex-wrap">
                        <select name="lab_type" class="select-premium" onchange="this.form.submit()">
                            <option value="">All Lab Standards</option>
                            <option value="basic" <?= ($lab_filter === 'basic') ? 'selected' : '' ?>>Basic Lab</option>
                            <option value="advance" <?= ($lab_filter === 'advance') ? 'selected' : '' ?>>Advance Lab</option>
                        </select>
                        <input type="text" name="search" class="search-input" placeholder="Search center ID, name..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn btn-primary rounded-pill px-4" style="background-color:#0d47a1; border-color:#0d47a1; font-weight:700; font-size:13px; height:38px;">Filter</button>
                    </form>
                    
                    <a href="add.php" class="btn-green-premium">
                        <i class="fa-solid fa-house-medical"></i> Add New Center
                    </a>
                </div>
            </div>
            
            <div class="admin-body">
                <div class="table-responsive">
                    <table class="table table-premium">
                        <thead>
                            <tr>
                                <th style="width: 80px;" class="text-center">Logo</th>
                                <th>Center ID</th>
                                <th>Center Specification</th>
                                <th>Geographic Info</th>
                                <th class="text-center">Lab Type</th>
                                <th>Fees / Royalty</th>
                                <th style="width: 140px;" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($centers)): ?>
                                <?php foreach ($centers as $center): ?>
                                    <tr>
                                        <td class="text-center">
                                            <?php if (!empty($center['center_logo'])): ?>
                                                <img src="<?= '../../' . htmlspecialchars($center['center_logo']) ?>" class="logo-preview" alt="Logo">
                                            <?php else: ?>
                                                <img src="https://via.placeholder.com/50?text=CTR" class="logo-preview" alt="Placeholder">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <code class="text-primary font-weight-bold" style="font-size: 13.5px;"><?= htmlspecialchars($center['center_id']) ?></code>
                                        </td>
                                        <td>
                                            <div style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($center['center_name']) ?></div>
                                            <div class="text-muted" style="font-size: 12px;"><?= htmlspecialchars($center['email']) ?> | <?= htmlspecialchars($center['mobile']) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight:600;"><?= htmlspecialchars($center['city']) ?>, <?= htmlspecialchars($center['state']) ?></div>
                                            <div class="text-muted" style="font-size: 12px;">Pincode: <?= htmlspecialchars($center['pincode']) ?></div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge-premium badge-<?= $center['lab_type'] ?>">
                                                <?= ucfirst($center['lab_type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-weight:700;">₹<?= number_format($center['franchise_fees'], 2) ?>/yr</div>
                                            <div class="text-muted" style="font-size: 12px;">Royalty: <?= htmlspecialchars($center['royalty_percentage']) ?>%</div>
                                        </td>
                                        <td class="text-center">
                                            <div class="action-buttons-group">
                                                <a href="view.php?id=<?= $center['id'] ?>" class="btn-action-circle btn-view-circle" title="View Center Profile">
                                                    <i class="fa-solid fa-eye"></i>
                                                </a>
                                                <a href="edit.php?id=<?= $center['id'] ?>" class="btn-action-circle btn-edit-circle" title="Edit Center">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </a>
                                                <a href="javascript:void(0);" onclick="confirmDelete(<?= $center['id'] ?>, '<?= htmlspecialchars($center['center_name'], ENT_QUOTES) ?>')" class="btn-action-circle btn-delete-circle" title="Delete Center">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="fa-solid fa-building-circle-exclamation mb-3" style="font-size: 40px; color: #cbd5e0; display: block;"></i>
                                        No registered franchise centers match your filter specifications.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Pagination block -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-container" style="padding: 20px 25px; border-top: 1px solid #f0f0f1; display: flex; justify-content: space-between; align-items: center;">
                    <div class="pagination-info" style="font-size:13px; color:#64748b; font-weight:600;">
                        Showing <?= $offset + 1 ?> to <?= min($total_records, $offset + $limit) ?> of <?= $total_records ?> centers
                    </div>
                    
                    <ul class="pagination-list" style="display:flex; list-style:none; padding:0; margin:0; gap:5px;">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a href="?page=<?= $page - 1 ?>&lab_type=<?= $lab_filter ?>&search=<?= urlencode($search) ?>" class="page-link-premium" style="display:flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:8px; border:1px solid #cbd5e1; color:#475569; font-weight:700; text-decoration:none;">&laquo;</a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item">
                                <a href="?page=<?= $i ?>&lab_type=<?= $lab_filter ?>&search=<?= urlencode($search) ?>" class="page-link-premium" style="display:flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:8px; border:1px solid #cbd5e1; color:<?= ($page == $i) ? '#fff' : '#475569' ?>; background-color:<?= ($page == $i) ? '#0d47a1' : 'transparent' ?>; border-color:<?= ($page == $i) ? '#0d47a1' : '#cbd5e1' ?>; font-weight:700; text-decoration:none;"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a href="?page=<?= $page + 1 ?>&lab_type=<?= $lab_filter ?>&search=<?= urlencode($search) ?>" class="page-link-premium" style="display:flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:8px; border:1px solid #cbd5e1; color:#475569; font-weight:700; text-decoration:none;">&raquo;</a>
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
            text: `You are about to permanently delete the franchise center "${name}". All credentials and uploaded files will be removed from disk!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `index.php?delete_id=${id}&token=<?= $_SESSION['csrf_token'] ?? '' ?>`;
            }
        });
    }
</script>

<?php include '../footer.php'; ?>
