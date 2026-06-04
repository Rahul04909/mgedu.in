<?php
/**
 * MG Education & Social Development Organization
 * Admin Authentication Middleware
 * Enforces session security, timeouts, and anti-hijacking measures.
 */

// Establish secure session settings before starting session (if not already active)
if (session_status() === PHP_SESSION_NONE) {
    // Session Cookie Parameters
    $cookieParams = [
        'lifetime' => 0, // Session cookie expires when browser closes
        'path' => '/',
        'domain' => '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] == 443),
        'httponly' => true,
        'samesite' => 'Lax'
    ];
    session_set_cookie_params($cookieParams);
    session_start();
}

// Prevent browser caching sensitive admin dashboards
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Ensure admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Clear session entirely and redirect
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// Session Hijacking Protection: Check User-Agent and IP
$currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$currentIP = $_SERVER['REMOTE_ADDR'] ?? '';

if (!isset($_SESSION['auth_user_agent']) || $_SESSION['auth_user_agent'] !== md5($currentUserAgent) ||
    !isset($_SESSION['auth_ip_address']) || $_SESSION['auth_ip_address'] !== md5($currentIP)) {
    
    // Log suspicious activity
    error_log("Potential session hijacking detected for Admin ID: " . ($_SESSION['admin_id'] ?? 'Unknown'));
    
    // Terminate session
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    
    header("Location: login.php?error=sec");
    exit();
}

// Session Idle Timeout: 30 minutes (1800 seconds)
$timeoutLimit = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeoutLimit)) {
    // Terminate session due to inactivity
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    
    header("Location: login.php?error=timeout");
    exit();
}

// Update last activity timestamp
$_SESSION['last_activity'] = time();
