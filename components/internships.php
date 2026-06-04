<?php
/**
 * MG Education & Social Development Organization
 * Dynamic Internships Component — Fetches from Admin Database
 * Displays internships grouped by category with slider UI
 */

// Load config if not already loaded
if (!function_exists('MG_GetDBConnection')) {
    require_once __DIR__ . '/../includes/config.php';
}

$db_intern_comp = MG_GetDBConnection();

// Fetch all internship categories that have at least one internship
$internCategories = [];
$allInternships = [];

try {
    // Get categories that have internships
    $catStmt = $db_intern_comp->query("
        SELECT ic.id, ic.name, ic.slug 
        FROM internship_categories ic 
        INNER JOIN internships i ON i.category_id = ic.id 
        GROUP BY ic.id, ic.name, ic.slug 
        ORDER BY ic.name ASC
    ");
    $internCategories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all internships with their category info
    if (!empty($internCategories)) {
        $intStmt = $db_intern_comp->query("
            SELECT i.*, ic.name AS category_name, ic.slug AS category_slug
            FROM internships i
            INNER JOIN internship_categories ic ON i.category_id = ic.id
            ORDER BY ic.name ASC, i.id DESC
        ");
        $allInternships = $intStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Silent fail — show empty state
    $internCategories = [];
    $allInternships = [];
}

// Group internships by category slug
$internsByCategory = [];
foreach ($allInternships as $intern) {
    $catSlug = $intern['category_slug'];
    if (!isset($internsByCategory[$catSlug])) {
        $internsByCategory[$catSlug] = [];
    }
    $internsByCategory[$catSlug][] = $intern;
}

// Determine default active category (first one)
$defaultInternCatSlug = !empty($internCategories) ? $internCategories[0]['slug'] : '';

// Helper: Generate star rating HTML from ratings_count and ratings_sum
function renderInternStars($ratingsCount, $ratingsSum) {
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
function formatInternPrice($price) {
    return '₹' . number_format($price, 2);
}
?>

<?php if (!empty($internCategories) && !empty($allInternships)): ?>
<!-- Link to Internships Stylesheet -->
<link rel="stylesheet" href="assets/css/internships.css">

<section class="internships-section">
    <div class="internships-container">
        
        <!-- Headers Section -->
        <div class="internships-header">
            <h2>Gain Practical Experience with MG Internships</h2>
            <p>Kickstart your career with real-world projects, expert mentorship, and industry-recognized certifications.</p>
        </div>

        <!-- Filter Tab Buttons (Dynamic from DB categories) -->
        <div class="internships-filter-wrapper">
            <div class="internships-filter-tabs">
                <?php foreach ($internCategories as $idx => $cat): ?>
                    <button 
                        class="intern-filter-tab <?= $idx === 0 ? 'active' : '' ?>" 
                        id="tab-intern-<?= htmlspecialchars($cat['slug']) ?>" 
                        onclick="filterInternships('<?= htmlspecialchars($cat['slug']) ?>')"
                    ><?= htmlspecialchars($cat['name']) ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Slider Grid Wrapper -->
        <div class="internships-grid-container">
            
            <!-- Slider Arrows -->
            <button class="internships-nav-btn prev-btn" id="internPrevBtn" onclick="slideInternGrid('prev')" aria-label="Previous Internships">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            
            <button class="internships-nav-btn next-btn" id="internNextBtn" onclick="slideInternGrid('next')" aria-label="Next Internships">
                <i class="fa-solid fa-chevron-right"></i>
            </button>

            <!-- Internship Cards Grid (Dynamic from DB) -->
            <div class="internships-grid" id="internshipsGrid" onscroll="updateInternNavButtons()">
                
                <?php foreach ($internCategories as $catIdx => $cat): ?>
                    <?php 
                        $catSlug = $cat['slug'];
                        $catInterns = $internsByCategory[$catSlug] ?? [];
                        $hiddenClass = $catIdx === 0 ? '' : 'hidden';
                    ?>
                    
                    <?php foreach ($catInterns as $intern): ?>
                        <?php
                            $rating = renderInternStars($intern['ratings_count'] ?? 0, $intern['ratings_sum'] ?? 0);
                            $internImage = !empty($intern['internship_image']) ? $intern['internship_image'] : 'assets/images/placeholder-internship.jpg';
                            $internSlug = htmlspecialchars($intern['slug']);
                            $internName = htmlspecialchars($intern['name']);
                            $duration = $intern['duration'] . ' ' . ucfirst($intern['duration_unit'] ?? 'Months');
                            $mode = ucfirst($intern['mode'] ?? 'offline');
                            $salesPrice = formatInternPrice($intern['sales_price']);
                            $mrpPrice = formatInternPrice($intern['mrp']);
                            $discount = $intern['mrp'] > 0 ? round((($intern['mrp'] - $intern['sales_price']) / $intern['mrp']) * 100) : 0;
                        ?>
                        <a href="internship-details.php?slug=<?= $internSlug ?>" class="internship-card <?= $hiddenClass ?>" data-category="<?= htmlspecialchars($catSlug) ?>">
                            <div class="internship-image-box">
                                <img src="<?= htmlspecialchars($internImage) ?>" alt="<?= $internName ?>" loading="lazy">
                            </div>
                            <div class="internship-info">
                                <h3 class="internship-title"><?= $internName ?></h3>
                                <p class="internship-instructor">MG Education | <?= $mode ?> • <?= htmlspecialchars($duration) ?></p>
                                <div class="internship-ratings">
                                    <span class="intern-rating-score"><?= $rating['score'] ?></span>
                                    <div class="intern-rating-stars">
                                        <?= $rating['stars'] ?>
                                    </div>
                                    <span class="intern-rating-count">(<?= number_format($rating['count']) ?> interns)</span>
                                </div>
                                <div class="internship-badge-container">
                                    <?php if ($intern['brochure_enabled']): ?>
                                        <span class="intern-badge-featured">Brochure Available</span>
                                    <?php else: ?>
                                        <span class="intern-badge-featured">Certificate Included</span>
                                    <?php endif; ?>
                                </div>
                                <div class="internship-stipend-box">
                                    <span class="stipend-label">Fee:</span>
                                    <span class="stipend-value"><?= $salesPrice ?></span>
                                    <?php if ($intern['mrp'] > $intern['sales_price']): ?>
                                        <span style="text-decoration: line-through; color: #94a3b8; font-size: 12px; margin-left: 6px;"><?= $mrpPrice ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endforeach; ?>

            </div> <!-- /internships-grid -->
        </div> <!-- /internships-grid-container -->

        <!-- Bottom Redirection Link -->
        <a href="internships" class="internships-show-all" id="showAllInternLink">
            Show all <?= htmlspecialchars($internCategories[0]['name'] ?? 'All') ?> internships <i class="fa-solid fa-arrow-right"></i>
        </a>

    </div> <!-- /internships-container -->
</section>

<!-- Slider & Filter Script -->
<script>
    function filterInternships(category) {
        // 1. Manage Active Tab Highlight State
        const tabs = document.querySelectorAll('.intern-filter-tab');
        tabs.forEach(tab => {
            if(tab.id === 'tab-intern-' + category) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });

        // 2. Filter Internship Cards
        const cards = document.querySelectorAll('.internship-card');
        cards.forEach(card => {
            const cardCategory = card.getAttribute('data-category');
            if (cardCategory === category) {
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        });

        // 3. Update dynamic "Show All" text using data from PHP
        const showAllLink = document.getElementById('showAllInternLink');
        const categoryLabels = {
            <?php foreach ($internCategories as $cat): ?>
                '<?= htmlspecialchars($cat['slug']) ?>': '<?= htmlspecialchars($cat['name']) ?>',
            <?php endforeach; ?>
        };
        const categoryLabel = categoryLabels[category] || 'All';
        showAllLink.innerHTML = `Show all ${categoryLabel} internships <i class="fa-solid fa-arrow-right"></i>`;

        // 4. Reset Slider Scroll Position
        const grid = document.getElementById('internshipsGrid');
        grid.scrollLeft = 0;
        setTimeout(updateInternNavButtons, 50);
    }

    // Slider Smooth Scrolling Action
    function slideInternGrid(direction) {
        const grid = document.getElementById('internshipsGrid');
        const scrollAmount = grid.clientWidth * 0.75;
        if (direction === 'prev') {
            grid.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        } else {
            grid.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        }
        setTimeout(updateInternNavButtons, 400);
    }

    // Dynamic slider next/prev button boundaries
    function updateInternNavButtons() {
        const grid = document.getElementById('internshipsGrid');
        const prevBtn = document.getElementById('internPrevBtn');
        const nextBtn = document.getElementById('internNextBtn');
        
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
        filterInternships('<?= htmlspecialchars($defaultInternCatSlug) ?>');
        window.addEventListener('resize', updateInternNavButtons);
    });
</script>

<?php else: ?>
<!-- No internships found - show empty state -->
<link rel="stylesheet" href="assets/css/internships.css">
<section class="internships-section">
    <div class="internships-container">
        <div class="internships-header">
            <h2>Gain Practical Experience with MG Internships</h2>
            <p>Kickstart your career with real-world projects, expert mentorship, and industry-recognized certifications.</p>
        </div>
        <div style="text-align: center; padding: 60px 20px;">
            <i class="fa-solid fa-briefcase" style="font-size: 48px; color: #cbd5e1; margin-bottom: 16px;"></i>
            <h3 style="color: #64748b; font-size: 18px; font-weight: 600; margin-bottom: 8px;">Internships Coming Soon</h3>
            <p style="color: #94a3b8; font-size: 14px;">Our professional internship programs are being prepared. Check back soon!</p>
        </div>
    </div>
</section>
<?php endif; ?>
