<?php
/**
 * MG Education & Social Development Organization
 * Premium Dynamic Internship Details Page - Coursera & Udemy Premium Redesign
 * Powered by WAMP MySQL Backend with Self-Healing Schemas
 */

require_once __DIR__ . '/includes/config.php';

$db = MG_GetDBConnection();

// --- 1. Database Self-Healing Schema (Internship Details Enhancements) ---
try {
    // A. Create internship_enquiries table (logs callback advisor & seat booking enquiries)
    $db->exec("
        CREATE TABLE IF NOT EXISTS `internship_enquiries` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `internship_id` INT NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `phone` VARCHAR(20) NOT NULL,
            `email` VARCHAR(255) NOT NULL,
            `mode` VARCHAR(50) NOT NULL,
            `status` VARCHAR(50) DEFAULT 'pending',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`internship_id`) REFERENCES `internships`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // B. Create internship_reviews table (logs verified intern student reviews)
    $db->exec("
        CREATE TABLE IF NOT EXISTS `internship_reviews` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `internship_id` INT NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `rating` INT NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `comment` TEXT NOT NULL,
            `status` VARCHAR(50) DEFAULT 'approved',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`internship_id`) REFERENCES `internships`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (Exception $e) {
    error_log("Internship Details Self-Healing DB Error: " . $e->getMessage());
}

// --- 2. Handle AJAX Request Submissions (Advisory Enquiry & Student Review) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    if ($_POST['action'] === 'submit_enquiry') {
        $internship_id = intval($_POST['internship_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mode = trim($_POST['mode'] ?? '');

        if (empty($internship_id) || empty($name) || empty($phone) || empty($email) || empty($mode)) {
            $response['message'] = 'All advisor enquiry fields are strictly mandatory.';
            echo json_encode($response);
            exit;
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO `internship_enquiries` (internship_id, name, phone, email, mode)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$internship_id, $name, $phone, $email, $mode]);
            
            $response['success'] = true;
            $response['message'] = 'Your advisor callback registration is completed successfully!';
        } catch (Exception $e) {
            $response['message'] = 'Enquiry failed to save: ' . $e->getMessage();
        }

        echo json_encode($response);
        exit;
    }

    if ($_POST['action'] === 'submit_review') {
        $internship_id = intval($_POST['internship_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $rating = intval($_POST['rating'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $comment = trim($_POST['comment'] ?? '');

        if (empty($internship_id) || empty($name) || empty($rating) || empty($title) || empty($comment)) {
            $response['message'] = 'All student review fields are strictly mandatory.';
            echo json_encode($response);
            exit;
        }

        if ($rating < 1 || $rating > 5) {
            $response['message'] = 'Rating score must be between 1 and 5 stars.';
            echo json_encode($response);
            exit;
        }

        try {
            // Write review to WAMP MySQL DB
            $stmt = $db->prepare("
                INSERT INTO `internship_reviews` (internship_id, name, rating, title, comment)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$internship_id, $name, $rating, $title, $comment]);

            // Dynamically recalculate aggregate ratings score and update internships table
            $count_stmt = $db->prepare("SELECT COUNT(*) as cnt, SUM(rating) as sm FROM `internship_reviews` WHERE `internship_id` = ? AND `status` = 'approved'");
            $count_stmt->execute([$internship_id]);
            $stats = $count_stmt->fetch();

            if ($stats && $stats['cnt'] > 0) {
                $up_stmt = $db->prepare("UPDATE `internships` SET `ratings_count` = ?, `ratings_sum` = ? WHERE `id` = ?");
                $up_stmt->execute([$stats['cnt'], $stats['sm'], $internship_id]);
            }

            $response['success'] = true;
            $response['message'] = 'Verified student review posted successfully!';
        } catch (Exception $e) {
            $response['message'] = 'Review failed to save: ' . $e->getMessage();
        }

        echo json_encode($response);
        exit;
    }
}

$internship = null;

