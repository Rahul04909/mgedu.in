<?php
/**
 * MG Education & Social Development Organization
 * Internships Management Dashboard
 */

include '../header.php';

$db = MG_GetDBConnection();
$error_message = '';
$success_message = '';

// Handle Internship Deletion
if (isset($_GET['delete_id'])) {
    try {
        $delete_id = intval($_GET['delete_id']);
        
        // CSRF verification on delete
        if (!isset($_GET['token']) || !hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
            throw new Exception("Security mismatch: CSRF validation failed.");
        }

        // Fetch internship files to clean up from disk
        $fileStmt = $db->prepare("SELECT internship_image, gallery_images, brochure_pdf, featured_image FROM internships WHERE id = :id LIMIT 1");
        $fileStmt->execute(['id' => $delete_id]);
        $internshipFiles = $fileStmt->fetch();

        if ($internshipFiles) {
            $rootPath = dirname(dirname(__DIR__));
            
            // Delete main cover image
            if (!empty($internshipFiles['internship_image'])) {
                $coverFile = $rootPath . '/' . $internshipFiles['internship_image'];
                if (file_exists($coverFile)) { @unlink($coverFile); }
            }
            
            // Delete gallery images
            if (!empty($internshipFiles['gallery_images'])) {
                $galleryPaths = json_decode($internshipFiles['gallery_images'], true);
                if (is_array($galleryPaths)) {
                    foreach ($galleryPaths as $gPath) {
                        $gFile = $rootPath . '/' . $gPath;
                        if (file_exists($gFile)) { @unlink($gFile); }
                    }
                }
            }

            // Delete brochure PDF
            if (!empty($internshipFiles['brochure_pdf'])) {
                $pdfFile = $rootPath . '/' . $internshipFiles['brochure_pdf'];
                if (file_exists($pdfFile)) { @unlink($pdfFile); }
            }

            // Delete SEO Featured Image
            if (!empty($internshipFiles['featured_image'])) {
                $seoImg = $rootPath . '/' . $internshipFiles['featured_image'];
                if (file_exists($seoImg)) { @unlink($seoImg); }
            }
        }

        // Delete internship record
        $stmt = $db->prepare("DELETE FROM internships WHERE id = :id");
        $stmt->execute(['id' => $delete_id]);
        
        $success_message = "Internship deleted successfully!";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Internship Removed',
                    text: 'The internship program and associated files were successfully deleted.',
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

// Fetch categories for the filter dropdown
try {
    $cat_stmt = $db->query("SELECT id, name FROM internship_categories ORDER BY name ASC");
    $categories = $cat_stmt->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

// Fetch Internships with joined Category names
try {
    $search = trim($_GET['search'] ?? '');
    $cat_filter = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
    
    $query = "
        SELECT i.*, cat.name as category_name 
        FROM internships i
        INNER JOIN internship_categories cat ON i.category_id = cat.id
    ";
    
    $params = [];
    $where_clauses = [];
    
    if (!empty($search)) {
        $where_clauses[] = "(i.name LIKE :search OR i.slug LIKE :search)";
        $params['search'] = "%{$search}%";
    }
    
    if ($cat_filter > 0) {
        $where_clauses[] = "i.category_id = :category_id";
        $params['category_id'] = $cat_filter;
    }
    
    if (!empty($where_clauses)) {
        $query .= " WHERE " . implode(" AND ", $where_clauses);
    }
    
    $query .= " ORDER BY i.id DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $internships = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = $e->getMessage();
    $internships = [];
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
        width: 200px;
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
    .internship-cover-preview {
        width: 50px;
        height: 38px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
    }
    .badge-premium {
        border-radius: 50px;
        padding: 4px 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        display: inline-block;
    }
    .badge-online {
        background-color: rgba(40, 167, 69, 0.1);
        color: #28a745;
        border: 1px solid rgba(40, 167, 69, 0.2);
    }
    .badge-offline {
        background-color: rgba(23, 162, 184, 0.1);
        color: #17a2b8;
        border: 1px solid rgba(23, 162, 184, 0.2);
    }
    .badge-pdf-enabled {
        background-color: rgba(220, 53, 69, 0.08);
        color: #dc3545;
        border: 1px solid rgba(220, 53, 69, 0.15);
        text-decoration: none;
    }
    .badge-pdf-disabled {
        background-color: #f1f2f6;
        color: #a4b0be;
        border: 1px solid #dfe4ea;
    }
    .price-mrp {
        text-decoration: line-through;
        color: #a4b0be;
        font-size: 12px;
        margin-right: 6px;
    }
    .price-sales {
        font-weight: 700;
        color: #2c3e50;
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
                <h3>Professional Internships Management</h3>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <form method="GET" action="" class="mb-0 d-flex gap-2">
                        <select name="category_id" class="select-premium" onchange="this.form.submit()">
                            <option value="0">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= (isset($_GET['category_id']) && $_GET['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="search" class="search-input" placeholder="Search internship name..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </form>
                    <a href="add.php" class="btn-green-premium">
                        <i class="fa-solid fa-graduation-cap"></i> Add New Internship
                    </a>
                </div>
            </div>
            
            <div class="admin-body p-0">
                <div class="table-responsive">
                    <table class="table table-premium">
                        <thead>
                            <tr>
                                <th style="width: 80px;" class="text-center">Cover</th>
                                <th>Internship Name</th>
                                <th>Category</th>
                                <th>Duration</th>
                                <th class="text-center">Mode</th>
                                <th>Pricing (₹)</th>
                                <th class="text-center">Brochure PDF</th>
                                <th style="width: 120px;" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($internships)): ?>
                                <?php foreach ($internships as $intern): ?>
                                    <tr>
                                        <td class="text-center">
                                            <?php if (!empty($intern['internship_image'])): ?>
                                                <img src="<?= $project_base . $intern['internship_image'] ?>" class="internship-cover-preview" alt="Internship Cover">
                                            <?php else: ?>
                                                <img src="https://via.placeholder.com/50x38?text=Work" class="internship-cover-preview" alt="Internship Cover">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($intern['name']) ?></strong><br>
                                            <small class="text-muted"><code class="text-secondary"><?= htmlspecialchars($intern['slug']) ?></code></small>
                                        </td>
                                        <td><?= htmlspecialchars($intern['category_name']) ?></td>
                                        <td><strong><?= htmlspecialchars($intern['duration']) ?></strong> <?= ucfirst($intern['duration_unit']) ?></td>
                                        <td class="text-center">
                                            <span class="badge-premium <?= strtolower($intern['mode']) === 'online' ? 'badge-online' : 'badge-offline' ?>">
                                                <?= htmlspecialchars($intern['mode']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="price-mrp">₹<?= number_format($intern['mrp'], 2) ?></span>
                                            <span class="price-sales">₹<?= number_format($intern['sales_price'], 2) ?></span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($intern['brochure_enabled'] && !empty($intern['brochure_pdf'])): ?>
                                                <a href="<?= $project_base . $intern['brochure_pdf'] ?>" target="_blank" class="badge-premium badge-pdf-enabled" title="Download PDF Brochure">
                                                    <i class="fa-solid fa-file-pdf"></i> PDF
                                                </a>
                                            <?php else: ?>
                                                <span class="badge-premium badge-pdf-disabled">Disabled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="edit.php?id=<?= $intern['id'] ?>" class="btn-action-circle btn-edit-circle" title="Edit Internship">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                            <a href="javascript:void(0);" onclick="confirmDelete(<?= $intern['id'] ?>, '<?= htmlspecialchars($intern['name'], ENT_QUOTES) ?>')" class="btn-action-circle btn-delete-circle" title="Delete Internship">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">
                                        <i class="fa-solid fa-graduation-cap mb-3" style="font-size: 40px; color: #cbd5e0; display: block;"></i>
                                        No internships found in records. Click "Add New Internship" to register one.
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
            text: `You are about to permanently delete the internship "${name}". This will clean up all associated images and PDFs from disk!`,
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
                title: 'Database Alert',
                text: <?= json_encode($error_message) ?>,
                confirmButtonColor: '#28a745'
            });
        <?php endif; ?>
    });
</script>

<?php include '../footer.php'; ?>
