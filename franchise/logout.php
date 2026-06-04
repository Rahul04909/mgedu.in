<?php
/**
 * MG Education & Social Development Organization
 * Franchise Portal Logout Handler
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Unset active franchise center session parameters
unset($_SESSION['center_role']);
unset($_SESSION['center_logged_id']);
unset($_SESSION['center_id']);
unset($_SESSION['center_name']);
unset($_SESSION['center_email']);

// Optional: destroy session if no other roles are active
// session_destroy();

header("Location: login.php?logout=success");
exit();
