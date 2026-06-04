<?php
/**
 * MG Education & Social Development Organization
 * Academic Sessions Management
 */

include 'header.php';

$db = MG_GetDBConnection();
$error_message = '';
$success_message = '';

// Self-healing Database for Academic Sessions
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `academic_sessions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `session_name` varchar(50) NOT NULL,
        `is_active` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    $error_message = "Database initialization error: " . $e->getMessage();
}

// Handle Add Session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_session') {
    try {
        if (!isset($_POST['token']) || !hash_equals($_SESSION['csrf_token'], $_POST['token'])) {
            throw new Exception("Security mismatch: CSRF validation failed.");
        }
        
        $start = trim($_POST['start_month']);
        $end = trim($_POST['end_month']);
        
        if (empty($start) || empty($end)) {
            throw new Exception("Both start and end dates are required.");
        }

        $start_formatted = date('m/Y', strtotime($start . '-01'));
        $end_formatted = date('m/Y', strtotime($end . '-01'));
        $session_name = $start_formatted . ' to ' . $end_formatted;

        $stmt = $db->prepare("INSERT INTO `academic_sessions` (`session_name`, `is_active`) VALUES (:name, 1)");
        $stmt->execute(['name' => $session_name]);
        
        $success_message = "Session '$session_name' created successfully!";
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle Toggle Status
if (isset($_GET['toggle_id'])) {
    try {
        $toggle_id = intval($_GET['toggle_id']);
        $new_status = intval($_GET['status']);
        
        if (!isset($_GET['token']) || !hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
            throw new Exception("Security mismatch: CSRF validation failed.");
        }

        $stmt = $db->prepare("UPDATE `academic_sessions` SET `is_active` = :status WHERE `id` = :id");
        $stmt->execute(['status' => $new_status, 'id' => $toggle_id]);
        
        $success_message = "Session status updated successfully!";
        // We will just let the page reload to clear query params via redirect, or we can just render the success message.
        // It's cleaner to redirect to clear the GET parameter.
        echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'Session status updated successfully!',
                        confirmButtonColor: '#28a745',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'sessions.php';
                    });
                });
              </script>";
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle Delete Session
if (isset($_GET['delete_id'])) {
    try {
        $delete_id = intval($_GET['delete_id']);
        
        if (!isset($_GET['token']) || !hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
            throw new Exception("Security mismatch: CSRF validation failed.");
        }

        $stmt = $db->prepare("DELETE FROM `academic_sessions` WHERE `id` = :id");
        $stmt->execute(['id' => $delete_id]);
        
        $success_message = "Session deleted successfully!";
        echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted',
                        text: 'Session deleted successfully!',
                        confirmButtonColor: '#28a745',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'sessions.php';
                    });
                });
              </script>";
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Fetch all sessions
try {
    $stmt = $db->query("SELECT * FROM `academic_sessions` ORDER BY `id` DESC");
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = $e->getMessage();
    $sessions = [];
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
        font-size: 14px;
        border-bottom: 1px solid #e2e8f0;
        color: #2c3e50;
    }
    .table-premium tr:last-child td {
        border-bottom: none;
    }
    .table-premium tr:hover td {
        background-color: #fcfdfd;
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
        margin-right: 5px;
        text-decoration: none;
    }
    .btn-delete-circle {
        background-color: #dc3545;
    }
    .btn-delete-circle:hover {
        background-color: #bd2130;
        transform: scale(1.05);
        color: #fff;
    }
    .btn-green-premium {
        background-color: #28a745;
        color: #ffffff;
        font-weight: 600;
        border-radius: 50px;
        padding: 9px 20px;
        font-size: 14px;
        text-decoration: none;
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.15);
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: none;
    }
    .btn-green-premium:hover {
        background-color: #218838;
        box-shadow: 0 6px 16px rgba(40, 167, 69, 0.25);
        color: #ffffff;
        transform: translateY(-1px);
    }
</style>

<div class="row pt-3">
    <!-- Left Column: Add Session Form -->
    <div class="col-lg-4 mb-4">
        <div class="admin-card h-100">
            <div class="admin-header">
                <h3><i class="fas fa-plus-circle mr-2 text-success"></i> Add New Session</h3>
            </div>
            <div class="admin-body">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="add_session">
                    <input type="hidden" name="token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    
                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Start Month & Year</label>
                        <input type="month" class="form-control" name="start_month" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label font-weight-bold">End Month & Year</label>
                        <input type="month" class="form-control" name="end_month" required>
                    </div>
                    
                    <button type="submit" class="btn-green-premium w-100 justify-content-center">
                        Create Session
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Sessions List -->
    <div class="col-lg-8 mb-4">
        <div class="admin-card h-100">
            <div class="admin-header">
                <h3><i class="fas fa-list mr-2 text-success"></i> Academic Sessions</h3>
            </div>
            
            <div class="admin-body p-0">
                <div class="table-responsive">
                    <table class="table table-premium">
                        <thead>
                            <tr>
                                <th style="width: 80px;">ID</th>
                                <th>Session Name</th>
                                <th>Created On</th>
                                <th class="text-center">Status</th>
                                <th style="width: 120px;" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($sessions)): ?>
                                <?php foreach ($sessions as $session): ?>
                                    <tr>
                                        <td><strong>#<?= $session['id'] ?></strong></td>
                                        <td><strong><?= htmlspecialchars($session['session_name']) ?></strong></td>
                                        <td><span class="text-muted"><?= date('d M Y, h:i A', strtotime($session['created_at'])) ?></span></td>
                                        <td class="text-center">
                                            <?php if ($session['is_active'] == 1): ?>
                                                <a href="?toggle_id=<?= $session['id'] ?>&status=0&token=<?= $_SESSION['csrf_token'] ?? '' ?>" class="badge badge-success px-3 py-2 text-white text-decoration-none" title="Click to deactivate" style="font-size: 12px; cursor: pointer; display: inline-block;">Active</a>
                                            <?php else: ?>
                                                <a href="?toggle_id=<?= $session['id'] ?>&status=1&token=<?= $_SESSION['csrf_token'] ?? '' ?>" class="badge badge-secondary px-3 py-2 text-white text-decoration-none" title="Click to activate" style="font-size: 12px; cursor: pointer; display: inline-block;">Inactive</a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="javascript:void(0);" onclick="confirmDelete(<?= $session['id'] ?>, '<?= htmlspecialchars($session['session_name'], ENT_QUOTES) ?>')" class="btn-action-circle btn-delete-circle" title="Delete Session">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-5">
                                        <i class="fa-regular fa-calendar mb-3" style="font-size: 40px; color: #cbd5e0; display: block;"></i>
                                        No academic sessions found. Create your first session.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function confirmDelete(id, name) {
        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete the session "${name}".`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `sessions.php?delete_id=${id}&token=<?= $_SESSION['csrf_token'] ?? '' ?>`;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($success_message) && !isset($_GET['toggle_id']) && !isset($_GET['delete_id'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: <?= json_encode($success_message) ?>,
                confirmButtonColor: '#28a745',
                timer: 3000,
                timerProgressBar: true
            });
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Operation Failed',
                text: <?= json_encode($error_message) ?>,
                confirmButtonColor: '#28a745'
            });
        <?php endif; ?>
    });
</script>

<?php include 'footer.php'; ?>
