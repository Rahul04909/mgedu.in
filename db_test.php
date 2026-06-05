<?php
/**
 * MG Education - Database Connection Diagnostic Tool
 * A high-end visual diagnostic script to troubleshoot database connectivity.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Locate Autoloader & Dotenv
$root = __DIR__;
$autoloaderPath = $root . '/vendor/autoload.php';

$composer_loaded = false;
$dotenv_loaded = false;
$pdo_connected = false;
$socket_connected = false;

$error_msg = '';
$socket_error_msg = '';
$pdo_error_msg = '';

$env_exists = file_exists($root . '/.env');

if (file_exists($autoloaderPath)) {
    require_once $autoloaderPath;
    $composer_loaded = true;
    
    try {
        $dotenv = Dotenv\Dotenv::createImmutable($root);
        $dotenv->safeLoad();
        $dotenv_loaded = true;
    } catch (Exception $e) {
        $error_msg = "Dotenv load error: " . $e->getMessage();
    }
} else {
    $error_msg = "Composer autoloader not found at '{$autoloaderPath}'. Please run 'composer install'.";
}

$db_host = $_ENV['DB_HOST'] ?? null;
$db_port = $_ENV['DB_PORT'] ?? null;
$db_name = $_ENV['DB_NAME'] ?? null;
$db_user = $_ENV['DB_USER'] ?? null;
$db_pass = $_ENV['DB_PASS'] ?? null;

// Check server variables as well, in case $_ENV isn't populated but $_SERVER is
if (empty($db_host)) {
    $db_host = $_SERVER['DB_HOST'] ?? null;
    $db_port = $_SERVER['DB_PORT'] ?? null;
    $db_name = $_SERVER['DB_NAME'] ?? null;
    $db_user = $_SERVER['DB_USER'] ?? null;
    $db_pass = $_SERVER['DB_PASS'] ?? null;
}

// Try to open socket connection to host & port
if ($db_host && $db_port) {
    $timeout = 3; // seconds
    $fp = @fsockopen($db_host, intval($db_port), $errno, $errstr, $timeout);
    if ($fp) {
        $socket_connected = true;
        fclose($fp);
    } else {
        $socket_error_msg = "Connection to {$db_host}:{$db_port} failed: [{$errno}] {$errstr}";
    }
}

// Try PDO connection
if ($dotenv_loaded && $db_host && $db_name && $db_user) {
    try {
        $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 3,
        ];
        $pdo = new PDO($dsn, $db_user, $db_pass, $options);
        $pdo_connected = true;
    } catch (PDOException $e) {
        $pdo_error_msg = $e->getMessage();
    }
} else {
    $pdo_error_msg = "Missing environment configuration variables.";
}

// Format password for safe viewing
$masked_pass = '';
if ($db_pass !== null) {
    $len = strlen($db_pass);
    if ($len > 4) {
        $masked_pass = substr($db_pass, 0, 2) . str_repeat('*', $len - 4) . substr($db_pass, -2);
    } else {
        $masked_pass = str_repeat('*', $len);
    }
}

// Recommendations
$recommendations = [];
if (!$env_exists) {
    $recommendations[] = "Create a <code>.env</code> file in the project root directory. Use <code>.env.example</code> as a template.";
}
if (!$composer_loaded) {
    $recommendations[] = "Run <code>composer install</code> in the project root to install dependencies, including <code>vlucas/phpdotenv</code>.";
}
if (!empty($pdo_error_msg)) {
    if (strpos($pdo_error_msg, 'Connection refused') !== false || strpos($pdo_error_msg, 'HY000/2002') !== false || strpos($pdo_error_msg, 'timed out') !== false) {
        $recommendations[] = "<strong>Check DB_HOST:</strong> The host <code>{$db_host}</code> refused or timed out the connection on port <code>{$db_port}</code>. If this is a cPanel shared hosting server, try changing <code>DB_HOST=127.0.0.1</code> to <code>DB_HOST=localhost</code> in your <code>.env</code> file. Many shared servers restrict TCP connections to localhost and require unix socket connections via <code>localhost</code>.";
    } elseif (strpos($pdo_error_msg, 'Access denied') !== false) {
        $recommendations[] = "<strong>Verify Credentials & Permissions:</strong> Access was denied for user <code>{$db_user}</code>. Please double-check:
        <ul style='margin-top: 5px; margin-bottom: 5px; padding-left: 20px; line-height: 1.6;'>
            <li>The database username and password in your <code>.env</code> are exactly correct. Note: cPanel database usernames usually have a prefix like <code>jghfrodu_</code>.</li>
            <li>In cPanel -> MySQL Databases, ensure you have explicitly <strong>added the user</strong> <code>{$db_user}</code> to the database <code>{$db_name}</code> and selected the checkbox for <strong>ALL PRIVILEGES</strong>.</li>
        </ul>";
    } elseif (strpos($pdo_error_msg, 'Unknown database') !== false) {
        $recommendations[] = "<strong>Database Missing:</strong> The database <code>{$db_name}</code> was not found. Verify that the database exists on the server, or run the <a href='db-setup/setup.php' style='color:#60a5fa; text-decoration:underline;'>Database Setup Wizard</a> to create and initialize it.";
    } else {
        $recommendations[] = "Verify that your MySQL service is running and configured to accept connections from the PHP environment.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Diagnostic Hub — MG Education</title>
    <!-- Google Fonts & Tailwind-like CSS with rich aesthetics -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            --panel-bg: rgba(30, 41, 59, 0.7);
            --border-color: rgba(255, 255, 255, 0.08);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --accent-success: #10b981;
            --accent-error: #ef4444;
            --accent-warning: #f59e0b;
            --accent-info: #3b82f6;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-gradient);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .hub-container {
            width: 100%;
            max-width: 800px;
            background: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            overflow: hidden;
        }

        .hub-header {
            padding: 40px 40px 30px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-title h1 {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(to right, #60a5fa, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header-title p {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .badge-status {
            padding: 8px 16px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-transform: uppercase;
        }

        .status-success {
            background: rgba(16, 185, 129, 0.15);
            color: var(--accent-success);
            border: 1px solid rgba(16, 185, 129, 0.25);
        }

        .status-failed {
            background: rgba(239, 68, 68, 0.15);
            color: var(--accent-error);
            border: 1px solid rgba(239, 68, 68, 0.25);
        }

        .hub-content {
            padding: 40px;
        }

        .grid-status {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .status-card {
            background: rgba(15, 23, 42, 0.4);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }

        .card-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .icon-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--accent-success);
        }

        .icon-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--accent-error);
        }

        .card-details h4 {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .card-details p {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 4px;
            line-height: 1.4;
        }

        .section-title {
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #60a5fa;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .details-panel {
            background: rgba(15, 23, 42, 0.3);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 40px;
        }

        .config-list {
            list-style: none;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px 30px;
        }

        .config-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13.5px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            padding-bottom: 10px;
        }

        .config-row span:first-child {
            color: var(--text-secondary);
        }

        .config-row span:last-child {
            font-family: monospace;
            font-weight: 600;
            color: var(--text-primary);
        }

        .error-log-box {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 40px;
            color: #fca5a5;
        }

        .error-log-box h3 {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 10px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .error-log-box code {
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            word-break: break-all;
            background: rgba(0, 0, 0, 0.2);
            padding: 12px;
            border-radius: 8px;
            display: block;
            margin-top: 10px;
            line-height: 1.5;
            border: 1px solid rgba(239, 68, 68, 0.1);
        }

        .recommendations-panel {
            background: rgba(59, 130, 246, 0.07);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 16px;
            padding: 24px;
        }

        .recommendations-panel ul {
            list-style-type: none;
        }

        .recommendations-panel li {
            position: relative;
            padding-left: 28px;
            font-size: 13.5px;
            line-height: 1.6;
            margin-bottom: 12px;
            color: #bfdbfe;
        }

        .recommendations-panel li::before {
            content: "\f05a";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            left: 0;
            top: 2px;
            color: #60a5fa;
            font-size: 14px;
        }

        .recommendations-panel li:last-child {
            margin-bottom: 0;
        }

        .footer-note {
            text-align: center;
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.04);
        }
        
        .footer-note a {
            color: #60a5fa;
            text-decoration: none;
        }
        .footer-note a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="hub-container">
    <div class="hub-header">
        <div class="header-title">
            <h1>Database Diagnostic Hub</h1>
            <p>MG Education System Health & Connectivity Checker</p>
        </div>
        <div>
            <?php if ($pdo_connected): ?>
                <span class="badge-status status-success">
                    <i class="fa-solid fa-circle-check"></i> Connected
                </span>
            <?php else: ?>
                <span class="badge-status status-failed">
                    <i class="fa-solid fa-triangle-exclamation"></i> Error
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="hub-content">
        <!-- Status Grid -->
        <div class="grid-status">
            <!-- Env File Status -->
            <div class="status-card">
                <div class="card-icon <?= $env_exists ? 'icon-success' : 'icon-error' ?>">
                    <i class="fa-solid <?= $env_exists ? 'fa-file-code' : 'fa-file-circle-xmark' ?>"></i>
                </div>
                <div class="card-details">
                    <h4>Environment File</h4>
                    <p><?= $env_exists ? '<code>.env</code> file located and loaded.' : '<code>.env</code> file missing in root.' ?></p>
                </div>
            </div>

            <!-- Socket Status -->
            <div class="status-card">
                <div class="card-icon <?= $socket_connected ? 'icon-success' : 'icon-error' ?>">
                    <i class="fa-solid <?= $socket_connected ? 'fa-network-wired' : 'fa-circle-xmark' ?>"></i>
                </div>
                <div class="card-details">
                    <h4>Server Port Connection</h4>
                    <p><?= $socket_connected ? "Host port {$db_host}:{$db_port} is open." : "Unable to reach port {$db_host}:{$db_port}." ?></p>
                </div>
            </div>

            <!-- PDO Status -->
            <div class="status-card">
                <div class="card-icon <?= $pdo_connected ? 'icon-success' : 'icon-error' ?>">
                    <i class="fa-solid <?= $pdo_connected ? 'fa-database' : 'fa-circle-exmark' ?>"></i>
                </div>
                <div class="card-details">
                    <h4>Database Connection</h4>
                    <p><?= $pdo_connected ? "Successfully connected to schema." : "PDO connection failed." ?></p>
                </div>
            </div>
        </div>

        <!-- Loaded Environment Config Details -->
        <div class="section-title">
            <i class="fa-solid fa-sliders"></i> Active Configurations
        </div>
        <div class="details-panel">
            <div class="config-list">
                <div class="config-row">
                    <span>Database Host (DB_HOST)</span>
                    <span><?= htmlspecialchars($db_host ?? 'NULL') ?></span>
                </div>
                <div class="config-row">
                    <span>Database Port (DB_PORT)</span>
                    <span><?= htmlspecialchars($db_port ?? 'NULL') ?></span>
                </div>
                <div class="config-row">
                    <span>Database Name (DB_NAME)</span>
                    <span><?= htmlspecialchars($db_name ?? 'NULL') ?></span>
                </div>
                <div class="config-row">
                    <span>Database User (DB_USER)</span>
                    <span><?= htmlspecialchars($db_user ?? 'NULL') ?></span>
                </div>
                <div class="config-row" style="grid-column: 1 / -1;">
                    <span>Database Pass (DB_PASS)</span>
                    <span><?= htmlspecialchars($masked_pass ?? 'NULL') ?></span>
                </div>
            </div>
        </div>

        <!-- Error Log Block if Failed -->
        <?php if (!$pdo_connected && !empty($pdo_error_msg)): ?>
            <div class="error-log-box">
                <h3><i class="fa-solid fa-bug"></i> Connection Failure Details</h3>
                <p>The PHP PDO driver threw the following exception when attempting to connect:</p>
                <code><?= htmlspecialchars($pdo_error_msg) ?></code>
            </div>
        <?php endif; ?>

        <!-- Actionable Recommendations -->
        <div class="section-title" style="color: #60a5fa;">
            <i class="fa-solid fa-lightbulb"></i> Recommended Actions
        </div>
        <div class="recommendations-panel">
            <ul>
                <?php if ($pdo_connected): ?>
                    <li style="color: #a7f3d0; font-weight: 500;">
                        Database connection is fully operational! The system is successfully configured and authenticated. You can safely remove this diagnostic file.
                    </li>
                <?php else: ?>
                    <?php foreach ($recommendations as $rec): ?>
                        <li><?= $rec ?></li>
                    <?php endforeach; ?>
                <?php endif; ?>
                <li>Ensure that your web server has the correct read permissions on the <code>.env</code> file.</li>
            </ul>
        </div>

        <div class="footer-note">
            MG Education Portal Diagnostics • <a href="index.php">Go Back to Site</a>
        </div>
    </div>
</div>

</body>
</html>
