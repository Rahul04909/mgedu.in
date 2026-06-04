<?php
/**
 * MG Education & Social Development Organization
 * Edit Internship Category Panel
 */

include '../header.php';

$db = MG_GetDBConnection();
$error_message = '';
$cat_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$category = null;

// Fetch active category
try {
    if ($cat_id <= 0) {
        throw new Exception("Invalid category identifier.");
    }
    
    $stmt = $db->prepare("SELECT * FROM internship_categories WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $cat_id]);
    $category = $stmt->fetch();
    
    if (!$category) {
        throw new Exception("The requested internship category was not found.");
    }
} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Generate CSRF token if empty
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Process update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category']) && $category) {
    try {
        // Validate CSRF
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Security mismatch: CSRF validation failed.");
        }

        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        // SEO Fields
        $meta_title = trim($_POST['meta_title'] ?? '');
        $meta_description = trim($_POST['meta_description'] ?? '');
        $meta_keywords = trim($_POST['meta_keywords'] ?? '');

        if (empty($name) || empty($slug)) {
            throw new Exception("Category Name and Slug are required.");
        }

        // Clean slug format
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9\-]/', '', str_replace(' ', '-', $slug)));

        // Verify unique slug in other categories
        $checkStmt = $db->prepare("SELECT id FROM internship_categories WHERE slug = :slug AND id != :id LIMIT 1");
        $checkStmt->execute(['slug' => $slug, 'id' => $cat_id]);
        if ($checkStmt->fetch()) {
            throw new Exception("The slug '{$slug}' is occupied by another category. Please provide a unique slug.");
        }

        // Generate Automatic Schema Markup (JSON-LD format)
        $schemaArray = [
            "@context" => "https://schema.org",
            "@type" => "ItemList",
            "name" => $name,
            "description" => strip_tags($description) ?: "Explore all internships in " . $name,
            "url" => "https://mgedu.in/internships/" . $slug
        ];
        $seo_schema = json_encode($schemaArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // Perform Database Update
        $stmt = $db->prepare("
            UPDATE internship_categories 
            SET name = :name, slug = :slug, description = :description, meta_title = :meta_title, 
                meta_description = :meta_description, meta_keywords = :meta_keywords, seo_schema = :seo_schema
            WHERE id = :id
        ");
        
        $stmt->execute([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'meta_title' => $meta_title ?: $name,
            'meta_description' => $meta_description,
            'meta_keywords' => $meta_keywords,
            'seo_schema' => $seo_schema,
            'id' => $cat_id
        ]);

        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Category Saved',
                    text: 'The category details were updated successfully!',
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
?>

<style>
    .admin-card {
        background: #ffffff;
        border: 1px solid #d1d7dc;
        border-radius: 12px;
        transition: all 0.3s ease;
        overflow: hidden;
        margin-bottom: 30px;
    }
    .admin-header {
        border-bottom: 1px solid #f0f0f1;
        padding: 20px 25px;
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
        padding: 30px 25px;
    }
    .form-label {
        font-size: 12px;
        font-weight: 600;
        color: #2c3e50;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
    }
    .form-control {
        border: 1px solid #d1d7dc;
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 14px;
        color: #2c3e50;
        width: 100%;
        box-sizing: border-box;
    }
    .form-control:focus {
        border-color: #8c9096;
        box-shadow: 0 0 0 3px rgba(140, 144, 150, 0.1);
        outline: none;
    }
    .slug-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }
    .slug-lock-btn {
        position: absolute;
        right: 12px;
        background: none;
        border: none;
        cursor: pointer;
        color: #718096;
        font-size: 14px;
        transition: color 0.2s ease;
    }
    .slug-lock-btn:hover {
        color: #2c3e50;
    }
    .btn-green-premium {
        background-color: #28a745;
        border-color: #28a745;
        color: #ffffff;
        font-weight: 600;
        border-radius: 8px;
        padding: 11px 22px;
        font-size: 14px;
        transition: all 0.2s ease;
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.15);
        border: none;
        cursor: pointer;
    }
    .btn-green-premium:hover {
        background-color: #218838;
        box-shadow: 0 6px 16px rgba(40, 167, 69, 0.25);
        color: #ffffff;
    }
    .btn-secondary-premium {
        background-color: #e2e8f0;
        color: #4a5568;
        font-weight: 600;
        border-radius: 8px;
        padding: 11px 22px;
        font-size: 14px;
        text-decoration: none;
        transition: all 0.2s ease;
        display: inline-block;
    }
    .btn-secondary-premium:hover {
        background-color: #cbd5e1;
        color: #2d3748;
    }
    .schema-preview-box {
        background-color: #f8f9fa;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 15px;
        font-family: 'Courier New', Courier, monospace;
        font-size: 12px;
        color: #4a5568;
        white-space: pre-wrap;
        word-break: break-all;
        max-height: 180px;
        overflow-y: auto;
    }
    .seo-indicator-badge {
        background-color: rgba(40, 167, 69, 0.1);
        border: 1px solid rgba(40, 167, 69, 0.2);
        color: #28a745;
        border-radius: 4px;
        padding: 2px 8px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        display: inline-block;
        margin-left: 10px;
    }
