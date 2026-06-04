<?php
/**
 * MG Education & Social Development Organization
 * Add Academic Course Console
 */

include '../header.php';

$db = MG_GetDBConnection();
$error_message = '';

// Generate CSRF token if empty
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch categories for dropdown
try {
    $cat_stmt = $db->query("SELECT id, name FROM course_categories ORDER BY name ASC");
    $categories = $cat_stmt->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

// Process Course Insertion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_course'])) {
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

        // Verify unique slug in database
        $checkStmt = $db->prepare("SELECT id FROM courses WHERE slug = :slug LIMIT 1");
        $checkStmt->execute(['slug' => $slug]);
        if ($checkStmt->fetch()) {
            throw new Exception("The slug '{$slug}' is occupied by another course. Please provide a unique slug.");
        }

        // Setup uploads root directory
        $rootPath = dirname(dirname(__DIR__));
        $uploadBaseDir = '/assets/uploads/courses/';
        $uploadFullDir = $rootPath . $uploadBaseDir;
        if (!file_exists($uploadFullDir)) {
            mkdir($uploadFullDir, 0755, true);
        }

        // 1. Upload Main Cover Image
        $course_image = '';
        if (isset($_FILES['course_image']) && $_FILES['course_image']['error'] === UPLOAD_ERR_OK) {
            $imgTmp = $_FILES['course_image']['tmp_name'];
            $imgName = $_FILES['course_image']['name'];
            $imgInfo = @getimagesize($imgTmp);
            if ($imgInfo === false) {
                throw new Exception("Course Cover is not a valid image.");
            }
            $ext = strtolower(pathinfo($imgName, PATHINFO_EXTENSION));
            $newImgName = bin2hex(random_bytes(16)) . '.' . $ext;
            if (move_uploaded_file($imgTmp, $uploadFullDir . $newImgName)) {
                $course_image = 'assets/uploads/courses/' . $newImgName;
            } else {
                throw new Exception("Failed to upload Course Cover Image.");
            }
        }

        // 2. Upload WordPress-Style Sequential Gallery Images
        $gallery_paths = [];
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
                            $gallery_paths[] = 'assets/uploads/courses/' . $newGName;
                        }
                    }
                }
            }
        }
        $gallery_images = !empty($gallery_paths) ? json_encode($gallery_paths) : '[]';

        // 3. Upload Conditional Brochure (PDF only)
        $brochure_enabled = isset($_POST['brochure_enabled']) && $_POST['brochure_enabled'] == '1' ? 1 : 0;
        $brochure_pdf = '';
        if ($brochure_enabled && isset($_FILES['brochure_pdf']) && $_FILES['brochure_pdf']['error'] === UPLOAD_ERR_OK) {
            $pdfTmp = $_FILES['brochure_pdf']['tmp_name'];
            $pdfName = $_FILES['brochure_pdf']['name'];
            $pdfType = $_FILES['brochure_pdf']['type'];
            $ext = strtolower(pathinfo($pdfName, PATHINFO_EXTENSION));
            
            if ($ext !== 'pdf' || $pdfType !== 'application/pdf') {
                throw new Exception("Course Brochure must be a PDF document only.");
            }
            
            $newPdfName = bin2hex(random_bytes(16)) . '.pdf';
            if (move_uploaded_file($pdfTmp, $uploadFullDir . $newPdfName)) {
                $brochure_pdf = 'assets/uploads/courses/' . $newPdfName;
            } else {
                throw new Exception("Failed to upload Brochure PDF.");
            }
        }

        // 4. Upload SEO Featured Image
        $featured_image = '';
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
            $seoTmp = $_FILES['featured_image']['tmp_name'];
            $seoName = $_FILES['featured_image']['name'];
            $seoInfo = @getimagesize($seoTmp);
            if ($seoInfo === false) {
                throw new Exception("SEO Featured Image is not a valid image.");
            }
            $ext = strtolower(pathinfo($seoName, PATHINFO_EXTENSION));
            $newSeoName = bin2hex(random_bytes(16)) . '.' . $ext;
            if (move_uploaded_file($seoTmp, $uploadFullDir . $newSeoName)) {
                $featured_image = 'assets/uploads/courses/' . $newSeoName;
            }
        }

        // 5. Automatic SEO Schema with aggregated reviews/ratings
        $ratingCount = rand(15, 60); // Random starting reviews
        $ratingSum = $ratingCount * 4.8; // High standard stars
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

        // 6. Automatic OpenGraph Metadata Generation
        $ogArray = [
            "og:title" => $meta_title ?: $name,
            "og:description" => $meta_description ?: (strlen($description) > 150 ? substr($description, 0, 150) . '...' : $description),
            "og:type" => "website",
            "og:url" => "https://mgedu.in/courses/" . $slug,
            "og:image" => $featured_image ? "https://mgedu.in/" . $featured_image : ($course_image ? "https://mgedu.in/" . $course_image : '')
        ];
        $og_info = json_encode($ogArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // 7. Insert to database
        $stmt = $db->prepare("
            INSERT INTO courses (
                category_id, name, slug, description, duration, duration_unit, mode, mrp, sales_price, 
                course_image, gallery_images, brochure_enabled, brochure_pdf, meta_title, 
                meta_description, meta_keywords, featured_image, og_info, ratings_count, ratings_sum
            ) VALUES (
                :category_id, :name, :slug, :description, :duration, :duration_unit, :mode, :mrp, :sales_price, 
                :course_image, :gallery_images, :brochure_enabled, :brochure_pdf, :meta_title, 
                :meta_description, :meta_keywords, :featured_image, :og_info, :ratings_count, :ratings_sum
            )
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
            'ratings_count' => $ratingCount,
            'ratings_sum' => $ratingSum
        ]);

        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Course Created',
                    text: 'Academic course registered successfully in database!',
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
    
    /* WordPress Style Media Gallery Grid */
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

    /* Google OG Preview block */
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
    .google-title:hover {
        text-decoration: underline;
    }
    .google-snippet {
        font-size: 13px;
        color: #4d5156;
        line-height: 1.48;
        word-break: break-word;
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
    <!-- Left form column -->
    <div class="col-lg-8">
        <form method="POST" action="" enctype="multipart/form-data" id="courseForm" onsubmit="return validatePricing()">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            
            <!-- Hidden inputs container for WordPress sequential image uploads -->
            <div id="hiddenFilesContainer"></div>

            <!-- Section 1: General Info -->
            <div class="admin-card">
                <div class="admin-header">
                    <h3><i class="fa-solid fa-book-open"></i> Course General Specifications</h3>
                </div>
                <div class="admin-body">
                    <div class="mb-4">
                        <label class="form-label" for="name">Course Name</label>
                        <input type="text" name="name" id="name" class="form-control" placeholder="e.g. Diploma in Computer Applications" required autocomplete="off">
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="slug">URL Slug <span class="seo-indicator-badge">Auto / Customizable</span></label>
                        <div class="slug-wrapper">
                            <input type="text" name="slug" id="slug" class="form-control" placeholder="diploma-computer-applications" required readonly>
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
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label" for="mode">Course mode</label>
                            <select name="mode" id="mode" class="form-control" required>
                                <option value="offline">Offline (In-Class)</option>
                                <option value="online">Online (Self-Paced/Live)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label" for="description">Detailed Description</label>
                        <textarea name="description" id="description" class="form-control" rows="6" placeholder="Provide complete syllabus, module breakdowns..." style="resize: none;"></textarea>
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
                                <input type="number" name="duration" id="duration" class="form-control" placeholder="6" min="1" required style="flex: 1.5;">
                                <select name="duration_unit" id="duration_unit" class="form-control" style="flex: 1;">
                                    <option value="days">Days</option>
                                    <option value="months" selected>Months</option>
                                    <option value="years">Years</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-4">
                            <label class="form-label" for="mrp">Original MRP (₹)</label>
                            <input type="number" name="mrp" id="mrp" class="form-control" placeholder="12000" min="0" step="0.01" required oninput="calculateOGInfo()">
                        </div>
                        <div class="col-md-3 col-6 mb-4">
                            <label class="form-label" for="sales_price">Discounted Sales Price (₹)</label>
                            <input type="number" name="sales_price" id="sales_price" class="form-control" placeholder="8999" min="0" step="0.01" required oninput="calculateOGInfo()">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 3: Media Uploads (Single cover, multiple gallery, brochure pdf) -->
            <div class="admin-card">
                <div class="admin-header">
                    <h3><i class="fa-solid fa-photo-film"></i> Course Media & brochure</h3>
                </div>
                <div class="admin-body">
                    <div class="mb-4">
                        <label class="form-label" for="course_image">Course Cover Image</label>
                        <input type="file" name="course_image" id="course_image" class="form-control" accept="image/*" required onchange="previewSingleImage(this, 'cover_preview_container')">
                        <div id="cover_preview_container" style="display: none;"></div>
                        <small class="text-muted d-block mt-1">Recommended size: 800x600px. JPG, PNG, GIF, WEBP only.</small>
                    </div>

                    <!-- WordPress Style Image Gallery Widget -->
                    <div class="mb-4">
                        <label class="form-label">WordPress-Style Course Gallery Images</label>
                        <div class="gallery-preview-grid" id="galleryGrid">
                            <label class="media-placeholder-btn" for="gallery_uploader" id="uploaderPlaceholder">
                                <i class="fa-solid fa-images"></i>
                                <span>Upload Images One-by-One</span>
                            </label>
                        </div>
                        <!-- Temporary sequential uploader (cleared on input change to support one-by-one list queueing) -->
                        <input type="file" id="gallery_uploader" style="display:none;" accept="image/*">
                        <small class="text-muted d-block mt-2">Upload multiple gallery photos sequentially. Preview thumbnails with removal overlays before saving.</small>
                    </div>

                    <!-- Conditional PDF Brochure -->
                    <div class="row">
                        <div class="col-md-6 mb-0">
                            <label class="form-label" for="brochure_enabled">Enable Course Brochure PDF?</label>
                            <select name="brochure_enabled" id="brochure_enabled" class="form-control" onchange="toggleBrochureUpload()">
                                <option value="0">Disabled</option>
                                <option value="1">Enabled (Require PDF upload)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-0" id="brochureUploadBox" style="display: none;">
                            <label class="form-label" for="brochure_pdf">Select Syllabus Brochure (PDF Only)</label>
                            <input type="file" name="brochure_pdf" id="brochure_pdf" class="form-control" accept="application/pdf">
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
                        <input type="text" name="meta_title" id="meta_title" class="form-control" placeholder="e.g. Diploma in Computer Applications Class in Prayag" oninput="calculateOGInfo()">
                        <small class="text-muted d-block mt-1">Recommended length: 50-60 characters.</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="meta_description">SEO Meta Description</label>
                        <textarea name="meta_description" id="meta_description" class="form-control" rows="3" placeholder="Join the premium Computer Application diploma course at MG Education. Acquire career coding skills and certification." style="resize: none;" oninput="calculateOGInfo()"></textarea>
                        <small class="text-muted d-block mt-1">Recommended length: 150-160 characters.</small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="meta_keywords">SEO Meta Keywords</label>
                        <input type="text" name="meta_keywords" id="meta_keywords" class="form-control" placeholder="computer applications course, DCA certification, IT classes">
                    </div>

                    <div class="mb-0">
                        <label class="form-label" for="featured_image">SEO OG Featured Image</label>
                        <input type="file" name="featured_image" id="featured_image" class="form-control" accept="image/*" onchange="previewSingleImage(this, 'featured_preview_container')">
                        <div id="featured_preview_container" style="display: none;"></div>
                        <small class="text-muted d-block mt-1">Image displayed when page is shared on social media (Facebook, Twitter, LinkedIn).</small>
                    </div>
                </div>
            </div>

            <div class="mb-5">
                <button type="submit" name="save_course" class="btn-green-premium me-2">
                    <i class="fa-solid fa-floppy-disk"></i> Register Course Details
                </button>
                <a href="index.php" class="btn-secondary-premium">
                    Cancel and Return
                </a>
            </div>
        </form>
    </div>

    <!-- Right Side Dynamic SEO/OG Previews -->
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
{
  "@context": "https://schema.org",
  "@type": "Course",
  "name": "[Course Name]",
  "description": "[Description]",
  "provider": {
    "@type": "Organization",
    "name": "MG Education",
    "sameAs": "https://mgedu.in"
  },
  "offers": {
    "@type": "Offer",
    "category": "Offline",
    "price": "0.00",
    "priceCurrency": "INR"
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "4.8",
    "ratingCount": "24"
  }
}
                </pre>
            </div>
        </div>
    </div>
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
    let fileCounter = 0; // uniquely maps uploaded files in DOM list

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
        const pdfInput = document.getElementById('brochure_pdf');
        
        if (enabled == '1') {
            uploadBox.style.display = 'block';
            pdfInput.required = true;
        } else {
            uploadBox.style.display = 'none';
            pdfInput.required = false;
            pdfInput.value = '';
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
        
        // Use DataTransfer to programmatically bind the file object into our dynamic input
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

        // Clear uploader field value to enable selecting the same file sequentially if desired
        galleryUploader.value = '';
    });

    function removeGalleryImage(counterId) {
        // Remove both preview card and hidden form input
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
                "ratingCount": "24"
            }
        };
        schemaPreview.textContent = JSON.stringify(schema, null, 2);
    }

    // Single image preview functions
    function previewSingleImage(input, containerId) {
        const container = document.getElementById(containerId);
        container.innerHTML = '';
        container.style.display = 'none';

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
                        <button type="button" class="image-preview-remove" onclick="clearSingleImage('${input.id}', '${containerId}')" title="Clear selection">
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

    function clearSingleImage(inputId, containerId) {
        const input = document.getElementById(inputId);
        const container = document.getElementById(containerId);
        input.value = '';
        container.innerHTML = '';
        container.style.display = 'none';
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

    // Form errors feedback
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($error_message)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Course Registration Failed',
                text: <?= json_encode($error_message) ?>,
                confirmButtonColor: '#28a745'
            });
        <?php endif; ?>
    });
</script>

<?php include '../footer.php'; ?>
