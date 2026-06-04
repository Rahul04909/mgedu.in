<?php
/**
 * MG Education & Social Development Organization
 * Premium Emerald-Green Split-Screen Franchise Center Authentication Portal
 */

require_once __DIR__ . '/../includes/config.php';

// Self-healing: Dynamically create `franchise_otps` table if it does not exist
try {
    $db = MG_GetDBConnection();
    $db->exec("
        CREATE TABLE IF NOT EXISTS `franchise_otps` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `center_id` VARCHAR(50) NOT NULL,
            `otp` VARCHAR(10) NOT NULL,
            `purpose` VARCHAR(50) NOT NULL,
            `expired_at` TIMESTAMP NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (Exception $e) {
    error_log("Failed to initialize franchise_otps: " . $e->getMessage());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
            $center_id = trim($_POST['center_id'] ?? '');

            if (empty($center_id)) {
                echo json_encode(['success' => false, 'message' => 'Please enter your Center ID.']);
                exit;
            }

            $stmt = $db->prepare("SELECT * FROM `franchise_centers` WHERE `center_id` = ? LIMIT 1");
            $stmt->execute([$center_id]);
            $center = $stmt->fetch();

            if (!$center) {
                echo json_encode(['success' => false, 'message' => 'Invalid Center ID. Please check your credentials.']);
                exit;
            }

            $email = $center['email'];
            $name = $center['center_name'];

            if (empty($email)) {
                echo json_encode(['success' => false, 'message' => 'No registered email address found for this Center.']);
                exit;
            }

            $otp = sprintf("%06d", mt_rand(100000, 999999));
            $expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

            $stmtOtp = $db->prepare("INSERT INTO `franchise_otps` (`center_id`, `otp`, `purpose`, `expired_at`) VALUES (?, ?, 'login', ?)");
            $stmtOtp->execute([$center_id, $otp, $expiry]);

            $subject = "[MG Education] Security Verification Code - Center Portal";
            $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; background-color: #ffffff;'>
                <div style='text-align: center; border-bottom: 2px solid #10b981; padding-bottom: 20px; margin-bottom: 20px;'>
                    <h2 style='color: #10b981; margin: 0; text-transform: uppercase;'>MG EDUCATION</h2>
                    <p style='color: #475569; font-size: 14px; margin: 5px 0 0 0;'>Accredited Franchise Console</p>
                </div>
                <div style='padding: 10px 0;'>
                    <p style='font-size: 16px; color: #1e293b;'>Hello Director/Manager of <strong>{$name}</strong>,</p>
                    <p style='font-size: 15px; color: #475569; line-height: 1.6;'>You requested a security verification code (OTP) to securely sign into your **Franchise Center Dashboard** (Center ID: {$center_id}).</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <div style='display: inline-block; background-color: #ecfdf5; border: 1px dashed #34d399; border-radius: 6px; padding: 15px 30px;'>
                            <span style='font-size: 32px; font-weight: bold; letter-spacing: 4px; color: #10b981;'>{$otp}</span>
                        </div>
                        <p style='font-size: 12px; color: #64748b; margin-top: 10px;'>This verification code is valid for exactly <strong>5 minutes</strong>. Do not share this OTP with anyone.</p>
                    </div>

                    <p style='font-size: 14px; color: #475569; line-height: 1.5;'>If you did not initiate this request, please contact MG Education Franchise Operations or change your credentials immediately.</p>
                </div>
                <div style='border-top: 1px solid #e2e8f0; padding-top: 15px; margin-top: 30px; font-size: 12px; color: #94a3b8; text-align: center;'>
                    &copy; " . date('Y') . " MG Education Org. All rights reserved.<br>
                    This is a security alert. Do not reply.
                </div>
            </div>";

            MG_SendMail($email, $subject, $body);

            echo json_encode([
                'success' => true,
                'message' => 'A dynamic security OTP has been sent successfully to your registered email: ' . obfuscateEmail($email)
            ]);
            exit;

        } elseif ($action === 'verify_login_otp') {
            $center_id = trim($_POST['center_id'] ?? '');
            $otp = trim($_POST['otp'] ?? '');

            if (empty($center_id) || empty($otp)) {
                echo json_encode(['success' => false, 'message' => 'Please enter both Center ID and OTP.']);
                exit;
            }

            $stmt = $db->prepare("SELECT * FROM `franchise_otps` WHERE `center_id` = ? AND `otp` = ? AND `purpose` = 'login' AND `expired_at` > ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$center_id, $otp, date('Y-m-d H:i:s')]);
            $otpRecord = $stmt->fetch();

            if (!$otpRecord) {
                echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP. Please try dispatching a new OTP code.']);
                exit;
            }

            // Cleanup OTPs for this center
            $stmtDel = $db->prepare("DELETE FROM `franchise_otps` WHERE `center_id` = ? AND `purpose` = 'login'");
            $stmtDel->execute([$center_id]);

            // Fetch center profile details
            $stmtCenter = $db->prepare("SELECT * FROM `franchise_centers` WHERE `center_id` = ? LIMIT 1");
            $stmtCenter->execute([$center_id]);
            $center = $stmtCenter->fetch();

            if (!$center) {
                echo json_encode(['success' => false, 'message' => 'Unable to locate center information.']);
                exit;
            }

            $_SESSION['center_role'] = 'franchise';
            $_SESSION['center_logged_id'] = $center['id'];
            $_SESSION['center_id'] = $center['center_id'];
            $_SESSION['center_name'] = $center['center_name'];
            $_SESSION['center_email'] = $center['email'];

            echo json_encode(['success' => true, 'redirect' => 'index.php']);
            exit;

        } elseif ($action === 'login_password') {
            $center_id = trim($_POST['center_id'] ?? '');
            $password = trim($_POST['password'] ?? '');

            if (empty($center_id) || empty($password)) {
                echo json_encode(['success' => false, 'message' => 'Please fill in both Center ID and password.']);
                exit;
            }

            $stmt = $db->prepare("SELECT * FROM `franchise_centers` WHERE `center_id` = ? AND `password` = ? LIMIT 1");
            $stmt->execute([$center_id, $password]);
            $center = $stmt->fetch();

            if (!$center) {
                echo json_encode(['success' => false, 'message' => 'Incorrect Center ID or password.']);
                exit;
            }

            $_SESSION['center_role'] = 'franchise';
            $_SESSION['center_logged_id'] = $center['id'];
            $_SESSION['center_id'] = $center['center_id'];
            $_SESSION['center_name'] = $center['center_name'];
            $_SESSION['center_email'] = $center['email'];

            echo json_encode(['success' => true, 'redirect' => 'index.php']);
            exit;

        } elseif ($action === 'send_forgot_otp') {
            $center_id = trim($_POST['center_id'] ?? '');

            if (empty($center_id)) {
                echo json_encode(['success' => false, 'message' => 'Please enter Center ID.']);
                exit;
            }

            $stmt = $db->prepare("SELECT * FROM `franchise_centers` WHERE `center_id` = ? LIMIT 1");
            $stmt->execute([$center_id]);
            $center = $stmt->fetch();

            if (!$center) {
                echo json_encode(['success' => false, 'message' => 'Center ID not found in the official registry.']);
                exit;
            }

            $email = $center['email'];
            $name = $center['center_name'];

            $otp = sprintf("%06d", mt_rand(100000, 999999));
            $expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

            $stmtOtp = $db->prepare("INSERT INTO `franchise_otps` (`center_id`, `otp`, `purpose`, `expired_at`) VALUES (?, ?, 'forgot', ?)");
            $stmtOtp->execute([$center_id, $otp, $expiry]);

            $subject = "[MG Education] Verification OTP for Franchise Password Reset";
            $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; background-color: #ffffff;'>
                <div style='text-align: center; border-bottom: 2px solid #ef4444; padding-bottom: 20px; margin-bottom: 20px;'>
                    <h2 style='color: #ef4444; margin: 0; text-transform: uppercase;'>SECURITY VERIFICATION</h2>
                    <p style='color: #475569; font-size: 14px; margin: 5px 0 0 0;'>MG Education Franchise Operations</p>
                </div>
                <div style='padding: 10px 0;'>
                    <p style='font-size: 16px; color: #1e293b;'>Hello Director of <strong>{$name}</strong>,</p>
                    <p style='font-size: 15px; color: #475569; line-height: 1.6;'>You requested to reset your access password for **Franchise Center ID: {$center_id}**.</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <div style='display: inline-block; background-color: #fef2f2; border: 1px dashed #f87171; border-radius: 6px; padding: 15px 30px;'>
                            <span style='font-size: 32px; font-weight: bold; letter-spacing: 4px; color: #ef4444;'>{$otp}</span>
                        </div>
                        <p style='font-size: 12px; color: #64748b; margin-top: 10px;'>This recovery OTP is valid for exactly <strong>5 minutes</strong>. Do not share this security code.</p>
                    </div>

                    <p style='font-size: 14px; color: #475569; line-height: 1.5;'>If you did not make this request, please secure your email account immediately or notify MG Education IT Support.</p>
                </div>
                <div style='border-top: 1px solid #e2e8f0; padding-top: 15px; margin-top: 30px; font-size: 12px; color: #94a3b8; text-align: center;'>
                    &copy; " . date('Y') . " MG Education Org. All rights reserved.<br>
                    Automated security notice.
                </div>
            </div>";

            MG_SendMail($email, $subject, $body);

            echo json_encode([
                'success' => true,
                'message' => 'Reset Verification OTP sent successfully to registered email: ' . obfuscateEmail($email)
            ]);
            exit;

        } elseif ($action === 'reset_password') {
            $center_id = trim($_POST['center_id'] ?? '');
            $otp = trim($_POST['otp'] ?? '');
            $newPassword = trim($_POST['new_password'] ?? '');

            if (empty($center_id) || empty($otp) || empty($newPassword)) {
                echo json_encode(['success' => false, 'message' => 'All inputs (Center ID, OTP, and new password) are required.']);
                exit;
            }

            if (strlen($newPassword) < 6) {
                echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long.']);
                exit;
            }

            $stmt = $db->prepare("SELECT * FROM `franchise_otps` WHERE `center_id` = ? AND `otp` = ? AND `purpose` = 'forgot' AND `expired_at` > ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$center_id, $otp, date('Y-m-d H:i:s')]);
            $otpRecord = $stmt->fetch();

            if (!$otpRecord) {
                echo json_encode(['success' => false, 'message' => 'Incorrect or expired recovery OTP code. Please request a new one.']);
                exit;
            }

            $stmtDel = $db->prepare("DELETE FROM `franchise_otps` WHERE `center_id` = ? AND `purpose` = 'forgot'");
            $stmtDel->execute([$center_id]);

            $stmtUpd = $db->prepare("UPDATE `franchise_centers` SET `password` = ? WHERE `center_id` = ?");
            $stmtUpd->execute([$newPassword, $center_id]);

            echo json_encode(['success' => true, 'message' => 'Password reset successful! You can now log in securely with your new password.']);
            exit;

        } elseif ($action === 'find_center_id') {
            $email = trim($_POST['email'] ?? '');

            if (empty($email)) {
                echo json_encode(['success' => false, 'message' => 'Please enter your registered email address.']);
                exit;
            }

            $stmt = $db->prepare("SELECT `center_name`, `center_id` FROM `franchise_centers` WHERE `email` = ? AND `center_id` IS NOT NULL");
            $stmt->execute([$email]);
            $records = $stmt->fetchAll();

            if (empty($records)) {
                echo json_encode(['success' => false, 'message' => 'No active franchise records found associated with this email address.']);
                exit;
            }

            $recordsHtml = "<table style='width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px;'>
                <thead>
                    <tr style='background-color: #f1f5f9;'>
                        <th style='border: 1px solid #cbd5e1; padding: 10px; text-align: left;'>Center Name</th>
                        <th style='border: 1px solid #cbd5e1; padding: 10px; text-align: left;'>Center ID (Enrollment)</th>
                    </tr>
                </thead>
                <tbody>";
            foreach ($records as $rec) {
                $recordsHtml .= "<tr>
                    <td style='border: 1px solid #cbd5e1; padding: 10px;'>" . htmlspecialchars($rec['center_name']) . "</td>
                    <td style='border: 1px solid #cbd5e1; padding: 10px; font-family: monospace; font-weight: bold; color: #10b981;'>" . htmlspecialchars($rec['center_id']) . "</td>
                </tr>";
            }
            $recordsHtml .= "</tbody></table>";

            $subject = "[MG Education] Recovered Franchise Center Profiles";
            $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; background-color: #ffffff;'>
                <div style='text-align: center; border-bottom: 2px solid #10b981; padding-bottom: 20px; margin-bottom: 20px;'>
                    <h2 style='color: #10b981; margin: 0; text-transform: uppercase;'>CENTER RECOVERY</h2>
                    <p style='color: #475569; font-size: 14px; margin: 5px 0 0 0;'>MG Education Service Utility</p>
                </div>
                <div style='padding: 10px 0;'>
                    <p style='font-size: 16px; color: #1e293b;'>Hello,</p>
                    <p style='font-size: 15px; color: #475569; line-height: 1.6;'>Per your request, we found the following franchise center profile(s) associated with your email address:</p>
                    
                    {$recordsHtml}

                    <p style='font-size: 13.5px; color: #64748b; line-height: 1.6; margin-top: 25px;'>You can log in to your franchise dashboard using these credentials by visiting the franchise portal.</p>
                </div>
                <div style='border-top: 1px solid #e2e8f0; padding-top: 15px; margin-top: 30px; font-size: 12px; color: #94a3b8; text-align: center;'>
                    &copy; " . date('Y') . " MG Education Org. All rights reserved.<br>
                    Automated support utility notice.
                </div>
            </div>";

            MG_SendMail($email, $subject, $body);

            echo json_encode(['success' => true, 'message' => 'All matching active franchise profiles have been compiled and sent to your email. Please check your inbox.']);
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
    <title>Franchise Center Login | MG Education Console</title>
    <meta name="description" content="Access your accredited franchise dashboard, student registries, and affiliation certifications. Secure unified console.">
    <link rel="icon" href="../favicon.ico" type="image/x-icon">

    <!-- Fonts & Icons matching the student portal premium styles -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.min.css" rel="stylesheet">

    <!-- Custom Emerald Green and Teal Theme matching franchise dashboard style -->
    <style>
        :root {
            --franchise-green: #10b981;
            --franchise-teal: #059669;
            --franchise-dark: #064e3b;
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

        /* Left Section: Bright green gradient and floating card */
        .left-panel {
            flex: 1.2;
            background: linear-gradient(135deg, #34d399 0%, #059669 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
            position: relative;
            z-index: 10;
            overflow: hidden;
        }

        /* Right Section: Franchise network / modern corporate image with slanted border */
        .right-panel {
            flex: 1.8;
            background-image: url('https://images.unsplash.com/photo-1522071820081-009f0129c71c?q=80&w=1200&auto=format&fit=crop');
            background-size: cover;
            background-position: center;
            position: relative;
            clip-path: polygon(10% 0, 100% 0, 100% 100%, 0% 100%);
            z-index: 5;
            transition: all 0.3s ease;
        }

        /* Slanted decorative geometric shapes on left panel */
        .deco-shape-top {
            position: absolute;
            top: -20px;
            left: -20px;
            width: 250px;
            height: 250px;
            background: rgba(52, 211, 153, 0.4);
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
            background: rgba(5, 150, 105, 0.35);
            clip-path: polygon(30% 0, 100% 0%, 100% 100%, 0% 100%);
            z-index: 1;
            pointer-events: none;
        }

        /* Premium Floating Card */
        .login-card {
            position: relative;
            z-index: 20;
            width: 100%;
            max-width: 420px;
            background: var(--mockup-bg-card);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 15px 35px rgba(15, 23, 42, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.7);
            margin: auto;
        }

        /* Lock Icon header matching mockup */
        .card-header-panel {
            text-align: center;
            margin-bottom: 15px;
        }

        .lock-icon-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: 1.5px solid var(--franchise-green);
            color: var(--franchise-green);
            margin-bottom: 8px;
            background-color: rgba(16, 185, 129, 0.05);
        }

        .lock-icon-wrapper i {
            font-size: 16px;
        }

        .card-header-panel h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 22px;
            font-weight: 600;
            color: var(--mockup-text-dark);
            margin-bottom: 2px;
        }

        .card-header-panel p {
            color: var(--mockup-text-muted);
            font-size: 13px;
            margin: 0;
        }

        /* Premium Form Input Fields */
        .form-label-custom {
            font-weight: 600;
            font-size: 12.5px;
            color: var(--mockup-text-muted);
            margin-bottom: 6px;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.25px;
        }

        .required-star {
            color: #ef4444;
            margin-left: 2px;
        }

        .input-group-custom {
            position: relative;
            margin-bottom: 12px;
        }

        .form-control-custom {
            width: 100%;
            padding: 10px 14px;
            border: 1.2px solid var(--mockup-border);
            background-color: #ffffff;
            color: var(--mockup-text-dark);
            font-size: 13.5px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s ease;
            outline: none;
            box-shadow: none;
        }

        .form-control-custom:focus {
            border-color: var(--franchise-green);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }

        /* Eye Icon Centered Professionally */
        .eye-toggle-icon {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--mockup-text-muted);
            cursor: pointer;
            z-index: 10;
            transition: color 0.2s;
        }
        .eye-toggle-icon:hover {
            color: var(--mockup-text-dark);
        }

        /* Auth Method Switcher Tabs */
        .method-tab-panel {
            display: flex;
            background-color: #f1f5f9;
            border-radius: 8px;
            padding: 3px;
            margin-bottom: 15px;
        }

        .method-tab-btn {
            flex: 1;
            padding: 8px;
            font-size: 12.5px;
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
            color: var(--franchise-teal);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        /* OTP Verification dispatch details block */
        .otp-box-expanded {
            background-color: #f0fdf4;
            border: 1px dashed var(--franchise-green);
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
            display: none;
        }

        /* Clean action row with custom emerald green pill button */
        .submit-action-panel {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 15px;
        }

        .btn-green-pill {
            background: linear-gradient(135deg, var(--franchise-green), #34d399);
            color: #ffffff;
            font-weight: 600;
            font-size: 13.5px;
            padding: 9px 26px;
            border-radius: 50px;
            border: none;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
            transition: all 0.2s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-green-pill:hover {
            transform: translateY(-1.5px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
            filter: brightness(1.05);
        }

        .btn-green-pill:disabled {
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
            color: var(--franchise-teal);
            text-decoration: underline;
        }

        /* Divider & recovery utilities */
        .card-divider {
            height: 1px;
            background-color: #e2e8f0;
            margin: 15px 0 12px 0;
        }

        .card-footer-support {
            font-size: 11px;
            color: var(--mockup-text-muted);
            text-align: center;
            line-height: 1.5;
        }

        .card-footer-support a {
            color: var(--franchise-teal);
            text-decoration: none;
            font-weight: 600;
        }

        .card-footer-support a:hover {
            text-decoration: underline;
        }

        /* Overlay recovery panels smoothly hidden */
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
            color: var(--franchise-teal);
            cursor: pointer;
            font-size: 13.5px;
            font-weight: 600;
            margin-bottom: 20px;
            transition: color 0.2s ease;
        }
        .back-nav-trigger:hover {
            color: var(--franchise-dark);
        }

        /* Responsive styling for portrait panel concealment */
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
        
        <!-- Left Side Panel with Emerald background and floating card -->
        <div class="left-panel">
            <div class="deco-shape-top"></div>
            <div class="deco-shape-bottom"></div>

            <div class="login-card">
                
                <!-- ================= CORE LOGIN INTERFACE ================= -->
                <div id="loginFormPane" class="overlay-pane" style="display: block;">
                    <div class="card-header-panel">
                        <div class="lock-icon-wrapper">
                            <i class="fa-solid fa-building-shield"></i>
                        </div>
                        <h2>Center Login</h2>
                        <p>Sign in to your accredited learning console</p>
                    </div>

                    <form id="franchiseLoginForm" onsubmit="handleLoginSubmission(event)">
                        
                        <!-- Center ID Input -->
                        <div class="input-group-custom">
                            <label class="form-label-custom">Center ID<span class="required-star">*</span></label>
                            <input type="text" class="form-control-custom" name="center_id" id="center_id" placeholder="e.g. MGEDU001" required style="font-family: monospace; font-weight: bold; letter-spacing: 0.5px;">
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

                        <!-- Password Input -->
                        <div class="input-group-custom" id="password-input-wrapper">
                            <label class="form-label-custom">Access Password<span class="required-star">*</span></label>
                            <div style="position: relative;">
                                <input type="password" class="form-control-custom" name="password" id="password_field" placeholder="••••••••">
                                <i class="fa-solid fa-eye-slash eye-toggle-icon" onclick="revealPassword('password_field', this)"></i>
                            </div>
                        </div>

                        <!-- OTP Verification Panel -->
                        <div id="otp-input-wrapper" style="display: none; margin-bottom: 15px;">
                            <button type="button" class="btn-premium-outline w-100 py-2 border rounded text-secondary" id="otp-dispatch-btn" onclick="sendLoginOTP()" style="font-size: 13px; font-weight: 600; background: #f8fafc; border-color: #cbd5e1 !important;">
                                <i class="fa-solid fa-paper-plane text-success"></i> Dispatch security OTP to email
                            </button>

                            <div class="otp-box-expanded" id="login-otp-field-box">
                                <label class="form-label-custom">Enter 6-Digit OTP<span class="required-star">*</span></label>
                                <input type="text" class="form-control-custom" name="otp" id="otp_field" placeholder="Verification Code" maxlength="6" pattern="\d{6}">
                            </div>
                        </div>

                        <!-- Action trigger buttons -->
                        <div class="submit-action-panel">
                            <button type="submit" class="btn-green-pill" id="login-submit-btn">
                                Authenticate <i class="fa-solid fa-circle-arrow-right"></i>
                            </button>
                            <div class="register-tip-text">
                                Affiliation? <a target="_blank" href="../#franchise" class="register-tip-link">Apply</a>
                            </div>
                        </div>
                    </form>

                    <!-- Divider & recovery links -->
                    <div class="card-divider"></div>
                    <div class="d-flex justify-content-between" style="font-size: 12.5px;">
                        <a href="javascript:void(0);" onclick="displayPane('forgotFormPane')" class="text-secondary text-decoration-none">
                            <i class="fa-solid fa-key text-success"></i> Forgot Password?
                        </a>
                        <a href="javascript:void(0);" onclick="displayPane('findFormPane')" class="text-secondary text-decoration-none">
                            <i class="fa-solid fa-magnifying-glass text-success"></i> Find Center ID
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
                        <h2>Reset Password</h2>
                        <p>Recover access credentials for your center</p>
                    </div>

                    <form id="forgotForm" onsubmit="handlePasswordRecovery(event)">
                        <!-- Center ID -->
                        <div class="input-group-custom">
                            <label class="form-label-custom">Center ID<span class="required-star">*</span></label>
                            <input type="text" class="form-control-custom" name="forgot_center_id" placeholder="e.g. MGEDU001" required style="font-family: monospace; font-weight: bold; letter-spacing: 0.5px;">
                        </div>

                        <!-- Send OTP button -->
                        <button type="button" class="btn-premium-outline w-100 py-2 border rounded mb-3" id="forgot-otp-btn" onclick="sendForgotPasswordOTP()" style="font-size: 13px; font-weight: 600; background: #f8fafc; border-color: #cbd5e1 !important;">
                            <i class="fa-solid fa-paper-plane text-danger"></i> Dispatch verification OTP
                        </button>

                        <!-- Expanded fields for reset -->
                        <div id="forgot-reset-box" style="display: none; background-color: #fef2f2; border: 1px dashed #f87171; border-radius: 8px; padding: 12px; margin-bottom: 20px;">
                            <div class="input-group-custom">
                                <label class="form-label-custom">Enter 6-Digit OTP<span class="required-star">*</span></label>
                                <input type="text" class="form-control-custom" name="forgot_otp" placeholder="Enter verification OTP" maxlength="6">
                            </div>
                            
                            <div class="input-group-custom mb-0">
                                <label class="form-label-custom">New Access Password<span class="required-star">*</span></label>
                                <div style="position: relative;">
                                    <input type="password" class="form-control-custom" name="forgot_new_password" id="forgot_new_password" placeholder="Min 6 characters">
                                    <i class="fa-solid fa-eye-slash eye-toggle-icon" onclick="revealPassword('forgot_new_password', this)"></i>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn-green-pill w-100 justify-content-center" id="forgot-submit-btn" style="background: linear-gradient(135deg, #ef4444, #f87171); box-shadow: 0 4px 14px rgba(239, 68, 68, 0.3);" disabled>
                            Reset Password <i class="fa-solid fa-rotate-right"></i>
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
                        <h2>Find Center ID</h2>
                        <p>Retrieve registered Center IDs via email</p>
                    </div>

                    <form id="findForm" onsubmit="handleFindCenterId(event)">
                        <!-- Email input -->
                        <div class="input-group-custom">
                            <label class="form-label-custom">Registered Email Address<span class="required-star">*</span></label>
                            <input type="email" class="form-control-custom" name="find_email" placeholder="e.g. director@domain.com" required>
                        </div>

                        <button type="submit" class="btn-green-pill w-100 justify-content-center" id="find-submit-btn" style="background: linear-gradient(135deg, #06b6d4, #0891b2); box-shadow: 0 4px 14px rgba(6, 182, 212, 0.3);">
                            Retrieve Center IDs <i class="fa-solid fa-envelope"></i>
                        </button>
                    </form>
                </div>

                <!-- support information at bottom -->
                <div class="card-divider"></div>
                <div class="card-footer-support">
                    For portal operations, contact support at <a href="mailto:support@mgedu.in">support@mgedu.in</a> or alternatively email <a href="mailto:info@mgedu.in">info@mgedu.in</a>
                </div>

            </div>
        </div>

        <!-- Right Side Panel with networking portrait photography -->
        <div class="right-panel"></div>

    </div>

    <!-- Core Javascript system -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.all.min.js"></script>

    <script>
        let activeLoginMethod = 'password';

        $(document).ready(function() {
            // Check for authorization error redirect parameter
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('error') === 'unauthorized') {
                Swal.fire({
                    title: 'Access Restricted',
                    text: 'You must log in to access the secure franchise dashboard.',
                    icon: 'warning',
                    background: '#ffffff',
                    color: '#1e293b',
                    confirmButtonColor: '#10b981'
                });
            } else if (urlParams.get('logout') === 'success') {
                Swal.fire({
                    title: 'Logged Out',
                    text: 'You have been successfully and securely logged out.',
                    icon: 'success',
                    background: '#ffffff',
                    color: '#1e293b',
                    confirmButtonColor: '#10b981',
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

        // Send OTP verification to center email address via AJAX
        function sendLoginOTP() {
            const centerId = $('#center_id').val().trim();
            const sendBtn = $('#otp-dispatch-btn');

            if (!centerId) {
                Swal.fire({
                    title: 'Attention',
                    text: 'Please enter your Center ID first to receive the verification code.',
                    icon: 'info',
                    background: '#ffffff',
                    color: '#1e293b',
                    confirmButtonColor: '#10b981'
                });
                return;
            }

            sendBtn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Dispatching OTP...');

            $.ajax({
                type: 'POST',
                url: 'login.php',
                data: {
                    action: 'send_login_otp',
                    center_id: centerId
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
                            confirmButtonColor: '#10b981'
                        });
                        $('#login-otp-field-box').slideDown();
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.message,
                            icon: 'error',
                            background: '#ffffff',
                            color: '#1e293b',
                            confirmButtonColor: '#10b981'
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
                        confirmButtonColor: '#10b981'
                    });
                },
                complete: function() {
                    sendBtn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane text-success"></i> Dispatch security OTP to email');
                }
            });
        }

        // Handle center login form submission via AJAX
        function handleLoginSubmission(event) {
            event.preventDefault();
            const centerId = $('#center_id').val().trim();
            const submitBtn = $('#login-submit-btn');

            let postData = {
                center_id: centerId
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
                        confirmButtonColor: '#10b981'
                    });
                    return;
                }
                postData.action = 'verify_login_otp';
                postData.otp = otp;
            }

            submitBtn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Checking...');

            $.ajax({
                type: 'POST',
                url: 'login.php',
                data: postData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'Access Approved',
                            text: 'Login successful! Directing to your dashboard...',
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
                            confirmButtonColor: '#10b981'
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
                        confirmButtonColor: '#10b981'
                    });
                },
                complete: function() {
                    submitBtn.prop('disabled', false).html('Authenticate <i class="fa-solid fa-circle-arrow-right"></i>');
                }
            });
        }

        // Send OTP verification during forgot password flow via AJAX
        function sendForgotPasswordOTP() {
            const centerId = $('input[name="forgot_center_id"]').val().trim();
            const sendBtn = $('#forgot-otp-btn');

            if (!centerId) {
                Swal.fire({
                    title: 'Attention',
                    text: 'Please enter your Center ID first to receive verification OTP.',
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
                url: 'login.php',
                data: {
                    action: 'send_forgot_otp',
                    center_id: centerId
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
                    sendBtn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane text-danger"></i> Dispatch verification OTP');
                }
            });
        }

        // Complete password recovery reset action via AJAX
        function handlePasswordRecovery(event) {
            event.preventDefault();
            const form = $('#forgotForm');
            const centerId = form.find('input[name="forgot_center_id"]').val().trim();
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
                url: 'login.php',
                data: {
                    action: 'reset_password',
                    center_id: centerId,
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
                            confirmButtonColor: '#10b981'
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
                    submitBtn.prop('disabled', false).html('Reset Password <i class="fa-solid fa-rotate-right"></i>');
                }
            });
        }

        // Recover Center IDs by registered email via AJAX
        function handleFindCenterId(event) {
            event.preventDefault();
            const form = $('#findForm');
            const email = form.find('input[name="find_email"]').val().trim();
            const submitBtn = $('#find-submit-btn');

            submitBtn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Searching...');

            $.ajax({
                type: 'POST',
                url: 'login.php',
                data: {
                    action: 'find_center_id',
                    email: email
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
                    submitBtn.prop('disabled', false).html('Retrieve Center IDs <i class="fa-solid fa-envelope"></i>');
                }
            });
        }
    </script>
</body>
</html>
