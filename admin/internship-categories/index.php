<?php
/**
 * MG Education & Social Development Organization
 * Internship Categories Management Panel
 */

include '../header.php';

$db = MG_GetDBConnection();
$error_message = '';
$success_message = '';

// Handle Category Deletion
if (isset($_GET['delete_id'])) {
    try {
        $delete_id = intval($_GET['delete_id']);
        
        // CSRF verification on delete
        if (!isset($_GET['token']) || !hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
            throw new Exception("Security mismatch: CSRF validation failed.");
        }

        // Delete the category (Cascade constraint handles Internships deletion automatically)
        $stmt = $db->prepare("DELETE FROM internship_categories WHERE id = :id");
        $stmt->execute(['id' => $delete_id]);
        
        $success_message = "Category deleted successfully!";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Category Removed',
                    text: 'The internship category was deleted from system records.',
                    confirmButtonColor: '#28a745'
                }).then(() => {
                    window.location.href = 'index.php';
                });
            });
        </script>";
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Fetch all categories
try {
    $search = trim($_GET['search'] ?? '');
    if (!empty($search)) {
        $stmt = $db->prepare("SELECT * FROM internship_categories WHERE name LIKE :search OR slug LIKE :search ORDER BY id DESC");
        $stmt->execute(['search' => "%{$search}%"]);
    } else {
        $stmt = $db->query("SELECT * FROM internship_categories ORDER BY id DESC");
    }
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    $error_message = $e->getMessage();
    $categories = [];
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
        width: 250px;
        transition: all 0.3s ease;
    }
    .search-input:focus {
        border-color: #8c9096;
        outline: none;
        box-shadow: 0 0 0 3px rgba(140, 144, 150, 0.1);
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
    }
    .btn-green-premium:hover {
        background-color: #218838;
        box-shadow: 0 6px 16px rgba(40, 167, 69, 0.25);
        color: #ffffff;
        transform: translateY(-1px);
    }
</style>

<div class="row pt-3">
    <div class="col-12">
        <div class="admin-card">
            <div class="admin-header">
                <h3>Internship Categories Management</h3>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <form method="GET" action="" class="mb-0">
                        <input type="text" name="search" class="search-input" placeholder="Search category..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </form>
                    <a href="add.php" class="btn-green-premium">
                        <i class="fa-solid fa-plus-circle"></i> Add New Category
                    </a>
                </div>
            </div>
            
            <div class="admin-body p-0">
                <div class="table-responsive">
                    <table class="table table-premium">
                        <thead>
                            <tr>
                                <th style="width: 80px;">ID</th>
                                <th>Category Name</th>
                                <th>Slug</th>
                                <th>Description</th>
                                <th>Meta Title</th>
                                <th style="width: 120px;" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($categories)): ?>
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td><strong>#<?= $cat['id'] ?></strong></td>
                                        <td><strong><?= htmlspecialchars($cat['name']) ?></strong></td>
                                        <td><code class="text-success"><?= htmlspecialchars($cat['slug']) ?></code></td>
                                        <td>
                                            <span class="text-muted" title="<?= htmlspecialchars(strip_tags($cat['description'] ?? '')) ?>">
                                                <?= !empty($cat['description']) ? htmlspecialchars(substr(strip_tags($cat['description']), 0, 60)) . (strlen(strip_tags($cat['description'])) > 60 ? '...' : '') : '<em class="text-secondary">No description</em>' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-muted">
                                                <?= !empty($cat['meta_title']) ? htmlspecialchars(substr($cat['meta_title'], 0, 30)) . (strlen($cat['meta_title']) > 30 ? '...' : '') : '<em class="text-secondary">Not configured</em>' ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="edit.php?id=<?= $cat['id'] ?>" class="btn-action-circle btn-edit-circle" title="Edit Category">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                            <a href="javascript:void(0);" onclick="confirmDelete(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>')" class="btn-action-circle btn-delete-circle" title="Delete Category">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        <i class="fa-solid fa-folder-open mb-3" style="font-size: 40px; color: #cbd5e0; display: block;"></i>
                                        No categories found. Click "Add New Category" to create one.
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
            text: `You are about to delete the category "${name}". Note: ALL internships matching this category will be permanently deleted!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `index.php?delete_id=${id}&token=<?= $_SESSION['csrf_token'] ?? '' ?>`;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
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

<?php include '../footer.php'; ?>
