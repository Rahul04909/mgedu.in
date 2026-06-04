<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load configuration and DB systems
require_once dirname(__DIR__) . '/includes/config.php';

// Route authorization guard: must have franchise role and active center ID
if (!isset($_SESSION['center_role']) || $_SESSION['center_role'] !== 'franchise' || empty($_SESSION['center_id'])) {
    header("Location: login.php?error=unauthorized");
    exit();
}

$center_id = $_SESSION['center_id'];
$center_db_id = $_SESSION['center_logged_id'];
$center_name = $_SESSION['center_name'] ?? 'Franchise Center';

// Fetch current center details from the database
try {
    $db = MG_GetDBConnection();
    $stmt = $db->prepare("SELECT * FROM `franchise_centers` WHERE `id` = ? LIMIT 1");
    $stmt->execute([$center_db_id]);
    $center = $stmt->fetch();
    
    if (!$center) {
        // Revoke session if center not found
        unset($_SESSION['center_role']);
        unset($_SESSION['center_logged_id']);
        unset($_SESSION['center_id']);
        unset($_SESSION['center_name']);
        unset($_SESSION['center_email']);
        header("Location: login.php?error=unauthorized");
        exit();
    }
    
    // Sync the center name
    $center_name = $center['center_name'];
    $_SESSION['center_name'] = $center_name;
} catch (Exception $e) {
    error_log("Database error in franchise dashboard: " . $e->getMessage());
}

$currentPage = basename($_SERVER['SCRIPT_NAME']);

$menuItems = [
    [
        "menuTitle" => "Dashboard",
        "icon" => "fas fa-home",
        "pages" => [
            ["title" => "Home", "url" => "index.php"]
        ],
    ],
    [
        "menuTitle" => "Student Mgmt",
        "icon" => "fas fa-user-graduate",
        "pages" => [
            ["title" => "Enroll Course", "url" => "enroll-course.php"],
            ["title" => "Enroll Internship", "url" => "enroll-internship.php"],
            ["title" => "Admissions List", "url" => "admissions.php"]
        ],
    ],
    [
        "menuTitle" => "Financials",
        "icon" => "fas fa-wallet",
        "pages" => [
            ["title" => "My Wallet", "url" => "wallet.php"],
            ["title" => "Fees Ledgers", "url" => "fees.php"]
        ],
    ],
    [
        "menuTitle" => "Settings",
        "icon" => "fas fa-cog",
        "pages" => [
            ["title" => "Profile", "url" => "profile.php"]
        ],
    ]
];

$active_pageInfo = null;
foreach ($menuItems as $menuItem) {
    foreach ($menuItem['pages'] as $page) {
        if ($currentPage === $page['url']) {
            $active_pageInfo = [
                "breadcrumb_Items" => [
                    ["title" => $menuItem['menuTitle'], "url" => "#"],
                    ["title" => $page['title'], "url" => $page['url']]
                ],
                "page_title" => $page['title'],
                "active_menu" => $menuItem,
                "active_page" => $page
            ];
            break 2;
        }
    }
}

