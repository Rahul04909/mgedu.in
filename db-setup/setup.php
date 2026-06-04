<?php
/**
 * MG Education & Social Development Organization
 * Database Setup & Migration Wizard
 * Performs zero-configuration initialization of database schema.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Locate Autoloader & Dotenv
$root = dirname(__DIR__);
$autoloaderPath = $root . '/vendor/autoload.php';

if (file_exists($autoloaderPath)) {
    require_once $autoloaderPath;
} else {
    die("Composer autoloader not found at '{$autoloaderPath}'. Please run 'composer install' in the project root.");
}

// Load Environment Variables manually
try {
    $dotenv = Dotenv\Dotenv::createImmutable($root);
    $dotenv->safeLoad();
} catch (Exception $e) {
    // If safeLoad fails, we fallback to default env
}

$db_host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$db_port = $_ENV['DB_PORT'] ?? '3306';
$db_name = $_ENV['DB_NAME'] ?? 'mgedu_in';
$db_user = $_ENV['DB_USER'] ?? 'root';
$db_pass = $_ENV['DB_PASS'] ?? '';

$logs = [];
$success = false;

if (isset($_POST['run_setup'])) {
    try {
        $logs[] = ["status" => "info", "message" => "Connecting to MySQL server at {$db_host}:{$db_port}..."];
        
        // 1. Connect without dbname to ensure we can create it
        $dsn = "mysql:host={$db_host};port={$db_port};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        
        $pdo = new PDO($dsn, $db_user, $db_pass, $options);
        $logs[] = ["status" => "success", "message" => "Successfully connected to MySQL server."];

        // 2. Create database if not exists
        $logs[] = ["status" => "info", "message" => "Checking/Creating database `{$db_name}`..."];
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $logs[] = ["status" => "success", "message" => "Database `{$db_name}` verified/created."];

        // 3. Switch to the target database
        $pdo->exec("USE `{$db_name}`");
        $logs[] = ["status" => "info", "message" => "Switched context to database `{$db_name}`."];

        // 4. Create admins table
        $logs[] = ["status" => "info", "message" => "Creating `admins` table..."];
        $adminsTableSql = "
            CREATE TABLE IF NOT EXISTS `admins` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(50) UNIQUE NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `email` VARCHAR(100) UNIQUE NOT NULL,
                `mobile` VARCHAR(20) NOT NULL,
                `profile_image` VARCHAR(255) DEFAULT NULL,
                `password` VARCHAR(255) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $pdo->exec($adminsTableSql);
        $logs[] = ["status" => "success", "message" => "`admins` table verified/created."];

        // 5. Create login_attempts table (for brute-force rate-limiting)
        $logs[] = ["status" => "info", "message" => "Creating `login_attempts` table for security..."];
        $attemptsTableSql = "
            CREATE TABLE IF NOT EXISTS `login_attempts` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `ip_address` VARCHAR(45) NOT NULL,
                `attempt_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $pdo->exec($attemptsTableSql);
        $logs[] = ["status" => "success", "message" => "`login_attempts` table verified/created."];

        // 5b. Create course_categories table
        $logs[] = ["status" => "info", "message" => "Creating `course_categories` table..."];
        $categoriesTableSql = "
            CREATE TABLE IF NOT EXISTS `course_categories` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(255) UNIQUE NOT NULL,
                `description` TEXT DEFAULT NULL,
                `meta_title` VARCHAR(255) DEFAULT NULL,
                `meta_description` TEXT DEFAULT NULL,
                `meta_keywords` TEXT DEFAULT NULL,
                `seo_schema` TEXT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $pdo->exec($categoriesTableSql);
        $logs[] = ["status" => "success", "message" => "`course_categories` table verified/created."];

        // 5c. Create courses table
        $logs[] = ["status" => "info", "message" => "Creating `courses` table..."];
        $coursesTableSql = "
            CREATE TABLE IF NOT EXISTS `courses` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `category_id` INT NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(255) UNIQUE NOT NULL,
                `description` TEXT DEFAULT NULL,
                `duration` INT NOT NULL,
                `duration_unit` VARCHAR(20) NOT NULL,
                `mode` VARCHAR(20) NOT NULL,
                `mrp` DECIMAL(10,2) NOT NULL,
                `sales_price` DECIMAL(10,2) NOT NULL,
                `course_image` VARCHAR(255) DEFAULT NULL,
                `gallery_images` TEXT DEFAULT NULL,
                `brochure_enabled` TINYINT(1) DEFAULT 0,
                `brochure_pdf` VARCHAR(255) DEFAULT NULL,
                `meta_title` VARCHAR(255) DEFAULT NULL,
                `meta_description` TEXT DEFAULT NULL,
                `meta_keywords` TEXT DEFAULT NULL,
                `featured_image` VARCHAR(255) DEFAULT NULL,
                `og_info` TEXT DEFAULT NULL,
                `ratings_count` INT DEFAULT 0,
                `ratings_sum` DECIMAL(10,2) DEFAULT 0.00,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`category_id`) REFERENCES `course_categories`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $pdo->exec($coursesTableSql);
        $logs[] = ["status" => "success", "message" => "`courses` table verified/created."];

        // 5d. Create internship_categories table
        $logs[] = ["status" => "info", "message" => "Creating `internship_categories` table..."];
        $internshipCategoriesSql = "
            CREATE TABLE IF NOT EXISTS `internship_categories` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(255) UNIQUE NOT NULL,
                `description` TEXT DEFAULT NULL,
                `meta_title` VARCHAR(255) DEFAULT NULL,
                `meta_description` TEXT DEFAULT NULL,
                `meta_keywords` TEXT DEFAULT NULL,
                `seo_schema` TEXT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $pdo->exec($internshipCategoriesSql);
        $logs[] = ["status" => "success", "message" => "`internship_categories` table verified/created."];

        // 5e. Create internships table
        $logs[] = ["status" => "info", "message" => "Creating `internships` table..."];
        $internshipsTableSql = "
            CREATE TABLE IF NOT EXISTS `internships` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `category_id` INT NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(255) UNIQUE NOT NULL,
                `description` TEXT DEFAULT NULL,
                `duration` INT NOT NULL,
                `duration_unit` VARCHAR(20) NOT NULL,
                `mode` VARCHAR(20) NOT NULL,
                `mrp` DECIMAL(10,2) NOT NULL,
                `sales_price` DECIMAL(10,2) NOT NULL,
                `internship_image` VARCHAR(255) DEFAULT NULL,
                `gallery_images` TEXT DEFAULT NULL,
                `brochure_enabled` TINYINT(1) DEFAULT 0,
                `brochure_pdf` VARCHAR(255) DEFAULT NULL,
                `meta_title` VARCHAR(255) DEFAULT NULL,
                `meta_description` TEXT DEFAULT NULL,
                `meta_keywords` TEXT DEFAULT NULL,
                `featured_image` VARCHAR(255) DEFAULT NULL,
                `og_info` TEXT DEFAULT NULL,
                `ratings_count` INT DEFAULT 0,
                `ratings_sum` DECIMAL(10,2) DEFAULT 0.00,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (`category_id`) REFERENCES `internship_categories`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $pdo->exec($internshipsTableSql);
        $logs[] = ["status" => "success", "message" => "`internships` table verified/created."];


        // 6. Check and Seed default admin
        $logs[] = ["status" => "info", "message" => "Checking for existing administrator accounts..."];
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM `admins` WHERE `username` = :username OR `email` = :email");
        $stmt->execute(['username' => 'admin', 'email' => 'admin@mgedu.in']);
        $adminExists = $stmt->fetch()['count'] > 0;

        if (!$adminExists) {
            $logs[] = ["status" => "info", "message" => "No default admin found. Injecting default administrator..."];
            $defaultPassword = 'Admin@MGedu2026';
            $hashedPassword = password_hash($defaultPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            
            $insertStmt = $pdo->prepare("
                INSERT INTO `admins` (`username`, `name`, `email`, `mobile`, `profile_image`, `password`)
                VALUES (:username, :name, :email, :mobile, :profile_image, :password)
            ");
            
            $insertStmt->execute([
                'username' => 'admin',
                'name' => 'MG Admin',
                'email' => 'admin@mgedu.in',
                'mobile' => '8059982049',
                'profile_image' => 'assets/images/admin_profile.png',
                'password' => $hashedPassword
            ]);
            
            $logs[] = ["status" => "success", "message" => "Default admin created successfully! Credentials: admin / {$defaultPassword}"];
        } else {
            $logs[] = ["status" => "warning", "message" => "Default admin account already exists. Skipping insertion to preserve current credentials."];
        }

        $logs[] = ["status" => "success", "message" => "=== SETUP COMPLETED SUCCESSFULLY ==="];
        $success = true;

    } catch (PDOException $e) {
        $logs[] = ["status" => "danger", "message" => "Database Setup Error: " . $e->getMessage()];
    } catch (Exception $e) {
        $logs[] = ["status" => "danger", "message" => "Unexpected Error: " . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup Wizard - MG Education</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #28a745;
            --primary-hover: #218838;
            --bg-color: #f8f9fa;
            --card-bg: #ffffff;
            --text-color: #2c3e50;
            --border-color: #d1d7dc;
            --success-color: #28a745;
            --info-color: #17a2b8;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            box-sizing: border-box;
        }

        .setup-container {
            width: 100%;
            max-width: 650px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .setup-header {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            padding: 30px 40px;
            color: #ffffff;
            text-align: center;
            position: relative;
        }

        .setup-header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .setup-header p {
            margin: 10px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }

        .setup-content {
            padding: 40px;
        }

        .config-box {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .config-box h3 {
            margin: 0 0 15px 0;
            font-size: 16px;
            font-weight: 600;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 8px;
        }

        .config-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            font-size: 13px;
        }

        .config-item span:first-child {
            font-weight: 500;
            color: #6c757d;
            display: block;
            margin-bottom: 2px;
        }

        .config-item span:last-child {
            font-weight: 600;
            color: #212529;
        }

        .logs-container {
            background-color: #1e1e1e;
            color: #f1f1f1;
            border-radius: 8px;
            padding: 20px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            max-height: 250px;
            overflow-y: auto;
            margin-bottom: 25px;
            border: 1px solid #333;
        }

        .log-entry {
            margin-bottom: 8px;
            line-height: 1.4;
            display: flex;
            align-items: flex-start;
        }

        .log-entry i {
            margin-right: 8px;
            margin-top: 3px;
        }

        .log-info { color: #5bc0de; }
        .log-success { color: #5cb85c; }
        .log-warning { color: #f0ad4e; }
        .log-danger { color: #d9534f; }

        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            background-color: var(--primary-color);
            color: #ffffff;
            border: none;
            padding: 14px 28px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2);
            text-decoration: none;
        }

        .btn-action:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(40, 167, 69, 0.3);
        }

        .btn-action i {
            margin-right: 8px;
        }

        .btn-secondary-action {
            background-color: #4a5568;
            box-shadow: 0 4px 12px rgba(74, 85, 104, 0.2);
        }

        .btn-secondary-action:hover {
            background-color: #2d3748;
            box-shadow: 0 6px 16px rgba(74, 85, 104, 0.3);
        }

        .success-badge {
            background-color: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.2);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin-bottom: 25px;
        }

        .success-badge i {
            font-size: 40px;
            color: var(--success-color);
            margin-bottom: 12px;
        }

        .success-badge h4 {
            margin: 0 0 5px 0;
            font-size: 18px;
            color: var(--success-color);
            font-weight: 700;
        }

        .success-badge p {
            margin: 0;
            font-size: 13px;
            color: #555;
        }

        /* Webkit scrollbar for logs */
        .logs-container::-webkit-scrollbar {
            width: 8px;
        }
        .logs-container::-webkit-scrollbar-track {
            background: #1e1e1e;
        }
        .logs-container::-webkit-scrollbar-thumb {
            background: #444;
            border-radius: 4px;
        }
        .logs-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body>

