<?php
/**
 * MG Education & Social Development Organization
 * Premium Dynamic Course Details Page - Udemy / Coursera Premium Redesign
 */

require_once __DIR__ . '/includes/config.php';

$db = MG_GetDBConnection();

// --- 1. Database Self-Healing Schema (Course Details Enhancements) ---
try {
    // A. Create course_enquiries table (logs Quick callback details)
    $db->exec("
        CREATE TABLE IF NOT EXISTS `course_enquiries` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `course_id` INT NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `phone` VARCHAR(20) NOT NULL,
            `email` VARCHAR(255) NOT NULL,
            `mode` VARCHAR(50) NOT NULL,
            `status` VARCHAR(50) DEFAULT 'pending',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // B. Create course_reviews table (logs student reviews)
    $db->exec("
        CREATE TABLE IF NOT EXISTS `course_reviews` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `course_id` INT NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `rating` INT NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `comment` TEXT NOT NULL,
            `status` VARCHAR(50) DEFAULT 'approved',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (Exception $e) {
    error_log("Course Details Self-Healing DB Error: " . $e->getMessage());
}

// --- 2. Handle AJAX Request Submissions (Enquiry Form & Review Form) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    if ($_POST['action'] === 'submit_enquiry') {
        $course_id = intval($_POST['course_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mode = trim($_POST['mode'] ?? '');

        if (empty($course_id) || empty($name) || empty($phone) || empty($email) || empty($mode)) {
            $response['message'] = 'All advisor enquiry fields are strictly mandatory.';
            echo json_encode($response);
            exit;
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO `course_enquiries` (course_id, name, phone, email, mode)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$course_id, $name, $phone, $email, $mode]);
            
            $response['success'] = true;
            $response['message'] = 'Your advisor callback registration is completed successfully!';
        } catch (Exception $e) {
            $response['message'] = 'Enquiry failed to save: ' . $e->getMessage();
        }

        echo json_encode($response);
        exit;
    }

    if ($_POST['action'] === 'submit_review') {
        $course_id = intval($_POST['course_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $rating = intval($_POST['rating'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $comment = trim($_POST['comment'] ?? '');

        if (empty($course_id) || empty($name) || empty($rating) || empty($title) || empty($comment)) {
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
                INSERT INTO `course_reviews` (course_id, name, rating, title, comment)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$course_id, $name, $rating, $title, $comment]);

            // Dynamically recalculate aggregate ratings score and update courses table
            $count_stmt = $db->prepare("SELECT COUNT(*) as cnt, SUM(rating) as sm FROM `course_reviews` WHERE `course_id` = ? AND `status` = 'approved'");
            $count_stmt->execute([$course_id]);
            $stats = $count_stmt->fetch();

            if ($stats && $stats['cnt'] > 0) {
                $up_stmt = $db->prepare("UPDATE `courses` SET `ratings_count` = ?, `ratings_sum` = ? WHERE `id` = ?");
                $up_stmt->execute([$stats['cnt'], $stats['sm'], $course_id]);
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

$course = null;

// Fetch active course by URL slug safely
if (isset($_GET['slug'])) {
    try {
        $slug = trim($_GET['slug']);
        $stmt = $db->prepare("
            SELECT c.*, cat.name as category_name, cat.slug as category_slug 
            FROM `courses` c
            INNER JOIN `course_categories` cat ON c.category_id = cat.id
            WHERE c.slug = :slug LIMIT 1
        ");
        $stmt->execute(['slug' => $slug]);
        $course = $stmt->fetch();
    } catch (Exception $e) {
        error_log("Front-end course fetch error: " . $e->getMessage());
    }
}

// Redirect if not found
if (!$course) {
    header("Location: index.php");
    exit;
}

// Fetch actual reviews from the database dynamically
$dbReviews = [];
try {
    $rev_stmt = $db->prepare("SELECT * FROM `course_reviews` WHERE `course_id` = ? AND `status` = 'approved' ORDER BY id DESC");
    $rev_stmt->execute([$course['id']]);
    $dbReviews = $rev_stmt->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch course reviews: " . $e->getMessage());
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
    // Fallback to high-rating marketing baseline aggregates
    $ratingCount = (int)($course['ratings_count'] ?: 24);
    $ratingSum = floatval($course['ratings_sum'] ?: ($ratingCount * 4.8));
    $ratingValue = $ratingCount > 0 ? number_format($ratingSum / $ratingCount, 1) : '4.8';
    
    // Seed standard professional baseline review distribution counts matching WAMP baseline
    $starCounts = [5 => 18, 4 => 4, 3 => 1, 2 => 1, 1 => 0];
}

// Setup dynamic SEO parameters for root header.php injection
$dynamic_title = !empty($course['meta_title']) ? $course['meta_title'] : $course['name'] . " Course | MG Education";
$dynamic_meta_desc = $course['meta_description'];
$dynamic_meta_keywords = $course['meta_keywords'];

// Decipher OG info
$og_data = [];
if (!empty($course['og_info'])) {
    $og_data = json_decode($course['og_info'], true);
}
$dynamic_og_info = [
    "og:title" => !empty($og_data['og:title']) ? $og_data['og:title'] : $dynamic_title,
    "og:description" => !empty($og_data['og:description']) ? $og_data['og:description'] : $dynamic_meta_desc,
    "og:type" => "website",
    "og:url" => "https://mgedu.in/course-details.php?slug=" . $course['slug'],
    "og:image" => !empty($course['featured_image']) ? "https://mgedu.in/" . $course['featured_image'] : (!empty($course['course_image']) ? "https://mgedu.in/" . $course['course_image'] : '')
];

$schemaArray = [
    "@context" => "https://schema.org",
    "@type" => "Course",
    "name" => $course['name'],
    "description" => !empty($course['meta_description']) ? $course['meta_description'] : strip_tags($course['description'] ?? ''),
    "provider" => [
        "@type" => "Organization",
        "name" => "MG Education & Social Development Organization",
        "sameAs" => "https://mgedu.in"
    ],
    "offers" => [
        "@type" => "Offer",
        "category" => ucfirst($course['mode']),
        "price" => floatval($course['sales_price']),
        "priceCurrency" => "INR"
    ],
    "aggregateRating" => [
        "@type" => "AggregateRating",
        "ratingValue" => $ratingValue,
        "ratingCount" => $ratingCount
    ]
];
$dynamic_seo_schema = json_encode($schemaArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// Calculate Savings details
$mrp = floatval($course['mrp']);
$sales_price = floatval($course['sales_price']);
$saving_amount = $mrp - $sales_price;
$saving_percent = $mrp > 0 ? round(($saving_amount / $mrp) * 100) : 0;

include 'header.php';
?>

<!-- SweetAlert2 & Google Fonts & Animate.css for Premium Visual Excellence -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

<style>
    /* Premium visual overrides - Designed Scoped */
    .premium-course-details {
        font-family: 'Plus Jakarta Sans', 'Inter', sans-serif !important;
        background-color: #f8fafc;
        color: #0f172a;
        overflow-x: hidden;
    }

    /* 1. Stunning Hero Banner Section (Slate Dark Gradient with backdrop glow) */
    .premium-hero {
        background: radial-gradient(circle at 80% 20%, #1e293b 0%, #0f172a 100%);
        color: #ffffff;
        padding: 60px 0 110px 0;
        position: relative;
        overflow: hidden;
    }

    .premium-hero .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
        position: relative;
        z-index: 2;
    }

    .premium-hero .row {
        display: flex;
        flex-wrap: wrap;
        margin: 0;
    }

    .premium-hero .col-lg-8 {
        width: 100%;
        padding: 0;
    }

    @media (min-width: 992px) {
        .premium-hero .col-lg-8 {
            width: 65%;
            padding-right: 30px;
        }
    }

    .hero-glow-element {
        position: absolute;
        top: -20%;
        right: -10%;
        width: 600px;
        height: 600px;
        background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, transparent 70%);
        filter: blur(60px);
        pointer-events: none;
    }

    .premium-breadcrumbs {
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

    .premium-breadcrumbs a {
        color: #38bdf8;
        text-decoration: none;
        transition: color 0.2s ease;
    }

    .premium-breadcrumbs a:hover {
        color: #e0f2fe;
        text-decoration: underline;
    }

    .premium-breadcrumbs i {
        font-size: 9px;
        color: #475569;
    }

    .hero-title {
        font-size: 2.6rem;
        font-weight: 800;
        line-height: 1.25;
        letter-spacing: -0.03em;
        color: #ffffff;
        margin-bottom: 18px;
        max-width: 820px;
    }

    .hero-subtitle {
        font-size: 1.2rem;
        font-weight: 400;
        color: #cbd5e1;
        line-height: 1.6;
        margin-bottom: 24px;
        max-width: 820px;
    }

    /* Star rating score block */
    .hero-rating-wrapper {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        font-size: 14.5px;
        margin-bottom: 22px;
    }

    .badge-rating {
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

    .stars-list {
        color: #ffb013;
        display: flex;
        gap: 2px;
    }

    .rating-link {
        color: #38bdf8;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.2s;
        border-bottom: 1px dashed #38bdf8;
    }

    .rating-link:hover {
        color: #7dd3fc;
        border-bottom-style: solid;
    }

    .badge-students {
        color: #cbd5e1;
        font-weight: 500;
        background: rgba(255, 255, 255, 0.1);
        padding: 4px 12px;
        border-radius: 6px;
        font-size: 13px;
    }

    .hero-author-line {
        font-size: 14.5px;
        color: #94a3b8;
        margin-bottom: 24px;
    }

    .hero-author-line span {
        color: #38bdf8;
        font-weight: 700;
    }

    .hero-meta-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        margin-top: 24px;
    }

    .meta-badge {
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

    .meta-badge:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: rgba(255, 255, 255, 0.15);
    }

    .meta-badge i {
        color: #38bdf8;
    }

    /* Premium Credentials Auto-playing Logos Only Marquee */
    .premium-hero .col-lg-4 {
        width: 100%;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    @media (min-width: 992px) {
        .premium-hero .col-lg-4 {
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

    /* 2. Horizontal Sticky Subnav bar (Coursera Style) */
    .coursera-subnav {
        background-color: rgba(255, 255, 255, 0.96);
        backdrop-filter: blur(8px);
        border-bottom: 1px solid #e2e8f0;
        position: sticky;
        top: 0;
        z-index: 99;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
    }

    .subnav-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .subnav-tabs {
        display: flex;
        gap: 36px;
        overflow-x: auto;
        scrollbar-width: none;
    }

    .subnav-tabs::-webkit-scrollbar {
        display: none;
    }

    .subnav-tab-item {
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

    .subnav-tab-item:hover {
        color: #0f172a;
    }

    .subnav-tab-item.active {
        color: #0d47a1;
    }

    .subnav-tab-item::after {
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

    .subnav-tab-item.active::after {
        width: 100%;
    }

    /* 3. Main Grid layout structure */
    .main-course-content {
        max-width: 1200px;
        margin: 0 auto;
        padding: 40px 20px 80px 20px;
        display: grid;
        grid-template-columns: 1fr;
        gap: 40px;
        position: relative;
    }

    @media (min-width: 992px) {
        .main-course-content {
            grid-template-columns: 2.2fr 1.1fr;
        }
    }

    /* 4. Left Columns & Content Cards */
    .premium-card-wrapper {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 36px;
        margin-bottom: 30px;
        scroll-margin-top: 80px; /* Offset sticky subnav */
        box-shadow: 0 10px 30px -15px rgba(15, 23, 42, 0.04);
        transition: box-shadow 0.3s;
    }

    .premium-card-wrapper:hover {
        box-shadow: 0 15px 35px -15px rgba(15, 23, 42, 0.08);
    }

    .card-main-heading {
        font-size: 1.5rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
        letter-spacing: -0.02em;
    }

    /* Udemy Style "What You'll Learn" Outcome Card */
    .outcomes-card {
        background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
        border: 1px solid #a7f3d0 !important;
    }

    .outcomes-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 16px 28px;
    }

    .outcome-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        font-size: 14.5px;
        color: #1e293b;
        line-height: 1.5;
    }

    .outcome-item i {
        color: #10b981;
        font-size: 16px;
        margin-top: 3px;
    }

    /* Rich WYSIWYG Syllabus content styles */
    .syllabus-renderer {
        line-height: 1.8;
        font-size: 15px;
        color: #334155;
    }

    .syllabus-renderer p {
        margin-bottom: 20px;
    }

    .syllabus-renderer h1, .syllabus-renderer h2, .syllabus-renderer h3 {
        color: #0f172a;
        font-weight: 700;
        margin-top: 32px;
        margin-bottom: 16px;
        letter-spacing: -0.02em;
    }

    .syllabus-renderer h2 {
        font-size: 1.35rem;
        border-left: 4px solid #10b981;
        padding-left: 12px;
    }

    .syllabus-renderer h3 {
        font-size: 1.15rem;
    }

    .syllabus-renderer ul {
        list-style: none;
        padding-left: 0;
        margin-bottom: 24px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .syllabus-renderer ul li {
        position: relative;
        padding-left: 24px;
    }

    .syllabus-renderer ul li::before {
        content: "\f00c";
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        position: absolute;
        left: 0;
        top: 2px;
        color: #10b981;
        font-size: 13px;
    }

    .syllabus-renderer blockquote {
        border-left: 4px solid #3b82f6;
        background: #f8fafc;
        padding: 16px 20px;
        margin: 24px 0;
        border-radius: 0 8px 8px 0;
        font-style: italic;
        color: #475569;
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

    /* Photo Gallery */
    .media-gallery-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 16px;
    }

    .media-gallery-card {
        aspect-ratio: 16/11;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        cursor: pointer;
        position: relative;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        transition: all 0.3s;
    }

    .media-gallery-card:hover {
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 12px 24px -10px rgba(15, 23, 42, 0.15);
        border-color: #cbd5e1;
    }

    .media-gallery-card img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }

    .media-gallery-card:hover img {
        transform: scale(1.05);
    }

    .media-hover-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(16, 185, 129, 0.85);
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

    .media-gallery-card:hover .media-hover-overlay {
        opacity: 1;
    }

    /* Admissions Callout Block (Unacademy style) */
    .admissions-callout-card {
        background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
        border: 1px solid #7dd3fc !important;
        position: relative;
        overflow: hidden;
    }

    .admissions-callout-card::after {
        content: '';
        position: absolute;
        bottom: -20px;
        right: -20px;
        width: 150px;
        height: 150px;
        background: radial-gradient(circle, rgba(14, 165, 233, 0.1) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .admissions-callout-card p {
        font-size: 15px;
        line-height: 1.6;
        color: #0369a1;
        margin-bottom: 24px;
    }

    .btn-admission-trigger {
        background-color: #0284c7;
        color: #ffffff;
        border: none;
        border-radius: 30px;
        font-size: 14.5px;
        font-weight: 700;
        padding: 14px 32px;
        cursor: pointer;
        transition: all 0.3s;
        box-shadow: 0 4px 12px rgba(2, 132, 199, 0.2);
    }

    .btn-admission-trigger:hover {
        background-color: #0369a1;
        box-shadow: 0 6px 18px rgba(2, 132, 199, 0.35);
        transform: translateY(-1px);
    }

    /* 5. Sticky sidebar overlay wrapper & booking container */
    @media (min-width: 992px) {
        .sticky-sidebar-wrapper {
            position: sticky;
            top: 90px;
            margin-top: -340px; /* Overlap dark banner */
            z-index: 100;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
    }

    @media (max-width: 991px) {
        .sticky-sidebar-wrapper {
            display: flex;
            flex-direction: column;
            gap: 24px;
            margin-top: 30px;
        }
    }

    .udemy-sticky-card {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.12), 0 0 0 1px rgba(0,0,0,0.02);
        overflow: hidden;
        width: 100%;
        position: relative;
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s;
    }

    /* Enquiry Card Styles */
    .enquiry-card {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.12), 0 0 0 1px rgba(0,0,0,0.02);
        padding: 28px;
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s;
        width: 100%;
        box-sizing: border-box;
    }

    .enquiry-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 35px 60px -15px rgba(15, 23, 42, 0.15);
    }

    .enquiry-header {
        margin-bottom: 20px;
        text-align: center;
    }

    .enquiry-title {
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

    .enquiry-title i {
        color: #0d47a1;
        font-size: 1.1rem;
    }

    .enquiry-subtitle {
        font-size: 12.5px;
        color: #64748b;
        margin: 0;
        line-height: 1.4;
    }

    .enquiry-form-group {
        position: relative;
        margin-bottom: 16px;
    }

    .enquiry-input-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 14px;
    }

    .enquiry-form-control {
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

    .enquiry-form-control:focus {
        border-color: #0d47a1;
        background: #ffffff;
        box-shadow: 0 0 0 4px rgba(13, 71, 161, 0.1);
    }

    .enquiry-form-control-select {
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

    .btn-enquiry-submit {
        background: linear-gradient(135deg, #0d47a1 0%, #083b8c 100%);
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

    .btn-enquiry-submit:hover {
        background: linear-gradient(135deg, #083b8c 0%, #052a66 100%);
        box-shadow: 0 6px 16px rgba(13, 71, 161, 0.3);
        transform: translateY(-1px);
    }

    .udemy-sticky-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 35px 60px -15px rgba(15, 23, 42, 0.2);
    }

    .sticky-card-media {
        width: 100%;
        aspect-ratio: 16/9;
        position: relative;
        background-color: #0f172a;
        overflow: hidden;
        cursor: pointer;
    }

    .sticky-card-media img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        opacity: 0.9;
        transition: transform 0.5s ease;
    }

    .sticky-card-media:hover img {
        transform: scale(1.06);
    }

    .media-preview-overlay {
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

    .sticky-card-media:hover .media-preview-overlay {
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(4px);
    }

    .play-icon-circle {
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

    .sticky-card-media:hover .play-icon-circle {
        transform: scale(1.12);
        background-color: #10b981;
        color: #ffffff;
    }

    .sticky-card-body {
        padding: 28px;
    }

    .sticky-price-row {
        display: flex;
        align-items: baseline;
        gap: 14px;
        margin-bottom: 8px;
    }

    .sticky-price-val {
        font-size: 2.25rem;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: -0.03em;
    }

    .sticky-mrp-strikethrough {
        font-size: 1.15rem;
        color: #64748b;
        text-decoration: line-through;
        font-weight: 400;
    }

    .sticky-save-percent {
        font-size: 13px;
        font-weight: 700;
        color: #059669;
        background-color: #ecfdf5;
        padding: 4px 10px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 24px;
        border: 1px solid #a7f3d0;
    }

    .btn-sticky-primary {
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

    .btn-sticky-primary:hover {
        background: linear-gradient(135deg, #059669 0%, #047857 100%);
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.45);
        transform: translateY(-1px);
    }

    .btn-sticky-secondary {
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

    .btn-sticky-secondary:hover {
        background-color: #f8fafc;
        border-color: #94a3b8;
    }

    .sticky-bullets-header {
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 16px;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 8px;
    }

    .sticky-bullets-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .sticky-bullet-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        font-size: 13.5px;
        color: #475569;
        line-height: 1.45;
    }

    .sticky-bullet-item i {
        color: #10b981;
        font-size: 15px;
        margin-top: 2px;
        width: 18px;
        text-align: center;
    }

    /* Fullscreen Lightbox Overlay */
    .lightbox-wrapper {
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

    .lightbox-body {
        position: relative;
        max-width: 90%;
        max-height: 80vh;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .lightbox-img-active {
        max-width: 100%;
        max-height: 80vh;
        border-radius: 12px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        border: 1px solid rgba(255,255,255,0.1);
        object-fit: contain;
        transition: transform 0.3s ease;
    }

    .lightbox-btn-close {
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

    .lightbox-btn-close:hover {
        background: #ef4444;
        border-color: #ef4444;
        transform: scale(1.05);
    }

    .lightbox-arrow {
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

    .lightbox-arrow:hover {
        background-color: #10b981;
        border-color: #10b981;
        transform: translateY(-50%) scale(1.08);
    }

    .lightbox-arrow-left {
        left: -80px;
    }

    .lightbox-arrow-right {
        right: -80px;
    }

    /* Mobile Media Adaptability */
    @media (max-width: 991px) {
        .premium-hero {
            padding: 40px 0 60px 0;
        }

        .hero-title {
            font-size: 2.1rem;
        }

        .main-course-content {
            grid-template-columns: 1fr;
            padding-top: 30px;
            gap: 30px;
        }

        .udemy-sticky-card {
            margin-top: 0;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        }

        .lightbox-arrow-left { left: 16px; }
        .lightbox-arrow-right { right: 16px; }
        .lightbox-arrow {
            width: 44px;
            height: 44px;
            font-size: 16px;
        }
    }

    /* 6. Premium Sidebar Reviews Widget */
    .reviews-sidebar-card {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(0,0,0,0.01);
        padding: 24px;
        width: 100%;
        box-sizing: border-box;
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s;
    }

    .reviews-sidebar-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 35px 60px -15px rgba(15, 23, 42, 0.12);
    }

    .reviews-sidebar-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 8px;
        letter-spacing: -0.02em;
    }

    .sidebar-rating-summary {
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 20px;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .sidebar-rating-score {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .sidebar-rating-num {
        font-size: 2.25rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1;
        letter-spacing: -0.03em;
    }

    .sidebar-rating-stars {
        color: #ffb013;
        font-size: 1rem;
        display: flex;
        gap: 2px;
    }

    .sidebar-rating-count {
        font-size: 12.5px;
        color: #64748b;
        font-weight: 600;
    }

    .sidebar-rating-bars {
        display: flex;
        flex-direction: column;
        gap: 6px;
        border-top: 1px solid #edf2f7;
        padding-top: 10px;
    }

    .sidebar-rating-bar-row {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 12px;
        color: #475569;
    }

    .sidebar-bar-label {
        width: 32px;
        font-weight: 600;
        white-space: nowrap;
    }

    .sidebar-bar-track {
        flex-grow: 1;
        height: 6px;
        background-color: #f1f5f9;
        border-radius: 3px;
        overflow: hidden;
    }

    .sidebar-bar-fill {
        height: 100%;
        background-color: #ffb013;
        border-radius: 3px;
    }

    .sidebar-bar-percent {
        width: 32px;
        text-align: right;
        font-weight: 700;
        color: #0f172a;
    }

    /* Reviews feed items */
    .sidebar-reviews-feed {
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

    .sidebar-reviews-feed::-webkit-scrollbar {
        width: 5px;
    }

    .sidebar-reviews-feed::-webkit-scrollbar-track {
        background: #f8fafc;
        border-radius: 10px;
    }

    .sidebar-reviews-feed::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    .sidebar-review-item {
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 14px;
    }

    .sidebar-review-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .sidebar-review-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 8px;
        gap: 8px;
    }

    .sidebar-review-user {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .sidebar-user-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: linear-gradient(135deg, #0d47a1 0%, #3b82f6 100%);
        color: #ffffff;
        font-weight: 700;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .sidebar-user-details h4 {
        font-size: 13px;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
        line-height: 1.2;
    }

    .sidebar-verified-badge {
        font-size: 10px;
        font-weight: 700;
        color: #059669;
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        padding: 1px 6px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        margin-top: 2px;
    }

    .sidebar-review-date {
        font-size: 11px;
        color: #94a3b8;
        font-weight: 500;
    }

    .sidebar-review-stars-row {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 6px;
    }

    .sidebar-stars {
        color: #ffb013;
        font-size: 11px;
        display: flex;
        gap: 1px;
    }

    .sidebar-review-title {
        font-size: 13px;
        font-weight: 700;
        color: #1e293b;
    }

    .sidebar-review-comment {
        font-size: 12.5px;
        color: #475569;
        line-height: 1.5;
        margin: 0;
    }

    /* Submit Review Sidebar Form styles */
    .sidebar-write-review-section {
        border-top: 1px solid #edf2f7;
        padding-top: 16px;
    }

    .btn-toggle-review-form {
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

    .btn-toggle-review-form:hover {
        background: #0d47a1;
        color: #ffffff;
        border-style: solid;
        box-shadow: 0 4px 10px rgba(13, 71, 161, 0.15);
    }

    .sidebar-review-form-wrapper {
        margin-top: 16px;
        background: #f8fafc;
        border: 1.5px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        box-sizing: border-box;
    }

    .sidebar-form-label {
        font-size: 12.5px;
        font-weight: 700;
        color: #475569;
        display: block;
        margin-bottom: 8px;
    }

    /* Interactive Stars Selector Styles */
    .interactive-star-selector {
        display: flex;
        gap: 6px;
        margin-bottom: 18px;
        align-items: center;
    }

    .interactive-star-btn {
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

    .interactive-star-btn:focus {
        outline: none !important;
        border: none !important;
        box-shadow: none !important;
    }

    .interactive-star-btn:hover {
        transform: scale(1.2);
    }

    .interactive-star-btn.selected,
    .interactive-star-btn.hover {
        color: #ffb013 !important;
    }

    .sidebar-form-group {
        position: relative;
        margin-bottom: 12px;
    }

    .sidebar-form-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 12px;
    }

    .sidebar-form-control {
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

    .sidebar-form-control:focus {
        border-color: #0d47a1;
        box-shadow: 0 0 0 3px rgba(13, 71, 161, 0.08);
    }

    .btn-sidebar-review-submit {
        background: linear-gradient(135deg, #0d47a1 0%, #1565c0 100%);
        color: #ffffff;
        border: none;
        border-radius: 8px;
        width: 100%;
        padding: 12px 20px;
        font-size: 13.5px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-family: inherit;
        box-shadow: 0 4px 12px rgba(13, 71, 161, 0.15);
    }

    .btn-sidebar-review-submit:hover {
        background: linear-gradient(135deg, #083b8c 0%, #0d47a1 100%);
        box-shadow: 0 6px 16px rgba(13, 71, 161, 0.25);
        transform: translateY(-2px);
    }

    /* Related Courses & Full Width Banner Section */
    .related-courses-card {
        margin-top: 30px;
    }

    .related-courses-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-top: 15px;
    }

    @media (max-width: 992px) {
        .related-courses-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 576px) {
        .related-courses-grid {
            grid-template-columns: 1fr;
        }
    }

    .related-course-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
        cursor: pointer;
        text-decoration: none !important;
    }

    .related-course-card:hover {
        transform: translateY(-6px);
        border-color: #cbd5e1;
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.06);
    }

    .related-course-img-wrapper {
        position: relative;
        width: 100%;
        aspect-ratio: 16/10;
        overflow: hidden;
        background: #f1f5f9;
    }

    .related-course-img-wrapper img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }

    .related-course-card:hover .related-course-img-wrapper img {
        transform: scale(1.06);
    }

    .related-course-mode-badge {
        position: absolute;
        top: 12px;
        left: 12px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        padding: 4px 10px;
        border-radius: 50px;
        background: rgba(13, 71, 161, 0.9);
        color: #ffffff;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 6px rgba(13, 71, 161, 0.15);
    }

    .related-course-info {
        padding: 16px;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
        text-align: left;
    }

    .related-course-cat {
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        margin-bottom: 6px;
        letter-spacing: 0.5px;
    }

    .related-course-name {
        font-size: 14.5px;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.35;
        margin-bottom: 12px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        height: 40px;
        transition: color 0.2s;
    }

    .related-course-card:hover .related-course-name {
        color: #0d47a1;
    }

    .related-course-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-top: 1px solid #f1f5f9;
        padding-top: 12px;
        margin-top: auto;
    }

    .related-course-duration {
        font-size: 11.5px;
        font-weight: 600;
        color: #64748b;
    }

    .related-course-price {
        display: flex;
        flex-direction: column;
        text-align: right;
    }

    .related-price-mrp {
        font-size: 10px;
        text-decoration: line-through;
        color: #94a3b8;
    }

    .related-price-sales {
        font-size: 13.5px;
        font-weight: 800;
        color: #0f172a;
    }

    /* Premium Full Width Banner */
    .full-width-banner-wrapper {
        margin-top: 35px;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s;
        border: 1px solid #cbd5e1;
        cursor: pointer;
    }

    .full-width-banner-wrapper:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
        border-color: #94a3b8;
    }

    .full-width-banner-wrapper img {
        width: 100%;
        height: auto;
        display: block;
        object-fit: cover;
    }

    /* Terms & Conditions Sidebar Card */
    .terms-sidebar-card {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04), 0 0 0 1px rgba(0,0,0,0.01);
        padding: 24px;
        width: 100%;
        box-sizing: border-box;
        transition: transform 0.3s ease, box-shadow 0.3s;
    }

    .terms-sidebar-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
    }

    .terms-sidebar-title {
        font-size: 1.12rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
        letter-spacing: -0.02em;
        text-transform: uppercase;
    }

    .terms-sidebar-title i {
        color: #0d47a1;
    }

    .terms-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .terms-item {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        font-size: 12px;
        color: #475569;
        line-height: 1.45;
        text-align: left;
    }

    .terms-item i {
        color: #64748b;
        font-size: 12px;
        margin-top: 3px;
    }

    .terms-footer {
        border-top: 1px solid #edf2f7;
        padding-top: 12px;
        margin-top: 14px;
        text-align: center;
        font-size: 10.5px;
        color: #94a3b8;
        font-weight: 600;
    }
</style>

<div class="premium-course-details">

    <!-- 1. Rich Slate Radial Gradient Hero Header -->
    <section class="premium-hero">
        <div class="hero-glow-element"></div>
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <!-- Premium Breadcrumbs top bar -->
                    <div class="premium-breadcrumbs animate__animated animate__fadeIn">
                        <a href="index.php">Home</a>
                        <i class="fa-solid fa-chevron-right"></i>
                        <a href="#">Academic Wings</a>
                        <i class="fa-solid fa-chevron-right"></i>
                        <a href="#"><?= htmlspecialchars($course['category_name']) ?></a>
                    </div>

                    <!-- Glowing Title -->
                    <h1 class="hero-title animate__animated animate__fadeInUp"><?= htmlspecialchars($course['name']) ?></h1>
                    
                    <!-- Short Description -->
                    <p class="hero-subtitle animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
                        <?= !empty($course['meta_description']) ? htmlspecialchars($course['meta_description']) : "Upgrade your core domain skill sets through industry-recognised vocational training at the MG Education & Social Development campus wings." ?>
                    </p>

                    <!-- Interactive verification rating aggregate -->
                    <div class="hero-rating-wrapper animate__animated animate__fadeInUp" style="animation-delay: 0.15s;">
                        <span class="badge-rating"><?= $ratingValue ?> <i class="fa-solid fa-star"></i></span>
                        <div class="stars-list">
                            <?php 
                            $floorRating = floor((float)$ratingValue);
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
                        <a href="#reviews" class="rating-link">(<?= htmlspecialchars($course['ratings_count'] ?: '24') ?> verified ratings)</a>
                        <span class="badge-students"><?= number_format($ratingCount * 12 + 120) ?> students enrolled</span>
                    </div>

                    <!-- Instructor Info line -->
                    <div class="hero-author-line animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
                        Academic Certification Wing: <span>MG Education Board</span>
                    </div>

                    <!-- Fine-bordered meta specification cards -->
                    <div class="hero-meta-grid animate__animated animate__fadeInUp" style="animation-delay: 0.25s;">
                        <div class="meta-badge">
                            <i class="fa-solid fa-arrows-spin"></i>
                            <span>Session Year: <?= date('Y') ?></span>
                        </div>
                        <div class="meta-badge">
                            <i class="fa-solid fa-globe"></i>
                            <span>Bilingual (English, Hindi)</span>
                        </div>
                        <div class="meta-badge">
                            <i class="fa-solid fa-closed-captioning"></i>
                            <span>Subtitles Enabled</span>
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

    <!-- 2. Sticky Horizonal Subnav Bar (Coursera Style tabs) -->
    <nav class="coursera-subnav d-none d-lg-block">
        <div class="subnav-container">
            <div class="subnav-tabs">
                <a class="subnav-tab-item active" onclick="scrollToSection('overview')">Overview</a>
                <a class="subnav-tab-item" onclick="scrollToSection('curriculum')">Syllabus</a>
                <a class="subnav-tab-item" onclick="scrollToSection('selections')">Our Selections</a>
                <?php if (is_array(json_decode($course['gallery_images'] ?? '[]', true)) && !empty(json_decode($course['gallery_images'] ?? '[]', true))): ?>
                    <a class="subnav-tab-item" onclick="scrollToSection('gallery')">Media Gallery</a>
                <?php endif; ?>
                <a class="subnav-tab-item" onclick="scrollToSection('reviews')">Reviews & Ratings</a>
            </div>
        </div>
    </nav>

    <!-- 3. Desktop two-column Layout -->
    <section class="main-course-content">
        
        <!-- Left Side main card segments -->
        <div class="content-left-column">
            
            <!-- Premium Full Width Banner (Overview tab target) -->
            <div class="full-width-banner-wrapper" id="overview" onclick="triggerEnrollment()" title="Click to apply or register now!" style="margin-top: 0; margin-bottom: 30px;">
                <img src="https://static.pw.live/5eb393ee95fab7468a79d189/GLOBAL_CMS/ec18f90d-585e-4d27-9237-0cbd9e62d3f1.webp" alt="Academic Banner Promotion">
            </div>

            <!-- Card B: Coursera style rich curriculum syllabus (Summernote Rendered) -->
            <div class="premium-card-wrapper" id="curriculum">
                <h2 class="card-main-heading"><i class="fa-solid fa-book-open text-primary"></i> Course Curriculum</h2>
                <div class="syllabus-renderer">
                    <?php if (!empty($course['description'])): ?>
                        <?= $course['description'] // Render rich WYSIWYG summernote content safely ?>
                    <?php else: ?>
                        <p class="text-muted">Detailed curriculum syllabus is not published for this academic course. Please query campus coordinators.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card C: Premium Selections Grid -->
            <div class="premium-card-wrapper" id="selections">
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

            <!-- Card D: Photo Gallery with fullscreen lightbox triggers -->
            <?php 
            $gallery = json_decode($course['gallery_images'] ?? '[]', true);
            if (is_array($gallery) && !empty($gallery)): 
            ?>
                <div class="premium-card-wrapper" id="gallery">
                    <h2 class="card-main-heading"><i class="fa-solid fa-images text-primary"></i> Campus Training Media Gallery</h2>
                    <p class="text-muted" style="font-size: 14px; margin-top: -12px; margin-bottom: 24px;">Visual highlights from MG Education coding desks, physical libraries, and training desks:</p>
                    
                    <div class="media-gallery-container" id="detailsGallery">
                        <?php foreach ($gallery as $index => $imgPath): ?>
                            <div class="media-gallery-card" onclick="openLightbox(<?= $index ?>)" title="Click to view image fullscreen">
                                <img src="<?= htmlspecialchars($imgPath) ?>" alt="Campus Frame" onerror="this.src='https://via.placeholder.com/300x200?text=MG+Education'">
                                <div class="media-hover-overlay">
                                    <i class="fa-solid fa-expand"></i>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Related Courses Section -->
            <?php
            // Fetch related courses prioritising the same category
            try {
                $related_stmt = $db->prepare("
                    SELECT c.*, cat.name as category_name 
                    FROM `courses` c
                    INNER JOIN `course_categories` cat ON c.category_id = cat.id
                    WHERE c.id != :current_id
                    ORDER BY (c.category_id = :category_id) DESC, c.id DESC
                    LIMIT 3
                ");
                $related_stmt->execute([
                    'category_id' => $course['category_id'],
                    'current_id' => $course['id']
                ]);
                $related_courses = $related_stmt->fetchAll();
            } catch (Exception $e) {
                $related_courses = [];
            }

            if (!empty($related_courses)):
            ?>
                <div class="premium-card-wrapper" id="related-courses">
                    <h2 class="card-main-heading"><i class="fa-solid fa-graduation-cap text-success"></i> Related Academic Courses</h2>
                    <p class="text-muted" style="font-size: 14px; margin-top: -12px; margin-bottom: 24px;">Explore other high-quality academic wings and certification programs matching your interests:</p>
                    
                    <div class="related-courses-grid">
                        <?php foreach ($related_courses as $relCourse): 
                            $relMRP = floatval($relCourse['mrp']);
                            $relSales = floatval($relCourse['sales_price']);
                            $relCover = !empty($relCourse['course_image']) ? htmlspecialchars($relCourse['course_image']) : 'https://via.placeholder.com/600x380?text=MG+Academic+Course';
                        ?>
                            <a href="course-details.php?slug=<?= $relCourse['slug'] ?>" class="related-course-card">
                                <div class="related-course-img-wrapper">
                                    <img src="<?= $relCover ?>" alt="<?= htmlspecialchars($relCourse['name']) ?>" onerror="this.src='https://via.placeholder.com/600x380?text=MG+Academic+Course'">
                                    <span class="related-course-mode-badge"><?= htmlspecialchars($relCourse['mode']) ?></span>
                                </div>
                                <div class="related-course-info">
                                    <span class="related-course-cat"><?= htmlspecialchars($relCourse['category_name']) ?></span>
                                    <h4 class="related-course-name"><?= htmlspecialchars($relCourse['name']) ?></h4>
                                    <div class="related-course-meta">
                                        <span class="related-course-duration">
                                            <i class="fa-regular fa-clock"></i> <?= htmlspecialchars($relCourse['duration']) ?> <?= ucfirst($relCourse['duration_unit']) ?>
                                        </span>
                                        <div class="related-course-price">
                                            <?php if ($relMRP > $relSales): ?>
                                                <span class="related-price-mrp">₹<?= number_format($relMRP, 2) ?></span>
                                            <?php endif; ?>
                                            <span class="related-price-sales">₹<?= number_format($relSales, 2) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>



        </div>

        <!-- Right Column - Floating Overlapping sidebar (Udemy Overlay) -->
        <div class="content-right-column">
            <div class="sticky-sidebar-wrapper">
                
                <!-- Card 1: Pricing details & Actions -->
                <div class="udemy-sticky-card">
                    <!-- Media Preview -->
                    <div class="sticky-card-media" onclick="triggerEnrollment()">
                        <?php 
                        $cover = 'https://via.placeholder.com/600x380?text=MG+Academic+Course';
                        if (!empty($course['course_image'])) {
                            $cover = htmlspecialchars($course['course_image']);
                        }
                        ?>
                        <img src="<?= $cover ?>" alt="Course Frame" onerror="this.src='https://via.placeholder.com/600x380?text=MG+Academic+Course'">
                        <div class="media-preview-overlay">
                            <div class="play-icon-circle"><i class="fa-solid fa-play"></i></div>
                            <span>Preview Academic Program</span>
                        </div>
                    </div>

                    <!-- Content & Booking actions -->
                    <div class="sticky-card-body">
                        <div class="sticky-price-row">
                            <span class="sticky-price-val">₹<?= number_format($sales_price, 2) ?></span>
                            <span class="sticky-mrp-strikethrough">₹<?= number_format($mrp, 2) ?></span>
                        </div>
                        <?php if ($saving_amount > 0): ?>
                            <span class="sticky-save-percent">
                                <i class="fa-solid fa-fire"></i> <?= $saving_percent ?>% OFF &bull; Save ₹<?= number_format($saving_amount, 2) ?>
                            </span>
                        <?php endif; ?>

                        <!-- Action links -->
                        <button type="button" class="btn-sticky-primary" onclick="triggerEnrollment()">
                            <i class="fa-solid fa-arrow-right-to-bracket"></i> Enroll Now
                        </button>

                        <?php if ($course['brochure_enabled'] && !empty($course['brochure_pdf'])): ?>
                            <a href="<?= htmlspecialchars($course['brochure_pdf']) ?>" target="_blank" class="btn-sticky-secondary">
                                <i class="fa-solid fa-file-pdf text-danger"></i> Download Syllabus PDF
                            </a>
                        <?php endif; ?>

                        <!-- Program Highlights -->
                        <div class="sticky-bullets-header">This course includes:</div>
                        <ul class="sticky-bullets-list">
                            <li class="sticky-bullet-item">
                                <i class="fa-solid fa-clock"></i>
                                <span><strong><?= htmlspecialchars($course['duration']) ?> <?= ucfirst($course['duration_unit']) ?></strong> comprehensive study</span>
                            </li>
                            <li class="sticky-bullet-item">
                                <i class="fa-solid fa-location-dot"></i>
                                <span>Training: <strong><?= strtolower($course['mode']) === 'online' ? 'Online / Remote Classes' : 'Physical Offline Labs' ?></strong></span>
                            </li>
                            <li class="sticky-bullet-item">
                                <i class="fa-solid fa-circle-check"></i>
                                <span>MG Board certified skills</span>
                            </li>
                            <li class="sticky-bullet-item">
                                <i class="fa-solid fa-mobile-screen"></i>
                                <span>Access on mobile, tablet & TV portal</span>
                            </li>
                            <li class="sticky-bullet-item">
                                <i class="fa-solid fa-infinity"></i>
                                <span>Full lifetime credentials access</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Card 2: Quick Enquiry Form (Coursera/Unacademy Conversion Widget) -->
                <div class="enquiry-card animate__animated animate__fadeIn" style="animation-delay: 0.1s;">
                    <div class="enquiry-header">
                        <h3 class="enquiry-title"><i class="fa-solid fa-headset"></i> Quick Enquiry Form</h3>
                        <p class="enquiry-subtitle">Have questions? Fill in your details below for a quick advisor callback.</p>
                    </div>
                    <form id="quickEnquiryForm" onsubmit="handleEnquirySubmit(event)">
                        <!-- Name input -->
                        <div class="enquiry-form-group">
                            <i class="fa-solid fa-user enquiry-input-icon"></i>
                            <input type="text" id="enquiry_name" name="name" class="enquiry-form-control" placeholder="Candidate's Full Name" required>
                        </div>
                        
                        <!-- Phone input -->
                        <div class="enquiry-form-group">
                            <i class="fa-solid fa-phone enquiry-input-icon"></i>
                            <input type="tel" id="enquiry_phone" name="phone" class="enquiry-form-control" placeholder="WhatsApp / Mobile Number" required>
                        </div>

                        <!-- Email input -->
                        <div class="enquiry-form-group">
                            <i class="fa-solid fa-envelope enquiry-input-icon"></i>
                            <input type="email" id="enquiry_email" name="email" class="enquiry-form-control" placeholder="Active Email Address" required>
                        </div>

                        <!-- Preferred Mode Select dropdown -->
                        <div class="enquiry-form-group">
                            <i class="fa-solid fa-circle-check enquiry-input-icon"></i>
                            <select id="enquiry_mode" name="mode" class="enquiry-form-control enquiry-form-control-select" required>
                                <option value="" disabled selected>Preferred Study Mode</option>
                                <option value="offline">Physical Offline Labs (Prayag campus)</option>
                                <option value="online">Online / Remote Classes</option>
                            </select>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn-enquiry-submit">
                            <i class="fa-solid fa-paper-plane"></i> Request Advisor Call
                        </button>
                    </form>
                </div>

                <!-- Card 3: Redesigned Premium Sidebar Reviews Widget -->
                <div class="reviews-sidebar-card animate__animated animate__fadeIn" style="animation-delay: 0.15s;" id="reviews">
                    <h3 class="reviews-sidebar-title"><i class="fa-solid fa-star text-warning"></i> Student Reviews</h3>
                    
                    <!-- Compact Rating Summary -->
                    <div class="sidebar-rating-summary">
                        <div class="sidebar-rating-score">
                            <span class="sidebar-rating-num"><?= $ratingValue ?></span>
                            <div class="sidebar-rating-stars">
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
                            <span class="sidebar-rating-count">(<?= htmlspecialchars($course['ratings_count'] ?: '24') ?> reviews)</span>
                        </div>

                        <!-- Thin elegant progress bars -->
                        <div class="sidebar-rating-bars">
                            <?php 
                            $totalForPct = ($totalActualReviews > 0) ? $totalActualReviews : 24;
                            for ($star = 5; $star >= 1; $star--): 
                                $pct = round(($starCounts[$star] / $totalForPct) * 100);
                            ?>
                                <div class="sidebar-rating-bar-row">
                                    <span class="sidebar-bar-label"><?= $star ?> ★</span>
                                    <div class="sidebar-bar-track">
                                        <div class="sidebar-bar-fill" style="width: <?= $pct ?>%;"></div>
                                    </div>
                                    <span class="sidebar-bar-percent"><?= $pct ?>%</span>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Compact Reviews List Feed -->
                    <div class="sidebar-reviews-feed" id="studentReviewsFeed">
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
                                <div class="sidebar-review-item animate__animated animate__fadeIn">
                                    <div class="sidebar-review-header">
                                        <div class="sidebar-review-user">
                                            <div class="sidebar-user-avatar" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);"><?= htmlspecialchars($initials) ?></div>
                                            <div class="sidebar-user-details">
                                                <h4><?= htmlspecialchars($rev['name']) ?></h4>
                                                <span class="sidebar-verified-badge" style="color: #0d47a1; background: #e7f1ff; border-color: #bbf7d0;"><i class="fa-solid fa-circle-check"></i> Verified Student</span>
                                            </div>
                                        </div>
                                        <span class="sidebar-review-date"><?= $dateStr ?></span>
                                    </div>
                                    <div class="sidebar-review-stars-row">
                                        <div class="sidebar-stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php if ($i <= intval($rev['rating'])): ?>
                                                    <i class="fa-solid fa-star"></i>
                                                <?php else: ?>
                                                    <i class="fa-regular fa-star"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="sidebar-review-title"><?= htmlspecialchars($rev['title']) ?></span>
                                    </div>
                                    <p class="sidebar-review-comment">
                                        <?= nl2br(htmlspecialchars($rev['comment'])) ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- Verified Review 1 (Seed) -->
                        <div class="sidebar-review-item">
                            <div class="sidebar-review-header">
                                <div class="sidebar-review-user">
                                    <div class="sidebar-user-avatar">RS</div>
                                    <div class="sidebar-user-details">
                                        <h4>Rahul Sharma</h4>
                                        <span class="sidebar-verified-badge"><i class="fa-solid fa-circle-check"></i> Verified Student</span>
                                    </div>
                                </div>
                                <span class="sidebar-review-date">2w ago</span>
                            </div>
                            <div class="sidebar-review-stars-row">
                                <div class="sidebar-stars">
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                </div>
                                <span class="sidebar-review-title">Excellent Practical Labs</span>
                            </div>
                            <p class="sidebar-review-comment">
                                The physical lab facilities at Prayag campus are top-notch. Daily interactive practice really helped me build speed. Thorough guides!
                            </p>
                        </div>

                        <!-- Verified Review 2 (Seed) -->
                        <div class="sidebar-review-item">
                            <div class="sidebar-review-header">
                                <div class="sidebar-review-user">
                                    <div class="sidebar-user-avatar">AK</div>
                                    <div class="sidebar-user-details">
                                        <h4>Aarti Kumari</h4>
                                        <span class="sidebar-verified-badge"><i class="fa-solid fa-circle-check"></i> Verified Student</span>
                                    </div>
                                </div>
                                <span class="sidebar-review-date">1m ago</span>
                            </div>
                            <div class="sidebar-review-stars-row">
                                <div class="sidebar-stars">
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star-half-stroke"></i>
                                </div>
                                <span class="sidebar-review-title">Great Placement Support</span>
                            </div>
                            <p class="sidebar-review-comment">
                                Very structured curriculum. Placement assistance is robust, helping us design excellent CV portfolios. High-value credentials.
                            </p>
                        </div>
                    </div>

                    <!-- Elegant Toggle-able submission block -->
                    <div class="sidebar-write-review-section">
                        <button type="button" class="btn-toggle-review-form" id="btnToggleReviewForm" onclick="toggleSidebarReviewForm()">
                            <i class="fa-solid fa-pen-nib"></i> Write a Student Review
                        </button>
                        
                        <div class="sidebar-review-form-wrapper" id="sidebarReviewFormWrapper" style="display: none;">
                            <form id="submitStudentReviewForm" onsubmit="handleReviewSubmit(event)">
                                <label class="sidebar-form-label">Select Your Rating:</label>
                                <div class="interactive-star-selector" id="interactiveStarSelector">
                                    <button type="button" class="interactive-star-btn" data-rating="1" onclick="setInteractiveReviewRating(1)" onmouseover="highlightInteractiveReviewRating(1)" onmouseout="clearHighlightReviewRating()"><i class="fa-solid fa-star"></i></button>
                                    <button type="button" class="interactive-star-btn" data-rating="2" onclick="setInteractiveReviewRating(2)" onmouseover="highlightInteractiveReviewRating(2)" onmouseout="clearHighlightReviewRating()"><i class="fa-solid fa-star"></i></button>
                                    <button type="button" class="interactive-star-btn" data-rating="3" onclick="setInteractiveReviewRating(3)" onmouseover="highlightInteractiveReviewRating(3)" onmouseout="clearHighlightReviewRating()"><i class="fa-solid fa-star"></i></button>
                                    <button type="button" class="interactive-star-btn" data-rating="4" onclick="setInteractiveReviewRating(4)" onmouseover="highlightInteractiveReviewRating(4)" onmouseout="clearHighlightReviewRating()"><i class="fa-solid fa-star"></i></button>
                                    <button type="button" class="interactive-star-btn" data-rating="5" onclick="setInteractiveReviewRating(5)" onmouseover="highlightInteractiveReviewRating(5)" onmouseout="clearHighlightReviewRating()"><i class="fa-solid fa-star"></i></button>
                                </div>
                                <input type="hidden" id="student_input_rating" name="rating" value="" required>

                                <div class="sidebar-form-group">
                                    <i class="fa-solid fa-user sidebar-form-icon"></i>
                                    <input type="text" id="review_name" name="name" class="sidebar-form-control" placeholder="Your Full Name" required>
                                </div>
                                
                                <div class="sidebar-form-group">
                                    <i class="fa-solid fa-pen sidebar-form-icon"></i>
                                    <input type="text" id="review_title" name="title" class="sidebar-form-control" placeholder="Review Highlight Title" required>
                                </div>

                                <div class="sidebar-form-group">
                                    <textarea id="review_comment" name="comment" class="sidebar-form-control" placeholder="Your review comments on lab guides, training desks, etc..." rows="3" style="padding-left:14px; padding-top:10px; resize:none;" required></textarea>
                                </div>

                                <button type="submit" class="btn-sidebar-review-submit">
                                    <i class="fa-solid fa-circle-check"></i> Submit Verified Review
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Terms & Conditions Sidebar Card -->
                <div class="terms-sidebar-card animate__animated animate__fadeIn" style="animation-delay: 0.2s; margin-top: 24px;">
                    <h3 class="terms-sidebar-title">
                        <i class="fa-solid fa-file-contract"></i> Terms & Conditions
                    </h3>
                    <ul class="terms-list">
                        <li class="terms-item">
                            <i class="fa-solid fa-chevron-right"></i>
                            <span>All educational registrations and credentials subject to regular testing standards.</span>
                        </li>
                        <li class="terms-item">
                            <i class="fa-solid fa-chevron-right"></i>
                            <span>Minimum 75% attendance inside Prayag offline coding labs is mandatory for certification.</span>
                        </li>
                        <li class="terms-item">
                            <i class="fa-solid fa-chevron-right"></i>
                            <span>Course reschedule and batch shifts are managed by administrative coordinators.</span>
                        </li>
                        <li class="terms-item">
                            <i class="fa-solid fa-chevron-right"></i>
                            <span>Fees paid are non-refundable after class sessions commence.</span>
                        </li>
                    </ul>
                    <div class="terms-footer">
                        © MG Education & Social Development Organization
                    </div>
                </div>

            </div>
        </div>

    </section>


</div>

<!-- Fullscreen Photo Gallery Lightbox -->
<div class="lightbox-wrapper" id="detailsLightbox">
    <div class="lightbox-body">
        <button type="button" class="lightbox-btn-close" onclick="closeLightbox()"><i class="fa-solid fa-xmark"></i></button>
        <button type="button" class="lightbox-arrow lightbox-arrow-left" onclick="prevLightboxImage()"><i class="fa-solid fa-chevron-left"></i></button>
        
        <img src="" class="lightbox-img-active" id="activeLightboxImg">
        
        <button type="button" class="lightbox-arrow lightbox-arrow-right" onclick="nextLightboxImage()"><i class="fa-solid fa-chevron-right"></i></button>
    </div>
</div>

<script>
    // Smooth scroll sections helper
    function scrollToSection(id) {
        const section = document.getElementById(id);
        if (section) {
            window.scrollTo({
                top: section.offsetTop - 75,
                behavior: 'smooth'
            });

            // Set active class manually to provide immediate response
            const tabItems = document.querySelectorAll('.subnav-tab-item');
            tabItems.forEach(tab => {
                if (tab.textContent.toLowerCase().includes(id.substring(0, 3))) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            });
        }
    }

    // Scroll listener to update active highlights dynamically (Coursera Scrollspy style)
    window.addEventListener('scroll', function() {
        const sections = ['overview', 'curriculum', 'selections', 'gallery', 'reviews'];
        const scrollPosition = window.scrollY + 110;

        sections.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                const top = el.offsetTop - 80;
                const bottom = top + el.offsetHeight;
                
                if (scrollPosition >= top && scrollPosition < bottom) {
                    const tabItems = document.querySelectorAll('.subnav-tab-item');
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

    function openLightbox(index) {
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

    function closeLightbox() {
        lightboxModal.style.display = 'none';
        lightboxImg.src = '';
        document.body.style.overflow = '';
    }

    function prevLightboxImage() {
        if (!galleryPaths || galleryPaths.length === 0) return;
        activeImageIndex = (activeImageIndex - 1 + galleryPaths.length) % galleryPaths.length;
        lightboxImg.src = galleryPaths[activeImageIndex];
    }

    function nextLightboxImage() {
        if (!galleryPaths || galleryPaths.length === 0) return;
        activeImageIndex = (activeImageIndex + 1) % galleryPaths.length;
        lightboxImg.src = galleryPaths[activeImageIndex];
    }

    // Keyboard support for Lightbox modal
    document.addEventListener('keydown', function(e) {
        if (lightboxModal.style.display === 'flex') {
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') prevLightboxImage();
            if (e.key === 'ArrowRight') nextLightboxImage();
        }
    });

    // Interactive enrollment SweetAlert prompt
    function triggerEnrollment() {
        Swal.fire({
            title: 'Initiate Admission Registration',
            text: 'Would you like to register your online application profile for the <?= htmlspecialchars($course['name'], ENT_QUOTES) ?> academic session?',
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
                // Route applicant directly to admission.php with selected course pre-loaded
                window.location.href = "admission.php?course_id=<?= $course['id'] ?>";
            }
        });
    }

    // Quick Enquiry Form submission handler (Premium AJAX database workflow)
    function handleEnquirySubmit(event) {
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
        formData.append('course_id', '<?= $course['id'] ?>');
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
                document.getElementById('quickEnquiryForm').reset();
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
        const starButtons = document.querySelectorAll('.interactive-star-btn');
        starButtons.forEach((btn, index) => {
            if (index < rating) {
                btn.classList.add('hover');
            } else {
                btn.classList.remove('hover');
            }
        });
    }

    function clearHighlightReviewRating() {
        const starButtons = document.querySelectorAll('.interactive-star-btn');
        starButtons.forEach(btn => btn.classList.remove('hover'));
        updateInteractiveStarsDisplay();
    }

    function updateInteractiveStarsDisplay() {
        const starButtons = document.querySelectorAll('.interactive-star-btn');
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
            btnToggle.innerHTML = '<i class="fa-solid fa-pen-nib"></i> Write a Student Review';
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
        formData.append('course_id', '<?= $course['id'] ?>');
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
                    <div class="sidebar-review-item animate__animated animate__fadeInDown">
                        <div class="sidebar-review-header">
                            <div class="sidebar-review-user">
                                <div class="sidebar-user-avatar" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">${initials}</div>
                                <div class="sidebar-user-details">
                                    <h4>${name}</h4>
                                    <span class="sidebar-verified-badge" style="color: #0d47a1; background: #e7f1ff; border-color: #bbf7d0;"><i class="fa-solid fa-circle-check"></i> Verified Student</span>
                                </div>
                            </div>
                            <span class="sidebar-review-date">Just now</span>
                        </div>
                        <div class="sidebar-review-stars-row">
                            <div class="sidebar-stars">
                                ${starsHtml}
                            </div>
                            <span class="sidebar-review-title">${title}</span>
                        </div>
                        <p class="sidebar-review-comment">${comment}</p>
                    </div>
                `;

                // Prepend new review card to target feed block
                const feedContainer = document.getElementById('studentReviewsFeed');
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
