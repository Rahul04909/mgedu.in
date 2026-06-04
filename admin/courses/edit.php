<?php
/**
 * MG Education & Social Development Organization
 * Edit Course Panel
 */

include '../header.php';

$db = MG_GetDBConnection();
$error_message = '';
$course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$course = null;

// Fetch Course details
try {
    if ($course_id <= 0) {
        throw new Exception("Invalid course identifier.");
    }
    
    $stmt = $db->prepare("SELECT * FROM courses WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $course_id]);
    $course = $stmt->fetch();
    
    if (!$course) {
        throw new Exception("Academic course record not found.");
    }
} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Fetch categories for dropdown
try {
    $cat_stmt = $db->query("SELECT id, name FROM course_categories ORDER BY name ASC");
    $categories = $cat_stmt->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

// Generate CSRF token if empty
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Process Course Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_course']) && $course) {
    try {
        // Validate CSRF
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Security mismatch: CSRF validation failed.");
        }

        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $category_id = intval($_POST['category_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        
        $duration = intval($_POST['duration'] ?? 0);
        $duration_unit = trim($_POST['duration_unit'] ?? 'months');
        $mode = trim($_POST['mode'] ?? 'offline');
        
        $mrp = floatval($_POST['mrp'] ?? 0.00);
        $sales_price = floatval($_POST['sales_price'] ?? 0.00);

        // SEO Fields
        $meta_title = trim($_POST['meta_title'] ?? '');
        $meta_description = trim($_POST['meta_description'] ?? '');
        $meta_keywords = trim($_POST['meta_keywords'] ?? '');

        // Validations
        if (empty($name) || empty($slug) || $category_id <= 0 || $duration <= 0 || $mrp <= 0 || $sales_price <= 0) {
            throw new Exception("Please fill in all mandatory general, duration, and pricing fields.");
        }

        if ($sales_price > $mrp) {
            throw new Exception("Sales Price cannot exceed the original MRP price.");
        }

        // Clean slug
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9\-]/', '', str_replace(' ', '-', $slug)));

        // Verify unique slug in other courses
        $checkStmt = $db->prepare("SELECT id FROM courses WHERE slug = :slug AND id != :id LIMIT 1");
        $checkStmt->execute(['slug' => $slug, 'id' => $course_id]);
        if ($checkStmt->fetch()) {
            throw new Exception("The slug '{$slug}' is occupied by another course. Please provide a unique slug.");
        }

        // Setup uploads directory
        $rootPath = dirname(dirname(__DIR__));
        $uploadBaseDir = '/assets/uploads/courses/';
        $uploadFullDir = $rootPath . $uploadBaseDir;
        if (!file_exists($uploadFullDir)) {
            mkdir($uploadFullDir, 0755, true);
        }

        // 1. Update Main Cover Image
        $course_image = $course['course_image'];
        if (isset($_FILES['course_image']) && $_FILES['course_image']['error'] === UPLOAD_ERR_OK) {
            $imgTmp = $_FILES['course_image']['tmp_name'];
            $imgName = $_FILES['course_image']['name'];
            $imgInfo = @getimagesize($imgTmp);
            if ($imgInfo === false) {
                throw new Exception("Uploaded file is not a valid cover image.");
            }
            
            // Delete old cover image from disk
            if (!empty($course['course_image'])) {
                $oldCoverFile = $rootPath . '/' . $course['course_image'];
                if (file_exists($oldCoverFile)) { @unlink($oldCoverFile); }
            }

            $ext = strtolower(pathinfo($imgName, PATHINFO_EXTENSION));
            $newImgName = bin2hex(random_bytes(16)) . '.' . $ext;
            if (move_uploaded_file($imgTmp, $uploadFullDir . $newImgName)) {
                $course_image = 'assets/uploads/courses/' . $newImgName;
            }
        }

        // 2. Parse Existing Gallery and Process Removals
        $current_gallery = json_decode($course['gallery_images'] ?? '[]', true);
        if (!is_array($current_gallery)) {
            $current_gallery = [];
        }

        // Handles deleted gallery file removals
        $removed_images = isset($_POST['removed_gallery_images']) ? json_decode($_POST['removed_gallery_images'], true) : [];
        if (is_array($removed_images)) {
            foreach ($removed_images as $removed_path) {
                // Delete file from disk
                $delFile = $rootPath . '/' . $removed_path;
                if (file_exists($delFile)) { @unlink($delFile); }
                
                // Remove from gallery array
                if (($key = array_search($removed_path, $current_gallery)) !== false) {
                    unset($current_gallery[$key]);
                }
            }
        }

        // 3. Process and upload newly appended sequential gallery images
        if (isset($_FILES['gallery_files'])) {
            $files = $_FILES['gallery_files'];
            foreach ($files['name'] as $key => $nameVal) {
                if ($files['error'][$key] === UPLOAD_ERR_OK) {
                    $gTmp = $files['tmp_name'][$key];
                    $gInfo = @getimagesize($gTmp);
                    if ($gInfo !== false) {
                        $ext = strtolower(pathinfo($nameVal, PATHINFO_EXTENSION));
                        $newGName = bin2hex(random_bytes(16)) . '.' . $ext;
                        if (move_uploaded_file($gTmp, $uploadFullDir . $newGName)) {
                            $current_gallery[] = 'assets/uploads/courses/' . $newGName;
                        }
                    }
                }
            }
        }
        $gallery_images = json_encode(array_values($current_gallery)); // reset array keys

        // 4. Update Conditional Brochure
        $brochure_enabled = isset($_POST['brochure_enabled']) && $_POST['brochure_enabled'] == '1' ? 1 : 0;
        $brochure_pdf = $course['brochure_pdf'];

        if ($brochure_enabled) {
            if (isset($_FILES['brochure_pdf']) && $_FILES['brochure_pdf']['error'] === UPLOAD_ERR_OK) {
                $pdfTmp = $_FILES['brochure_pdf']['tmp_name'];
                $pdfName = $_FILES['brochure_pdf']['name'];
                $pdfType = $_FILES['brochure_pdf']['type'];
                $ext = strtolower(pathinfo($pdfName, PATHINFO_EXTENSION));
                
                if ($ext !== 'pdf' || $pdfType !== 'application/pdf') {
                    throw new Exception("Course Brochure must be a PDF document only.");
                }

                // Delete old PDF from disk
                if (!empty($course['brochure_pdf'])) {
                    $oldPdfFile = $rootPath . '/' . $course['brochure_pdf'];
                    if (file_exists($oldPdfFile)) { @unlink($oldPdfFile); }
                }

                $newPdfName = bin2hex(random_bytes(16)) . '.pdf';
                if (move_uploaded_file($pdfTmp, $uploadFullDir . $newPdfName)) {
                    $brochure_pdf = 'assets/uploads/courses/' . $newPdfName;
                }
            }
        } else {
            // If brochure was disabled, clean up current PDF file and empty database reference
            if (!empty($course['brochure_pdf'])) {
                $oldPdfFile = $rootPath . '/' . $course['brochure_pdf'];
                if (file_exists($oldPdfFile)) { @unlink($oldPdfFile); }
                $brochure_pdf = '';
            }
        }

        // 5. Update SEO Featured Image
        $featured_image = $course['featured_image'];
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
            $seoTmp = $_FILES['featured_image']['tmp_name'];
            $seoName = $_FILES['featured_image']['name'];
            $seoInfo = @getimagesize($seoTmp);
            if ($seoInfo === false) {
                throw new Exception("SEO Featured Image is not a valid image.");
            }

            // Delete old SEO image
            if (!empty($course['featured_image'])) {
                $oldSeoFile = $rootPath . '/' . $course['featured_image'];
                if (file_exists($oldSeoFile)) { @unlink($oldSeoFile); }
            }

            $ext = strtolower(pathinfo($seoName, PATHINFO_EXTENSION));
            $newSeoName = bin2hex(random_bytes(16)) . '.' . $ext;
            if (move_uploaded_file($seoTmp, $uploadFullDir . $newSeoName)) {
                $featured_image = 'assets/uploads/courses/' . $newSeoName;
            }
        }

        // 6. Regenerate Schema Markup (JSON-LD)
        $ratingCount = $course['ratings_count'] ?: rand(15, 60);
        $ratingSum = $course['ratings_sum'] ?: ($ratingCount * 4.8);
        $schemaArray = [
            "@context" => "https://schema.org",
            "@type" => "Course",
            "name" => $name,
            "description" => $description ?: "Acquire skills through our dynamic " . $name . " program.",
            "provider" => [
                "@type" => "Organization",
                "name" => "MG Education & Social Development Organization",
                "sameAs" => "https://mgedu.in"
            ],
            "offers" => [
                "@type" => "Offer",
                "category" => ucfirst($mode),
                "price" => $sales_price,
                "priceCurrency" => "INR"
            ],
            "aggregateRating" => [
                "@type" => "AggregateRating",
                "ratingValue" => "4.8",
                "ratingCount" => $ratingCount
            ]
        ];
        $seo_schema = json_encode($schemaArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // 7. Regenerate OG Metadata
        $ogArray = [
            "og:title" => $meta_title ?: $name,
            "og:description" => $meta_description ?: (strlen($description) > 150 ? substr($description, 0, 150) . '...' : $description),
            "og:type" => "website",
            "og:url" => "https://mgedu.in/courses/" . $slug,
            "og:image" => $featured_image ? "https://mgedu.in/" . $featured_image : ($course_image ? "https://mgedu.in/" . $course_image : '')
        ];
        $og_info = json_encode($ogArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // 8. Database Update
        $stmt = $db->prepare("
            UPDATE courses SET 
                category_id = :category_id, name = :name, slug = :slug, description = :description, 
                duration = :duration, duration_unit = :duration_unit, mode = :mode, mrp = :mrp, 
                sales_price = :sales_price, course_image = :course_image, gallery_images = :gallery_images, 
                brochure_enabled = :brochure_enabled, brochure_pdf = :brochure_pdf, meta_title = :meta_title, 
                meta_description = :meta_description, meta_keywords = :meta_keywords, 
                featured_image = :featured_image, og_info = :og_info
            WHERE id = :id
        ");

        $stmt->execute([
            'category_id' => $category_id,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'duration' => $duration,
            'duration_unit' => $duration_unit,
            'mode' => $mode,
            'mrp' => $mrp,
            'sales_price' => $sales_price,
            'course_image' => $course_image,
            'gallery_images' => $gallery_images,
            'brochure_enabled' => $brochure_enabled,
            'brochure_pdf' => $brochure_pdf,
            'meta_title' => $meta_title ?: $name,
            'meta_description' => $meta_description,
            'meta_keywords' => $meta_keywords,
            'featured_image' => $featured_image,
            'og_info' => $og_info,
            'id' => $course_id
        ]);

        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Course Updated',
                    text: 'The academic course changes have been saved successfully!',
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
    .gallery-preview-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }
    .gallery-preview-card {
        width: 100%;
        aspect-ratio: 4/3;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        position: relative;
        overflow: hidden;
        background-color: #f1f5f9;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .gallery-preview-card img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .gallery-remove-badge {
        position: absolute;
        top: 6px;
        right: 6px;
        width: 20px;
        height: 20px;
        background-color: rgba(220, 53, 69, 0.9);
        color: #fff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        cursor: pointer;
        transition: transform 0.2s ease;
        border: none;
    }
    .gallery-remove-badge:hover {
        transform: scale(1.15);
        background-color: #c0392b;
    }
    .media-placeholder-btn {
        width: 100%;
        aspect-ratio: 4/3;
        border-radius: 8px;
        border: 2px dashed #cbd5e1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #64748b;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 11px;
        font-weight: 600;
        gap: 6px;
    }
    .media-placeholder-btn:hover {
        border-color: #94a3b8;
        color: #334155;
        background-color: #f8fafc;
    }
    .media-placeholder-btn i {
        font-size: 20px;
    }
    .google-preview-card {
        background-color: #ffffff;
        border: 1px solid #dadce0;
        border-radius: 8px;
        padding: 15px;
        margin-top: 15px;
        font-family: Arial, sans-serif;
    }
    .google-url {
        font-size: 12px;
        color: #202124;
        margin-bottom: 4px;
        display: block;
        word-break: break-all;
    }
    .google-title {
        font-size: 18px;
        color: #1a0dab;
        text-decoration: none;
        margin-bottom: 4px;
        display: block;
        font-weight: normal;
        line-height: 1.2;
    }
    .google-snippet {
        font-size: 13px;
        color: #4d5156;
        line-height: 1.48;
    }
    .file-indicator-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 11px;
        background-color: #f1f2f6;
        border: 1px solid #dfe4ea;
        color: #57606f;
        padding: 4px 10px;
        border-radius: 4px;
        margin-top: 8px;
        text-decoration: none;
    }
    .file-indicator-badge:hover {
        background-color: #dfe4ea;
    }

    /* Premium Single Image Upload Previews */
    .image-preview-container {
        display: flex;
        align-items: center;
        padding: 10px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        background-color: #f8fafc;
        max-width: 380px;
        margin-top: 10px;
        transition: all 0.3s ease;
    }
    .image-preview-thumb {
        width: 70px;
        height: 52px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        background-color: #fff;
        flex-shrink: 0;
    }
    .image-preview-info {
        margin-left: 12px;
        overflow: hidden;
        flex-grow: 1;
    }
    .image-preview-filename {
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
        text-overflow: ellipsis;
        white-space: nowrap;
        overflow: hidden;
        max-width: 200px;
    }
    .image-preview-size {
        font-size: 11px;
        color: #64748b;
        margin-top: 2px;
    }
    .image-preview-remove {
        background: none;
        border: none;
        color: #ef4444;
        cursor: pointer;
        padding: 6px;
        font-size: 15px;
        transition: transform 0.2s ease, color 0.2s ease;
        line-height: 1;
        margin-left: auto;
    }
    .image-preview-remove:hover {
        transform: scale(1.15);
        color: #b91c1c;
    }
</style>

<div class="row pt-3">
    <?php if ($course): ?>
        <!-- Left form column -->
        <div class="col-lg-8">
            <form method="POST" action="" enctype="multipart/form-data" id="courseForm" onsubmit="return validatePricing()">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <!-- Hidden inputs container for newly selected gallery files -->
                <div id="hiddenFilesContainer"></div>
                
                <!-- Hidden queue for storing deleted existing files -->
                <input type="hidden" name="removed_gallery_images" id="removedGalleryImagesInput" value="[]">

                <!-- Section 1: General Info -->
                <div class="admin-card">
                    <div class="admin-header">
                        <h3><i class="fa-solid fa-book-open"></i> Course General Specifications</h3>
                    </div>
                    <div class="admin-body">
                        <div class="mb-4">
                            <label class="form-label" for="name">Course Name</label>
                            <input type="text" name="name" id="name" class="form-control" value="<?= htmlspecialchars($course['name']) ?>" required autocomplete="off">
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="slug">URL Slug <span class="seo-indicator-badge">Auto / Customizable</span></label>
                            <div class="slug-wrapper">
                                <input type="text" name="slug" id="slug" class="form-control" value="<?= htmlspecialchars($course['slug']) ?>" required readonly>
                                <button type="button" class="slug-lock-btn" id="slugLockBtn" title="Unlock Custom Editing"><i class="fa-solid fa-lock" id="lockIcon"></i></button>
                            </div>
                            <small class="text-muted d-block mt-1">Generated dynamically. Unlock to custom edit url path.</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="form-label" for="category_id">Course Category</label>
                                <select name="category_id" id="category_id" class="form-control" required>
                                    <option value="">-- Choose Category --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= $course['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label" for="mode">Course mode</label>
                                <select name="mode" id="mode" class="form-control" required>
                                    <option value="offline" <?= $course['mode'] === 'offline' ? 'selected' : '' ?>>Offline (In-Class)</option>
                                    <option value="online" <?= $course['mode'] === 'online' ? 'selected' : '' ?>>Online (Self-Paced/Live)</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label" for="description">Detailed Description</label>
                            <textarea name="description" id="description" class="form-control" rows="6" placeholder="Provide complete syllabus, module breakdowns..." style="resize: none;"><?= htmlspecialchars($course['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Duration and Pricing -->
                <div class="admin-card">
                    <div class="admin-header">
                        <h3><i class="fa-solid fa-clock-rotate-left"></i> Duration & Financials</h3>
                    </div>
                    <div class="admin-body">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="form-label" for="duration">Course Duration</label>
                                <div class="d-flex gap-2">
                                    <input type="number" name="duration" id="duration" class="form-control" value="<?= htmlspecialchars($course['duration']) ?>" min="1" required style="flex: 1.5;">
                                    <select name="duration_unit" id="duration_unit" class="form-control" style="flex: 1;">
                                        <option value="days" <?= $course['duration_unit'] === 'days' ? 'selected' : '' ?>>Days</option>
                                        <option value="months" <?= $course['duration_unit'] === 'months' ? 'selected' : '' ?>>Months</option>
                                        <option value="years" <?= $course['duration_unit'] === 'years' ? 'selected' : '' ?>>Years</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-6 mb-4">
                                <label class="form-label" for="mrp">Original MRP (₹)</label>
                                <input type="number" name="mrp" id="mrp" class="form-control" value="<?= htmlspecialchars($course['mrp']) ?>" min="0" step="0.01" required oninput="calculateOGInfo()">
                            </div>
                            <div class="col-md-3 col-6 mb-4">
                                <label class="form-label" for="sales_price">Discounted Sales Price (₹)</label>
                                <input type="number" name="sales_price" id="sales_price" class="form-control" value="<?= htmlspecialchars($course['sales_price']) ?>" min="0" step="0.01" required oninput="calculateOGInfo()">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Media Uploads -->
                <div class="admin-card">
                    <div class="admin-header">
                        <h3><i class="fa-solid fa-photo-film"></i> Course Media & brochure</h3>
                    </div>
                    <div class="admin-body">
                        <div class="mb-4">
                            <label class="form-label" for="course_image">Course Cover Image</label>
                            <input type="file" name="course_image" id="course_image" class="form-control" accept="image/*" onchange="previewSingleImage(this, 'cover_preview_container', 'existing_cover_box')">
                            <div id="cover_preview_container" style="display: none;"></div>
                            <?php if (!empty($course['course_image'])): ?>
                                <div class="mt-2" id="existing_cover_box">
                                    <div class="image-preview-container animate__animated animate__fadeIn">
                                        <img src="<?= $project_base . htmlspecialchars($course['course_image']) ?>" class="image-preview-thumb">
                                        <div class="image-preview-info">
                                            <div class="image-preview-filename" style="color: #28a745; font-weight: 600;">Active Cover Image</div>
                                            <a href="<?= $project_base . htmlspecialchars($course['course_image']) ?>" target="_blank" class="text-success text-decoration-none font-weight-bold" style="font-size: 11px;">
                                                <i class="fa-solid fa-up-right-from-square mr-1"></i>View Fullsize
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <small class="text-muted d-block mt-1">Leave blank to retain current cover file.</small>
                        </div>

                        <!-- WordPress Style Gallery Widget -->
                        <div class="mb-4">
                            <label class="form-label">WordPress-Style Course Gallery Images</label>
                            <div class="gallery-preview-grid" id="galleryGrid">
                                <?php 
                                $existing_gallery = json_decode($course['gallery_images'] ?? '[]', true);
                                if (is_array($existing_gallery)) {
                                    foreach ($existing_gallery as $index => $imgPath) {
                                        echo '
                                            <div class="gallery-preview-card" id="existing_card_' . $index . '">
                                                <img src="' . $project_base . $imgPath . '">
                                                <button type="button" class="gallery-remove-badge" onclick="removeExistingImage(' . $index . ', \'' . htmlspecialchars($imgPath, ENT_QUOTES) . '\')"><i class="fa-solid fa-xmark"></i></button>
                                            </div>
                                        ';
                                    }
                                }
                                ?>
                                <label class="media-placeholder-btn" for="gallery_uploader" id="uploaderPlaceholder">
                                    <i class="fa-solid fa-images"></i>
                                    <span>Upload Images One-by-One</span>
                                </label>
                            </div>
                            <input type="file" id="gallery_uploader" style="display:none;" accept="image/*">
                            <small class="text-muted d-block mt-2">Append new gallery photos sequentially. Preview thumbnails with removal overlays before saving.</small>
                        </div>

                        <!-- Conditional PDF Brochure -->
                        <div class="row">
                            <div class="col-md-6 mb-0">
                                <label class="form-label" for="brochure_enabled">Enable Course Brochure PDF?</label>
                                <select name="brochure_enabled" id="brochure_enabled" class="form-control" onchange="toggleBrochureUpload()">
                                    <option value="0" <?= $course['brochure_enabled'] == 0 ? 'selected' : '' ?>>Disabled</option>
                                    <option value="1" <?= $course['brochure_enabled'] == 1 ? 'selected' : '' ?>>Enabled (Require PDF upload)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-0" id="brochureUploadBox" style="display: <?= $course['brochure_enabled'] == 1 ? 'block' : 'none' ?>;">
                                <label class="form-label" for="brochure_pdf">Select Syllabus Brochure (PDF Only)</label>
                                <input type="file" name="brochure_pdf" id="brochure_pdf" class="form-control" accept="application/pdf">
                                <?php if (!empty($course['brochure_pdf'])): ?>
                                    <a href="<?= $project_base . $course['brochure_pdf'] ?>" target="_blank" class="file-indicator-badge">
                                        <i class="fa-solid fa-file-pdf"></i> Download PDF Brochure
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Advanced SEO -->
                <div class="admin-card">
                    <div class="admin-header">
                        <h3><i class="fa-solid fa-magnifying-glass-chart"></i> Search Engine Optimization (SEO)</h3>
                    </div>
                    <div class="admin-body">
                        <div class="mb-4">
                            <label class="form-label" for="meta_title">SEO Meta Title</label>
                            <input type="text" name="meta_title" id="meta_title" class="form-control" value="<?= htmlspecialchars($course['meta_title'] ?? '') ?>" placeholder="DCA Diploma classes in Prayag" oninput="calculateOGInfo()">
                            <small class="text-muted d-block mt-1">Recommended length: 50-60 characters.</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="meta_description">SEO Meta Description</label>
                            <textarea name="meta_description" id="meta_description" class="form-control" rows="3" placeholder="Join the DCA program at MG Education. Certification and coding wings." style="resize: none;" oninput="calculateOGInfo()"><?= htmlspecialchars($course['meta_description'] ?? '') ?></textarea>
                            <small class="text-muted d-block mt-1">Recommended length: 150-160 characters.</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="meta_keywords">SEO Meta Keywords</label>
                            <input type="text" name="meta_keywords" id="meta_keywords" class="form-control" value="<?= htmlspecialchars($course['meta_keywords'] ?? '') ?>" placeholder="computer, DCA course, coding classes">
                        </div>

                        <div class="mb-0">
                            <label class="form-label" for="featured_image">SEO OG Featured Image</label>
                            <input type="file" name="featured_image" id="featured_image" class="form-control" accept="image/*" onchange="previewSingleImage(this, 'featured_preview_container', 'existing_featured_box')">
                            <div id="featured_preview_container" style="display: none;"></div>
                            <?php if (!empty($course['featured_image'])): ?>
                                <div class="mt-2" id="existing_featured_box">
                                    <div class="image-preview-container animate__animated animate__fadeIn">
                                        <img src="<?= $project_base . htmlspecialchars($course['featured_image']) ?>" class="image-preview-thumb">
                                        <div class="image-preview-info">
                                            <div class="image-preview-filename" style="color: #28a745; font-weight: 600;">Active Social OG Image</div>
                                            <a href="<?= $project_base . htmlspecialchars($course['featured_image']) ?>" target="_blank" class="text-success text-decoration-none font-weight-bold" style="font-size: 11px;">
                                                <i class="fa-solid fa-up-right-from-square mr-1"></i>View Fullsize
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <small class="text-muted d-block mt-1">Leave blank to retain current social media card cover image.</small>
                        </div>
                    </div>
                </div>

                <div class="mb-5">
                    <button type="submit" name="update_course" class="btn-green-premium me-2">
                        <i class="fa-solid fa-floppy-disk"></i> Save Course Changes
                    </button>
                    <a href="index.php" class="btn-secondary-premium">
                        Cancel and Return
                    </a>
                </div>
            </form>
        </div>

        <!-- Right Side Previews -->
        <div class="col-lg-4">
            <div class="admin-card">
                <div class="admin-header">
                    <h3>Google Search Preview</h3>
                </div>
                <div class="admin-body">
                    <div class="google-preview-card">
                        <span class="google-url" id="googleUrlPreview">https://mgedu.in/courses/</span>
                        <a href="javascript:void(0);" class="google-title" id="googleTitlePreview">[Course Name] - MG Education</a>
                        <span class="google-snippet" id="googleSnippetPreview">[Detailed Course Description snippet...]</span>
                    </div>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-header">
                    <h3>Automatic Schema (JSON-LD)</h3>
                </div>
                <div class="admin-body">
                    <p style="font-size:13px; line-height:1.5; color:#4a5568;" class="mb-3">
                        Rich structured dynamic Course schema injected automatically:
                    </p>
                    <pre class="schema-preview-box" id="schemaPreview" style="max-height: 250px; overflow-y: auto;">
                    </pre>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    // Elements references
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');
    const slugLockBtn = document.getElementById('slugLockBtn');
    const lockIcon = document.getElementById('lockIcon');
    const modeInput = document.getElementById('mode');
    const salesPriceInput = document.getElementById('sales_price');
    const descInput = document.getElementById('description');
    
    // SEO Previews Elements
    const metaTitleInput = document.getElementById('meta_title');
    const metaDescInput = document.getElementById('meta_description');
    const googleTitlePreview = document.getElementById('googleTitlePreview');
    const googleSnippetPreview = document.getElementById('googleSnippetPreview');
    const googleUrlPreview = document.getElementById('googleUrlPreview');
    const schemaPreview = document.getElementById('schemaPreview');

    let slugLocked = true;
    let fileCounter = 0; // dynamically appends file DOM links

    // Dynamic track of deleted existing images
    let removedGalleryImages = [];
    const removedGalleryImagesInput = document.getElementById('removedGalleryImagesInput');

    function removeExistingImage(index, path) {
        // Hide card
        const card = document.getElementById(`existing_card_${index}`);
        if (card) card.remove();
        
        // Push path to removed array
        removedGalleryImages.push(path);
        removedGalleryImagesInput.value = JSON.stringify(removedGalleryImages);
    }

    // Helper slugify
    function generateSlug(text) {
        return text.toString().toLowerCase()
            .replace(/\s+/g, '-')
            .replace(/[^\w\-]+/g, '')
            .replace(/\-\-+/g, '-')
            .replace(/^-+/, '')
            .replace(/-+$/, '');
    }

    // Auto slug listeners
    nameInput.addEventListener('input', function() {
        if (slugLocked) {
            slugInput.value = generateSlug(nameInput.value);
        }
        calculateOGInfo();
    });

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
        calculateOGInfo();
    });

    slugInput.addEventListener('input', function() {
        slugInput.value = generateSlug(slugInput.value);
        calculateOGInfo();
    });

    // Toggle brochure upload field
    function toggleBrochureUpload() {
        const enabled = document.getElementById('brochure_enabled').value;
        const uploadBox = document.getElementById('brochureUploadBox');
        
        if (enabled == '1') {
            uploadBox.style.display = 'block';
        } else {
            uploadBox.style.display = 'none';
        }
    }

    // Dynamic Pricing Validator
    function validatePricing() {
        const mrp = parseFloat(document.getElementById('mrp').value || 0);
        const sales = parseFloat(document.getElementById('sales_price').value || 0);
        
        if (sales > mrp) {
            Swal.fire({
                icon: 'error',
                title: 'Pricing Conflict',
                text: 'Discounted Sales Price cannot be greater than the original MRP!',
                confirmButtonColor: '#28a745'
            });
            return false;
        }
        return true;
    }

    // WordPress style sequential uploader logic
    const galleryUploader = document.getElementById('gallery_uploader');
    const galleryGrid = document.getElementById('galleryGrid');
    const placeholder = document.getElementById('uploaderPlaceholder');
    const hiddenContainer = document.getElementById('hiddenFilesContainer');

    galleryUploader.addEventListener('change', function() {
        if (galleryUploader.files.length === 0) return;
        
        const file = galleryUploader.files[0];
        fileCounter++;

        // 1. Create a dynamic hidden input inside the form to transmit this specific file object on submit
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'file';
        hiddenInput.name = 'gallery_files[]';
        hiddenInput.style.display = 'none';
        hiddenInput.id = `gallery_file_${fileCounter}`;
        
        const container = new DataTransfer();
        container.items.add(file);
        hiddenInput.files = container.files;
        
        hiddenContainer.appendChild(hiddenInput);

        // 2. Render thumbnail preview in gallery grid
        const reader = new FileReader();
        reader.onload = function(e) {
            const card = document.createElement('div');
            card.className = 'gallery-preview-card animate__animated animate__zoomIn';
            card.id = `card_${fileCounter}`;

            card.innerHTML = `
                <img src="${e.target.result}">
                <button type="button" class="gallery-remove-badge" onclick="removeGalleryImage(${fileCounter})"><i class="fa-solid fa-xmark"></i></button>
            `;
            
            // Insert thumbnail card right before the plus uploader placeholder
            galleryGrid.insertBefore(card, placeholder);
        };
        reader.readAsDataURL(file);

        galleryUploader.value = '';
    });

    function removeGalleryImage(counterId) {
        const card = document.getElementById(`card_${counterId}`);
        const input = document.getElementById(`gallery_file_${counterId}`);
        if (card) card.remove();
        if (input) input.remove();
    }

    // Real-time SEO Preview generator
    function calculateOGInfo() {
        const name = nameInput.value || "[Course Name]";
        const desc = descInput.value || "[Detailed Course Description snippet...]";
        
        // Strip HTML tags for clean search/schema previews
        const cleanDesc = desc.replace(/<\/?[^>]+(>|$)/g, "");

        const slug = slugInput.value || "[slug]";
        const mTitle = metaTitleInput.value || (name + " - MG Education");
        const mDesc = metaDescInput.value || (cleanDesc.length > 150 ? cleanDesc.substr(0, 150) + '...' : cleanDesc);
        const mode = modeInput.value || "offline";
        const price = parseFloat(salesPriceInput.value || 0).toFixed(2);

        // Update Google SEO Preview
        googleUrlPreview.textContent = "https://mgedu.in/courses/" + slug;
        googleTitlePreview.textContent = mTitle;
        googleSnippetPreview.textContent = mDesc;

        // Update JSON-LD preview
        const schema = {
            "@context": "https://schema.org",
            "@type": "Course",
            "name": name,
            "description": cleanDesc,
            "provider": {
                "@type": "Organization",
                "name": "MG Education & Social Development Organization",
                "sameAs": "https://mgedu.in"
            },
            "offers": {
                "@type": "Offer",
                "category": mode.charAt(0).toUpperCase() + mode.slice(1),
                "price": price,
                "priceCurrency": "INR"
            },
            "aggregateRating": {
                "@type": "AggregateRating",
                "ratingValue": "4.8",
                "ratingCount": "<?= $course ? $course['ratings_count'] : '24' ?>"
            }
        };
        schemaPreview.textContent = JSON.stringify(schema, null, 2);
    }

    // Single image preview functions
    function previewSingleImage(input, containerId, existingBoxId = null) {
        const container = document.getElementById(containerId);
        container.innerHTML = '';
        container.style.display = 'none';

        if (existingBoxId) {
            const existingBox = document.getElementById(existingBoxId);
            if (existingBox) existingBox.style.display = input.files && input.files[0] ? 'none' : 'block';
        }

        if (input.files && input.files[0]) {
            const file = input.files[0];
            const sizeInKb = (file.size / 1024).toFixed(1) + ' KB';
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewHtml = `
                    <div class="image-preview-container animate__animated animate__fadeIn">
                        <img src="${e.target.result}" class="image-preview-thumb">
                        <div class="image-preview-info">
                            <div class="image-preview-filename" title="${file.name}">${file.name}</div>
                            <div class="image-preview-size">${sizeInKb}</div>
                        </div>
                        <button type="button" class="image-preview-remove" onclick="clearSingleImage('${input.id}', '${containerId}', '${existingBoxId}')" title="Clear selection">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                `;
                container.innerHTML = previewHtml;
                container.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    }

    function clearSingleImage(inputId, containerId, existingBoxId = null) {
        const input = document.getElementById(inputId);
        const container = document.getElementById(containerId);
        input.value = '';
        container.innerHTML = '';
        container.style.display = 'none';
        
        if (existingBoxId) {
            const existingBox = document.getElementById(existingBoxId);
            if (existingBox) existingBox.style.display = 'block';
        }
        calculateOGInfo();
    }

    // Initialize Trumbowyg Editor
    $(document).ready(function() {
        $('#description').trumbowyg({
            btns: [
                ['viewHTML'],
                ['undo', 'redo'],
                ['formatting'],
                ['strong', 'em', 'underline', 'del'],
                ['link'],
                ['insertImage'],
                ['justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull'],
                ['unorderedList', 'orderedList'],
                ['horizontalRule'],
                ['removeformat'],
                ['fullscreen']
            ],
            autogrow: true
        }).on('tbwchange', function() {
            calculateOGInfo();
        });
    });

    // Form errors feedback & initial runs
    document.addEventListener('DOMContentLoaded', function() {
        calculateOGInfo();

        <?php if (!empty($error_message)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Data Update Failed',
                text: <?= json_encode($error_message) ?>,
                confirmButtonColor: '#28a745'
            });
        <?php endif; ?>
    });
</script>

<?php include '../footer.php'; ?>