<div class="setup-container">
    <div class="setup-header">
        <h1>MG Education Database Setup</h1>
        <p>Schema Migrations & Security Initialization Wizard</p>
    </div>
    
    <div class="setup-content">
        <div class="config-box">
            <h3><i class="fa-solid fa-server"></i> Targeted Connection Details</h3>
            <div class="config-grid">
                <div class="config-item">
                    <span>Host Address</span>
                    <span><?= htmlspecialchars($db_host) ?></span>
                </div>
                <div class="config-item">
                    <span>Port</span>
                    <span><?= htmlspecialchars($db_port) ?></span>
                </div>
                <div class="config-item">
                    <span>Target Database</span>
                    <span><?= htmlspecialchars($db_name) ?></span>
                </div>
                <div class="config-item">
                    <span>Database Username</span>
                    <span><?= htmlspecialchars($db_user) ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($logs)): ?>
            <div class="logs-container">
                <?php foreach ($logs as $log): ?>
                    <div class="log-entry log-<?= $log['status'] ?>">
                        <?php if ($log['status'] === 'success'): ?>
                            <i class="fa-solid fa-circle-check"></i>
                        <?php elseif ($log['status'] === 'warning'): ?>
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        <?php elseif ($log['status'] === 'danger'): ?>
                            <i class="fa-solid fa-circle-xmark"></i>
                        <?php else: ?>
                            <i class="fa-solid fa-circle-info"></i>
                        <?php endif; ?>
                        <span><?= htmlspecialchars($log['message']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-badge">
                <i class="fa-solid fa-circle-check animate__animated animate__zoomIn"></i>
                <h4>Setup Completed Successfully!</h4>
                <p>Database schema initialized. Default admin seeded safely.</p>
            </div>
            <a href="../admin/login.php" class="btn-action btn-secondary-action">
                <i class="fa-solid fa-right-to-bracket"></i> Go to Admin Login Portal
            </a>
        <?php else: ?>
            <form method="post" action="">
                <button type="submit" name="run_setup" class="btn-action">
                    <i class="fa-solid fa-play"></i> Initialize Database & Create Schema
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
