<?php
require_once __DIR__ . '/auth_check.php';
require_once dirname(__DIR__) . '/includes/config.php';

// Dynamically compute Admin Base and Project Base paths to support sub-folders seamlessly
$script_name = $_SERVER['SCRIPT_NAME'];
$admin_pos = strpos($script_name, '/admin/');
if ($admin_pos !== false) {
    $project_base = substr($script_name, 0, $admin_pos) . '/';
    $admin_base = substr($script_name, 0, $admin_pos + 7) . '/';
    $currentPage = substr($script_name, $admin_pos + 7);
} else {
    $project_base = '/';
    $admin_base = '/admin/';
    $currentPage = basename($script_name);
}

$menuItems = [
    [
        "menuTitle" => "Dashboard",
        "icon" => "fas fa-home",
        "pages" => [
            ["title" => "Home", "url" => "index.php"]
        ],
    ],
    [
        "menuTitle" => "Sessions",
        "icon" => "fas fa-calendar-alt",
        "pages" => [
            ["title" => "Manage Sessions", "url" => "sessions.php"]
        ]
    ],
    [
        "menuTitle" => "Course Categories",
        "icon" => "fas fa-tags",
        "pages" => [
            ["title" => "Manage Categories", "url" => "course-categories/index.php"],
            ["title" => "Add Category", "url" => "course-categories/add.php"]
        ]
    ],
    [
        "menuTitle" => "Courses",
        "icon" => "fas fa-graduation-cap",
        "pages" => [
            ["title" => "Manage Courses", "url" => "courses/index.php"],
            ["title" => "Add Course", "url" => "courses/add.php"],
            ["title" => "Enroll Candidate", "url" => "courses/enroll.php"],
            ["title" => "Student Admissions", "url" => "courses/admissions.php"]
        ]
    ],
    [
        "menuTitle" => "Internship Categories",
        "icon" => "fas fa-tags",
        "pages" => [
            ["title" => "Manage Categories", "url" => "internship-categories/index.php"],
            ["title" => "Add Category", "url" => "internship-categories/add.php"]
        ]
    ],
    [
        "menuTitle" => "Internships",
        "icon" => "fas fa-laptop-code",
        "pages" => [
            ["title" => "Manage Internships", "url" => "internships/index.php"],
            ["title" => "Add Internship", "url" => "internships/add.php"],
            ["title" => "Enroll Candidate", "url" => "internships/enroll.php"],
            ["title" => "Internship Admissions", "url" => "internships/admissions.php"]
        ]
    ],
    [
        "menuTitle" => "Franchise Centers",
        "icon" => "fas fa-building",
        "pages" => [
            ["title" => "Manage Centers", "url" => "franchise/index.php"],
            ["title" => "Add Center", "url" => "franchise/add.php"]
        ]
    ],
    [
        "menuTitle" => "Fees Management",
        "icon" => "fas fa-wallet",
        "pages" => [
            ["title" => "Global Fees Ledger", "url" => "courses/fees.php"]
        ]
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
                    ["title" => $page['title'], "url" => $admin_base . $page['url']]
                ],
                "page_title" => $page['title'],
                "active_menu" => $menuItem,
                "active_page" => $page
            ];
            break 2;
        }
    }
}

// Fallback for edit/sub-pages matching the folder prefix
if (!$active_pageInfo) {
    foreach ($menuItems as $menuItem) {
        foreach ($menuItem['pages'] as $page) {
            $pageDir = dirname($page['url']);
            $currentDir = dirname($currentPage);
            if ($pageDir !== '.' && $pageDir === $currentDir) {
                $isEdit = (strpos(basename($currentPage), 'edit') !== false);
                $isView = (strpos(basename($currentPage), 'view') !== false);
                if ($isEdit) {
                    $subTitle = 'Edit ' . str_replace('Add ', '', $page['title']);
                } elseif ($isView) {
                    $subTitle = 'View Profile';
                } else {
                    $subTitle = basename($currentPage);
                }
                $active_pageInfo = [
                    "breadcrumb_Items" => [
                        ["title" => $menuItem['menuTitle'], "url" => "#"],
                        ["title" => $subTitle, "url" => $admin_base . $currentPage]
                    ],
                    "page_title" => $subTitle,
                    "active_menu" => $menuItem,
                    "active_page" => $page
                ];
                break 2;
            }
        }
    }
}