</style>

<div class="row pt-3">
    <?php if ($category): ?>
        <!-- Left Form Area -->
        <div class="col-lg-8">
            <form method="POST" action="" id="categoryForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div class="admin-card">
                    <div class="admin-header">
                        <h3><i class="fa-solid fa-folder-open"></i> Category Details Overview</h3>
                    </div>
                    
                    <div class="admin-body">
                        <div class="mb-4">
                            <label class="form-label" for="name">Category Name</label>
                            <input type="text" name="name" id="name" class="form-control" value="<?= htmlspecialchars($category['name']) ?>" required autocomplete="off">
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="slug">URL Slug <span class="seo-indicator-badge">Auto / Customizable</span></label>
                            <div class="slug-wrapper">
                                <input type="text" name="slug" id="slug" class="form-control" value="<?= htmlspecialchars($category['slug']) ?>" required readonly>
                                <button type="button" class="slug-lock-btn" id="slugLockBtn" title="Unlock Custom Editing"><i class="fa-solid fa-lock" id="lockIcon"></i></button>
                            </div>
                            <small class="text-muted d-block mt-1">Generated dynamically. Unlock to custom edit url path.</small>
                        </div>

                        <div class="mb-0">
                            <label class="form-label" for="description">General Description</label>
                            <textarea name="description" id="description" class="form-control" rows="5" placeholder="Provide category detailed context..." style="resize: none;"><?= htmlspecialchars($category['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- SEO Specifications -->
                <div class="admin-card">
                    <div class="admin-header">
                        <h3><i class="fa-solid fa-magnifying-glass"></i> Search Engine Optimization (SEO)</h3>
                    </div>
                    <div class="admin-body">
                        <div class="mb-4">
                            <label class="form-label" for="meta_title">SEO Meta Title</label>
                            <input type="text" name="meta_title" id="meta_title" class="form-control" value="<?= htmlspecialchars($category['meta_title'] ?? '') ?>" placeholder="Web Development Internships - MG Education">
                            <small class="text-muted d-block mt-1">Recommended length: 50-60 characters.</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="meta_description">SEO Meta Description</label>
                            <textarea name="meta_description" id="meta_description" class="form-control" rows="3" placeholder="Explore industry-oriented software engineering internships at MG Education." style="resize: none;"><?= htmlspecialchars($category['meta_description'] ?? '') ?></textarea>
                            <small class="text-muted d-block mt-1">Recommended length: 150-160 characters.</small>
                        </div>

                        <div class="mb-0">
                            <label class="form-label" for="meta_keywords">SEO Meta Keywords</label>
                            <input type="text" name="meta_keywords" id="meta_keywords" class="form-control" value="<?= htmlspecialchars($category['meta_keywords'] ?? '') ?>" placeholder="web development internships, computer science internships">
                            <small class="text-muted d-block mt-1">Comma-separated list of target keywords.</small>
                        </div>
                    </div>
                </div>

                <div class="mb-5">
                    <button type="submit" name="update_category" class="btn-green-premium me-2">
                        <i class="fa-solid fa-floppy-disk"></i> Save Category Changes
                    </button>
                    <a href="index.php" class="btn-secondary-premium">
                        Cancel and Return
                    </a>
                </div>
            </form>
        </div>

        <!-- Right Side: Automatic Schema Preview -->
        <div class="col-lg-4">
            <div class="admin-card">
                <div class="admin-header">
                    <h3><i class="fa-solid fa-code"></i> Automatic SEO Schema</h3>
                </div>
                <div class="admin-body">
                    <p style="font-size: 13px; line-height: 1.5; color: #4a5568;" class="mb-3">
                        MG Education systems dynamically generate highly structured **JSON-LD Schema Markup** for Google indexing on page generation:
                    </p>
                    <div class="schema-preview-box" id="schemaPreview"><?= htmlspecialchars($category['seo_schema'] ?: '') ?></div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');
    const slugLockBtn = document.getElementById('slugLockBtn');
    const lockIcon = document.getElementById('lockIcon');
    const descInput = document.getElementById('description');
    const schemaPreview = document.getElementById('schemaPreview');

    let slugLocked = true;

    // Helper to generate slug
    function generateSlug(text) {
        return text.toString().toLowerCase()
            .replace(/\s+/g, '-')           // Replace spaces with -
            .replace(/[^\w\-]+/g, '')       // Remove all non-word chars
            .replace(/\-\-+/g, '-')         // Replace multiple - with single -
            .replace(/^-+/, '')             // Trim - from start
            .replace(/-+$/, '');            // Trim - from end
    }

    // Initialize Summernote Lite Editor
    $(document).ready(function() {
        $('#description').summernote({
            placeholder: 'Provide category detailed context...',
            tabsize: 2,
            height: 220,
            toolbar: [
                ['font', ['bold', 'underline', 'clear']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['insert', ['link']],
                ['view', ['fullscreen', 'codeview']]
            ],
            callbacks: {
                onChange: function(contents) {
                    descInput.value = contents;
                    updateSchemaPreview();
                }
            }
        });
    });

    // Auto-slug generation listener
    nameInput.addEventListener('input', function() {
        if (slugLocked) {
            slugInput.value = generateSlug(nameInput.value);
        }
        updateSchemaPreview();
    });

    // Toggle custom slug locks
    slugLockBtn.addEventListener('click', function() {
        slugLocked = !slugLocked;
        if (slugLocked) {
            lockIcon.className = 'fa-solid fa-lock';
            slugInput.readOnly = true;
            slugInput.value = generateSlug(nameInput.value);
        } else {
            lockIcon.className = 'fa-solid fa-lock-open';
            slugInput.readOnly = false;
            slugInput.focus();
        }
        updateSchemaPreview();
    });

    slugInput.addEventListener('input', function() {
        slugInput.value = generateSlug(slugInput.value);
        updateSchemaPreview();
    });

    // Real-time Schema previewer
    function updateSchemaPreview() {
        const name = nameInput.value || "[Category Name]";
        const desc = descInput.value || "[General Description]";
        const slug = slugInput.value || "[slug]";

        // Strip HTML tags for clean schema display
        const cleanDesc = desc.replace(/<\/?[^>]+(>|$)/g, "");

        const schema = {
            "@context": "https://schema.org",
            "@type": "ItemList",
            "name": name,
            "description": cleanDesc,
            "url": "https://mgedu.in/internships/" + slug
        };

        schemaPreview.textContent = JSON.stringify(schema, null, 2);
    }

    // Form validation and feedback
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($error_message)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Data Load Error',
                text: <?= json_encode($error_message) ?>,
                confirmButtonColor: '#28a745'
            });
        <?php endif; ?>
    });
</script>

<?php include '../footer.php'; ?>