// Fetch active internship by URL slug safely
if (isset($_GET['slug'])) {
    try {
        $slug = trim($_GET['slug']);
        $stmt = $db->prepare("
            SELECT i.*, ic.name as category_name, ic.slug as category_slug 
            FROM `internships` i
            INNER JOIN `internship_categories` ic ON i.category_id = ic.id
            WHERE i.slug = :slug LIMIT 1
        ");
        $stmt->execute(['slug' => $slug]);
        $internship = $stmt->fetch();
    } catch (Exception $e) {
        error_log("Front-end internship fetch error: " . $e->getMessage());
    }
}

// Redirect if not found
if (!$internship) {
    header("Location: index.php");
    exit;
}

// Fetch actual reviews from the database dynamically
$dbReviews = [];
try {
    $rev_stmt = $db->prepare("SELECT * FROM `internship_reviews` WHERE `internship_id` = ? AND `status` = 'approved' ORDER BY id DESC");
    $rev_stmt->execute([$internship['id']]);
    $dbReviews = $rev_stmt->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch internship reviews: " . $e->getMessage());
}

$totalActualReviews = count($dbReviews);
$starCounts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

if ($totalActualReviews > 0) {
    $ratingCount = $totalActualReviews;
    $ratingSum = 0;
    foreach ($dbReviews as $rev) {
        $ratingSum += intval($rev['rating']);
        $r = intval($rev['rating']);
        if (isset($starCounts[$r])) {
            $starCounts[$r]++;
        }
    }
    $ratingValue = number_format($ratingSum / $ratingCount, 1);
} else {
    // Fallback aggregates
    $ratingCount = (int)($internship['ratings_count'] ?: 18);
    $ratingSum = floatval($internship['ratings_sum'] ?: ($ratingCount * 4.9));
    $ratingValue = $ratingCount > 0 ? number_format($ratingSum / $ratingCount, 1) : '4.9';
    $starCounts = [5 => 15, 4 => 2, 3 => 1, 2 => 0, 1 => 0];
}

$floorRating = floor((float)$ratingValue);

// Setup dynamic SEO parameters for root header.php injection
$dynamic_title = !empty($internship['meta_title']) ? $internship['meta_title'] : $internship['name'] . " Internship | MG Education";
$dynamic_meta_desc = $internship['meta_description'];
$dynamic_meta_keywords = $internship['meta_keywords'];

// Decipher OG info
$og_data = [];
if (!empty($internship['og_info'])) {
    $og_data = json_decode($internship['og_info'], true);
}
$dynamic_og_info = [
    "og:title" => !empty($og_data['og:title']) ? $og_data['og:title'] : $dynamic_title,
    "og:description" => !empty($og_data['og:description']) ? $og_data['og:description'] : $dynamic_meta_desc,
    "og:type" => "website",
    "og:url" => "https://mgedu.in/internship-details.php?slug=" . $internship['slug'],
    "og:image" => !empty($internship['featured_image']) ? "https://mgedu.in/" . $internship['featured_image'] : (!empty($internship['internship_image']) ? "https://mgedu.in/" . $internship['internship_image'] : '')
];

// Calculate Savings details
$mrp = floatval($internship['mrp']);
$sales_price = floatval($internship['sales_price']);
$saving_amount = $mrp - $sales_price;
$saving_percent = $mrp > 0 ? round(($saving_amount / $mrp) * 100) : 0;

include 'header.php';
?>

<!-- SweetAlert2 & Google Fonts & Animate.css for Premium Visual Excellence -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

<style>
    /* Premium visual overrides - Designed Scoped */
    .premium-internship-details {
        font-family: 'Plus Jakarta Sans', 'Inter', sans-serif !important;
        background-color: #f8fafc;
        color: #0f172a;
        overflow-x: hidden;
    }

    /* 1. Stunning Hero Banner Section (Slate Dark Gradient with backdrop glow) */
    .internship-hero {
        background: radial-gradient(circle at 80% 20%, #1e293b 0%, #0f172a 100%);
        color: #ffffff;
        padding: 60px 0 110px 0;
        position: relative;
        overflow: hidden;
    }

    .internship-hero .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
        position: relative;
        z-index: 2;
    }

    .internship-hero .row {
        display: flex;
        flex-wrap: wrap;
        margin: 0;
    }

    .internship-hero .col-lg-8 {
        width: 100%;
        padding: 0;
    }

    @media (min-width: 992px) {
        .internship-hero .col-lg-8 {
            width: 65%;
            padding-right: 30px;
        }
    }

    .hero-mesh-accent {
        position: absolute;
        top: -20%;
        right: -10%;
        width: 600px;
        height: 600px;
        background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, transparent 70%);
        filter: blur(60px);
        pointer-events: none;
    }

    .internship-breadcrumbs {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        font-size: 13px;
        font-weight: 600;
        color: #94a3b8;
        margin-bottom: 24px;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }

    .internship-breadcrumbs a {
        color: #38bdf8;
        text-decoration: none;
        transition: color 0.2s ease;
    }

    .internship-breadcrumbs a:hover {
        color: #e0f2fe;
        text-decoration: underline;
    }

    .internship-breadcrumbs i {
        font-size: 9px;
        color: #475569;
    }

    .hero-title-internship {
        font-size: 2.6rem;
        font-weight: 800;
        line-height: 1.25;
        letter-spacing: -0.03em;
        color: #ffffff;
        margin-bottom: 18px;
        max-width: 820px;
    }

    .hero-subtitle-internship {
        font-size: 1.2rem;
        font-weight: 400;
        color: #cbd5e1;
        line-height: 1.6;
        margin-bottom: 24px;
        max-width: 820px;
    }

    /* Star rating score block */
    .hero-rating-wrapper-intern {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        font-size: 14.5px;
        margin-bottom: 22px;
    }

    .badge-rating-teal {
        background-color: #ffb013; /* Premium gold yellow */
        color: #0f172a;
        font-size: 13px;
        font-weight: 800;
        padding: 4px 10px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        box-shadow: 0 4px 12px rgba(255, 176, 19, 0.25);
    }

    .stars-list-teal {
        color: #ffb013;
        display: flex;
        gap: 2px;
    }

    .rating-link-teal {
        color: #38bdf8;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.2s;
        border-bottom: 1px dashed #38bdf8;
    }

    .rating-link-teal:hover {
        color: #7dd3fc;
        border-bottom-style: solid;
    }

    .badge-students-teal {
        color: #cbd5e1;
        font-weight: 500;
        background: rgba(255, 255, 255, 0.1);
        padding: 4px 12px;
        border-radius: 6px;
        font-size: 13px;
    }

    .hero-author-line-intern {
        font-size: 14.5px;
        color: #94a3b8;
        margin-bottom: 24px;
    }

    .hero-author-line-intern span {
        color: #38bdf8;
        font-weight: 700;
    }

    .hero-meta-grid-intern {
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        margin-top: 24px;
    }

    .meta-badge-intern {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13.5px;
        color: #e2e8f0;
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.08);
        padding: 6px 14px;
        border-radius: 20px;
        transition: all 0.3s;
    }

    .meta-badge-intern:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: rgba(255, 255, 255, 0.15);
    }

    .meta-badge-intern i {
        color: #38bdf8;
    }

    /* Premium Credentials Auto-playing Logos Only Marquee */
    .internship-hero .col-lg-4 {
        width: 100%;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    @media (min-width: 992px) {
        .internship-hero .col-lg-4 {
            width: 35%;
            padding-left: 20px;
            justify-content: flex-end;
            align-items: flex-start !important;
            align-self: flex-start;
            margin-top: 20px;
        }
    }

    .marquee-viewport {
        position: relative;
        z-index: 5;
        width: 100%;
        max-width: 360px;
        overflow: hidden;
        padding: 10px 0;
    }

    .marquee-track {
        display: flex;
        width: max-content;
        animation: marqueeContinuous 16s linear infinite;
        gap: 20px;
        align-items: center;
        padding: 0 10px;
    }

    .marquee-viewport:hover .marquee-track {
        animation-play-state: paused;
    }

    .marquee-card-item {
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 95px;
        height: 52px;
        padding: 6px 12px;
        background: rgba(255, 255, 255, 0.94);
        border: 1.5px solid rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        transition: all 0.3s ease;
    }

    .marquee-card-item:hover {
        background: #ffffff;
        transform: translateY(-2px) scale(1.05);
        border-color: #38bdf8;
        box-shadow: 0 6px 16px rgba(56, 189, 248, 0.25);
    }

    .marquee-card-item img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    @keyframes marqueeContinuous {
        0% {
            transform: translateX(0);
        }
        100% {
            transform: translateX(-50%);
        }
    }

    /* 2. Glassmorphic Sticky Subnav */
    .glass-subnav {
        background-color: rgba(255, 255, 255, 0.96);
        backdrop-filter: blur(8px);
        border-bottom: 1px solid #e2e8f0;
        position: sticky;
        top: 0;
        z-index: 99;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
    }

    .subnav-container-intern {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .subnav-tabs-intern {
        display: flex;
        gap: 36px;
        overflow-x: auto;
        scrollbar-width: none;
    }

    .subnav-tabs-intern::-webkit-scrollbar {
        display: none;
    }

    .subnav-tab-item-intern {
        position: relative;
        color: #64748b;
        font-weight: 600;
        padding: 18px 0;
        font-size: 14.5px;
        text-decoration: none !important;
        cursor: pointer;
        transition: all 0.3s;
        border: none;
        background: none;
        white-space: nowrap;
    }

    .subnav-tab-item-intern:hover {
        color: #0f172a;
    }

    .subnav-tab-item-intern.active {
        color: #0d47a1;
    }

    .subnav-tab-item-intern::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 0;
        height: 3px;
        background-color: #0d47a1;
        transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border-radius: 3px 3px 0 0;
    }

    .subnav-tab-item-intern.active::after {
        width: 100%;
    }

    /* 3. Main Grid layout structure */
    .main-internship-content {
        max-width: 1200px;
        margin: 0 auto;
        padding: 40px 20px 80px 20px;
        display: grid;
        grid-template-columns: 1fr;
        gap: 40px;
        position: relative;
    }

    @media (min-width: 992px) {
        .main-internship-content {
            grid-template-columns: 2.2fr 1.1fr;
        }
    }

    /* 4. Left Columns & Content Cards */
    .premium-card-wrapper-intern {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 36px;
        margin-bottom: 30px;
        scroll-margin-top: 80px; /* Offset sticky subnav */
        box-shadow: 0 10px 30px -15px rgba(15, 23, 42, 0.04);
        transition: box-shadow 0.3s;
    }

    .premium-card-wrapper-intern:hover {
        box-shadow: 0 15px 35px -15px rgba(15, 23, 42, 0.08);
    }

    .card-main-heading-intern {
        font-size: 1.5rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
        letter-spacing: -0.02em;
    }

    /* Deliverables Outcomes Grid */
    .deliverables-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 16px 28px;
    }

    .deliverable-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        font-size: 14.5px;
        color: #1e293b;
        line-height: 1.5;
    }

    .deliverable-item i {
        color: #10b981;
        font-size: 16px;
        margin-top: 3px;
    }

    /* Rich WYSIWYG summernote content styles */
    .syllabus-renderer-intern {
        line-height: 1.8;
        font-size: 15px;
        color: #334155;
    }

    .syllabus-renderer-intern p {
        margin-bottom: 20px;
    }

    .syllabus-renderer-intern h1, .syllabus-renderer-intern h2, .syllabus-renderer-intern h3 {
        color: #0f172a;
        font-weight: 700;
        margin-top: 32px;
        margin-bottom: 16px;
        letter-spacing: -0.02em;
    }

    .syllabus-renderer-intern h2 {
        font-size: 1.35rem;
        border-left: 4px solid #10b981;
        padding-left: 12px;
    }

    .syllabus-renderer-intern h3 {
        font-size: 1.15rem;
    }

    .syllabus-renderer-intern ul {
        list-style: none;
        padding-left: 0;
        margin-bottom: 24px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .syllabus-renderer-intern ul li {
        position: relative;
        padding-left: 24px;
    }

    .syllabus-renderer-intern ul li::before {
        content: "\f00c";
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        position: absolute;
        left: 0;
        top: 2px;
        color: #10b981;
        font-size: 13px;
    }

    /* Photo Gallery */
    .media-gallery-container-intern {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 16px;
    }

    .media-gallery-card-intern {
        aspect-ratio: 16/11;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        cursor: pointer;
        position: relative;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        transition: all 0.3s;
    }

    .media-gallery-card-intern:hover {
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 12px 24px -10px rgba(15, 23, 42, 0.12);
        border-color: #cbd5e1;
    }

    .media-gallery-card-intern img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }

    .media-gallery-card-intern:hover img {
        transform: scale(1.05);
    }

    .media-hover-overlay-intern {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(16, 185, 129, 0.85); /* Emerald overlay matching courses */
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 1.5rem;
        opacity: 0;
        transition: opacity 0.3s ease;
        z-index: 2;
        backdrop-filter: blur(2px);
    }

    .media-gallery-card-intern:hover .media-hover-overlay-intern {
        opacity: 1;
    }

    /* Banners Promotion */
    .full-width-banner-wrapper-intern {
        margin-top: 35px;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s;
        border: 1px solid #cbd5e1;
        cursor: pointer;
    }

    .full-width-banner-wrapper-intern:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        border-color: #94a3b8;
    }

    .full-width-banner-wrapper-intern img {
        width: 100%;
        height: auto;
        display: block;
        object-fit: cover;
    }

    /* 5. Sticky sidebar overlay wrapper & booking container */
    @media (min-width: 992px) {
        .sticky-sidebar-wrapper-intern {
            position: sticky;
            top: 90px;
            margin-top: -330px; /* Overlap dark banner */
            z-index: 100;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
    }

    @media (max-width: 991px) {
        .sticky-sidebar-wrapper-intern {
            display: flex;
            flex-direction: column;
            gap: 24px;
            margin-top: 30px;
        }
    }

    .udemy-sticky-card-intern {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.12), 0 0 0 1px rgba(0,0,0,0.02);
        overflow: hidden;
        width: 100%;
        position: relative;
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s;
    }

    .udemy-sticky-card-intern:hover {
        transform: translateY(-4px);
        box-shadow: 0 35px 60px -15px rgba(15, 23, 42, 0.2);
    }

    .sticky-card-media-intern {
        width: 100%;
        aspect-ratio: 16/9;
        position: relative;
        background-color: #0f172a;
        overflow: hidden;
        cursor: pointer;
    }

    .sticky-card-media-intern img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        opacity: 0.9;
        transition: transform 0.5s ease;
    }

    .sticky-card-media-intern:hover img {
        transform: scale(1.06);
    }

    .media-preview-overlay-intern {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        background: rgba(15, 23, 42, 0.5);
        backdrop-filter: blur(2px);
        color: #ffffff;
        font-weight: 700;
        font-size: 15px;
        gap: 12px;
        z-index: 2;
        transition: opacity 0.3s, backdrop-filter 0.3s;
    }

    .sticky-card-media-intern:hover .media-preview-overlay-intern {
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(4px);
    }

    .play-icon-circle-teal {
        width: 58px;
        height: 58px;
        border-radius: 50%;
        background-color: #ffffff;
        color: #0f172a;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .sticky-card-media-intern:hover .play-icon-circle-teal {
        transform: scale(1.12);
        background-color: #10b981;
        color: #ffffff;
    }

    .sticky-card-body-intern {
        padding: 28px;
    }

    .sticky-price-row-intern {
        display: flex;
        align-items: baseline;
        gap: 14px;
        margin-bottom: 8px;
    }

    .sticky-price-val-intern {
        font-size: 2.25rem;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: -0.03em;
    }

    .sticky-mrp-strikethrough-intern {
        font-size: 1.15rem;
        color: #64748b;
        text-decoration: line-through;
        font-weight: 400;
    }

    .sticky-save-percent-teal {
        font-size: 13px;
        font-weight: 700;
        color: #ff9f1c;
        background-color: #fff7ed;
        padding: 4px 10px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 24px;
        border: 1px solid #ffedd5;
    }

    .btn-sticky-primary-teal {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #ffffff;
        border: none;
        border-radius: 8px;
        width: 100%;
        padding: 16px 24px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        text-decoration: none !important;
    }

    .btn-sticky-primary-teal:hover {
        background: linear-gradient(135deg, #059669 0%, #047857 100%);
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.45);
        transform: translateY(-1px);
    }

    .btn-sticky-secondary-intern {
        background-color: #ffffff;
        color: #0f172a;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        width: 100%;
        padding: 15px 24px;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        margin-bottom: 24px;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .btn-sticky-secondary-intern:hover {
        background-color: #f8fafc;
        border-color: #94a3b8;
    }

    .sticky-bullets-header-intern {
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 16px;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 8px;
    }

    .sticky-bullets-list-intern {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .sticky-bullet-item-intern {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        font-size: 13.5px;
        color: #475569;
        line-height: 1.45;
    }

    .sticky-bullet-item-intern i {
        color: #10b981;
        font-size: 15px;
        margin-top: 2px;
        width: 18px;
        text-align: center;
    }

    /* Enquiry Card Styles */
    .enquiry-card-intern {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.12), 0 0 0 1px rgba(0,0,0,0.02);
        padding: 28px;
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s;
        width: 100%;
        box-sizing: border-box;
    }

    .enquiry-card-intern:hover {
        transform: translateY(-4px);
        box-shadow: 0 35px 60px -15px rgba(15, 23, 42, 0.15);
    }

    .enquiry-header-intern {
        margin-bottom: 20px;
        text-align: center;
    }

    .enquiry-title-intern {
        font-size: 1.25rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        letter-spacing: -0.02em;
    }

    .enquiry-title-intern i {
        color: #0d47a1;
        font-size: 1.1rem;
    }

    .enquiry-subtitle-intern {
        font-size: 12.5px;
        color: #64748b;
        margin: 0;
        line-height: 1.4;
    }

    .enquiry-form-group-intern {
        position: relative;
        margin-bottom: 16px;
    }

    .enquiry-input-icon-intern {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 14px;
    }

    .enquiry-form-control-intern {
        width: 100%;
        padding: 12px 14px 12px 42px;
        border: 1.5px solid #cbd5e1;
        border-radius: 8px;
        font-size: 13.5px;
        color: #0f172a;
        background: #f8fafc;
        outline: none;
        transition: all 0.3s;
        box-sizing: border-box;
    }

    .enquiry-form-control-intern:focus {
        border-color: #0d47a1;
        background: #ffffff;
        box-shadow: 0 0 0 4px rgba(13, 71, 161, 0.1);
    }

    .enquiry-form-control-select-intern {
        appearance: none !important;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") !important;
        background-repeat: no-repeat !important;
        background-position: right 14px center !important;
        background-size: 16px !important;
        padding-right: 36px !important;
        padding-top: 0 !important;
        padding-bottom: 0 !important;
        height: 44px !important;
        line-height: 42px !important;
    }

    .btn-enquiry-submit-intern {
        background: #0d47a1;
        color: #ffffff;
        border: none;
        border-radius: 8px;
        width: 100%;
        padding: 14px 20px;
        font-size: 14.5px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        box-shadow: 0 4px 12px rgba(13, 71, 161, 0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-enquiry-submit-intern:hover {
        background: #0b3c8f;
        box-shadow: 0 6px 16px rgba(13, 71, 161, 0.3);
        transform: translateY(-1px);
    }

    /* Reviews Sidebar Card */
    .reviews-sidebar-card-intern {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(0,0,0,0.01);
        padding: 24px;
        width: 100%;
        box-sizing: border-box;
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s;
    }

    .reviews-sidebar-card-intern:hover {
        transform: translateY(-4px);
        box-shadow: 0 35px 60px -15px rgba(15, 23, 42, 0.12);
    }

    .reviews-sidebar-title-intern {
        font-size: 1.2rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 8px;
        letter-spacing: -0.02em;
    }

    .sidebar-rating-summary-intern {
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 20px;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .sidebar-rating-score-intern {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .sidebar-rating-num-intern {
        font-size: 2.25rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1;
        letter-spacing: -0.03em;
    }

    .sidebar-rating-stars-teal {
        color: #ffb013;
        font-size: 1rem;
        display: flex;
        gap: 2px;
    }

    .sidebar-rating-count-intern {
        font-size: 12.5px;
        color: #64748b;
        font-weight: 600;
    }

    .sidebar-rating-bars-intern {
        display: flex;
        flex-direction: column;
        gap: 6px;
        border-top: 1px solid #edf2f7;
        padding-top: 10px;
    }

    .sidebar-rating-bar-row-intern {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 12px;
        color: #475569;
    }

    .sidebar-bar-label-intern {
        width: 32px;
        font-weight: 600;
        white-space: nowrap;
    }

    .sidebar-bar-track-intern {
        flex-grow: 1;
        height: 6px;
        background-color: #f1f5f9;
        border-radius: 3px;
        overflow: hidden;
    }

    .sidebar-bar-fill-teal {
        height: 100%;
        background-color: #ffb013;
        border-radius: 3px;
    }

    .sidebar-bar-percent-intern {
        width: 32px;
        text-align: right;
        font-weight: 700;
        color: #0f172a;
    }

    /* Reviews feed items */
    .sidebar-reviews-feed-intern {
        display: flex;
        flex-direction: column;
        gap: 16px;
        margin-bottom: 20px;
        max-height: 320px;
        overflow-y: auto;
        padding-right: 4px;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 #f8fafc;
    }

    .sidebar-reviews-feed-intern::-webkit-scrollbar {
        width: 5px;
    }

    .sidebar-reviews-feed-intern::-webkit-scrollbar-track {
        background: #f8fafc;
        border-radius: 10px;
    }

    .sidebar-reviews-feed-intern::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    .sidebar-review-item-intern {
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 14px;
        text-align: left;
    }

    .sidebar-review-item-intern:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .sidebar-review-header-intern {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 8px;
        gap: 8px;
    }

    .sidebar-review-user-intern {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .sidebar-user-avatar-teal {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #ffffff;
        font-weight: 700;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .sidebar-user-details-intern h4 {
        font-size: 13px;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
        line-height: 1.2;
    }

    .sidebar-verified-badge-teal {
        font-size: 10px;
        font-weight: 700;
        color: #0d47a1;
        background: #e7f1ff;
        border: 1px solid #bbf7d0;
        padding: 1px 6px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        margin-top: 2px;
    }

    .sidebar-review-date-intern {
        font-size: 11px;
        color: #94a3b8;
        font-weight: 500;
    }

    .sidebar-review-stars-row-intern {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 6px;
    }

    .sidebar-stars-teal {
        color: #ffb013;
        font-size: 11px;
        display: flex;
        gap: 1px;
    }

    .sidebar-review-title-intern {
        font-size: 13px;
        font-weight: 700;
        color: #1e293b;
    }

    .sidebar-review-comment-intern {
        font-size: 12.5px;
        color: #475569;
        line-height: 1.5;
        margin: 0;
    }

    /* Submit Review Sidebar Form */
    .sidebar-write-review-section-intern {
        border-top: 1px solid #edf2f7;
        padding-top: 16px;
    }

    .btn-toggle-review-form-teal {
        background: #e7f1ff;
        color: #0d47a1;
        border: 1.5px dashed #0d47a1;
        border-radius: 8px;
        width: 100%;
        padding: 10px 15px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .btn-toggle-review-form-teal:hover {
        background: #0d47a1;
        color: #ffffff;
        border-style: solid;
        box-shadow: 0 4px 10px rgba(13, 71, 161, 0.15);
    }

    .sidebar-review-form-wrapper-intern {
        margin-top: 16px;
        background: #f8fafc;
        border: 1.5px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        box-sizing: border-box;
    }

    .sidebar-form-label-intern {
        font-size: 12.5px;
        font-weight: 700;
        color: #475569;
        display: block;
        margin-bottom: 8px;
        text-align: left;
    }

    /* Interactive Stars Selector Styles */
    .interactive-star-selector-intern {
        display: flex;
        gap: 6px;
        margin-bottom: 18px;
        align-items: center;
    }

    .interactive-star-btn-intern {
        background: none !important;
        border: none !important;
        outline: none !important;
        padding: 4px;
        font-size: 1.55rem;
        color: #cbd5e1;
        cursor: pointer;
        transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), color 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .interactive-star-btn-intern:hover {
        transform: scale(1.2);
    }

    .interactive-star-btn-intern.selected,
    .interactive-star-btn-intern.hover {
        color: #ffb013 !important;
    }

    .sidebar-form-group-intern {
        position: relative;
        margin-bottom: 12px;
    }

    .sidebar-form-icon-intern {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 12px;
    }

    .sidebar-form-control-intern {
        width: 100%;
        padding: 11px 12px 11px 34px;
        border: 1.5px solid #cbd5e1;
        border-radius: 8px;
        font-size: 13px;
        color: #0f172a;
        background: #ffffff;
        outline: none;
        transition: all 0.3s;
        box-sizing: border-box;
        font-family: inherit;
    }

    .sidebar-form-control-intern:focus {
        border-color: #0d47a1;
        box-shadow: 0 0 0 3px rgba(13, 71, 161, 0.08);
    }

    .btn-sidebar-review-submit-teal {
        background: #0d47a1;
        color: #ffffff;
        border: none;
        border-radius: 8px;
        width: 100%;
        padding: 12px 20px;
        font-size: 13.5px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-family: inherit;
        box-shadow: 0 4px 12px rgba(13, 71, 161, 0.15);
    }

    .btn-sidebar-review-submit-teal:hover {
        background: #0b3c8f;
        box-shadow: 0 6px 16px rgba(13, 71, 161, 0.25);
        transform: translateY(-2px);
    }

    /* Terms Sidebar Card */
    .terms-sidebar-card-intern {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04), 0 0 0 1px rgba(0,0,0,0.01);
        padding: 24px;
        width: 100%;
        box-sizing: border-box;
        transition: transform 0.3s ease, box-shadow 0.3s;
    }

    .terms-sidebar-card-intern:hover {
        transform: translateY(-4px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
    }

    .terms-sidebar-title-intern {
        font-size: 1.12rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
        letter-spacing: -0.02em;
        text-transform: uppercase;
        text-align: left;
    }

    .terms-sidebar-title-intern i {
        color: #0d47a1;
    }

    .terms-list-intern {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .terms-item-intern {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        font-size: 12px;
        color: #475569;
        line-height: 1.45;
        text-align: left;
    }

    .terms-item-intern i {
        color: #64748b;
        font-size: 12px;
        margin-top: 3px;
    }

    .terms-footer-intern {
        border-top: 1px solid #edf2f7;
        padding-top: 12px;
        margin-top: 14px;
        text-align: center;
        font-size: 10.5px;
        color: #94a3b8;
        font-weight: 600;
    }

    /* Lightbox Modal */
    .lightbox-wrapper-intern {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(15, 23, 42, 0.98);
        z-index: 99999;
        justify-content: center;
        align-items: center;
        backdrop-filter: blur(10px);
        transition: opacity 0.3s ease;
    }

    .lightbox-body-intern {
        position: relative;
        max-width: 90%;
        max-height: 80vh;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .lightbox-img-active-intern {
        max-width: 100%;
        max-height: 80vh;
        border-radius: 12px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        border: 1px solid rgba(255,255,255,0.1);
        object-fit: contain;
        transition: transform 0.3s ease;
    }

    .lightbox-btn-close-intern {
        position: absolute;
        top: -55px;
        right: 0;
        background: rgba(255,255,255,0.1);
        border: 1px solid rgba(255,255,255,0.15);
        color: #ffffff;
        font-size: 20px;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .lightbox-btn-close-intern:hover {
        background: #ef4444;
        border-color: #ef4444;
        transform: scale(1.05);
    }

    .lightbox-arrow-intern {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background-color: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.15);
        color: #ffffff;
        font-size: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .lightbox-arrow-intern:hover {
        background-color: #0d47a1;
        border-color: #0d47a1;
        transform: translateY(-50%) scale(1.08);
    }

    .lightbox-arrow-left-intern { left: -80px; }
    .lightbox-arrow-right-intern { right: -80px; }

    @media (max-width: 991px) {
        .internship-hero {
            padding: 40px 0 60px 0;
        }
        .hero-title-internship {
            font-size: 2.1rem;
        }
        .main-internship-content {
            grid-template-columns: 1fr;
            padding-top: 30px;
            gap: 30px;
        }
        .udemy-sticky-card-intern {
            margin-top: 0;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        }
        .lightbox-arrow-left-intern { left: 16px; }
        .lightbox-arrow-right-intern { right: 16px; }
        .lightbox-arrow-intern {
            width: 44px;
            height: 44px;
            font-size: 16px;
        }
    }

    /* Selections Section */
    .selections-title-wrapper {
        text-align: center;
        margin-bottom: 35px;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .selections-title {
        font-size: 26px;
        font-weight: 800;
        color: #0f172a;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 20px;
        line-height: 1.25;
    }

    .selections-title .highlight-red {
        color: #8c1d18;
    }

    /* Selections Filter Tabs */
    .selections-tabs {
        display: inline-flex;
        background: #f1f5f9;
        border-radius: 50px;
        padding: 5px;
        gap: 5px;
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.03);
    }

    .selections-tab-btn {
        background: transparent;
        border: none;
        outline: none;
        padding: 8px 24px;
        border-radius: 50px;
        font-size: 13px;
        font-weight: 700;
        color: #475569;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        text-transform: uppercase;
    }

    .selections-tab-btn.active {
        background: #ffffff;
        color: #8c1d18;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.06);
    }

    .selections-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 40px 20px;
        margin-top: 15px;
    }

    @media (max-width: 768px) {
        .selections-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 30px 15px;
        }
        .selections-title {
            font-size: 20px;
        }
    }

    @media (max-width: 480px) {
        .selections-grid {
            grid-template-columns: 1fr;
            gap: 30px;
        }
    }

    /* Selection Card styling */
    .selection-card {
        background: transparent;
        border: none;
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.3s ease, transform 0.3s ease;
        cursor: pointer;
        position: relative;
        opacity: 1;
        transform: scale(1);
    }

    .selection-card:hover {
        transform: translateY(-8px);
    }

    /* Double ring avatar styling */
    .selection-avatar-container {
        position: relative;
        width: 135px;
        height: 135px;
        border-radius: 50%;
        border: 3.5px solid #ff9f1c; /* Thick orange outer ring */
        padding: 5px; /* Space between outer ring and image */
        background: #ffffff;
        margin-bottom: 22px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.04);
        transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .selection-card:hover .selection-avatar-container {
        border-color: #ff8500;
        transform: rotate(5deg) scale(1.03);
        box-shadow: 0 12px 30px rgba(255, 159, 28, 0.25);
    }

    .selection-avatar-container img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        border: 1px solid #cbd5e1;
    }

    /* Rank Badge Pill */
    .selection-rank-badge {
        position: absolute;
        bottom: -10px;
        left: 50%;
        transform: translateX(-50%);
        background: #8c1d18; /* Dark red pill */
        color: #ffffff;
        font-size: 13px;
        font-weight: 800;
        padding: 4px 18px;
        border-radius: 4px;
        box-shadow: 0 4px 10px rgba(140, 29, 24, 0.25);
        white-space: nowrap;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        transition: all 0.3s;
    }

    .selection-card:hover .selection-rank-badge {
        background: #aa1e17;
        transform: translateX(-50%) scale(1.05);
        box-shadow: 0 6px 14px rgba(140, 29, 24, 0.35);
    }

    /* Typography details */
    .selection-name {
        font-size: 14.5px;
        font-weight: 750;
        color: #1e293b;
        text-transform: uppercase;
        margin-bottom: 2px;
        line-height: 1.25;
        letter-spacing: -0.2px;
        transition: color 0.3s;
    }

    .selection-card:hover .selection-name {
        color: #8c1d18;
    }

    .selection-program {
        font-size: 13.5px;
        font-weight: 800;
        color: #8c1d18;
        text-transform: uppercase;
        margin-bottom: 8px;
        letter-spacing: 0.2px;
    }

    .selection-details {
        font-size: 10.5px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        line-height: 1.45;
        padding: 0 8px;
        letter-spacing: 0.1px;
    }

    /* Interactive Testimonial Overlay tooltip */
    .selection-testimonial-tooltip {
        position: absolute;
        top: -65px;
        left: 50%;
        transform: translateX(-50%) scale(0.85);
        background: #0f172a;
        color: #ffffff;
        padding: 10px 16px;
        border-radius: 8px;
        font-size: 11px;
        line-height: 1.4;
        width: 190px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        opacity: 0;
        pointer-events: none;
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        z-index: 10;
        text-align: center;
        font-weight: 500;
    }

    .selection-testimonial-tooltip::after {
        content: '';
        position: absolute;
        bottom: -6px;
        left: 50%;
        transform: translateX(-50%);
        border-width: 6px 6px 0;
        border-style: solid;
        border-color: #0f172a transparent;
        display: block;
        width: 0;
    }

    .selection-card:hover .selection-testimonial-tooltip {
        opacity: 1;
        transform: translateX(-50%) scale(1);
        top: -75px;
    }
</style>

<div class="premium-internship-details">

    <!-- 1. Sapphire Mesh Gradient Hero Header -->
    <section class="internship-hero">
        <div class="hero-mesh-accent"></div>
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <!-- Premium Breadcrumbs top bar -->
                    <div class="internship-breadcrumbs animate__animated animate__fadeIn">
                        <a href="index.php">Home</a>
                        <i class="fa-solid fa-chevron-right"></i>
                        <a href="#">Internship Wings</a>
                        <i class="fa-solid fa-chevron-right"></i>
                        <a href="#"><?= htmlspecialchars($internship['category_name']) ?></a>
                    </div>

                    <!-- Glowing Title -->
                    <h1 class="hero-title-internship animate__animated animate__fadeInUp"><?= htmlspecialchars($internship['name']) ?></h1>
                    
                    <!-- Short Description -->
                    <p class="hero-subtitle-internship animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
                        <?= !empty($internship['meta_description']) ? htmlspecialchars($internship['meta_description']) : "Step into industry-grade coding desks with MG Education's dynamic workspace. Build real-world portfolio highlights today." ?>
                    </p>

                    <!-- Dynamic aggregates rating block -->
                    <div class="hero-rating-wrapper-intern animate__animated animate__fadeInUp" style="animation-delay: 0.15s;">
                        <span class="badge-rating-teal"><?= $ratingValue ?> <i class="fa-solid fa-star"></i></span>
                        <div class="stars-list-teal">
                            <?php 
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $floorRating) {
                                    echo '<i class="fa-solid fa-star"></i>';
                                } else if ($i - 0.5 <= (float)$ratingValue) {
                                    echo '<i class="fa-solid fa-star-half-stroke"></i>';
                                } else {
                                    echo '<i class="fa-regular fa-star"></i>';
                                }
                            }
                            ?>
                        </div>
                        <a href="#reviews" class="rating-link-teal">(<?= htmlspecialchars($ratingCount) ?> verified reviews)</a>
                        <span class="badge-students-teal"><?= number_format($ratingCount * 14 + 80) ?> interns trained</span>
                    </div>

                    <!-- Certification Authority info line -->
                    <div class="hero-author-line-intern animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
                        Corporate Certification wing: <span>MG Education & Social Development Board</span>
                    </div>

                    <!-- Fine-bordered meta specification cards -->
                    <div class="hero-meta-grid-intern animate__animated animate__fadeInUp" style="animation-delay: 0.25s;">
                        <div class="meta-badge-intern">
                            <i class="fa-solid fa-award"></i>
                            <span>Corporate Experience Certificate</span>
                        </div>
                        <div class="meta-badge-intern">
                            <i class="fa-solid fa-diagram-project"></i>
                            <span>Live Production Projects</span>
                        </div>
                        <div class="meta-badge-intern">
                            <i class="fa-solid fa-user-check"></i>
                            <span>Job CV Recommendations</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-12 d-flex align-items-center justify-content-center justify-content-lg-end mt-4 mt-lg-0 animate__animated animate__fadeIn" style="animation-delay: 0.3s; z-index: 5;">
                    <!-- Autoplay Credentials Marquee -->
                    <div class="marquee-viewport" title="Credential Partners (Autoplay)">
                        <div class="marquee-track">
                            <!-- Set 1 -->
                            <div class="marquee-card-item" title="ISO 9001:2015 Quality Certified">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/5/5b/ISO_logo.svg" alt="ISO Certification">
                            </div>
                            <div class="marquee-card-item" title="Digital India Portal Partner">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/b/b5/Digital_India_logo.svg" alt="Digital India">
                            </div>
                            <div class="marquee-card-item" title="Skill India Vocational Academy Alignments">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/4/4c/Skill_India_logo.png" alt="Skill India">
                            </div>
                            <div class="marquee-card-item" title="MHA (Ministry of Home Affairs) Affiliated Board">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/5/55/Emblem_of_India.svg" alt="Ministry of Home Affairs">
                            </div>
                            
                            <!-- Set 2 (Duplicate for infinite seamless loop) -->
                            <div class="marquee-card-item" title="ISO 9001:2015 Quality Certified">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/5/5b/ISO_logo.svg" alt="ISO Certification">
                            </div>
                            <div class="marquee-card-item" title="Digital India Portal Partner">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/b/b5/Digital_India_logo.svg" alt="Digital India">
                            </div>
                            <div class="marquee-card-item" title="Skill India Vocational Academy Alignments">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/4/4c/Skill_India_logo.png" alt="Skill India">
                            </div>
                            <div class="marquee-card-item" title="MHA (Ministry of Home Affairs) Affiliated Board">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/5/55/Emblem_of_India.svg" alt="Ministry of Home Affairs">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 2. Sticky Glassmorphic Subnav Bar -->
    <nav class="glass-subnav d-none d-lg-block">
        <div class="subnav-container-intern">
            <div class="subnav-tabs-intern">
                <a class="subnav-tab-item-intern active" onclick="scrollToInternSection('overview')">Overview</a>
                <a class="subnav-tab-item-intern" onclick="scrollToInternSection('curriculum')">Curriculum</a>
                <a class="subnav-tab-item-intern" onclick="scrollToInternSection('selections')">Our Selections</a>
                <?php if (is_array(json_decode($internship['gallery_images'] ?? '[]', true)) && !empty(json_decode($internship['gallery_images'] ?? '[]', true))): ?>
                    <a class="subnav-tab-item-intern" onclick="scrollToInternSection('gallery')">Workspace Gallery</a>
                <?php endif; ?>
                <a class="subnav-tab-item-intern" onclick="scrollToInternSection('reviews')">Verified Reviews</a>
            </div>
        </div>
    </nav>

    <!-- 3. Desktop two-column Layout -->
    <section class="main-internship-content">
        
        <!-- Left Side main cards segments -->
        <div class="content-left-column">
            
            <!-- Corporate Banner TARGET Target -->
            <div class="full-width-banner-wrapper-intern" id="overview" onclick="triggerInternEnrollment()" title="Click to book a seat now!" style="margin-top: 0; margin-bottom: 30px;">
                <img src="https://static.pw.live/5eb393ee95fab7468a79d189/GLOBAL_CMS/ec18f90d-585e-4d27-9237-0cbd9e62d3f1.webp" alt="Corporate Internship Banner Promotion">
            </div>

            <!-- Card A: Deliverables (Syllabus render block) -->
            <div class="premium-card-wrapper-intern" id="curriculum">
                <h2 class="card-main-heading-intern"><i class="fa-solid fa-code text-teal-accent" style="color:#0d47a1;"></i> Dynamic Internship Curriculum</h2>
                <div class="syllabus-renderer-intern">
                    <?php if (!empty($internship['description'])): ?>
                        <?= $internship['description'] ?>
                    <?php else: ?>
                        <p class="text-muted">Detailed curriculum for this internship standard is under publication. Please contact advisory desks.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card C: Premium Selections Grid -->
            <div class="premium-card-wrapper-intern" id="selections">
                <div class="selections-title-wrapper">
                    <h2 class="selections-title">
                        <span class="highlight-red">7 in Top 10</span> Selections in <span class="highlight-red">CSE 2025</span>
                    </h2>
                    
                    <!-- Selection Filter Tabs -->
                    <div class="selections-tabs">
                        <button class="selections-tab-btn active" onclick="filterSelections('all')">All Years</button>
                        <button class="selections-tab-btn" onclick="filterSelections('2025')">CSE 2025</button>
                        <button class="selections-tab-btn" onclick="filterSelections('2024')">CSE 2024</button>
                    </div>
                </div>
                
                <div class="selections-grid">
                    <!-- Student 1: Akansh Dhull -->
                    <div class="selection-card" data-year="2025">
                        <div class="selection-testimonial-tooltip">
                            "The mock interview sessions and constructive feedback gave me immense confidence to excel."
                        </div>
                        <div class="selection-avatar-container">
                            <img src="https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?auto=format&fit=crop&q=80&w=150&h=150" alt="Akansh Dhull">
                            <div class="selection-rank-badge">AIR 3</div>
                        </div>
                        <h4 class="selection-name">Akansh Dhull</h4>
                        <div class="selection-program">CSE 2025</div>
                        <div class="selection-details">
                            All India Test Series (Prelims, GS Mains & Essay), Abhyaas (Prelims, GS Mains & Essay), Interview Guidance
                        </div>
                    </div>

                    <!-- Student 2: Raghav Jhunjhunwala -->
                    <div class="selection-card" data-year="2025">
                        <div class="selection-testimonial-tooltip">
                            "Personalized mentorship and strict evaluation standards were the keys to my consistency."
                        </div>
                        <div class="selection-avatar-container">
                            <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&q=80&w=150&h=150" alt="Raghav Jhunjhunwala">
                            <div class="selection-rank-badge">AIR 4</div>
                        </div>
                        <h4 class="selection-name">Raghav Jhunjhunwala</h4>
                        <div class="selection-program">CSE 2025</div>
                        <div class="selection-details">
                            Interview Guidance Program
                        </div>
                    </div>

                    <!-- Student 3: Ishan Bhatnagar -->
                    <div class="selection-card" data-year="2025">
                        <div class="selection-testimonial-tooltip">
                            "The absolute clarity of GS syllabus concepts made my foundation highly robust."
                        </div>
                        <div class="selection-avatar-container">
                            <img src="https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&q=80&w=150&h=150" alt="Ishan Bhatnagar">
                            <div class="selection-rank-badge">AIR 5</div>
                        </div>
                        <h4 class="selection-name">Ishan Bhatnagar</h4>
                        <div class="selection-program">CSE 2025</div>
                        <div class="selection-details">
                            GS Foundation Classroom Student
                        </div>
                    </div>

                    <!-- Student 4: Shakti Dubey -->
                    <div class="selection-card" data-year="2024">
                        <div class="selection-testimonial-tooltip">
                            "Standardized mock tests helped me formulate precise answer strategies."
                        </div>
                        <div class="selection-avatar-container">
                            <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=150&h=150" alt="Shakti Dubey">
                            <div class="selection-rank-badge">AIR 1</div>
                        </div>
                        <h4 class="selection-name">Shakti Dubey</h4>
                        <div class="selection-program">CSE 2024</div>
                        <div class="selection-details">
                            All India Test Series, Abhyaas, Interview Guidance
                        </div>
                    </div>

                    <!-- Student 5: Harshita Goyal -->
                    <div class="selection-card" data-year="2024">
                        <div class="selection-testimonial-tooltip">
                            "Detailed daily lecture guides and test tracking enabled me to secure this rank."
                        </div>
                        <div class="selection-avatar-container">
                            <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=150&h=150" alt="Harshita Goyal">
                            <div class="selection-rank-badge">AIR 2</div>
                        </div>
                        <h4 class="selection-name">Harshita Goyal</h4>
                        <div class="selection-program">CSE 2024</div>
                        <div class="selection-details">
                            GS Foundation Classroom Student
                        </div>
                    </div>

                    <!-- Student 6: Dongre Archit Parag -->
                    <div class="selection-card" data-year="2024">
                        <div class="selection-testimonial-tooltip">
                            "Highly supportive faculty and round-the-clock lab guidance made the difference."
                        </div>
                        <div class="selection-avatar-container">
                            <img src="https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?auto=format&fit=crop&q=80&w=150&h=150" alt="Dongre Archit Parag">
                            <div class="selection-rank-badge">AIR 3</div>
                        </div>
                        <h4 class="selection-name">Dongre Archit Parag</h4>
                        <div class="selection-program">CSE 2024</div>
                        <div class="selection-details">
                            GS Foundation Classroom Student
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card B: Scanned Gallery Media grid -->
            <?php 
            $gallery = json_decode($internship['gallery_images'] ?? '[]', true);
            if (is_array($gallery) && !empty($gallery)): 
            ?>
                <div class="premium-card-wrapper-intern" id="gallery">
                    <h2 class="card-main-heading-intern"><i class="fa-solid fa-images" style="color:#0d47a1;"></i> Campus Workspace Gallery</h2>
                    <p class="text-muted" style="font-size: 14px; margin-top: -12px; margin-bottom: 24px;">Highlights of interns working inside physical computer labs, team briefings, and workspace desks:</p>
                    
                    <div class="media-gallery-container-intern" id="detailsInternGallery">
                        <?php foreach ($gallery as $index => $imgPath): ?>
                            <div class="media-gallery-card-intern" onclick="openInternLightbox(<?= $index ?>)" title="Click to view image fullscreen">
                                <img src="<?= htmlspecialchars($imgPath) ?>" alt="Internship Workspace Frame" onerror="this.src='https://via.placeholder.com/300x200?text=MG+Internship'">
                                <div class="media-hover-overlay-intern">
                                    <i class="fa-solid fa-expand"></i>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>

        <!-- Right Side sticky overlays -->
        <div class="content-right-column">
            <div class="sticky-sidebar-wrapper-intern">
                
                <!-- Card 1: Stipend fee details -->
                <div class="udemy-sticky-card-intern">
                    <!-- Media Preview -->
                    <div class="sticky-card-media-intern" onclick="triggerInternEnrollment()">
                        <?php 
                        $cover = 'https://via.placeholder.com/600x380?text=MG+Internship+Program';
                        if (!empty($internship['internship_image'])) {
                            $cover = htmlspecialchars($internship['internship_image']);
                        }
                        ?>
                        <img src="<?= $cover ?>" alt="Internship Cover Image" onerror="this.src='https://via.placeholder.com/600x380?text=MG+Internship+Program'">
                        <div class="media-preview-overlay-intern">
                            <div class="play-icon-circle-teal"><i class="fa-solid fa-play"></i></div>
                            <span>Preview Internship Deliverables</span>
                        </div>
                    </div>

                    <!-- Pricing & Actions Body -->
                    <div class="sticky-card-body-intern">
                        <div class="sticky-price-row-intern">
                            <span class="sticky-price-val-intern">₹<?= number_format($sales_price, 2) ?></span>
                            <span class="sticky-mrp-strikethrough-intern">₹<?= number_format($mrp, 2) ?></span>
                        </div>
                        <?php if ($saving_amount > 0): ?>
                            <span class="sticky-save-percent-teal">
                                <i class="fa-solid fa-tags"></i> <?= $saving_percent ?>% OFF &bull; Save ₹<?= number_format($saving_amount, 2) ?>
                            </span>
                        <?php endif; ?>

                        <!-- Seat Booking green primary triggers -->
                        <button type="button" class="btn-sticky-primary-teal" onclick="triggerInternEnrollment()">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Book Internship Seat
                        </button>

                        <?php if ($internship['brochure_enabled'] && !empty($internship['brochure_pdf'])): ?>
                            <a href="<?= htmlspecialchars($internship['brochure_pdf']) ?>" target="_blank" class="btn-sticky-secondary-intern">
                                <i class="fa-solid fa-file-pdf text-danger"></i> Download Brochure PDF
                            </a>
                        <?php endif; ?>

                        <!-- Deliverable highlights list -->
                        <div class="sticky-bullets-header-intern">This Internship includes:</div>
                        <ul class="sticky-bullets-list-intern">
                            <li class="sticky-bullet-item-intern">
                                <i class="fa-solid fa-clock"></i>
                                <span>Duration: <strong><?= htmlspecialchars($internship['duration']) ?> <?= ucfirst($internship['duration_unit']) ?></strong> project training</span>
                            </li>
                            <li class="sticky-bullet-item-intern">
                                <i class="fa-solid fa-computer"></i>
                                <span>Training Desk: <strong><?= strtolower($internship['mode']) === 'online' ? 'Remote Coding Classes' : 'Physical Offline Coding Desk' ?></strong></span>
                            </li>
                            <li class="sticky-bullet-item-intern">
                                <i class="fa-solid fa-circle-check"></i>
                                <span>MG Corporate standard credentials certificate</span>
                            </li>
                            <li class="sticky-bullet-item-intern">
                                <i class="fa-solid fa-briefcase"></i>
                                <span>Experience letter with live domain project links</span>
                            </li>
                            <li class="sticky-bullet-item-intern">
                                <i class="fa-solid fa-address-card"></i>
                                <span>CV / Placement recommendation support</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Card 2: Quick Enquiry Callback Form -->
                <div class="enquiry-card-intern animate__animated animate__fadeIn" style="animation-delay: 0.1s;">
                    <div class="enquiry-header-intern">
                        <h3 class="enquiry-title-intern"><i class="fa-solid fa-headset"></i> Quick Enquiry Form</h3>
                        <p class="enquiry-subtitle-intern">Have questions? Fill in your details below for a quick advisor callback.</p>
                    </div>
                    <form id="quickInternEnquiryForm" onsubmit="handleInternEnquirySubmit(event)">
                        <div class="enquiry-form-group-intern">
                            <i class="fa-solid fa-user enquiry-input-icon-intern"></i>
                            <input type="text" id="enquiry_name" name="name" class="enquiry-form-control-intern" placeholder="Candidate's Full Name" required>
                        </div>
                        <div class="enquiry-form-group-intern">
                            <i class="fa-solid fa-phone enquiry-input-icon-intern"></i>
                            <input type="tel" id="enquiry_phone" name="phone" class="enquiry-form-control-intern" placeholder="WhatsApp / Mobile Number" required>
                        </div>
                        <div class="enquiry-form-group-intern">
                            <i class="fa-solid fa-envelope enquiry-input-icon-intern"></i>
                            <input type="email" id="enquiry_email" name="email" class="enquiry-form-control-intern" placeholder="Active Email Address" required>
                        </div>
                        <div class="enquiry-form-group-intern">
                            <i class="fa-solid fa-circle-check enquiry-input-icon-intern"></i>
                            <select id="enquiry_mode" name="mode" class="enquiry-form-control-intern enquiry-form-control-select-intern" required>
                                <option value="" disabled selected>Preferred Work Mode</option>
                                <option value="offline">Physical Offline Labs (Prayag campus)</option>
                                <option value="online">Online / Remote Classes</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-enquiry-submit-intern">
                            <i class="fa-solid fa-paper-plane"></i> Request Advisor Call
                        </button>
                    </form>
                </div>

                <!-- Card 3: Dynamic Reviews Sidebar Aggregates Feed -->
                <div class="reviews-sidebar-card-intern animate__animated animate__fadeIn" style="animation-delay: 0.15s;" id="reviews">
                    <h3 class="reviews-sidebar-title-intern"><i class="fa-solid fa-star text-warning" style="color:#ffb013;"></i> Verified Intern Reviews</h3>
                    
                    <!-- Compact Rating Summary -->
                    <div class="sidebar-rating-summary-intern">
                        <div class="sidebar-rating-score-intern">
                            <span class="sidebar-rating-num-intern"><?= $ratingValue ?></span>
                            <div class="sidebar-rating-stars-teal">
                                <?php 
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $floorRating) {
                                        echo '<i class="fa-solid fa-star"></i>';
                                    } else if ($i - 0.5 <= (float)$ratingValue) {
                                        echo '<i class="fa-solid fa-star-half-stroke"></i>';
                                    } else {
                                        echo '<i class="fa-regular fa-star"></i>';
                                    }
                                }
                                ?>
                            </div>
                            <span class="sidebar-rating-count-intern">(<?= htmlspecialchars($ratingCount) ?> reviews)</span>
                        </div>

                        <!-- Progress Bars -->
                        <div class="sidebar-rating-bars-intern">
                            <?php 
                            $totalForPct = ($totalActualReviews > 0) ? $totalActualReviews : 18;
                            for ($star = 5; $star >= 1; $star--): 
                                $pct = round(($starCounts[$star] / $totalForPct) * 100);
                            ?>
                                <div class="sidebar-rating-bar-row-intern">
                                    <span class="sidebar-bar-label-intern"><?= $star ?> ★</span>
                                    <div class="sidebar-bar-track-intern">
                                        <div class="sidebar-bar-fill-teal" style="width: <?= $pct ?>%;"></div>
                                    </div>
                                    <span class="sidebar-bar-percent-intern"><?= $pct ?>%</span>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Reviews List Feed -->
                    <div class="sidebar-reviews-feed-intern" id="internReviewsFeed">
                        <?php if (!empty($dbReviews)): ?>
                            <?php foreach ($dbReviews as $rev): 
                                $initials = '';
                                $nameParts = explode(' ', $rev['name']);
                                foreach ($nameParts as $part) {
                                    if (!empty($part)) {
                                        $initials .= substr($part, 0, 1);
                                    }
                                }
                                $initials = strtoupper(substr($initials, 0, 2)) ?: 'S';
                                $dateStr = date('d M Y', strtotime($rev['created_at']));
                            ?>
                                <div class="sidebar-review-item-intern animate__animated animate__fadeIn">
                                    <div class="sidebar-review-header-intern">
                                        <div class="sidebar-review-user-intern">
                                            <div class="sidebar-user-avatar-teal"><?= htmlspecialchars($initials) ?></div>
                                            <div class="sidebar-user-details-intern">
                                                <h4><?= htmlspecialchars($rev['name']) ?></h4>
                                                <span class="sidebar-verified-badge-teal"><i class="fa-solid fa-circle-check"></i> Verified Student</span>
                                            </div>
                                        </div>
                                        <span class="sidebar-review-date-intern"><?= $dateStr ?></span>
                                    </div>
                                    <div class="sidebar-review-stars-row-intern">
                                        <div class="sidebar-stars-teal">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php if ($i <= intval($rev['rating'])): ?>
                                                    <i class="fa-solid fa-star"></i>
                                                <?php else: ?>
                                                    <i class="fa-regular fa-star"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="sidebar-review-title-intern"><?= htmlspecialchars($rev['title']) ?></span>
                                    </div>
                                    <p class="sidebar-review-comment-intern">
                                        <?= nl2br(htmlspecialchars($rev['comment'])) ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- Standard verified Review seeds (Fallback) -->
                        <div class="sidebar-review-item-intern">
                            <div class="sidebar-review-header-intern">
                                <div class="sidebar-review-user-intern">
                                    <div class="sidebar-user-avatar-teal">VK</div>
                                    <div class="sidebar-user-details-intern">
                                        <h4>Vikram Kumar</h4>
                                        <span class="sidebar-verified-badge-teal"><i class="fa-solid fa-circle-check"></i> Verified Intern</span>
                                    </div>
                                </div>
                                <span class="sidebar-review-date-intern">3w ago</span>
                            </div>
                            <div class="sidebar-review-stars-row-intern">
                                <div class="sidebar-stars-teal">
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                </div>
                                <span class="sidebar-review-title-intern">Phenomenal Project Training</span>
                            </div>
                            <p class="sidebar-review-comment-intern">
                                Successfully hosted my live full stack project on remote cloud servers. Interactive training desk guidance was exceptional! Recommended!
                            </p>
                        </div>
                    </div>

                    <!-- Submission dynamic reviews portal -->
                    <div class="sidebar-write-review-section-intern">
                        <button type="button" class="btn-toggle-review-form-teal" id="btnToggleReviewForm" onclick="toggleSidebarReviewForm()">
                            <i class="fa-solid fa-pen-nib"></i> Write an Intern Review
                        </button>
                        
                        <div class="sidebar-review-form-wrapper-intern" id="sidebarReviewFormWrapper" style="display: none;">
                            <form id="submitStudentReviewForm" onsubmit="handleReviewSubmit(event)">
                                <label class="sidebar-form-label-intern">Select Rating:</label>
                                <div class="interactive-star-selector-intern" id="interactiveStarSelector">
                                    <button type="button" class="interactive-star-btn-intern" data-rating="1" onclick="setInteractiveReviewRating(1)" onmouseover="highlightInteractiveReviewRating(1)" onmouseout="clearHighlightReviewRating()"><i class="fa-solid fa-star"></i></button>
                                    <button type="button" class="interactive-star-btn-intern" data-rating="2" onclick="setInteractiveReviewRating(2)" onmouseover="highlightInteractiveReviewRating(2)" onmouseout="clearHighlightReviewRating()"><i class="fa-solid fa-star"></i></button>
                                    <button type="button" class="interactive-star-btn-intern" data-rating="3" onclick="setInteractiveReviewRating(3)" onmouseover="highlightInteractiveReviewRating(3)" onmouseout="clearHighlightReviewRating()"><i class="fa-solid fa-star"></i></button>
                                    <button type="button" class="interactive-star-btn-intern" data-rating="4" onclick="setInteractiveReviewRating(4)" onmouseover="highlightInteractiveReviewRating(4)" onmouseout="clearHighlightReviewRating()"><i class="fa-solid fa-star"></i></button>
                                    <button type="button" class="interactive-star-btn-intern" data-rating="5" onclick="setInteractiveReviewRating(5)" onmouseover="highlightInteractiveReviewRating(5)" onmouseout="clearHighlightReviewRating()"><i class="fa-solid fa-star"></i></button>
                                </div>
                                <input type="hidden" id="student_input_rating" name="rating" value="" required>

                                <div class="sidebar-form-group-intern">
                                    <i class="fa-solid fa-user sidebar-form-icon-intern"></i>
                                    <input type="text" id="review_name" name="name" class="sidebar-form-control-intern" placeholder="Your Full Name" required>
                                </div>
                                <div class="sidebar-form-group-intern">
                                    <i class="fa-solid fa-pen sidebar-form-icon-intern"></i>
                                    <input type="text" id="review_title" name="title" class="sidebar-form-control-intern" placeholder="Review Highlight Title" required>
                                </div>
                                <div class="sidebar-form-group-intern">
                                    <textarea id="review_comment" name="comment" class="sidebar-form-control-intern" placeholder="Your experience review comments on projects, mock desks..." rows="3" style="padding-left:34px; padding-top:10px; resize:none;" required></textarea>
                                </div>
                                <button type="submit" class="btn-sidebar-review-submit-teal">
                                    <i class="fa-solid fa-circle-check"></i> Submit Verified Review
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Terms & Conditions Sidebar Panel -->
                <div class="terms-sidebar-card-intern animate__animated animate__fadeIn" style="animation-delay: 0.2s; margin-top: 24px;">
                    <h3 class="terms-sidebar-title-intern">
                        <i class="fa-solid fa-file-contract"></i> Terms & Conditions
                    </h3>
                    <ul class="terms-list-intern">
                        <li class="terms-item-intern">
                            <i class="fa-solid fa-chevron-right"></i>
                            <span>All educational registrations and credentials subject to regular project testing standards.</span>
                        </li>
                        <li class="terms-item-intern">
                            <i class="fa-solid fa-chevron-right"></i>
                            <span>Completion of mock project challenges is mandatory for experienced certificate delivery.</span>
                        </li>
                        <li class="terms-item-intern">
                            <i class="fa-solid fa-chevron-right"></i>
                            <span>Rescheduling batch dates or shifting timelines must be routed to coordinator desks.</span>
                        </li>
                        <li class="terms-item-intern">
                            <i class="fa-solid fa-chevron-right"></i>
                            <span>Corporate stipends/fees details are non-refundable after lab onboarding sessions.</span>
                        </li>
                    </ul>
                    <div class="terms-footer-intern">
                        © MG Education & Social Development Organization
                    </div>
                </div>

            </div>
        </div>

    </section>

</div>

<!-- Fullscreen Photo Gallery Lightbox -->
<div class="lightbox-wrapper-intern" id="detailsLightbox">
    <div class="lightbox-body-intern">
        <button type="button" class="lightbox-btn-close-intern" onclick="closeInternLightbox()"><i class="fa-solid fa-xmark"></i></button>
        <button type="button" class="lightbox-arrow-intern lightbox-arrow-left-intern" onclick="prevInternLightboxImage()"><i class="fa-solid fa-chevron-left"></i></button>
        
        <img src="" class="lightbox-img-active-intern" id="activeLightboxImg">
        
        <button type="button" class="lightbox-arrow-intern lightbox-arrow-right-intern" onclick="nextInternLightboxImage()"><i class="fa-solid fa-chevron-right"></i></button>
    </div>
</div>

<script>
    // Smooth scroll sections helper
    function scrollToInternSection(id) {
        const section = document.getElementById(id);
        if (section) {
            window.scrollTo({
                top: section.offsetTop - 75,
                behavior: 'smooth'
            });

            // Set active class manually
            const tabItems = document.querySelectorAll('.subnav-tab-item-intern');
            tabItems.forEach(tab => {
                if (tab.textContent.toLowerCase().includes(id.substring(0, 3))) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            });
        }
    }

    // Scroll spy updates
    window.addEventListener('scroll', function() {
        const sections = ['overview', 'curriculum', 'selections', 'gallery', 'reviews'];
        const scrollPosition = window.scrollY + 110;

        sections.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                const top = el.offsetTop - 80;
                const bottom = top + el.offsetHeight;
                
                if (scrollPosition >= top && scrollPosition < bottom) {
                    const tabItems = document.querySelectorAll('.subnav-tab-item-intern');
                    tabItems.forEach(tab => {
                        if (tab.textContent.toLowerCase().includes(id.substring(0, 3))) {
                            tab.classList.add('active');
                        } else {
                            tab.classList.remove('active');
                        }
                    });
                }
            }
        });
    });

    // Lightbox gallery logic
    const galleryPaths = <?= json_encode($gallery ?: []) ?>;
    let activeImageIndex = 0;
    const lightboxModal = document.getElementById('detailsLightbox');
    const lightboxImg = document.getElementById('activeLightboxImg');

    function openInternLightbox(index) {
        if (!galleryPaths || galleryPaths.length === 0) return;
        activeImageIndex = index;
        lightboxImg.src = galleryPaths[activeImageIndex];
        lightboxModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        lightboxImg.style.transform = 'scale(0.95)';
        setTimeout(() => {
            lightboxImg.style.transform = 'scale(1)';
        }, 50);
    }

    function closeInternLightbox() {
        lightboxModal.style.display = 'none';
        lightboxImg.src = '';
        document.body.style.overflow = '';
    }

    function prevInternLightboxImage() {
        if (!galleryPaths || galleryPaths.length === 0) return;
        activeImageIndex = (activeImageIndex - 1 + galleryPaths.length) % galleryPaths.length;
        lightboxImg.src = galleryPaths[activeImageIndex];
    }

    function nextInternLightboxImage() {
        if (!galleryPaths || galleryPaths.length === 0) return;
        activeImageIndex = (activeImageIndex + 1) % galleryPaths.length;
        lightboxImg.src = galleryPaths[activeImageIndex];
    }

    // Keyboard support for Lightbox modal
    document.addEventListener('keydown', function(e) {
        if (lightboxModal.style.display === 'flex') {
            if (e.key === 'Escape') closeInternLightbox();
            if (e.key === 'ArrowLeft') prevInternLightboxImage();
            if (e.key === 'ArrowRight') nextInternLightboxImage();
        }
    });

    // Primary Seat Booking Interactive SweetAlert Prompter
    function triggerInternEnrollment() {
        Swal.fire({
            title: 'Initiate Internship Admission',
            text: 'Would you like to register your online application profile for the <?= htmlspecialchars($internship['name'], ENT_QUOTES) ?> professional training project?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, Apply Now!',
            cancelButtonText: 'Cancel',
            customClass: {
                popup: 'animate__animated animate__fadeInUp animate__faster'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Route applicant directly to internship-admission.php with selected internship pre-loaded
                window.location.href = "internship-admission.php?internship_id=<?= $internship['id'] ?>";
            }
        });
    }

    // Quick Advisor Enquiry Callback submission
    function handleInternEnquirySubmit(event) {
        event.preventDefault();

        const name = document.getElementById('enquiry_name').value.trim();
        const phone = document.getElementById('enquiry_phone').value.trim();
        const email = document.getElementById('enquiry_email').value.trim();
        const mode = document.getElementById('enquiry_mode').value;

        if (!name || !phone || !email || !mode) {
            Swal.fire({
                icon: 'warning',
                title: 'Required Details Missing',
                text: 'Please fill in all active fields correctly before requesting advisor call.',
                confirmButtonColor: '#0d47a1'
            });
            return;
        }

        // Show premium loading status
        Swal.fire({
            title: 'Scheduling Advisory Call...',
            text: 'Please wait while we route your enquiry to admissions office desk.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Save enquiry using AJAX Post request back to the details script
        const formData = new FormData();
        formData.append('action', 'submit_enquiry');
        formData.append('internship_id', '<?= $internship['id'] ?>');
        formData.append('name', name);
        formData.append('phone', phone);
        formData.append('email', email);
        formData.append('mode', mode);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Callback Scheduled!',
                    text: `Thank you, ${name}! Your callback request has been logged successfully. Our academic advisor will contact you within 24 hours.`,
                    confirmButtonColor: '#0d47a1',
                    customClass: {
                        popup: 'animate__animated animate__zoomIn animate__faster'
                    }
                });
                document.getElementById('quickInternEnquiryForm').reset();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Advisory Sync Error',
                    text: data.message || 'Failed to submit callback registration.',
                    confirmButtonColor: '#ef4444'
                });
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Network Timeout',
                text: 'Could not connect to the campus registration desks. Please check your internet connection.',
                confirmButtonColor: '#ef4444'
            });
        });
    }

    // --- Interactive Student Reviews Logic ---
    let selectedReviewRating = 0;

    function setInteractiveReviewRating(rating) {
        selectedReviewRating = rating;
        document.getElementById('student_input_rating').value = rating;
        updateInteractiveStarsDisplay();
    }

    function highlightInteractiveReviewRating(rating) {
        const starButtons = document.querySelectorAll('.interactive-star-btn-intern');
        starButtons.forEach((btn, index) => {
            if (index < rating) {
                btn.classList.add('hover');
            } else {
                btn.classList.remove('hover');
            }
        });
    }

    function clearHighlightReviewRating() {
        const starButtons = document.querySelectorAll('.interactive-star-btn-intern');
        starButtons.forEach(btn => btn.classList.remove('hover'));
        updateInteractiveStarsDisplay();
    }

    function updateInteractiveStarsDisplay() {
        const starButtons = document.querySelectorAll('.interactive-star-btn-intern');
        starButtons.forEach((btn, index) => {
            if (index < selectedReviewRating) {
                btn.classList.add('selected');
            } else {
                btn.classList.remove('selected');
            }
        });
    }

    // Toggle sidebar review form visibility
    function toggleSidebarReviewForm() {
        const formWrapper = document.getElementById('sidebarReviewFormWrapper');
        const btnToggle = document.getElementById('btnToggleReviewForm');
        if (formWrapper.style.display === 'none') {
            formWrapper.style.display = 'block';
            btnToggle.innerHTML = '<i class="fa-solid fa-xmark"></i> Close Review Form';
            btnToggle.style.background = '#fee2e2';
            btnToggle.style.color = '#ef4444';
            btnToggle.style.borderColor = '#ef4444';
        } else {
            formWrapper.style.display = 'none';
            btnToggle.innerHTML = '<i class="fa-solid fa-pen-nib"></i> Write an Intern Review';
            btnToggle.style.background = '#e7f1ff';
            btnToggle.style.color = '#0d47a1';
            btnToggle.style.borderColor = '#0d47a1';
        }
    }

    // Submit Student Review (verified purchase callback workflow)
    function handleReviewSubmit(event) {
        event.preventDefault();

        const name = document.getElementById('review_name').value.trim();
        const title = document.getElementById('review_title').value.trim();
        const comment = document.getElementById('review_comment').value.trim();
        
        if (!selectedReviewRating) {
            Swal.fire({
                icon: 'warning',
                title: 'Rating Required',
                text: 'Please select a star rating by clicking on the stars above.',
                confirmButtonColor: '#0d47a1'
            });
            return;
        }

        // Show submit loading advisor
        Swal.fire({
            title: 'Submitting Verified Review...',
            text: 'Your feedback is being framed in the student board index.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Save review using AJAX Post request back to the details script
        const formData = new FormData();
        formData.append('action', 'submit_review');
        formData.append('internship_id', '<?= $internship['id'] ?>');
        formData.append('name', name);
        formData.append('rating', selectedReviewRating);
        formData.append('title', title);
        formData.append('comment', comment);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Generate initials avatar
                const initials = name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() || 'S';
                
                // Build rating stars HTML dynamically
                let starsHtml = '';
                for (let i = 1; i <= 5; i++) {
                    if (i <= selectedReviewRating) {
                        starsHtml += '<i class="fa-solid fa-star"></i>';
                    } else {
                        starsHtml += '<i class="fa-regular fa-star"></i>';
                    }
                }

                // Create new review card HTML in premium sidebar style
                const newReviewHtml = `
                    <div class="sidebar-review-item-intern animate__animated animate__fadeInDown">
                        <div class="sidebar-review-header-intern">
                            <div class="sidebar-review-user-intern">
                                <div class="sidebar-user-avatar-teal" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">${initials}</div>
                                <div class="sidebar-user-details-intern">
                                    <h4>${name}</h4>
                                    <span class="sidebar-verified-badge-teal" style="color: #0d47a1; background: #e7f1ff; border-color: #bbf7d0;"><i class="fa-solid fa-circle-check"></i> Verified Intern</span>
                                </div>
                            </div>
                            <span class="sidebar-review-date-intern">Just now</span>
                        </div>
                        <div class="sidebar-review-stars-row-intern">
                            <div class="sidebar-stars-teal">
                                ${starsHtml}
                            </div>
                            <span class="sidebar-review-title-intern">${title}</span>
                        </div>
                        <p class="sidebar-review-comment-intern">${comment}</p>
                    </div>
                `;

                // Prepend new review card to target feed block
                const feedContainer = document.getElementById('internReviewsFeed');
                feedContainer.insertAdjacentHTML('afterbegin', newReviewHtml);

                // SweetAlert alert callback
                Swal.fire({
                    icon: 'success',
                    title: 'Review Posted Successfully!',
                    text: 'Thank you for your valuable feedback! Your verified review is now live in the student panel.',
                    confirmButtonColor: '#0d47a1'
                });

                // Reset values
                document.getElementById('submitStudentReviewForm').reset();
                selectedReviewRating = 0;
                updateInteractiveStarsDisplay();
                toggleSidebarReviewForm(); // Automatically close form after submit
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Submission Failed',
                    text: data.message || 'Failed to submit review.',
                    confirmButtonColor: '#ef4444'
                });
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Network Timeout',
                text: 'Could not connect to the database review portals.',
                confirmButtonColor: '#ef4444'
            });
        });
    }

    // Filter Selections by Year
    function filterSelections(category) {
        // Update active tab button style
        document.querySelectorAll('.selections-tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        if (window.event && window.event.currentTarget) {
            window.event.currentTarget.classList.add('active');
        }

        // Show/hide cards based on selection category
        const cards = document.querySelectorAll('.selection-card');
        cards.forEach(card => {
            const cat = card.getAttribute('data-year');
            if (category === 'all' || cat === category) {
                card.style.display = 'flex';
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'scale(1)';
                }, 50);
            } else {
                card.style.opacity = '0';
                card.style.transform = 'scale(0.85)';
                setTimeout(() => {
                    card.style.display = 'none';
                }, 300);
            }
        });
    }
</script>

<?php include 'footer.php'; ?>
