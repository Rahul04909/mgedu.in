<?php 
include './header.php'; 

// Initialize stats variables
$total_courses = 0;
$total_categories = 0;
$total_admins = 0;
$online_courses = 0;
$offline_courses = 0;
$recent_courses = [];

try {
    $db = MG_GetDBConnection();
    
    // 1. Fetch Total Courses
    $stmt = $db->query("SELECT COUNT(*) FROM `courses`");
    $total_courses = (int)$stmt->fetchColumn();
    
    // 2. Fetch Total Categories
    $stmt = $db->query("SELECT COUNT(*) FROM `course_categories`");
    $total_categories = (int)$stmt->fetchColumn();
    
    // 3. Fetch Total Admins
    $stmt = $db->query("SELECT COUNT(*) FROM `admins`");
    $total_admins = (int)$stmt->fetchColumn();

    // 4. Fetch Online Courses
    $stmt = $db->query("SELECT COUNT(*) FROM `courses` WHERE `mode` = 'Online'");
    $online_courses = (int)$stmt->fetchColumn();

    // 5. Fetch Offline Courses
    $stmt = $db->query("SELECT COUNT(*) FROM `courses` WHERE `mode` = 'Offline'");
    $offline_courses = (int)$stmt->fetchColumn();

    // 6. Fetch 5 Recent Courses with Category
    $stmt = $db->query("
        SELECT c.*, cat.name as category_name 
        FROM `courses` c 
        LEFT JOIN `course_categories` cat ON c.category_id = cat.id 
        ORDER BY c.id DESC 
        LIMIT 5
    ");
    $recent_courses = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
}

// Time-based welcome greeting
$hour = (int)date('H');
$greeting = "Welcome back";
if ($hour < 12) {
    $greeting = "Good morning";
} elseif ($hour < 17) {
    $greeting = "Good afternoon";
} else {
    $greeting = "Good evening";
}
?>

<style>
    /* Premium Dashboard Aesthetic Override */
    .dashboard-container {
        font-family: 'Inter', sans-serif;
    }

    /* Welcome Card Styling */
    .welcome-card {
        background: linear-gradient(135deg, #134e5e 0%, #71b280 100%);
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(19, 78, 94, 0.15);
        color: #ffffff;
        border: none;
        position: relative;
        overflow: hidden;
        margin-bottom: 30px;
        transition: transform 0.3s ease;
    }

    .welcome-card:hover {
        transform: translateY(-2px);
    }

    .welcome-card::after {
        content: '';
        position: absolute;
        width: 300px;
        height: 300px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50%;
        top: -100px;
        right: -100px;
    }

    .welcome-card-body {
        padding: 40px;
        position: relative;
        z-index: 2;
    }

    .welcome-title {
        font-weight: 700;
        font-size: 2.2rem;
        margin-bottom: 8px;
        letter-spacing: -0.5px;
    }

    .welcome-subtitle {
        font-weight: 300;
        opacity: 0.9;
        font-size: 1.1rem;
    }

    /* KPI Metrics Cards */
    .kpi-card {
        border-radius: 16px;
        border: none;
        color: #ffffff;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        height: 100%;
        min-height: 140px;
    }

    .kpi-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
    }

    .kpi-card-categories {
        background: linear-gradient(135deg, #02aab0 0%, #00cdac 100%);
    }

    .kpi-card-courses {
        background: linear-gradient(135deg, #2b5876 0%, #4e4376 100%);
    }

    .kpi-card-online {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }

    .kpi-card-offline {
        background: linear-gradient(135deg, #f2994a 0%, #f2c94c 100%);
    }

    .kpi-card-body {
        padding: 24px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
        position: relative;
        z-index: 2;
    }

    .kpi-icon {
        position: absolute;
        right: 20px;
        bottom: 20px;
        font-size: 4rem;
        opacity: 0.15;
        transition: transform 0.3s ease;
    }

    .kpi-card:hover .kpi-icon {
        transform: scale(1.15) rotate(-10deg);
    }

    .kpi-number {
        font-size: 2.8rem;
        font-weight: 700;
        line-height: 1;
        margin-bottom: 5px;
    }

    .kpi-label {
        font-size: 0.95rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        opacity: 0.9;
    }

    /* Panels & Tables */
    .dashboard-panel {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #eef0f2;
        box-shadow: 0 5px 20px rgba(0,0,0,0.02);
        margin-bottom: 30px;
    }

    .panel-header {
        padding: 20px 24px;
        border-bottom: 1px solid #f4f6f8;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .panel-title {
        font-weight: 700;
        font-size: 1.15rem;
        color: #2c3e50;
        margin: 0;
    }

    .panel-body {
        padding: 24px;
    }

    /* Table styling overrides */
    .table-premium {
        margin: 0;
    }

    .table-premium th {
        font-weight: 600;
        color: #8898aa;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #eef2f5;
        padding: 12px 16px;
    }

    .table-premium td {
        padding: 16px;
        vertical-align: middle;
        border-bottom: 1px solid #f4f6f8;
        color: #4a5568;
        font-size: 0.9rem;
    }

    .table-premium tr:last-child td {
        border-bottom: none;
    }

    /* Custom buttons and actions */
    .btn-quick-link {
        display: flex;
        align-items: center;
        padding: 15px 20px;
        border-radius: 12px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #334155;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.2s ease;
        margin-bottom: 15px;
    }

    .btn-quick-link:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
        color: #28a745;
        transform: translateX(4px);
    }

    .btn-quick-link i {
        font-size: 1.25rem;
        margin-right: 15px;
        width: 24px;
        text-align: center;
    }

    .quick-link-add-category i { color: #02aab0; }
    .quick-link-add-course i { color: #2b5876; }
    .quick-link-profile i { color: #f2994a; }
    .quick-link-site i { color: #28a745; }

    .course-mode-badge {
        font-size: 0.75rem;
        font-weight: 700;
        padding: 4px 8px;
        border-radius: 6px;
        display: inline-block;
    }

    .course-mode-online {
        background-color: #e0f2fe;
        color: #0369a1;
    }

    .course-mode-offline {
        background-color: #fef3c7;
        color: #b45309;
    }

    .currency-symbol {
        font-size: 0.8rem;
        margin-right: 2px;
        color: #718096;
    }
</style>

<div class="dashboard-container container-fluid py-4">

    <!-- Welcome Message Card -->
    <div class="welcome-card">
        <div class="welcome-card-body">
            <h2 class="welcome-title"><?= htmlspecialchars($greeting) ?>, <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Administrator') ?>!</h2>
            <p class="welcome-subtitle">Here is the latest overview of the MG Education and Social Development Organization database. You can manage courses, categories, and review dynamic schema attributes.</p>
            <div class="mt-3">
                <span class="badge bg-white text-dark py-2 px-3 rounded-pill shadow-sm font-weight-bold">
                    <i class="far fa-calendar-alt mr-2 text-success"></i><?= date('l, F j, Y') ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Main Metric Cards -->
    <div class="row mb-5">
        <!-- Course Categories -->
        <div class="col-lg-3 col-md-6 col-12 mb-4">
            <div class="kpi-card kpi-card-categories">
                <div class="kpi-card-body">
                    <div>
                        <div class="kpi-number"><?= $total_categories ?></div>
                        <div class="kpi-label">Categories</div>
                    </div>
                    <a href="course-categories/index.php" class="text-white font-weight-bold text-decoration-none mt-2" style="font-size: 0.85rem;">
                        Manage Categories <i class="fas fa-arrow-circle-right ml-1"></i>
                    </a>
                </div>
                <i class="fas fa-tags kpi-icon"></i>
            </div>
        </div>

        <!-- Total Courses -->
        <div class="col-lg-3 col-md-6 col-12 mb-4">
            <div class="kpi-card kpi-card-courses">
                <div class="kpi-card-body">
                    <div>
                        <div class="kpi-number"><?= $total_courses ?></div>
                        <div class="kpi-label">Total Courses</div>
                    </div>
                    <a href="courses/index.php" class="text-white font-weight-bold text-decoration-none mt-2" style="font-size: 0.85rem;">
                        Manage Courses <i class="fas fa-arrow-circle-right ml-1"></i>
                    </a>
                </div>
                <i class="fas fa-graduation-cap kpi-icon"></i>
            </div>
        </div>

        <!-- Online Courses -->
        <div class="col-lg-3 col-md-6 col-12 mb-4">
            <div class="kpi-card kpi-card-online">
                <div class="kpi-card-body">
                    <div>
                        <div class="kpi-number"><?= $online_courses ?></div>
                        <div class="kpi-label">Online Courses</div>
                    </div>
                    <a href="courses/index.php?mode=Online" class="text-white font-weight-bold text-decoration-none mt-2" style="font-size: 0.85rem;">
                        View Online <i class="fas fa-arrow-circle-right ml-1"></i>
                    </a>
                </div>
                <i class="fas fa-globe kpi-icon"></i>
            </div>
        </div>

        <!-- Offline Courses -->
        <div class="col-lg-3 col-md-6 col-12 mb-4">
            <div class="kpi-card kpi-card-offline">
                <div class="kpi-card-body">
                    <div>
                        <div class="kpi-number"><?= $offline_courses ?></div>
                        <div class="kpi-label">Offline Courses</div>
                    </div>
                    <a href="courses/index.php?mode=Offline" class="text-white font-weight-bold text-decoration-none mt-2" style="font-size: 0.85rem;">
                        View Offline <i class="fas fa-arrow-circle-right ml-1"></i>
                    </a>
                </div>
                <i class="fas fa-building kpi-icon"></i>
            </div>
        </div>
    </div>

    <!-- Double Column Info Grid -->
    <div class="row">
        <!-- Left: Quick Links -->
        <div class="col-lg-4 mb-4">
            <div class="dashboard-panel h-100">
                <div class="panel-header">
                    <h3 class="panel-title"><i class="fas fa-rocket mr-2 text-success"></i>Quick Administration</h3>
                </div>
                <div class="panel-body d-flex flex-column justify-content-between">
                    <div>
                        <a href="course-categories/add.php" class="btn-quick-link quick-link-add-category">
                            <i class="fas fa-folder-plus"></i>
                            <span>Create New Category</span>
                        </a>
                        <a href="courses/add.php" class="btn-quick-link quick-link-add-course">
                            <i class="fas fa-plus-circle"></i>
                            <span>Register New Course</span>
                        </a>
                        <a href="profile.php" class="btn-quick-link quick-link-profile">
                            <i class="fas fa-user-cog"></i>
                            <span>My Profile Settings</span>
                        </a>
                        <a href="../index.php" target="_blank" class="btn-quick-link quick-link-site">
                            <i class="fas fa-external-link-alt"></i>
                            <span>Visit Public Site</span>
                        </a>
                    </div>
                    
                    <!-- Little status card -->
                    <div class="card bg-light border-0 rounded-3 mt-3">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 40px; height: 40px;">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div>
                                    <div class="font-weight-bold text-dark" style="font-size: 0.85rem;">Secure Shield Active</div>
                                    <div class="text-muted" style="font-size: 0.75rem;">Session anti-hijack enabled</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Recent Courses Table -->
        <div class="col-lg-8 mb-4">
            <div class="dashboard-panel h-100">
                <div class="panel-header">
                    <h3 class="panel-title"><i class="fas fa-history mr-2 text-success"></i>Recently Registered Courses</h3>
                    <a href="courses/index.php" class="btn btn-sm btn-outline-success rounded-pill font-weight-bold px-3">View All</a>
                </div>
                <div class="panel-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-premium">
                            <thead>
                                <tr>
                                    <th>Cover</th>
                                    <th>Course Name</th>
                                    <th>Category</th>
                                    <th>Sales Price</th>
                                    <th>Mode</th>
                                    <th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recent_courses)): ?>
                                    <?php foreach ($recent_courses as $course): ?>
                                        <?php 
                                        $cover = $project_base . 'assets/uploads/courses/covers/placeholder.png';
                                        if (!empty($course['course_image'])) {
                                            $cover = $project_base . htmlspecialchars($course['course_image']);
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <img src="<?= $cover ?>" alt="Cover" class="rounded" style="width: 45px; height: 30px; object-fit: cover; border: 1px solid #eef2f5;" onerror="this.src='https://via.placeholder.com/45x30?text=Course'">
                                            </td>
                                            <td class="font-weight-bold text-dark">
                                                <?= htmlspecialchars($course['name']) ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-secondary border py-1 px-2">
                                                    <?= htmlspecialchars($course['category_name'] ?? 'Uncategorized') ?>
                                                </span>
                                            </td>
                                            <td class="font-weight-bold text-dark">
                                                <span class="currency-symbol">₹</span><?= number_format($course['sales_price'], 2) ?>
                                            </td>
                                            <td>
                                                <span class="course-mode-badge <?= $course['mode'] === 'Online' ? 'course-mode-online' : 'course-mode-offline' ?>">
                                                    <?= htmlspecialchars($course['mode']) ?>
                                                </span>
                                            </td>
                                            <td class="text-right">
                                                <a href="courses/edit.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-light border rounded-circle" title="Edit Course" style="width: 32px; height: 32px; padding: 0; line-height: 30px; text-align: center;">
                                                    <i class="fas fa-edit text-success"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <div class="mb-3"><i class="fas fa-graduation-cap fa-3x opacity-25"></i></div>
                                            <p class="m-0 font-weight-bold">No courses registered yet</p>
                                            <a href="courses/add.php" class="btn btn-sm btn-success rounded-pill mt-3 font-weight-bold px-3">Add Your First Course</a>
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

</div>

<?php include './footer.php'; ?>