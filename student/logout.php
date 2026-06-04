<?php
/**
 * MG Education & Social Development Organization
 * Student Secure Session Termination Handler
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Unset all student-related session credentials
unset($_SESSION['student_role']);
unset($_SESSION['student_id']);
unset($_SESSION['student_enrollment']);
unset($_SESSION['student_name']);

// Perform full session cleanup if no other context requires it
// Note: We avoid session_destroy() if administrative or franchise sessions are concurrently active.
if (empty($_SESSION['admin_id']) && empty($_SESSION['franchise_id'])) {
    session_destroy();
}

// Redirect cleanly back to the unified student entry portal
header("Location: index.php?logout=success");
exit();
