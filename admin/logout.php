<?php
/**
 * MG Education & Social Development Organization
 * Secure Session Termination
 * Destroys all session contexts and invalidates client session cookies.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Unset all session variables
$_SESSION = array();

// 2. Destroy the session cookie in client browser explicitly
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destroy server-side session context
session_destroy();

// 4. Redirect to login page with logout flag
header("Location: login.php?logout=success");
exit();
