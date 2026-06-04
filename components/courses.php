<?php
/**
 * MG Education & Social Development Organization
 * Dynamic Courses Component — Fetches from Admin Database
 * Displays courses grouped by category with Udemy-style slider UI
 */

// Load config if not already loaded
if (!function_exists('MG_GetDBConnection')) {
    require_once __DIR__ . '/../includes/config.php';
}

$db_courses_comp = MG_GetDBConnection();

// Fetch all active course categories that have at least one course
$courseCategories = [];
$allCourses = [];

try {
    // Get categories that have courses
    $catStmt = $db_courses_comp->query("
        SELECT cc.id, cc.name, cc.slug 
        FROM course_categories cc 
        INNER JOIN courses c ON c.category_id = cc.id 
        GROUP BY cc.id, cc.name, cc.slug 
        ORDER BY cc.name ASC
    ");
    $courseCategories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all courses with their category info
    if (!empty($courseCategories)) {
        $courseStmt = $db_courses_comp->query("
            SELECT c.*, cc.name AS category_name, cc.slug AS category_slug
            FROM courses c
            INNER JOIN course_categories cc ON c.category_id = cc.id
            ORDER BY cc.name ASC, c.id DESC
        ");
        $allCourses = $courseStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Silent fail — show empty state
    $courseCategories = [];
    $allCourses = [];
}

// Group courses by category slug
$coursesByCategory = [];
foreach ($allCourses as $course) {
    $catSlug = $course['category_slug'];
    if (!isset($coursesByCategory[$catSlug])) {
        $coursesByCategory[$catSlug] = [];
    }
    $coursesByCategory[$catSlug][] = $course;
}

// Determine default active category (first one)
$defaultCategorySlug = !empty($courseCategories) ? $courseCategories[0]['slug'] : '';

// Helper: Generate star rating HTML from ratings_count and ratings_sum
function renderCourseStars($ratingsCount, $ratingsSum) {
    if ($ratingsCount <= 0) return ['score' => '0.0', 'stars' => '', 'count' => 0];
    
    $avgRating = round($ratingsSum / $ratingsCount, 1);
    $fullStars = floor($avgRating);
    $halfStar = ($avgRating - $fullStars) >= 0.3;
    $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
    
    $starsHtml = '';
    for ($i = 0; $i < $fullStars; $i++) {
        $starsHtml .= '<i class="fa-solid fa-star"></i>';
    }
    if ($halfStar) {
        $starsHtml .= '<i class="fa-solid fa-star-half-stroke"></i>';
    }
    for ($i = 0; $i < $emptyStars; $i++) {
        $starsHtml .= '<i class="fa-regular fa-star"></i>';
    }
    
    return [
        'score' => number_format($avgRating, 1),
        'stars' => $starsHtml,
        'count' => $ratingsCount
    ];
}

// Helper: Format price for Indian numbering
function formatCoursePrice($price) {
    return '₹' . number_format($price, 2);
}
?>

<?php if (!empty($courseCategories) && !empty($allCourses)): ?>
<!-- Link to Courses Stylesheet -->
<link rel="stylesheet" href="assets/css/courses.css">

<section class="courses-section">
    <div class="courses-container">
        
        <!-- Headers Section -->
        <div class="courses-header">
            <h2>Skills to transform your career and life</h2>
            <p>From critical skills to technical topics, MG Education supports your professional development.</p>
        </div>

        <!-- Filter Tab Buttons (Dynamic from DB categories) -->
        <div class="courses-filter-wrapper">
            <div class="courses-filter-tabs">
                <?php foreach ($courseCategories as $idx => $cat): ?>
                    <button 
                        class="filter-tab <?= $idx === 0 ? 'active' : '' ?>" 
                        id="tab-<?= htmlspecialchars($cat['slug']) ?>" 
                        onclick="filterCourses('<?= htmlspecialchars($cat['slug']) ?>')"
                    ><?= htmlspecialchars($cat['name']) ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Slider Grid Wrapper -->
        <div class="courses-grid-container">
            
            <!-- Slider Arrows -->
            <button class="courses-nav-btn prev-btn" id="prevBtn" onclick="slideGrid('prev')" aria-label="Previous Courses">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            
            <button class="courses-nav-btn next-btn" id="nextBtn" onclick="slideGrid('next')" aria-label="Next Courses">
                <i class="fa-solid fa-chevron-right"></i>
            </button>

            <!-- Course Cards Grid (Dynamic from DB) -->
            <div class="courses-grid" id="coursesGrid" onscroll="updateNavButtons()">
                
                <?php foreach ($courseCategories as $catIdx => $cat): ?>
                    <?php 
                        $catSlug = $cat['slug'];
                        $catCourses = $coursesByCategory[$catSlug] ?? [];
                        $hiddenClass = $catIdx === 0 ? '' : 'hidden';
                    ?>
                    
                    <?php foreach ($catCourses as $course): ?>
                        <?php
                            $rating = renderCourseStars($course['ratings_count'] ?? 0, $course['ratings_sum'] ?? 0);
                            $courseImage = !empty($course['course_image']) ? $course['course_image'] : 'assets/images/placeholder-course.jpg';
                            $courseSlug = htmlspecialchars($course['slug']);
                            $courseName = htmlspecialchars($course['name']);
                            $duration = $course['duration'] . ' ' . ucfirst($course['duration_unit'] ?? 'Months');
                            $mode = ucfirst($course['mode'] ?? 'offline');
                            $salesPrice = formatCoursePrice($course['sales_price']);
                            $mrpPrice = formatCoursePrice($course['mrp']);
                            $discount = $course['mrp'] > 0 ? round((($course['mrp'] - $course['sales_price']) / $course['mrp']) * 100) : 0;
                        ?>
                        <a href="course-details.php?slug=<?= $courseSlug ?>" class="course-card <?= $hiddenClass ?>" data-category="<?= htmlspecialchars($catSlug) ?>">
                            <div class="course-image-box">
                                <img src="<?= htmlspecialchars($courseImage) ?>" alt="<?= $courseName ?>" loading="lazy">
                            </div>
                            <div class="course-info">
                                <h3 class="course-title"><?= $courseName ?></h3>
                                <p class="course-instructor"><?= $mode ?> • Duration: <?= htmlspecialchars($duration) ?></p>
                                <div class="course-ratings">
                                    <span class="rating-score"><?= $rating['score'] ?></span>
                                    <div class="rating-stars">
                                        <?= $rating['stars'] ?>
                                    </div>
                                    <span class="rating-count">(<?= number_format($rating['count']) ?> ratings)</span>
                                </div>
                                <div class="course-badge-container">
                                    <?php if ($discount >= 30): ?>
                                        <span class="badge-bestseller"><?= $discount ?>% OFF</span>
                                    <?php else: ?>
                                        <span class="badge-bestseller">MG Certified</span>
                                    <?php endif; ?>
                                </div>
                                <div class="course-price">
                                    <span class="price-current"><?= $salesPrice ?></span>
                                    <?php if ($course['mrp'] > $course['sales_price']): ?>
                                        <span class="price-original"><?= $mrpPrice ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endforeach; ?>

            </div> <!-- /courses-grid -->
        </div> <!-- /courses-grid-container -->

        <!-- Bottom Redirection Link -->
        <a href="courses" class="courses-show-all" id="showAllLink">
            Show all <?= htmlspecialchars($courseCategories[0]['name'] ?? 'All') ?> courses <i class="fa-solid fa-arrow-right"></i>
        </a>

    </div> <!-- /courses-container -->
</section>

<!-- Slider & Filter Script -->
<script>
    function filterCourses(category) {
        // 1. Manage Active Tab Highlight State
        const tabs = document.querySelectorAll('.filter-tab');
        tabs.forEach(tab => {
            if(tab.id === 'tab-' + category) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });

        // 2. Filter Course Cards with smooth display toggles
        const cards = document.querySelectorAll('.course-card');
        cards.forEach(card => {
            const cardCategory = card.getAttribute('data-category');
            if (cardCategory === category) {
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        });

        // 3. Update dynamic "Show All" text using data from PHP
        const showAllLink = document.getElementById('showAllLink');
        const categoryLabels = {
            <?php foreach ($courseCategories as $cat): ?>
                '<?= htmlspecialchars($cat['slug']) ?>': '<?= htmlspecialchars($cat['name']) ?>',
            <?php endforeach; ?>
        };
        const categoryLabel = categoryLabels[category] || 'All';
        showAllLink.innerHTML = `Show all ${categoryLabel} courses <i class="fa-solid fa-arrow-right"></i>`;

        // 4. Reset Slider Scroll Position and update Arrow states
        const grid = document.getElementById('coursesGrid');
        grid.scrollLeft = 0;
        setTimeout(updateNavButtons, 50);
    }

    // Slider Smooth Scrolling Action
    function slideGrid(direction) {
        const grid = document.getElementById('coursesGrid');
        const scrollAmount = grid.clientWidth * 0.75;
        if (direction === 'prev') {
            grid.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        } else {
            grid.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        }
        setTimeout(updateNavButtons, 400);
    }

    // Dynamic slider next/prev button boundaries
    function updateNavButtons() {
        const grid = document.getElementById('coursesGrid');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        
        if (!grid || !prevBtn || !nextBtn) return;

        if (window.innerWidth <= 768) {
            return;
        }

        if (grid.scrollLeft <= 10) {
            prevBtn.disabled = true;
        } else {
            prevBtn.disabled = false;
        }

        const isEnd = grid.scrollLeft + grid.clientWidth >= grid.scrollWidth - 10;
        if (isEnd) {
            nextBtn.disabled = true;
        } else {
            nextBtn.disabled = false;
        }
    }

    // Set Defaults on document load
    document.addEventListener('DOMContentLoaded', () => {
        filterCourses('<?= htmlspecialchars($defaultCategorySlug) ?>');
        window.addEventListener('resize', updateNavButtons);
    });
</script>

<?php else: ?>
<!-- No courses found - show empty state -->
<link rel="stylesheet" href="assets/css/courses.css">
<section class="courses-section">
    <div class="courses-container">
        <div class="courses-header">
            <h2>Skills to transform your career and life</h2>
            <p>From critical skills to technical topics, MG Education supports your professional development.</p>
        </div>
        <div style="text-align: center; padding: 60px 20px;">
            <i class="fa-solid fa-graduation-cap" style="font-size: 48px; color: #cbd5e1; margin-bottom: 16px;"></i>
            <h3 style="color: #64748b; font-size: 18px; font-weight: 600; margin-bottom: 8px;">Courses Coming Soon</h3>
            <p style="color: #94a3b8; font-size: 14px;">Our academic course catalog is being prepared. Check back soon!</p>
        </div>
    </div>
</section>
<?php endif; ?>
