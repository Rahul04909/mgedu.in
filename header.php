<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($dynamic_title) ? htmlspecialchars($dynamic_title) : "MG Education & Social Development Organization" ?></title>
    <?php if (isset($dynamic_meta_desc) && !empty($dynamic_meta_desc)): ?>
        <meta name="description" content="<?= htmlspecialchars($dynamic_meta_desc) ?>">
    <?php endif; ?>
    <?php if (isset($dynamic_meta_keywords) && !empty($dynamic_meta_keywords)): ?>
        <meta name="keywords" content="<?= htmlspecialchars($dynamic_meta_keywords) ?>">
    <?php endif; ?>
    <?php if (isset($dynamic_og_info) && is_array($dynamic_og_info)): ?>
        <?php foreach ($dynamic_og_info as $property => $content): ?>
            <meta property="<?= htmlspecialchars($property) ?>" content="<?= htmlspecialchars($content) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if (isset($dynamic_seo_schema) && !empty($dynamic_seo_schema)): ?>
        <script type="application/ld+json">
            <?= $dynamic_seo_schema ?>
        </script>
    <?php endif; ?>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/header.css">
</head>
<body>

    <!-- Top Bar -->
    <div class="top-bar">
        <div class="top-bar-left">
            <span><i class="fa-solid fa-phone"></i> Contact for Inquiry :</span>
            <a href="tel:+918059982049"><img src="https://flagcdn.com/w20/in.png" alt="IN" width="16"> +91 80599 82049</a>
        </div>
        <div class="top-bar-right">
            <span><i class="fa-solid fa-bullhorn"></i> <span class="offer-tag">Admission Open</span> - Enroll in Academic Programs 2026 | Ends in : <span id="timer">09h 08m 01s</span></span>
            <a href="#" class="grab-now">APPLY NOW</a>
        </div>
    </div>

    <!-- Main Header -->
    <header class="main-header">
        <div class="logo">
            <a href="index.php">
                <img src="assets/logo/logo.jpg" alt="MG Education Logo" onerror="this.src='https://via.placeholder.com/200x50?text=MG+EDUCATION'">
            </a>
        </div>

        <div class="header-search-container">
            <div class="search-bar">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" placeholder="Search courses, programs, news...">
            </div>
        </div>

        <div class="header-links">
            <a href="#">Academic Programs</a>
            <a href="#" class="highlight-link">Social Work</a>
            <a href="#">Resources</a>
        </div>

        <div class="auth-buttons">
            <a href="#" class="btn-auth btn-signup">Student Portal</a>
            <a href="#" class="btn-auth btn-login">Log in</a>
        </div>

        <div class="mobile-toggle" id="mobile-menu-btn">
            <i class="fa-solid fa-bars"></i>
        </div>
    </header>

    <!-- Navigation Bar -->
    <nav class="nav-bar">
        <ul class="nav-menu" id="nav-menu">
            <li><a href="index.php">Home</a></li>
            <li><a href="#">About Organization</a></li>
            <li><a href="#">Education Wing</a></li>
            <li><a href="#">Social Development</a></li>
            <li><a href="#">Success Stories</a></li>
            <li><a href="#">Contact Us</a></li>
        </ul>
    </nav>

    <!-- Mobile Sidebar -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>
    <aside class="mobile-sidebar" id="mobile-sidebar">
        <div class="sidebar-header">
            <img src="assets/logo/logo.jpg" alt="MG Education Logo" height="30" onerror="this.src='https://via.placeholder.com/120x30?text=MG+EDU'">
            <button class="close-sidebar" id="close-sidebar"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="sidebar-content">
            <div class="sidebar-section">
                <h4>Main Menu</h4>
                <ul>
                    <li><a href="index.php"><i class="fa-solid fa-house"></i> Home</a></li>
                    <li><a href="#"><i class="fa-solid fa-circle-info"></i> About Us</a></li>
                    <li><a href="#"><i class="fa-solid fa-graduation-cap"></i> Academic Programs</a></li>
                    <li><a href="#"><i class="fa-solid fa-hands-holding-heart"></i> Social Initiatives</a></li>
                    <li><a href="#"><i class="fa-solid fa-envelope"></i> Contact Us</a></li>
                </ul>
            </div>
            <div class="sidebar-section">
                <h4>Programs</h4>
                <ul id="sidebar-categories">
                    <li><a href="#">Primary Education</a></li>
                    <li><a href="#">Vocational Training</a></li>
                    <li><a href="#">Community Health</a></li>
                </ul>
            </div>
            <div class="sidebar-footer">
                <a href="#" class="btn-auth btn-signup">Student Portal</a>
                <a href="#" class="btn-auth btn-login">Log in</a>
            </div>
        </div>
    </aside>

    <script>
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const closeSidebar = document.getElementById('close-sidebar');
        const sidebar = document.getElementById('mobile-sidebar');
        const overlay = document.getElementById('sidebar-overlay');

        function toggleSidebar() {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
        }

        if(mobileMenuBtn) mobileMenuBtn.addEventListener('click', toggleSidebar);
        if(closeSidebar) closeSidebar.addEventListener('click', toggleSidebar);
        if(overlay) overlay.addEventListener('click', toggleSidebar);

        // Timer Logic
        let hours = 9, minutes = 8, seconds = 1;
        setInterval(() => {
            seconds--;
            if (seconds < 0) {
                seconds = 59;
                minutes--;
            }
            if (minutes < 0) {
                minutes = 59;
                hours--;
            }
            const timerEl = document.getElementById('timer');
            if (timerEl) {
                timerEl.innerText = `${hours.toString().padStart(2, '0')}h ${minutes.toString().padStart(2, '0')}m ${seconds.toString().padStart(2, '0')}s`;
            }
        }, 1000);
    </script>