$breadcrumb_Items = $active_pageInfo['breadcrumb_Items'] ?? [];
$page_title = $active_pageInfo['page_title'] ?? 'MG Admin Panel';
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
    
    <!-- Summernote Lite Editor CDN -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    
    <!-- Trumbowyg Editor CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Trumbowyg/2.27.3/ui/trumbowyg.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Trumbowyg/2.27.3/trumbowyg.min.js"></script>
    
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
            background-color: #ffffff !important;
            color: var(--sidebar-color) !important;
            border-bottom: 1px solid #dcdcde !important;
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

            /* Professional Custom Select Dropdowns */
            select.form-control, select.select-premium {
                appearance: none !important;
                -webkit-appearance: none !important;
                -moz-appearance: none !important;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232c3e50' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") !important;
                background-repeat: no-repeat !important;
                background-position: right 14px center !important;
                background-size: 16px !important;
                padding: 8px 40px 8px 14px !important; /* Explicit vertical and horizontal padding to prevent text clipping */
                cursor: pointer !important;
                background-color: #ffffff !important;
                border: 1px solid #d1d7dc !important;
                border-radius: 8px !important;
                font-size: 14px !important;
                color: #2c3e50 !important;
                height: 42px !important; /* Forces exact height to prevent Chromium height calculation clipping */
                min-height: 42px !important; /* Aligns perfectly with neighboring inputs */
                line-height: 1.5 !important;
                transition: border-color 0.2s ease, box-shadow 0.2s ease !important;
            }

            select.form-control:focus, select.select-premium:focus {
                border-color: #28a745 !important;
                box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.15) !important;
                outline: none !important;
            }

            /* For small select dropdowns (like filters) */
            select.select-premium {
                border-radius: 50px !important;
                padding-left: 18px !important;
                padding-right: 36px !important;
                background-position: right 12px center !important;
                height: 38px !important;
            }

            /* Professional Summernote Styling */
            .note-editor.note-lite {
                border: 1px solid #cbd5e1 !important;
                border-radius: 10px !important;
                overflow: hidden !important;
                background-color: #ffffff !important;
                box-shadow: 0 4px 6px rgba(0,0,0,0.01) !important;
                font-family: 'Inter', sans-serif !important;
            }

            .note-editor.note-lite.focus {
                border-color: #28a745 !important;
                box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.15) !important;
            }

            .note-toolbar {
                background-color: #f8fafc !important;
                border-bottom: 1px solid #e2e8f0 !important;
                padding: 8px 12px !important;
            }

            .note-btn {
                background-color: #ffffff !important;
                border: 1px solid #e2e8f0 !important;
                color: #475569 !important;
                border-radius: 6px !important;
                padding: 5px 8px !important;
                font-size: 13px !important;
                transition: all 0.2s ease !important;
                box-shadow: none !important;
            }

            .note-btn:hover, .note-btn.active {
                background-color: #f1f5f9 !important;
                color: #28a745 !important;
                border-color: #28a745 !important;
            }

            .note-statusbar {
                background-color: #f8fafc !important;
                border-top: 1px solid #e2e8f0 !important;
            }

            .note-editable {
                font-size: 14px !important;
                line-height: 1.6 !important;
                color: #334155 !important;
                background-color: #ffffff !important;
                padding: 18px !important;
                min-height: 180px !important;
            }

            /* Professional Fullscreen Fix for Summernote Lite */
            .note-editor.note-lite.fullscreen {
                background-color: #ffffff !important;
                z-index: 9999 !important; /* Forces editor to float cleanly over AdminLTE sidebars/navbars */
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                height: 100% !important;
            }

            .note-editor.note-lite.fullscreen .note-editable {
                height: calc(100vh - 70px) !important; /* Dynamic sizing to fill the whole screen space */
                overflow-y: auto !important;
            }
    </style>
</head>

<body class="hold-transition sidebar-mini">
    <!-- Body started -->
    <div class="wrapper">
        <!-- Wrapper started -->

        <nav class="main-header navbar navbar-expand navbar-white navbar-light">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <div class="nav-link">
                        <i class="fas fa-th-large"></i>
                    </div>
                </li>
                <li class="nav-item d-none d-sm-inline-block">
                    <a href="<?= $admin_base ?>" class="nav-link">Home</a>
                </li>
            </ul>
            <form class="form-inline ml-3">
                <div class="input-group input-group-sm">
                    <input class="form-control form-control-navbar" type="search" placeholder="Search" name="search">
                    <div class="input-group-append">
                        <button class="btn btn-navbar" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </form>
            <ul class="navbar-nav ml-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link" href="#messages">
                        <i class="far fa-comments"></i>
                        <span class="badge badge-danger navbar-badge">2</span>
                    </a>
                </li>
                <li class="nav-item dropdown"><a class="nav-link" href="#notifications">
                        <i class="far fa-bell"></i>
                        <span class="badge badge-warning navbar-badge">5</span>
                    </a>
                </li>
            </ul>
        </nav>



        <aside class="main-sidebar sidebar-light-primary elevation-4">
            <a href="<?= $admin_base ?>" class="brand-link">
                <img src="<?= $project_base ?>assets/logo/logo.jpg" alt="MG Logo" class="brand-image" style="border-radius: 4px; object-fit: contain; max-height: 40px; box-shadow: none !important;" onerror="this.src='https://via.placeholder.com/150x40?text=MG+EDUCATION'">
            </a>
            <div class="sidebar">
                <?php
                $admin_name = htmlspecialchars($_SESSION['admin_name'] ?? 'Administrator');
                $admin_profile_raw = $_SESSION['admin_profile_image'] ?? '';
                $admin_profile_image = $admin_base . 'src/images/user-avtar.png'; // default fallback
                if (!empty($admin_profile_raw)) {
                    $admin_profile_image = $project_base . $admin_profile_raw;
                }
                ?>
                <div class="user-panel mt-3 pb-3 mb-3">
                    <a href="<?= $admin_base ?>profile.php" class="d-flex">
                        <div class="image">
                            <img src="<?= $admin_profile_image ?>" class="img-circle elevation-2 bg-white" alt="User Image" style="object-fit: cover; width: 33px; height: 33px;">
                        </div>
                        <div class="info">
                            <?= $admin_name ?>
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
                                                <a href="<?= $admin_base . $page['url'] ?>"
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

        <div class="content-wrapper">
            <!-- Content-wrapper started -->
            <section class="content">
                <!-- Content section started -->
                <div class="container-fluid">
                    <!-- Container-fluid started -->