$breadcrumb_Items = $active_pageInfo['breadcrumb_Items'] ?? [];
$page_title = $active_pageInfo['page_title'] ?? '';
$active_menu = $active_pageInfo['active_menu'] ?? null;
$active_page = $active_pageInfo['active_page'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="icon" href="../favicon.ico" type="image/x-icon">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap"
        rel="stylesheet">

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
    <style>
        :root {
            --sidebar-bg: #ffffff;
            --sidebar-color: #2c3e50;
            --primary-green: #28a745;
            --accent-yellow: #ffc107;
            --border-color: #f0f0f1;
            --submenu-bg: #f6f7f7;
            --indicator-width: 4px;
        }

        /* Sidebar Container */
        .main-sidebar {
            background-color: var(--sidebar-bg) !important;
            border-right: 1px solid #dcdcde;
            box-shadow: none !important;
        }

        /* Brand Logo Area */
        .brand-link {
            background-color: var(--primary-green) !important;
            color: #fff !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
            padding: 15px !important;
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
            margin: 0 !important;
        }

        .brand-link .brand-image {
            float: none !important;
            line-height: .8;
            margin: 0 !important;
            max-height: 40px;
            width: auto;
        }

        /* User Panel */
        .user-panel {
            border-bottom: 1px solid var(--border-color) !important;
            margin: 0 !important;
            padding: 12px 15px !important;
        }

        .user-panel .info {
            color: var(--sidebar-color) !important;
            font-weight: 600;
            font-size: 0.85rem;
        }

        /* Navigation Items */
        .nav-sidebar > .nav-item {
            margin-bottom: 0 !important;
            border-bottom: 1px solid var(--border-color);
        }

        .nav-sidebar > .nav-item:first-child {
            border-top: 1px solid var(--border-color);
        }

        .nav-sidebar .nav-link {
            color: var(--sidebar-color) !important;
            padding: 12px 15px !important;
            border-radius: 0 !important;
            margin: 0 !important;
            position: relative;
            transition: background-color 0.1s ease-in-out;
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* WordPress Style Left Indicator */
        .nav-sidebar .nav-link.active::before,
        .nav-item.menu-open > .nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: var(--indicator-width);
            background-color: var(--primary-green);
        }

        /* Hover State */
        .nav-sidebar .nav-link:hover {
            background-color: var(--submenu-bg) !important;
            color: var(--primary-green) !important;
        }

        /* Active State */
        .nav-sidebar .nav-link.active {
            background-color: var(--submenu-bg) !important;
            color: var(--primary-green) !important;
            box-shadow: none !important;
        }

        .nav-sidebar .nav-link.active i {
            color: var(--primary-green) !important;
        }

        /* Icon Styling */
        .nav-sidebar .nav-icon {
            color: #8c8f94;
            margin-right: 10px !important;
            width: 20px;
            text-align: center;
            font-size: 0.95rem;
            transition: color 0.1s ease-in-out;
        }

        .nav-link:hover .nav-icon,
        .nav-link.active .nav-icon,
        .menu-open > .nav-link .nav-icon {
            color: var(--primary-green) !important;
        }

        /* Submenu (Treeview) Styling */
        .nav-treeview {
            background-color: var(--submenu-bg) !important;
            padding: 0 !important;
        }

        .nav-treeview > .nav-item > .nav-link {
            padding-left: 45px !important; /* Indent submenus like WP */
            font-size: 0.85rem;
            color: #50575e !important;
            border-bottom: none !important;
            border-top: none !important;
        }

        .nav-treeview > .nav-item > .nav-link:hover {
            color: var(--primary-green) !important;
            background-color: #fff !important;
        }

        .nav-treeview > .nav-item > .nav-link.active {
            color: var(--primary-green) !important;
            background-color: #fff !important;
            font-weight: 600;
        }

        /* Active highlight for submenu text in WP is often Yellow or lighter Green in our case */
        .nav-treeview > .nav-item > .nav-link.active::after {
            content: '';
            position: absolute;
            right: 0;
            top: 10px;
            bottom: 10px;
            width: 3px;
            background-color: var(--accent-yellow);
            border-radius: 2px 0 0 2px;
        }

        /* Expand/Collapse Toggle Icons (+/-) */
        .nav-sidebar .right {
            font-size: 0.8rem !important;
            top: 1.1rem !important;
            color: #8c8f94 !important;
            transition: all 0.2s ease-in-out;
            margin-top: -2px;
        }

        .nav-sidebar .right::before {
            content: "\f067"; /* plus */
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
        }

        .menu-open > .nav-link .right::before {
            content: "\f068"; /* minus */
        }

        .menu-open > .nav-link .right {
            transform: none !important; /* No rotation needed for +/- */
            color: var(--primary-green) !important;
        }

        /* Submenu Prefix Redesign */
        .submenu-icon {
            font-size: 0.6rem !important;
            opacity: 0.4;
            margin-right: 15px !important;
            width: auto !important;
        }

        .nav-link.active .submenu-icon {
            opacity: 1;
            color: var(--primary-green) !important;
        }

        /* Sidebar Mini behavior refinements */
        .sidebar-collapse .main-sidebar {
            width: 73px !important;
        }

        .sidebar-collapse .brand-link {
            padding: 10px !important;
            justify-content: center !important;
        }

        .sidebar-collapse .brand-link .brand-image {
            margin: 0 !important;
            max-height: 33px;
        }

        .sidebar-collapse .user-panel {
            padding: 12px 0 !important;
            display: flex !important;
            justify-content: center !important;
            border-bottom: 1px solid var(--border-color) !important;
        }

        .sidebar-collapse .user-panel a {
            display: flex !important;
            justify-content: center !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .sidebar-collapse .user-panel .image {
            padding: 0 !important;
            margin: 0 !important;
            display: flex !important;
            justify-content: center !important;
            width: 100% !important;
        }

        .sidebar-collapse .user-panel .info {
            display: none !important;
        }

        .sidebar-collapse .nav-sidebar .nav-link {
            padding: 12px 15px !important;
            display: flex !important;
            justify-content: center !important;
            width: 100% !important;
        }

        .sidebar-collapse .nav-sidebar .nav-icon {
            margin: 0 !important;
        }

        .sidebar-collapse .nav-sidebar .nav-link::before {
            display: none;
        }

        /* Ensure active indicator works in collapsed mode but is subtle */
        .sidebar-collapse .nav-sidebar .nav-link.active {
            border-left: 3px solid var(--primary-green);
        }

        /* WordPress-style Pop-out Labels on Hover */
        @media (min-width: 992px) {
            .sidebar-mini.sidebar-collapse .main-sidebar:not(.sidebar-no-expand):hover .nav-sidebar > .nav-item > .nav-link > p,
            .sidebar-mini.sidebar-collapse .main-sidebar:not(.sidebar-no-expand) .nav-sidebar > .nav-item > .nav-link > p {
                transition: none !important;
            }

            .sidebar-mini.sidebar-collapse .main-sidebar:not(.sidebar-no-expand) .nav-item:hover > .nav-link > p {
                display: block !important;
                position: absolute;
                left: 73px;
                top: 0;
                width: 200px;
                margin: 0 !important;
                padding: 12px 20px !important;
                background-color: #2c3338 !important; /* Dark WP style background */
                color: #fff !important;
                border-radius: 0 4px 4px 0;
                box-shadow: 2px 2px 10px rgba(0,0,0,0.1);
                z-index: 1000;
                pointer-events: none;
                font-weight: 500;
                font-size: 0.9rem;
            }

            /* Submenu hover refinement for collapsed mode */
            .sidebar-mini.sidebar-collapse .main-sidebar:not(.sidebar-no-expand) .nav-item:hover > .nav-treeview {
                display: block !important;
                position: absolute;
                left: 73px;
                top: 44px; /* Position below the main link p tag */
                width: 200px;
                background-color: var(--submenu-bg) !important;
                box-shadow: 2px 5px 10px rgba(0,0,0,0.1);
                border: 1px solid #dcdcde;
                border-left: none;
                z-index: 999;
            }

            .sidebar-mini.sidebar-collapse .main-sidebar:not(.sidebar-no-expand) .nav-item:hover > .nav-treeview .nav-link {
                padding-left: 20px !important;
                justify-content: flex-start !important;
            }
        }
    </style>
</head>

<body class="hold-transition sidebar-mini">
    <!-- Body started -->
    <div class="wrapper">
        <!-- Wrapper started -->

        <nav class="main-header navbar navbar-expand navbar-white navbar-light py-2">
            <!-- Left side: Hamburger Toggle & Quick Actions -->
            <ul class="navbar-nav align-items-center">
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
                </li>
                <!-- Quick Actions Indicator -->
                <li class="nav-item d-none d-md-inline-block ml-2">
                    <span class="badge bg-light text-secondary border px-3 py-2 font-weight-bold" style="font-size: 11px; border-radius: 50px; display: inline-flex; align-items: center; gap: 5px;">
                        <i class="fa-solid fa-bolt text-warning"></i> Quick Actions:
                    </span>
                </li>
                <!-- Quick Shortcuts -->
                <li class="nav-item ml-2">
                    <a href="index.php" class="btn btn-xs btn-outline-primary rounded-pill px-3 py-1 font-weight-bold d-inline-flex align-items-center" style="font-size: 11.5px; gap: 5px; transition: all 0.2s;">
                        <i class="fas fa-home"></i> <span class="d-none d-sm-inline">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item ml-2">
                    <a href="enroll-course.php" class="btn btn-xs btn-outline-info rounded-pill px-3 py-1 font-weight-bold d-inline-flex align-items-center" style="font-size: 11.5px; gap: 5px; transition: all 0.2s;">
                        <i class="fas fa-user-graduate"></i> <span class="d-none d-sm-inline">New Admission</span>
                    </a>
                </li>
                <li class="nav-item ml-2">
                    <a href="wallet.php" class="btn btn-xs btn-outline-success rounded-pill px-3 py-1 font-weight-bold d-inline-flex align-items-center" style="font-size: 11.5px; gap: 5px; transition: all 0.2s;">
                        <i class="fas fa-wallet"></i> <span class="d-none d-sm-inline">My Wallet</span>
                    </a>
                </li>
            </ul>
        </nav>



        <aside class="main-sidebar sidebar-light-primary elevation-4">
            <a href="./" class="brand-link" style="background-color: #ffffff !important; border-bottom: 1px solid #dcdcde !important; padding: 15px !important;">
                <img src="../assets/logo/logo.jpg" alt="MG Logo" class="brand-image" style="border-radius: 4px; object-fit: contain; max-height: 40px; box-shadow: none !important;" onerror="this.src='https://via.placeholder.com/150x40?text=MG+EDUCATION'">
            </a>
            <div class="sidebar">
                <div class="user-panel mt-3 pb-3 mb-3" style="border-bottom: 1px solid var(--border-color) !important; margin: 0 !important; padding: 12px 15px !important;">
                    <a href="./profile.php" class="d-flex align-items-center">
                        <div class="image">
                            <?php 
                                $sidebarPhoto = '../' . htmlspecialchars($center['owner_image'] ?? '');
                                if (empty($center['owner_image']) || !file_exists(dirname(__DIR__) . '/' . $center['owner_image'])) {
                                    $sidebarPhoto = './src/images/user-avtar.png';
                                }
                            ?>
                            <img src="<?= $sidebarPhoto ?>" class="img-circle elevation-2 bg-white" alt="User Image" style="width: 2.1rem; height: 2.1rem; object-fit: cover;">
                        </div>
                        <div class="info" style="color: var(--sidebar-color) !important; font-weight: 600; font-size: 0.85rem; padding-left: 10px; white-space: normal !important; word-wrap: break-word !important; overflow: visible !important;">
                            <span class="d-block text-dark font-weight-bold" style="font-size: 13px; line-height: 1.2;"><?= htmlspecialchars($center_name) ?></span>
                            <small class="text-secondary font-weight-bold" style="font-size: 11px; font-family: monospace; display: block; margin-top: 2px;"><?= htmlspecialchars($center_id) ?></small>
                        </div>
                    </a>
                </div>
                <nav class="mt-2">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                        <?php foreach ($menuItems as $menuItem): ?>
                            <li class="nav-item has-treeview <?= $menuItem === $active_menu ? 'menu-open' : '' ?>">
                                <a class="nav-link <?= $menuItem === $active_menu ? 'active' : '' ?>" href="#">
                                    <i class="nav-icon <?= $menuItem['icon'] ?>"></i>
                                    <p>
                                        <?= $menuItem['menuTitle'] ?>
                                        <?= !empty($menuItem['pages']) ? '<i class="right fas toggle-icon"></i>' : '' ?>
                                    </p>
                                </a>
                                <?php if (!empty($menuItem['pages'])): ?>
                                    <ul class="nav nav-treeview">
                                        <?php foreach ($menuItem['pages'] as $page): ?>
                                            <li class="nav-item">
                                                <a href="<?= $page['url'] ?>"
                                                    class="nav-link <?= $page === $active_page ? 'active' : '' ?>">
                                                    <i class="fas fa-minus nav-icon submenu-icon"></i>
                                                    <p><?= $page['title'] ?></p>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                        <li class="nav-item" onclick="logout()">
                            <a href="javascript:void(0);" class="nav-link">
                                <i class="nav-icon fas fa-sign-out-alt"></i>
                                <p>Logout</p>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </aside>

        <div class="content-wrapper pt-4">
            <!-- Content-wrapper started -->
            <section class="content">
                <!-- Content section started -->
                <div class="container-fluid">
                    <!-- Container-fluid started -->