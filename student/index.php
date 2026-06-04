<?php
/**
 * MG Education & Social Development Organization
 * Premium Split-Screen Student Authentication Portal
 * Designed to replicate the provided mockup layout.
 */

require_once __DIR__ . '/../includes/config.php';

// Self-healing: Dynamically create `student_otps` table if it does not exist
try {
    $db = MG_GetDBConnection();
    $db->exec("
        CREATE TABLE IF NOT EXISTS `student_otps` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `enrollment_number` VARCHAR(50) NOT NULL,
            `otp` VARCHAR(10) NOT NULL,
            `purpose` VARCHAR(50) NOT NULL,
            `expired_at` TIMESTAMP NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Exception $e) {
    error_log("Failed to initialize student_otps: " . $e->getMessage());
}

// Obfuscate email helper for privacy
function obfuscateEmail($email) {
    $parts = explode("@", $email);
    if(count($parts) < 2) return $email;
    $name = $parts[0];
    $domain = $parts[1];
    $len = strlen($name);
    if ($len <= 2) {
        $obfuscatedName = str_repeat("*", $len);
    } else {
        $obfuscatedName = substr($name, 0, 1) . str_repeat("*", $len - 2) . substr($name, -1);
    }
    return $obfuscatedName . "@" . $domain;
}

// Handle AJAX Request Endpoints
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $db = MG_GetDBConnection();

    try {
        if ($action === 'send_login_otp') {
            $enrollment = trim($_POST['enrollment_number'] ?? '');
            $portal = trim($_POST['portal'] ?? '');

            if (empty($enrollment) || empty($portal)) {
                echo json_encode(['success' => false, 'message' => 'Please enter enrollment number.']);
                exit;
            }

            $table = ($portal === 'course') ? 'admissions' : 'internship_admissions';
            $stmt = $db->prepare("SELECT * FROM `{$table}` WHERE `enrollment_number` = ? AND `status` = 'confirmed'");
            $stmt->execute([$enrollment]);
            $student = $stmt->fetch();

            if (!$student) {
                echo json_encode(['success' => false, 'message' => 'Invalid or unconfirmed enrollment number for this portal.']);
                exit;
            }

            $email = $student['email'];
            $name = $student['student_name'];

            if (empty($email)) {
                echo json_encode(['success' => false, 'message' => 'No registered email address found for this enrollment.']);
                exit;
            }

            $otp = sprintf("%06d", mt_rand(100000, 999999));
            $expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

            $stmtOtp = $db->prepare("INSERT INTO `student_otps` (`enrollment_number`, `otp`, `purpose`, `expired_at`) VALUES (?, ?, 'login', ?)");
            $stmtOtp->execute([$enrollment, $otp, $expiry]);

            $subject = "[MG Education] Verification Code for Learning Portal";
            $portalName = ($portal === 'course') ? 'Course Program Learning Portal' : 'Internship Development Portal';
            $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; background-color: #ffffff;'>
                <div style='text-align: center; border-bottom: 2px solid #00cbd5; padding-bottom: 20px; margin-bottom: 20px;'>
                    <h2 style='color: #00cbd5; margin: 0; text-transform: uppercase;'>MG EDUCATION</h2>
                    <p style='color: #475569; font-size: 14px; margin: 5px 0 0 0;'>Social Development & Educational Organization</p>
                </div>
                <div style='padding: 10px 0;'>
                    <p style='font-size: 16px; color: #1e293b;'>Hello <strong>{$name}</strong>,</p>
                    <p style='font-size: 15px; color: #475569; line-height: 1.6;'>You requested a One-Time Password (OTP) to securely sign into your <strong>{$portalName}</strong>.</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <div style='display: inline-block; background-color: #f1f5f9; border: 1px dashed #cbd5e1; border-radius: 6px; padding: 15px 30px;'>
                            <span style='font-size: 32px; font-weight: bold; letter-spacing: 4px; color: #00cbd5;'>{$otp}</span>
                        </div>
                        <p style='font-size: 12px; color: #64748b; margin-top: 10px;'>This verification code is valid for exactly <strong>5 minutes</strong>. Do not share this OTP with anyone.</p>
                    </div>

                    <p style='font-size: 14px; color: #475569; line-height: 1.5;'>If you did not initiate this request, please change your credentials immediately or contact support.</p>
                </div>
                <div style='border-top: 1px solid #e2e8f0; padding-top: 15px; margin-top: 30px; font-size: 12px; color: #94a3b8; text-align: center;'>
                    &copy; " . date('Y') . " MG Education Org. All rights reserved.<br>
                    This is an automated security notice. Do not reply directly to this email.
                </div>
            </div>";

            MG_SendMail($email, $subject, $body);

            echo json_encode([
                'success' => true,
                'message' => 'A dynamic security OTP has been sent successfully to your registered email: ' . obfuscateEmail($email)
            ]);
            exit;

        } elseif ($action === 'verify_login_otp') {
            $enrollment = trim($_POST['enrollment_number'] ?? '');
            $otp = trim($_POST['otp'] ?? '');
            $portal = trim($_POST['portal'] ?? '');

            if (empty($enrollment) || empty($otp) || empty($portal)) {
                echo json_encode(['success' => false, 'message' => 'Please enter all details including OTP.']);
                exit;
            }

            $stmt = $db->prepare("SELECT * FROM `student_otps` WHERE `enrollment_number` = ? AND `otp` = ? AND `purpose` = 'login' AND `expired_at` > ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$enrollment, $otp, date('Y-m-d H:i:s')]);
            $otpRecord = $stmt->fetch();

            if (!$otpRecord) {
                echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP. Please try sending a new OTP.']);
                exit;
            }

            $stmtDel = $db->prepare("DELETE FROM `student_otps` WHERE `enrollment_number` = ? AND `purpose` = 'login'");
            $stmtDel->execute([$enrollment]);

            $table = ($portal === 'course') ? 'admissions' : 'internship_admissions';
            $stmtStud = $db->prepare("SELECT * FROM `{$table}` WHERE `enrollment_number` = ? AND `status` = 'confirmed'");
            $stmtStud->execute([$enrollment]);
            $student = $stmtStud->fetch();

            if (!$student) {
                echo json_encode(['success' => false, 'message' => 'Unable to locate confirmed enrollment details.']);
                exit;
            }

            $_SESSION['student_role'] = $portal;
            $_SESSION['student_id'] = $student['id'];
            $_SESSION['student_enrollment'] = $student['enrollment_number'];
            $_SESSION['student_name'] = $student['student_name'];

            echo json_encode(['success' => true, 'redirect' => $portal . '/index.php']);
            exit;

        } elseif ($action === 'login_password') {
            $enrollment = trim($_POST['enrollment_number'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $portal = trim($_POST['portal'] ?? '');

            if (empty($enrollment) || empty($password) || empty($portal)) {
                echo json_encode(['success' => false, 'message' => 'Please fill in both enrollment number and password.']);
                exit;
            }

            $table = ($portal === 'course') ? 'admissions' : 'internship_admissions';
            $stmt = $db->prepare("SELECT * FROM `{$table}` WHERE `enrollment_number` = ? AND `password` = ? AND `status` = 'confirmed'");
            $stmt->execute([$enrollment, $password]);
            $student = $stmt->fetch();

            if (!$student) {
                echo json_encode(['success' => false, 'message' => 'Incorrect enrollment number or portal password.']);
                exit;
            }

            $_SESSION['student_role'] = $portal;
            $_SESSION['student_id'] = $student['id'];
            $_SESSION['student_enrollment'] = $student['enrollment_number'];
            $_SESSION['student_name'] = $student['student_name'];

            echo json_encode(['success' => true, 'redirect' => $portal . '/index.php']);
            exit;

        } elseif ($action === 'send_forgot_otp') {
            $enrollment = trim($_POST['enrollment_number'] ?? '');
            $portal = trim($_POST['portal'] ?? '');

            if (empty($enrollment) || empty($portal)) {
                echo json_encode(['success' => false, 'message' => 'Please enter enrollment number.']);
                exit;
            }

            $table = ($portal === 'course') ? 'admissions' : 'internship_admissions';
            $stmt = $db->prepare("SELECT * FROM `{$table}` WHERE `enrollment_number` = ? AND `status` = 'confirmed'");
            $stmt->execute([$enrollment]);
            $student = $stmt->fetch();

            if (!$student) {
                echo json_encode(['success' => false, 'message' => 'Enrollment number not found or pending verification.']);
                exit;
            }

            $email = $student['email'];
            $name = $student['student_name'];

            $otp = sprintf("%06d", mt_rand(100000, 999999));
            $expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

            $stmtOtp = $db->prepare("INSERT INTO `student_otps` (`enrollment_number`, `otp`, `purpose`, `expired_at`) VALUES (?, ?, 'forgot', ?)");
            $stmtOtp->execute([$enrollment, $otp, $expiry]);

            $subject = "[MG Education] Security OTP for Password Recovery";
            $portalName = ($portal === 'course') ? 'Course Program learning dashboard' : 'Internship Development dashboard';
            $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; background-color: #ffffff;'>
                <div style='text-align: center; border-bottom: 2px solid #b91c1c; padding-bottom: 20px; margin-bottom: 20px;'>
                    <h2 style='color: #b91c1c; margin: 0; text-transform: uppercase;'>PASSWORD RECOVERY</h2>
                    <p style='color: #475569; font-size: 14px; margin: 5px 0 0 0;'>MG Education Security Center</p>
                </div>
                <div style='padding: 10px 0;'>
                    <p style='font-size: 16px; color: #1e293b;'>Hello <strong>{$name}</strong>,</p>
                    <p style='font-size: 15px; color: #475569; line-height: 1.6;'>You requested to reset your password for your <strong>{$portalName}</strong> account (Enrollment: {$enrollment}).</p>
                    <p style='font-size: 15px; color: #475569;'>Please use the secure password recovery OTP below to verify your identity and finalize your new password:</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <div style='display: inline-block; background-color: #fef2f2; border: 1px dashed #f87171; border-radius: 6px; padding: 15px 30px;'>
                            <span style='font-size: 32px; font-weight: bold; letter-spacing: 4px; color: #b91c1c;'>{$otp}</span>
                        </div>
                        <p style='font-size: 12px; color: #64748b; margin-top: 10px;'>This recovery code is valid for exactly <strong>5 minutes</strong>. Do NOT share this security credential.</p>
                    </div>

                    <p style='font-size: 14px; color: #475569; line-height: 1.5;'>If you did not initiate this reset request, someone may be attempting to access your account. Please notify admin support immediately.</p>
                </div>
                <div style='border-top: 1px solid #e2e8f0; padding-top: 15px; margin-top: 30px; font-size: 12px; color: #94a3b8; text-align: center;'>
                    &copy; " . date('Y') . " MG Education Org. All rights reserved.<br>
                    This is a security alert. Do not reply.
                </div>
            </div>";

            MG_SendMail($email, $subject, $body);

            echo json_encode([
                'success' => true,
                'message' => 'Reset Verification OTP sent successfully to registered email: ' . obfuscateEmail($email)
            ]);
            exit;

        } elseif ($action === 'reset_password') {
            $enrollment = trim($_POST['enrollment_number'] ?? '');
            $otp = trim($_POST['otp'] ?? '');
            $newPassword = trim($_POST['new_password'] ?? '');
            $portal = trim($_POST['portal'] ?? '');

            if (empty($enrollment) || empty($otp) || empty($newPassword) || empty($portal)) {
                echo json_encode(['success' => false, 'message' => 'All inputs (enrollment number, OTP, new password) are required.']);
                exit;
            }

            if (strlen($newPassword) < 6) {
                echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long.']);
                exit;
            }

            $stmt = $db->prepare("SELECT * FROM `student_otps` WHERE `enrollment_number` = ? AND `otp` = ? AND `purpose` = 'forgot' AND `expired_at` > ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$enrollment, $otp, date('Y-m-d H:i:s')]);
            $otpRecord = $stmt->fetch();

            if (!$otpRecord) {
                echo json_encode(['success' => false, 'message' => 'Incorrect or expired recovery OTP. Please request a new one.']);
                exit;
            }

            $stmtDel = $db->prepare("DELETE FROM `student_otps` WHERE `enrollment_number` = ? AND `purpose` = 'forgot'");
            $stmtDel->execute([$enrollment]);

            $table = ($portal === 'course') ? 'admissions' : 'internship_admissions';
            $stmtUpd = $db->prepare("UPDATE `{$table}` SET `password` = ? WHERE `enrollment_number` = ? AND `status` = 'confirmed'");
            $stmtUpd->execute([$newPassword, $enrollment]);

            echo json_encode(['success' => true, 'message' => 'Password reset successful! You can now log in securely with your new password.']);
            exit;

        } elseif ($action === 'find_enrollment') {
            $email = trim($_POST['email'] ?? '');
            $portal = trim($_POST['portal'] ?? '');

            if (empty($email) || empty($portal)) {
                echo json_encode(['success' => false, 'message' => 'Please enter your registered email address.']);
                exit;
            }

            $table = ($portal === 'course') ? 'admissions' : 'internship_admissions';
            $stmt = $db->prepare("SELECT `student_name`, `enrollment_number` FROM `{$table}` WHERE `email` = ? AND `status` = 'confirmed' AND `enrollment_number` IS NOT NULL");
            $stmt->execute([$email]);
            $records = $stmt->fetchAll();

            if (empty($records)) {
                echo json_encode(['success' => false, 'message' => 'No active enrollment records found associated with this email address.']);
                exit;
            }

            $recordsHtml = "<table style='width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px;'>
                <thead>
                    <tr style='background-color: #f1f5f9;'>
                        <th style='border: 1px solid #cbd5e1; padding: 10px; text-align: left;'>Student Name</th>
                        <th style='border: 1px solid #cbd5e1; padding: 10px; text-align: left;'>Enrollment Number</th>
                    </tr>
                </thead>
                <tbody>";
            foreach ($records as $rec) {
                $recordsHtml .= "<tr>
                    <td style='border: 1px solid #cbd5e1; padding: 10px;'>" . htmlspecialchars($rec['student_name']) . "</td>
                    <td style='border: 1px solid #cbd5e1; padding: 10px; font-family: monospace; font-weight: bold; color: #00cbd5;'>" . htmlspecialchars($rec['enrollment_number']) . "</td>
                </tr>";
            }
            $recordsHtml .= "</tbody></table>";

            $subject = "[MG Education] Recovered Enrollment Profiles";
            $portalName = ($portal === 'course') ? 'Course Program Portal' : 'Internship Program Portal';
            $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; background-color: #ffffff;'>
                <div style='text-align: center; border-bottom: 2px solid #00cbd5; padding-bottom: 20px; margin-bottom: 20px;'>
                    <h2 style='color: #00cbd5; margin: 0; text-transform: uppercase;'>PROFILE RECOVERY</h2>
                    <p style='color: #475569; font-size: 14px; margin: 5px 0 0 0;'>MG Education Service Utility</p>
                </div>
                <div style='padding: 10px 0;'>
                    <p style='font-size: 16px; color: #1e293b;'>Hello,</p>
                    <p style='font-size: 15px; color: #475569; line-height: 1.6;'>Per your request, we found the following confirmed enrollment profile(s) associated with your email address for the <strong>{$portalName}</strong>:</p>
                    
                    {$recordsHtml}

                    <p style='font-size: 13.5px; color: #64748b; line-height: 1.6; margin-top: 25px;'>You can log in to your learning dashboard using these credentials by visiting the student portal.</p>
                </div>
                <div style='border-top: 1px solid #e2e8f0; padding-top: 15px; margin-top: 30px; font-size: 12px; color: #94a3b8; text-align: center;'>
                    &copy; " . date('Y') . " MG Education Org. All rights reserved.<br>
                    This is an automated support notice.
                </div>
            </div>";

            MG_SendMail($email, $subject, $body);

            echo json_encode(['success' => true, 'message' => 'All matching active enrollment records have been compiled and sent to your email. Please check your inbox.']);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Invalid action endpoint requested.']);
        exit;

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login | MG Education Portal</title>
    <meta name="description" content="Access your training console, courses, and internship materials. Secure unified login center.">
    <link rel="icon" href="../favicon.ico" type="image/x-icon">

    <!-- Fonts & Icons matching the premium training mockup -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.min.css" rel="stylesheet">

    <!-- Design System Replicating user's attached design mockup -->
    <style>
        :root {
            --mockup-cyan: #14b8a6;
            --mockup-cyan-light: #06b6d4;
            --mockup-teal: #0d9488;
            --mockup-text-dark: #1e293b;
            --mockup-text-muted: #64748b;
            --mockup-border: #cbd5e1;
            --mockup-bg-card: #ffffff;
        }

        body, html {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            overflow: hidden;
        }

        /* Split layout container */
        .split-viewport {
            display: flex;
            width: 100%;
            height: 100vh;
        }

        /* Left Section: Bright teal split and floating card */
        .left-panel {
            flex: 1.2;
            background: linear-gradient(135deg, #00f2fe 0%, #4facfe 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
            position: relative;
            z-index: 10;
            overflow: hidden; /* Hide scrollbars completely */
        }

        /* Right Section: Large student portrait with slanted left crop border */
        .right-panel {
            flex: 1.8;
            background-image: url('https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?q=80&w=1200&auto=format&fit=crop');
            background-size: cover;
            background-position: center 20%;
            position: relative;
            clip-path: polygon(10% 0, 100% 0, 100% 100%, 0% 100%);
            z-index: 5;
            transition: all 0.3s ease;
        }

        /* Slanted decorative geometric shapes on left panel matching the mockup */
        .deco-shape-top {
            position: absolute;
            top: -20px;
            left: -20px;
            width: 250px;
            height: 250px;
            background: rgba(0, 242, 254, 0.4);
            clip-path: polygon(0 0, 100% 0, 60% 100%, 0% 100%);
            z-index: 1;
            pointer-events: none;
        }

        .deco-shape-bottom {
            position: absolute;
            bottom: -50px;
            right: -50px;
            width: 350px;
            height: 350px;
            background: rgba(79, 172, 254, 0.35);
            clip-path: polygon(30% 0, 100% 0%, 100% 100%, 0% 100%);
            z-index: 1;
            pointer-events: none;
        }

        /* Premium Floating Card matching the attached image style */
        .login-card {
            position: relative;
            z-index: 20;
            width: 100%;
            max-width: 420px;
            background: var(--mockup-bg-card);
            border-radius: 16px;
            padding: 20px 24px 16px 24px;
            box-shadow: 0 15px 35px rgba(15, 23, 42, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.7);
            margin: auto;
        }

        /* Lock Icon header matching mockup */
        .card-header-panel {
            text-align: center;
            margin-bottom: 12px;
        }

        .lock-icon-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 1.5px solid var(--mockup-cyan);
            color: var(--mockup-cyan);
            margin-bottom: 6px;
            background-color: rgba(20, 184, 166, 0.05);
        }

        .lock-icon-wrapper i {
            font-size: 14px;
        }

        .card-header-panel h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 20px;
            font-weight: 600;
            color: var(--mockup-text-dark);
            margin-bottom: 2px;
        }

        .card-header-panel p {
            color: var(--mockup-text-muted);
            font-size: 12px;
            margin: 0;
        }

        /* Mockup Form Styling */
        .form-label-custom {
            font-weight: 500;
            font-size: 12.5px;
            color: var(--mockup-text-muted);
            margin-bottom: 6px;
            display: block;
        }

        .required-star {
            color: #ef4444;
            margin-left: 2px;
        }

        .input-group-custom {
            position: relative;
            margin-bottom: 10px;
        }

        .form-control-custom {
            width: 100%;
            padding: 8px 12px;
            border: 1.2px solid var(--mockup-border);
            background-color: #ffffff;
            color: var(--mockup-text-dark);
            font-size: 13px;
            border-radius: 8px;
            transition: all 0.2s ease;
            outline: none;
            box-shadow: none;
        }

        .form-control-custom:focus {
            border-color: var(--mockup-cyan);
            box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.15);
        }

        /* Role Selector Tabs styled identically to the password/otp switcher */
        .login-as-tabs {
            display: flex;
            background-color: #f1f5f9;
            border-radius: 8px;
            padding: 3px;
            margin-bottom: 2px;
        }

        .login-as-btn {
            flex: 1;
            padding: 7px;
            font-size: 12px;
            font-weight: 600;
            color: var(--mockup-text-muted);
            border: none;
            background: transparent;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .login-as-btn.active {
            background-color: #ffffff;
            color: var(--mockup-cyan);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .login-as-btn i {
            font-size: 12px;
        }

        /* Password reveal icon */
        .eye-toggle-icon {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--mockup-text-muted);
            cursor: pointer;
            z-index: 10;
        }
        .eye-toggle-icon:hover {
            color: var(--mockup-text-dark);
        }

        /* Auth Method Buttons Tab styled inside the form card */
        .method-tab-panel {
            display: flex;
            background-color: #f1f5f9;
            border-radius: 8px;
            padding: 3px;
            margin-bottom: 10px;
        }

        .method-tab-btn {
            flex: 1;
            padding: 7px;
            font-size: 12px;
            font-weight: 600;
            color: var(--mockup-text-muted);
            border: none;
            background: transparent;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .method-tab-btn.active {
            background-color: #ffffff;
            color: var(--mockup-cyan);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        /* Verification code entry box */
        .otp-box-expanded {
            background-color: #f8fafc;
            border: 1px dashed var(--mockup-cyan);
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
            display: none;
        }

        /* Clean action row matching "Login" cyan pill button and "Need account? Register" text */
        .submit-action-panel {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 12px;
        }

        .btn-cyan-pill {
            background: linear-gradient(135deg, var(--mockup-cyan), var(--mockup-cyan-light));
            color: #ffffff;
            font-weight: 600;
            font-size: 13px;
            padding: 8px 24px;
            border-radius: 50px;
            border: none;
            box-shadow: 0 4px 12px rgba(20, 184, 166, 0.25);
            transition: all 0.2s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-cyan-pill:hover {
            transform: translateY(-1.5px);
            box-shadow: 0 6px 20px rgba(20, 184, 166, 0.4);
            filter: brightness(1.05);
        }

        .btn-cyan-pill:disabled {
            background: #cbd5e1;
            box-shadow: none;
            cursor: not-allowed;
            transform: none;
        }

        .register-tip-text {
            font-size: 12px;
            color: var(--mockup-text-muted);
        }

        .register-tip-link {
            color: var(--mockup-text-dark);
            text-decoration: none;
            font-weight: 700;
        }
        .register-tip-link:hover {
            color: var(--mockup-cyan);
            text-decoration: underline;
        }

        /* Divider & support message at card bottom matching mockup */
        .card-divider {
            height: 1px;
            background-color: #e2e8f0;
            margin: 12px 0 10px 0;
        }

        .card-footer-support {
            font-size: 11px;
            color: var(--mockup-text-muted);
            text-align: center;
            line-height: 1.5;
        }

        .card-footer-support a {
            color: var(--mockup-cyan);
            text-decoration: none;
            font-weight: 600;
        }

        /* Overlay recovery panels styled within same card dimensions */
        .overlay-pane {
            display: none;
            animation: paneFadeIn 0.3s ease forwards;
        }

        @keyframes paneFadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .back-nav-trigger {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--mockup-cyan);
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
            transition: color 0.2s ease;
        }
        .back-nav-trigger:hover {
            color: var(--mockup-teal);
        }

        /* Responsive break point matching mockup content shift */
        @media (max-width: 991px) {
            .right-panel {
                display: none;
            }
            .left-panel {
                flex: 1;
                padding: 20px;
            }
        }
    </style>
</head>
<body>

    <div class="split-viewport">
        
        <!-- Left Side Panel with Slanted decorations & floating card -->
        <div class="left-panel">
            <div class="deco-shape-top"></div>
            <div class="deco-shape-bottom"></div>

            <div class="login-card">
                
                <!-- ================= CORE LOGIN INTERFACE ================= -->
                <div id="loginFormPane" class="overlay-pane" style="display: block;">
                    <div class="card-header-panel">
                        <div class="lock-icon-wrapper">
                            <i class="fa-solid fa-lock"></i>
                        </div>
                        <h2>Login</h2>
                        <p>Please log in to access your account.</p>
                    </div>

                    <form id="studentLoginForm" onsubmit="handleLoginSubmission(event)">
                        
                        <!-- Role Picker Tabs matching mockup "Login as" -->
                        <div class="input-group-custom">
                            <label class="form-label-custom">Login as<span class="required-star">*</span></label>
                            <div class="login-as-tabs">
                                <button type="button" class="login-as-btn active" id="btn-portal-course" onclick="switchLoginPortal('course')">
                                    <i class="fa-solid fa-book-open"></i> Course Student
                                </button>
                                <button type="button" class="login-as-btn" id="btn-portal-internship" onclick="switchLoginPortal('internship')">
                                    <i class="fa-solid fa-briefcase"></i> Internship Student
                                </button>
                            </div>
                            <input type="hidden" name="login_portal" id="login_portal" value="course">
                        </div>

                        <!-- Enrollment Input matching mockup "ID Number" -->
                        <div class="input-group-custom">
                            <label class="form-label-custom">ID Number<span class="required-star">*</span></label>
                            <input type="text" class="form-control-custom" name="enrollment_number" id="enrollment_number" placeholder="Enter Enrollment ID" required>
                        </div>

                        <!-- Authenticator switch method buttons -->
                        <div class="method-tab-panel">
                            <button type="button" class="method-tab-btn active" id="btn-method-pass" onclick="switchLoginMethod('password')">
                                Password Login
                            </button>
                            <button type="button" class="method-tab-btn" id="btn-method-otp" onclick="switchLoginMethod('otp')">
                                OTP Verification
                            </button>
                        </div>

                        <!-- Password Field matching mockup "User Pin" -->
                        <div class="input-group-custom" id="password-input-wrapper">
                            <label class="form-label-custom">User Pin<span class="required-star">*</span></label>
                            <div style="position: relative;">
                                <input type="password" class="form-control-custom" name="password" id="password_field" placeholder="••••••••">
                                <i class="fa-solid fa-eye-slash eye-toggle-icon" onclick="revealPassword('password_field', this)"></i>
                            </div>
                        </div>

                        <!-- OTP Verification panel -->
                        <div id="otp-input-wrapper" style="display: none; margin-bottom: 20px;">
                            <button type="button" class="btn-premium-outline w-100 py-2 border rounded text-secondary" id="otp-dispatch-btn" onclick="sendLoginOTP()" style="font-size: 13px; font-weight: 600; background: #f8fafc;">
                                <i class="fa-solid fa-paper-plane text-info"></i> Send Security OTP to Registered Email
                            </button>

                            <div class="otp-box-expanded" id="login-otp-field-box">
                                <label class="form-label-custom">Enter 6-Digit OTP<span class="required-star">*</span></label>
                                <input type="text" class="form-control-custom" name="otp" id="otp_field" placeholder="Verification OTP" maxlength="6" pattern="\d{6}">
                            </div>
                        </div>

                        <!-- Action trigger buttons -->
                        <div class="submit-action-panel">
                            <button type="submit" class="btn-cyan-pill" id="login-submit-btn">
                                Login <i class="fa-solid fa-circle-arrow-right"></i>
                            </button>
                            <div class="register-tip-text">
                                Need enrollment? <a target="_blank" href="../#admissions" class="register-tip-link">Apply</a>
                            </div>
                        </div>
                    </form>

                    <!-- Divider & recovery utilities matching bottom of card -->
                    <div class="card-divider"></div>
                    <div class="d-flex justify-content-between" style="font-size: 12.5px;">
                        <a href="javascript:void(0);" onclick="displayPane('forgotFormPane')" class="text-secondary text-decoration-none">
                            <i class="fa-solid fa-key text-teal"></i> Forgot Password?
                        </a>
                        <a href="javascript:void(0);" onclick="displayPane('findFormPane')" class="text-secondary text-decoration-none">
                            <i class="fa-solid fa-magnifying-glass text-teal"></i> Find Enrollment ID
                        </a>
                    </div>
                </div>

                <!-- ================= PASSWORD RESET INTERFACE ================= -->
                <div id="forgotFormPane" class="overlay-pane">
                    <div class="back-nav-trigger" onclick="displayPane('loginFormPane')">
                        <i class="fa-solid fa-arrow-left"></i> Back to Login
                    </div>
                    
                    <div class="card-header-panel">
                        <div class="lock-icon-wrapper" style="border-color: #ef4444; color: #ef4444; background-color: rgba(239, 68, 68, 0.05);">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>
                        <h2>Reset Pin</h2>
                        <p>Recover your secure entry password</p>
                    </div>

                    <form id="forgotForm" onsubmit="handlePasswordRecovery(event)">
                        <!-- Portal select -->
                        <div class="input-group-custom">
                            <label class="form-label-custom">Select Dashboard Portal<span class="required-star">*</span></label>
                            <select class="form-control-custom" name="forgot_portal" required>
                                <option value="course">Course Learning Dashboard</option>
                                <option value="internship">Internship Development Dashboard</option>
                            </select>
                        </div>

                        <!-- Enrollment -->
                        <div class="input-group-custom">
                            <label class="form-label-custom">ID Number<span class="required-star">*</span></label>
                            <input type="text" class="form-control-custom" name="forgot_enrollment" placeholder="Enter Enrollment ID" required>
                        </div>

                        <!-- Send OTP button -->
                        <button type="button" class="btn-premium-outline w-100 py-2 border rounded mb-3" id="forgot-otp-btn" onclick="sendForgotPasswordOTP()" style="font-size: 13px; font-weight: 600; background: #f8fafc;">
                            <i class="fa-solid fa-paper-plane text-info"></i> Dispatch Verification OTP
                        </button>

                        <!-- Expanded fields for reset -->
                        <div id="forgot-reset-box" style="display: none; background-color: #fef2f2; border: 1px dashed #f87171; border-radius: 8px; padding: 12px; margin-bottom: 20px;">
                            <div class="input-group-custom">
                                <label class="form-label-custom">Enter 6-Digit OTP<span class="required-star">*</span></label>
                                <input type="text" class="form-control-custom" name="forgot_otp" placeholder="Enter verification OTP" maxlength="6">
                            </div>
                            
                            <div class="input-group-custom mb-0">
                                <label class="form-label-custom">New User Pin<span class="required-star">*</span></label>
                                <div style="position: relative;">
                                    <input type="password" class="form-control-custom" name="forgot_new_password" id="forgot_new_password" placeholder="Min 6 characters">
                                    <i class="fa-solid fa-eye-slash eye-toggle-icon" onclick="revealPassword('forgot_new_password', this)"></i>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn-cyan-pill w-100 justify-content-center" id="forgot-submit-btn" style="background: linear-gradient(135deg, #ef4444, #f87171); box-shadow: 0 4px 14px rgba(239, 68, 68, 0.3);" disabled>
                            Reset Pin <i class="fa-solid fa-rotate-right"></i>
                        </button>
                    </form>
                </div>

                <!-- ================= PROFILE RECOVERY INTERFACE ================= -->
                <div id="findFormPane" class="overlay-pane">
                    <div class="back-nav-trigger" onclick="displayPane('loginFormPane')">
                        <i class="fa-solid fa-arrow-left"></i> Back to Login
                    </div>
                    
                    <div class="card-header-panel">
                        <div class="lock-icon-wrapper" style="border-color: #06b6d4; color: #06b6d4; background-color: rgba(6, 182, 212, 0.05);">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </div>
                        <h2>Find ID</h2>
                        <p>Locate enrollment credentials via registered email</p>
                    </div>

                    <form id="findForm" onsubmit="handleFindEnrollment(event)">
                        <!-- Portal select -->
                        <div class="input-group-custom">
                            <label class="form-label-custom">Select Dashboard Portal<span class="required-star">*</span></label>
                            <select class="form-control-custom" name="find_portal" required>
                                <option value="course">Course Learning Dashboard</option>
                                <option value="internship">Internship Development Dashboard</option>
                            </select>
                        </div>

                        <!-- Email input -->
                        <div class="input-group-custom">
                            <label class="form-label-custom">Registered Email Address<span class="required-star">*</span></label>
                            <input type="email" class="form-control-custom" name="find_email" placeholder="e.g. name@domain.com" required>
                        </div>

                        <button type="submit" class="btn-cyan-pill w-100 justify-content-center" id="find-submit-btn" style="background: linear-gradient(135deg, #06b6d4, #0891b2); box-shadow: 0 4px 14px rgba(6, 182, 212, 0.3);">
                            Retrieve IDs <i class="fa-solid fa-envelope"></i>
                        </button>
                    </form>
                </div>

                <!-- Subtle support information matching design mockup bottom -->
                <div class="card-divider"></div>
                <div class="card-footer-support">
                    If you have any portal related queries, feel free to contact learning support team at <a href="mailto:support@mgedu.in">support@mgedu.in</a> or alternatively email us on <a href="mailto:info@mgedu.in">info@mgedu.in</a>
                </div>

            </div>
        </div>

        <!-- Right Side Panel containing premium matching student photography slanted -->
        <div class="right-panel"></div>

    </div>

    <!-- Core Javascript system -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.all.min.js"></script>

    <script>
        // Track the current login method selection ('password' or 'otp')
        let activeLoginMethod = 'password';

        $(document).ready(function() {
            // Check for authorization error redirect parameter
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('error') === 'unauthorized') {
                Swal.fire({
                    title: 'Access Restricted',
                    text: 'You must log in to access this secure student portal.',
                    icon: 'warning',
                    background: '#ffffff',
                    color: '#1e293b',
                    confirmButtonColor: '#14b8a6'
                });
            } else if (urlParams.get('logout') === 'success') {
                Swal.fire({
                    title: 'Logged Out',
                    text: 'You have been successfully and securely logged out.',
                    icon: 'success',
                    background: '#ffffff',
                    color: '#1e293b',
                    confirmButtonColor: '#14b8a6',
                    timer: 2500
                });
            }
        });

        // Switch panel visibility smoothly
        function displayPane(paneId) {
            $('.overlay-pane').hide();
            $(`#${paneId}`).show();
        }

        // Reveal password input field value
        function revealPassword(fieldId, iconElement) {
            const field = document.getElementById(fieldId);
            if (field.type === 'password') {
                field.type = 'text';
                $(iconElement).removeClass('fa-eye-slash').addClass('fa-eye');
            } else {
                field.type = 'password';
                $(iconElement).removeClass('fa-eye').addClass('fa-eye-slash');
            }
        }

        // Toggle Password/OTP authentication tabs
        function switchLoginMethod(method) {
            activeLoginMethod = method;
            $('.method-tab-btn').removeClass('active');
            $(`#btn-method-${method}`).addClass('active');

            if (method === 'password') {
                $('#password-input-wrapper').show();
                $('#otp-input-wrapper').hide();
                $('#password_field').attr('required', true);
                $('#otp_field').attr('required', false);
            } else {
                $('#password-input-wrapper').hide();
                $('#otp-input-wrapper').show();
                $('#password_field').attr('required', false);
                $('#otp_field').attr('required', true);
            }
        }

        // Toggle Course/Internship portal tabs
        function switchLoginPortal(portal) {
            $('#login_portal').val(portal);
            $('.login-as-btn').removeClass('active');
            $(`#btn-portal-${portal}`).addClass('active');

            if (portal === 'course') {
                $('#enrollment_number').attr('placeholder', 'Enter Course Enrollment ID');
            } else {
                $('#enrollment_number').attr('placeholder', 'Enter Internship ID');
            }
        }

        // Send OTP verification to student registered email address via AJAX
        function sendLoginOTP() {
            const enrollment = $('#enrollment_number').val().trim();
            const portal = $('#login_portal').val();
            const sendBtn = $('#otp-dispatch-btn');

            if (!enrollment) {
                Swal.fire({
                    title: 'Attention',
                    text: 'Please enter your ID Number first to receive the security verification code.',
                    icon: 'info',
                    background: '#ffffff',
                    color: '#1e293b',
                    confirmButtonColor: '#14b8a6'
                });
                return;
            }

            sendBtn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Dispatching OTP...');

            $.ajax({
                type: 'POST',
                url: 'index.php',
                data: {
                    action: 'send_login_otp',
                    enrollment_number: enrollment,
                    portal: portal
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'OTP Dispatched',
                            text: response.message,
                            icon: 'success',
                            background: '#ffffff',
                            color: '#1e293b',
                            confirmButtonColor: '#14b8a6'
                        });
                        // Reveal OTP field panel
                        $('#login-otp-field-box').slideDown();
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.message,
                            icon: 'error',
                            background: '#ffffff',
                            color: '#1e293b',
                            confirmButtonColor: '#14b8a6'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        title: 'Error',
                        text: 'Security system encountered a server connection issue.',
                        icon: 'error',
                        background: '#ffffff',
                        color: '#1e293b',
                        confirmButtonColor: '#14b8a6'
                    });
                },
                complete: function() {
                    sendBtn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane text-info"></i> Send Security OTP to Registered Email');
                }
            });
        }

        // Handle direct student login form submission via AJAX
        function handleLoginSubmission(event) {
            event.preventDefault();
            const portal = $('#login_portal').val();
            const enrollment = $('#enrollment_number').val().trim();
            const submitBtn = $('#login-submit-btn');

            let postData = {
                enrollment_number: enrollment,
                portal: portal
            };

            if (activeLoginMethod === 'password') {
                const password = $('#password_field').val().trim();
                postData.action = 'login_password';
                postData.password = password;
            } else {
                const otp = $('#otp_field').val().trim();
                if (!otp || otp.length !== 6) {
                    Swal.fire({
                        title: 'Invalid Entry',
                        text: 'Please enter the complete 6-digit verification code.',
                        icon: 'warning',
                        background: '#ffffff',
                        color: '#1e293b',
                        confirmButtonColor: '#14b8a6'
                    });
                    return;
                }
                postData.action = 'verify_login_otp';
                postData.otp = otp;
            }

            submitBtn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Checking...');

            $.ajax({
                type: 'POST',
                url: 'index.php',
                data: postData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'Access Approved',
                            text: 'Login successful! Directing to your secure console...',
                            icon: 'success',
                            background: '#ffffff',
                            color: '#1e293b',
                            showConfirmButton: false,
                            timer: 1500
                        }).then(() => {
                            window.location.href = response.redirect;
                        });
                    } else {
                        Swal.fire({
                            title: 'Access Denied',
                            text: response.message,
                            icon: 'error',
                            background: '#ffffff',
                            color: '#1e293b',
                            confirmButtonColor: '#14b8a6'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        title: 'Error',
                        text: 'Server validation system communication fail.',
                        icon: 'error',
                        background: '#ffffff',
                        color: '#1e293b',
                        confirmButtonColor: '#14b8a6'
                    });
                },
                complete: function() {
                    submitBtn.prop('disabled', false).html('Login <i class="fa-solid fa-circle-arrow-right"></i>');
                }
            });
        }

        // Send OTP verification during forgot password flow via AJAX
        function sendForgotPasswordOTP() {
            const enrollment = $('input[name="forgot_enrollment"]').val().trim();
            const portal = $('select[name="forgot_portal"]').val();
            const sendBtn = $('#forgot-otp-btn');

            if (!enrollment) {
                Swal.fire({
                    title: 'Attention',
                    text: 'Please enter your ID Number first to receive verification OTP.',
                    icon: 'info',
                    background: '#ffffff',
                    color: '#1e293b',
                    confirmButtonColor: '#ef4444'
                });
                return;
            }

            sendBtn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Dispatching...');

            $.ajax({
                type: 'POST',
                url: 'index.php',
                data: {
                    action: 'send_forgot_otp',
                    enrollment_number: enrollment,
                    portal: portal
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'OTP Dispatched',
                            text: response.message,
                            icon: 'success',
                            background: '#ffffff',
                            color: '#1e293b',
                            confirmButtonColor: '#ef4444'
                        });
                        // Expand reset box fields and enable final submit
                        $('#forgot-reset-box').slideDown();
                        $('#forgot-submit-btn').prop('disabled', false);
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.message,
                            icon: 'error',
                            background: '#ffffff',
                            color: '#1e293b',
                            confirmButtonColor: '#ef4444'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        title: 'Error',
                        text: 'Verification server encountered an issue.',
                        icon: 'error',
                        background: '#ffffff',
                        color: '#1e293b',
                        confirmButtonColor: '#ef4444'
                    });
                },
                complete: function() {
                    sendBtn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane text-info"></i> Dispatch Verification OTP');
                }
            });
        }

        // Complete password recovery reset action via AJAX
        function handlePasswordRecovery(event) {
            event.preventDefault();
            const form = $('#forgotForm');
            const enrollment = form.find('input[name="forgot_enrollment"]').val().trim();
            const portal = form.find('select[name="forgot_portal"]').val();
            const otp = form.find('input[name="forgot_otp"]').val().trim();
            const newPassword = form.find('input[name="forgot_new_password"]').val().trim();
            const submitBtn = $('#forgot-submit-btn');

            if (!otp || !newPassword) {
                Swal.fire({
                    title: 'Attention',
                    text: 'Please input both verification OTP and a secure new password.',
                    icon: 'warning',
                    background: '#ffffff',
                    color: '#1e293b',
                    confirmButtonColor: '#ef4444'
                });
                return;
            }

            submitBtn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Saving...');

            $.ajax({
                type: 'POST',
                url: 'index.php',
                data: {
                    action: 'reset_password',
                    enrollment_number: enrollment,
                    portal: portal,
                    otp: otp,
                    new_password: newPassword
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'Reset Success',
                            text: response.message,
                            icon: 'success',
                            background: '#ffffff',
                            color: '#1e293b',
                            confirmButtonColor: '#28a745'
                        }).then(() => {
                            form[0].reset();
                            $('#forgot-reset-box').hide();
                            $('#forgot-submit-btn').prop('disabled', true);
                            displayPane('loginFormPane');
                        });
                    } else {
                        Swal.fire({
                            title: 'Reset Failed',
                            text: response.message,
                            icon: 'error',
                            background: '#ffffff',
                            color: '#1e293b',
                            confirmButtonColor: '#ef4444'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        title: 'Error',
                        text: 'Server update fails. Try again.',
                        icon: 'error',
                        background: '#ffffff',
                        color: '#1e293b',
                        confirmButtonColor: '#ef4444'
                    });
                },
                complete: function() {
                    submitBtn.prop('disabled', false).html('Reset Pin <i class="fa-solid fa-rotate-right"></i>');
                }
            });
        }

        // Recover student ID numbers by registered email via AJAX
        function handleFindEnrollment(event) {
            event.preventDefault();
            const form = $('#findForm');
            const email = form.find('input[name="find_email"]').val().trim();
            const portal = form.find('select[name="find_portal"]').val();
            const submitBtn = $('#find-submit-btn');

            submitBtn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Searching...');

            $.ajax({
                type: 'POST',
                url: 'index.php',
                data: {
                    action: 'find_enrollment',
                    email: email,
                    portal: portal
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'Details Emailed',
                            text: response.message,
                            icon: 'success',
                            background: '#ffffff',
                            color: '#1e293b',
                            confirmButtonColor: '#06b6d4'
                        }).then(() => {
                            form[0].reset();
                            displayPane('loginFormPane');
                        });
                    } else {
                        Swal.fire({
                            title: 'Not Found',
                            text: response.message,
                            icon: 'warning',
                            background: '#ffffff',
                            color: '#1e293b',
                            confirmButtonColor: '#06b6d4'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        title: 'Error',
                        text: 'Profile recovery server connection fail.',
                        icon: 'error',
                        background: '#ffffff',
                        color: '#1e293b',
                        confirmButtonColor: '#06b6d4'
                    });
                },
                complete: function() {
                    submitBtn.prop('disabled', false).html('Retrieve IDs <i class="fa-solid fa-envelope"></i>');
                }
            });
        }
    </script>
</body>
</html>
