<?php
/**
 * MG Education & Social Development Organization
 * Franchise Center Creation Console
 */

// Intercept AJAX registration request prior to rendering standard template shells
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_center_ajax'])) {
    header('Content-Type: application/json');
    try {
        require_once '../auth_check.php';
        require_once '../../includes/config.php';
        
        $db = MG_GetDBConnection();
        
        $post_center_id = trim($_POST['center_id'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $center_name = trim($_POST['center_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $pincode = trim($_POST['pincode'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $full_address = trim($_POST['full_address'] ?? '');
        
        $classrooms = intval($_POST['classrooms'] ?? 0);
        $computers = intval($_POST['computers'] ?? 0);
        $total_staff = intval($_POST['total_staff'] ?? 0);
        $lab_type = trim($_POST['lab_type'] ?? 'basic');
        $working_hours_from = trim($_POST['working_hours_from'] ?? '');
        $working_hours_to = trim($_POST['working_hours_to'] ?? '');
        
        $amenities_arr = $_POST['amenities'] ?? [];
        $amenities = implode(',', $amenities_arr);
        
        $working_days_from = trim($_POST['working_days_from'] ?? '');
        $working_days_to = trim($_POST['working_days_to'] ?? '');
        
        $gst_number = trim($_POST['gst_number'] ?? '');
        $aadhaar_number = trim($_POST['aadhaar_number'] ?? '');
        $pan_number = trim($_POST['pan_number'] ?? '');
        
        $franchise_fees = floatval($_POST['franchise_fees'] ?? 0.00);
        $royalty_percentage = floatval($_POST['royalty_percentage'] ?? 0.00);

        if (empty($center_name) || empty($email) || empty($mobile) || empty($pincode) || empty($city) || empty($state) || empty($full_address) || empty($aadhaar_number)) {
            throw new Exception("All core biographical details, geographical addresses, and Aadhaar numbers are mandatory.");
        }

        // 1. Intercept Duplicate Center ID
        $checkId = $db->prepare("SELECT id FROM franchise_centers WHERE center_id = ? LIMIT 1");
        $checkId->execute([$post_center_id]);
        if ($checkId->fetch()) {
            throw new Exception("The Center ID '" . htmlspecialchars($post_center_id) . "' is already registered. Please reload the page to generate the next unique Center ID.");
        }

        // 2. Intercept Duplicate Email
        $checkEmail = $db->prepare("SELECT id, center_name FROM franchise_centers WHERE email = ? LIMIT 1");
        $checkEmail->execute([$email]);
        $existEmail = $checkEmail->fetch();
        if ($existEmail) {
            throw new Exception("The email address '" . htmlspecialchars($email) . "' is already registered to another center: '" . htmlspecialchars($existEmail['center_name']) . "'. Please specify a unique active email.");
        }

        // 3. Intercept Duplicate Mobile
        $checkMobile = $db->prepare("SELECT id, center_name FROM franchise_centers WHERE mobile = ? LIMIT 1");
        $checkMobile->execute([$mobile]);
        $existMobile = $checkMobile->fetch();
        if ($existMobile) {
            throw new Exception("The mobile number '" . htmlspecialchars($mobile) . "' is already registered to another center: '" . htmlspecialchars($existMobile['center_name']) . "'. Please specify a unique mobile number.");
        }

        // Setup upload folders
        $baseDir = '../../assets/uploads/franchise/';
        $logoDir = $baseDir . 'logos/';
        $ownerDir = $baseDir . 'owners/';
        $sigDir = $baseDir . 'signatures/';
        $docDir = $baseDir . 'documents/';
        
        foreach ([$logoDir, $ownerDir, $sigDir, $docDir] as $dir) {
            if (!file_exists($dir)) { mkdir($dir, 0755, true); }
        }

        $uploadFile = function($fileKey, $targetDir, $prefix, $allowedExts = ['pdf', 'jpg', 'jpeg', 'png']) {
            if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES[$fileKey]['tmp_name'];
                $fileName = $_FILES[$fileKey]['name'];
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if (!in_array($ext, $allowedExts)) {
                    throw new Exception("Upload mismatch: '$fileKey' has an unsupported file format.");
                }
                if (filesize($tmpName) > 2 * 1024 * 1024) {
                    throw new Exception("Upload error: '$fileKey' exceeds the 2MB size cap.");
                }

                $newFileName = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                if (move_uploaded_file($tmpName, $targetDir . $newFileName)) {
                    return str_replace('../../', '', $targetDir) . $newFileName;
                }
            }
            return null;
        };

        // Core uploads validation
        $center_logo = $uploadFile('center_logo', $logoDir, 'logo', ['jpg', 'jpeg', 'png']);
        $owner_image = $uploadFile('owner_image', $ownerDir, 'owner', ['jpg', 'jpeg', 'png']);
        $auth_signatory = $uploadFile('auth_signatory', $sigDir, 'sig', ['jpg', 'jpeg', 'png']);
        
        $aadhaar_card_file = $uploadFile('aadhaar_card_file', $docDir, 'aadhaar', ['pdf', 'jpg', 'jpeg', 'png']);
        $pan_card_file = $uploadFile('pan_card_file', $docDir, 'pan', ['pdf', 'jpg', 'jpeg', 'png']);
        $msme_file = $uploadFile('msme_file', $docDir, 'msme', ['pdf', 'jpg', 'jpeg', 'png']);

        if (empty($auth_signatory)) {
            throw new Exception("Please upload a scanned copy of Authorized Signatory / Stamp.");
        }
        if (empty($aadhaar_card_file)) {
            throw new Exception("Please upload a scanned copy of Owner's Aadhaar Card.");
        }

        // Insert center
        $stmt = $db->prepare("
            INSERT INTO `franchise_centers` (
                center_id, password, center_name, email, mobile, pincode, city, state, full_address,
                center_logo, owner_image, auth_signatory,
                classrooms, computers, total_staff, lab_type, working_hours_from, working_hours_to,
                amenities, working_days_from, working_days_to,
                gst_number, aadhaar_number, aadhaar_card_file, pan_number, pan_card_file, msme_file,
                franchise_fees, royalty_percentage
            ) VALUES (
                :center_id, :password, :center_name, :email, :mobile, :pincode, :city, :state, :full_address,
                :center_logo, :owner_image, :auth_signatory,
                :classrooms, :computers, :total_staff, :lab_type, :working_hours_from, :working_hours_to,
                :amenities, :working_days_from, :working_days_to,
                :gst_number, :aadhaar_number, :aadhaar_card_file, :pan_number, :pan_card_file, :msme_file,
                :franchise_fees, :royalty_percentage
            )
        ");
        $stmt->execute([
            'center_id' => $post_center_id,
            'password' => $password,
            'center_name' => $center_name,
            'email' => $email,
            'mobile' => $mobile,
            'pincode' => $pincode,
            'city' => $city,
            'state' => $state,
            'full_address' => $full_address,
            'center_logo' => $center_logo,
            'owner_image' => $owner_image,
            'auth_signatory' => $auth_signatory,
            'classrooms' => $classrooms,
            'computers' => $computers,
            'total_staff' => $total_staff,
            'lab_type' => $lab_type,
            'working_hours_from' => $working_hours_from ?: null,
            'working_hours_to' => $working_hours_to ?: null,
            'amenities' => $amenities,
            'working_days_from' => $working_days_from ?: null,
            'working_days_to' => $working_days_to ?: null,
            'gst_number' => $gst_number ?: null,
            'aadhaar_number' => $aadhaar_number,
            'aadhaar_card_file' => $aadhaar_card_file,
            'pan_number' => $pan_number ?: null,
            'pan_card_file' => $pan_card_file,
            'msme_file' => $msme_file,
            'franchise_fees' => $franchise_fees,
            'royalty_percentage' => $royalty_percentage
        ]);

        $email_status_text = "The franchise center profile has been successfully saved, and the Affiliation Certificate PDF has been dispatched to: " . htmlspecialchars($email);
        
        // Fast response: Close the request early if possible so the UI returns instantly (under a second)
        if (function_exists('fastcgi_finish_request')) {
            echo json_encode(['success' => true, 'message' => $email_status_text]);
            session_write_close();
            fastcgi_finish_request();
        }

        // Background generation of PDF and PHPMailer dispatch
        try {
            // Generate Affiliation Certificate PDF
            $certFilePath = MG_GenerateAffiliationCertificatePDF($post_center_id);
            
            // Build Professional HTML Email Content
            $emailBody = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
                <div style="background-color: #0d47a1; padding: 25px; text-align: center;">
                    <h1 style="color: #ffffff; margin: 0; font-size: 20px; text-transform: uppercase; letter-spacing: 1px;">MG Education</h1>
                    <p style="color: #93c5fd; margin: 4px 0 0 0; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 2px;">Social Development & Education</p>
                </div>
                <div style="padding: 30px; background-color: #ffffff; color: #334155; line-height: 1.6;">
                    <p style="font-size: 15px; margin-top: 0; color: #0f172a;">Dear Partner,</p>
                    <p style="font-size: 14px;">We are thrilled to officially welcome you as an authorized stakeholder of the <strong>MG Education</strong> network! Your franchise center registration has been successfully processed and recorded in our central registry.</p>
                    
                    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px; margin: 25px 0;">
                        <h4 style="margin: 0 0 10px 0; color: #0d47a1; text-transform: uppercase; font-size: 11.5px; letter-spacing: 0.5px;">Your Portal Login Credentials</h4>
                        <table style="width: 100%; border-collapse: collapse; font-size: 13.5px;">
                            <tr>
                                <td style="padding: 6px 0; font-weight: bold; color: #475569; width: 120px; border-bottom: 1px solid #f1f5f9;">Center ID:</td>
                                <td style="padding: 6px 0; font-family: monospace; font-weight: bold; color: #0d47a1; font-size: 14px; border-bottom: 1px solid #f1f5f9;">' . htmlspecialchars($post_center_id) . '</td>
                            </tr>
                            <tr>
                                <td style="padding: 6px 0; font-weight: bold; color: #475569; border-bottom: 1px solid #f1f5f9;">Password:</td>
                                <td style="padding: 6px 0; font-family: monospace; font-weight: bold; color: #b45309; font-size: 14px; border-bottom: 1px solid #f1f5f9;">' . htmlspecialchars($password) . '</td>
                            </tr>
                            <tr>
                                <td style="padding: 6px 0; font-weight: bold; color: #475569; border-bottom: 1px solid #f1f5f9;">Center Name:</td>
                                <td style="padding: 6px 0; color: #0f172a; font-weight: bold; border-bottom: 1px solid #f1f5f9;">' . htmlspecialchars($center_name) . '</td>
                            </tr>
                            <tr>
                                <td style="padding: 6px 0; font-weight: bold; color: #475569;">Registered Email:</td>
                                <td style="padding: 6px 0; color: #0f172a; font-weight: bold;">' . htmlspecialchars($email) . '</td>
                            </tr>
                        </table>
                    </div>
                    
                    <p style="font-size: 14px;"><strong>Affiliation Certificate Attached:</strong></p>
                    <p style="font-size: 13.5px; color: #475569; margin-top: -8px;">We have generated your official, legally sealed <strong>Affiliation Certificate</strong> in standard A4 Portrait format, featuring our accredited signature and stamp, and attached it directly to this email. Please download, print, and display it prominently at your training center premises.</p>
                    
                    <p style="font-size: 13.5px; margin-bottom: 25px;">Please log in to your franchise administrative portal at our official website using your Center ID to manage student admission registries, course structures, and training catalogs.</p>
                    
                    <hr style="border: 0; border-top: 1px solid #e2e8f0; margin-bottom: 20px;">
                    
                    <p style="font-size: 12px; color: #64748b; margin-bottom: 0; line-height: 1.5;">Warm regards,<br>
                    <strong>Affiliations & Accreditation Board</strong><br>
                    MG Education Central Organization<br>
                    Website: <a href="https://www.mgedu.in" style="color: #0d47a1; text-decoration: none; font-weight: bold;">www.mgedu.in</a> | Email: support@mgedu.in</p>
                </div>
                <div style="background-color: #f8fafc; padding: 15px; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0;">
                    This is an automated notification. Please do not reply directly to this email.
                </div>
            </div>
            ';
            
            $emailOptions = [
                'isHTML' => true
            ];
            if ($certFilePath && file_exists($certFilePath)) {
                $emailOptions['attachments'] = [$certFilePath];
            }
            
            MG_SendMail($email, "Accredited Franchise Affiliation Confirmation - " . $post_center_id, $emailBody, $emailOptions);
        } catch (Exception $mailEx) {
            error_log("Franchise registration email dispatch failure: " . $mailEx->getMessage());
        }

        if (!function_exists('fastcgi_finish_request')) {
            echo json_encode(['success' => true, 'message' => $email_status_text]);
            exit;
        }
    } catch (Exception $e) {
        if (!headers_sent()) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

include '../header.php';

$db = MG_GetDBConnection();
$error_message = '';
$success_message = '';

// Self-Healing Database: Create franchise_centers table automatically if not exists
try {
    $db->query("SELECT 1 FROM `franchise_centers` LIMIT 1");
} catch (Exception $e) {
    try {
        $createSQL = "
            CREATE TABLE IF NOT EXISTS `franchise_centers` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `center_id` VARCHAR(50) NOT NULL UNIQUE,
                `password` VARCHAR(255) NOT NULL,
                `center_name` VARCHAR(255) NOT NULL,
                `email` VARCHAR(255) NOT NULL,
                `mobile` VARCHAR(20) NOT NULL,
                `pincode` VARCHAR(10) NOT NULL,
                `city` VARCHAR(100) NOT NULL,
                `state` VARCHAR(100) NOT NULL,
                `full_address` TEXT NOT NULL,
                `center_logo` VARCHAR(255) NULL,
                `owner_image` VARCHAR(255) NULL,
                `auth_signatory` VARCHAR(255) NULL,
                
                -- Infrastructure Details
                `classrooms` INT NOT NULL DEFAULT 0,
                `computers` INT NOT NULL DEFAULT 0,
                `total_staff` INT NOT NULL DEFAULT 0,
                `lab_type` VARCHAR(50) NOT NULL DEFAULT 'basic',
                `working_hours_from` TIME NULL,
                `working_hours_to` TIME NULL,
                `amenities` TEXT NULL,
                `working_days_from` VARCHAR(50) NULL,
                `working_days_to` VARCHAR(50) NULL,
                
                -- Documentation
                `gst_number` VARCHAR(50) NULL,
                `aadhaar_number` VARCHAR(20) NOT NULL,
                `aadhaar_card_file` VARCHAR(255) NULL,
                `pan_number` VARCHAR(20) NULL,
                `pan_card_file` VARCHAR(255) NULL,
                `msme_file` VARCHAR(255) NULL,
                
                -- Fees Structure
                `franchise_fees` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `royalty_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                
                `status` VARCHAR(50) DEFAULT 'active',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $db->exec($createSQL);
    } catch (Exception $ex) {
        $error_message = "Failed to establish franchise database tables: " . $ex->getMessage();
    }
}

// Generate Automatic Center ID (e.g. MGEDU-CTR-2026-001)
$year = date('Y');
$prefix = "MGEDU-CTR-" . $year . "-";
$center_id = "";
try {
    $stmtSeq = $db->prepare("SELECT center_id FROM franchise_centers WHERE center_id LIKE ? ORDER BY id DESC LIMIT 1");
    $stmtSeq->execute([$prefix . '%']);
    $lastRecord = $stmtSeq->fetch();

    $nextSeries = 1;
    if ($lastRecord && !empty($lastRecord['center_id'])) {
        $lastSeriesStr = substr($lastRecord['center_id'], -3);
        $nextSeries = intval($lastSeriesStr) + 1;
    }
    $center_id = $prefix . sprintf("%03d", $nextSeries);
} catch (Exception $e) {
    $center_id = $prefix . "001";
}

// Generate secure 8-character random password
$auto_password = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8);
?>


<style>
    .card-premium {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.01);
        overflow: hidden;
        margin-bottom: 24px;
    }
    .card-premium-header {
        background: #f8fafc;
        border-bottom: 1px solid #cbd5e1;
        padding: 16px 20px;
        font-size: 13.5px;
        font-weight: 800;
        color: #1e3a8a;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .card-premium-body {
        padding: 24px 20px 8px 20px;
    }
    .form-label {
        font-size: 11px;
        font-weight: 750;
        color: #475569;
        text-transform: uppercase;
        margin-bottom: 6px;
        display: block;
        letter-spacing: 0.3px;
    }
    .form-control-premium {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 10px 14px 10px 40px;
        font-size: 13.5px;
        font-weight: 600;
        color: #1e293b;
        height: 42px;
        transition: all 0.25s ease;
    }
    .form-control-premium:focus {
        border-color: #1e3a8a;
        outline: none;
        box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.08);
    }
    .field-icon-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        width: 100%;
    }
    .field-icon-wrapper .input-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 15px;
        pointer-events: none;
    }
    .form-select-premium {
        appearance: none;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23475569' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right 14px center !important;
        background-size: 14px 10px !important;
        padding-right: 40px !important;
    }
    .btn-submit-premium {
        background-color: #28a745;
        color: #ffffff;
        border: none;
        border-radius: 50px;
        padding: 14px 40px;
        font-size: 14px;
        font-weight: 800;
        text-transform: uppercase;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.18);
        transition: all 0.25s ease;
    }
    .btn-submit-premium:hover {
        background-color: #218838;
        box-shadow: 0 6px 18px rgba(40, 167, 69, 0.28);
        transform: translateY(-1px);
    }
    .pincode-spinner {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        display: none;
    }
    .file-upload-box {
        border: 2px dashed #cbd5e1;
        border-radius: 8px;
        padding: 16px;
        background: #f8fafc;
        text-align: center;
        cursor: pointer;
        transition: all 0.25s ease;
    }
    .file-upload-box:hover {
        border-color: #1e3a8a;
        background: #eff6ff;
    }
</style>

<div class="row pt-3 justify-content-center">
    <div class="col-xl-10">
        <form method="POST" action="" enctype="multipart/form-data" id="centerForm" class="needs-validation" novalidate>
            
            <!-- SECTION 1: Credentials and Identification -->
            <div class="card-premium animate__animated animate__fadeInUp">
                <div class="card-premium-header">
                    <i class="fa-solid fa-key"></i> 01. Center Identity & Login Credentials
                </div>
                <div class="card-premium-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label" for="center_id">Center ID (Automatic / Non-Customizable)</label>
                            <div class="field-icon-wrapper">
                                <input type="text" id="center_id" name="center_id" class="form-control-premium" value="<?= htmlspecialchars($center_id) ?>" readonly style="background-color: #f1f5f9; font-family: monospace; font-weight: bold; letter-spacing: 0.5px;">
                                <i class="fa-solid fa-fingerprint input-icon"></i>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label" for="password">Authentication Password <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <input type="text" id="password" name="password" class="form-control-premium" value="<?= htmlspecialchars($auto_password) ?>" required style="font-family: monospace; font-weight: bold;">
                                <i class="fa-solid fa-lock input-icon"></i>
                                <div class="invalid-feedback">A password is required for this center.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: General Specifications & Pincode Auto-Fill -->
            <div class="card-premium animate__animated animate__fadeInUp">
                <div class="card-premium-header">
                    <i class="fa-solid fa-map-location-dot"></i> 02. General & Geographic Specifications
                </div>
                <div class="card-premium-body">
                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <label class="form-label" for="center_name">Franchise Center Name <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <input type="text" id="center_name" name="center_name" class="form-control-premium" placeholder="e.g. MG Skill Development Center" required>
                                <i class="fa-solid fa-building input-icon"></i>
                                <div class="invalid-feedback">Please enter the center name.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label" for="email">Center Email Address <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <input type="email" id="email" name="email" class="form-control-premium" placeholder="center@mgedu.in" required>
                                <i class="fa-solid fa-envelope input-icon"></i>
                                <div class="invalid-feedback">Please enter a valid email.</div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label" for="mobile">Center Mobile Number <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <input type="tel" id="mobile" name="mobile" class="form-control-premium" placeholder="10-Digit Mobile" required pattern="[6-9][0-9]{9}" maxlength="10">
                                <i class="fa-solid fa-phone input-icon"></i>
                                <div class="invalid-feedback">Enter a valid 10-digit mobile number.</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <label class="form-label" for="pincode">Pincode <span class="text-danger">*</span> <span class="badge bg-info text-white text-lowercase" style="font-size:9.5px;">Auto-fill active</span></label>
                            <div class="field-icon-wrapper">
                                <input type="text" id="pincode" name="pincode" class="form-control-premium" placeholder="6-Digit Pin" required pattern="[0-9]{6}" maxlength="6">
                                <i class="fa-solid fa-location-crosshairs input-icon"></i>
                                <div class="spinner-border spinner-border-sm text-primary pincode-spinner" id="pinLoader" role="status"></div>
                                <div class="invalid-feedback">A valid 6-digit Pincode is required.</div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label" for="city">City / District <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <input type="text" id="city" name="city" class="form-control-premium" placeholder="City" required>
                                <i class="fa-solid fa-city input-icon"></i>
                                <div class="invalid-feedback">Please specify city.</div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label" for="state">State <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <input type="text" id="state" name="state" class="form-control-premium" placeholder="State" required>
                                <i class="fa-solid fa-map input-icon"></i>
                                <div class="invalid-feedback">Please specify state.</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <label class="form-label" for="full_address">Full Address <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <input type="text" id="full_address" name="full_address" class="form-control-premium" placeholder="Building No, Street, Landmark" required>
                                <i class="fa-solid fa-house-chimney input-icon"></i>
                                <div class="invalid-feedback">Please enter the full address.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: Physical Infrastructure & Staffing -->
            <div class="card-premium animate__animated animate__fadeInUp">
                <div class="card-premium-header">
                    <i class="fa-solid fa-network-wired"></i> 03. Infrastructure Details & Facilities
                </div>
                <div class="card-premium-body">
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <label class="form-label" for="classrooms">Number of Classrooms <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <input type="number" id="classrooms" name="classrooms" class="form-control-premium" placeholder="e.g. 3" min="1" required>
                                <i class="fa-solid fa-chalkboard-user input-icon"></i>
                                <div class="invalid-feedback">Classrooms count is required.</div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label" for="computers">Number of Computers <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <input type="number" id="computers" name="computers" class="form-control-premium" placeholder="e.g. 15" min="1" required>
                                <i class="fa-solid fa-desktop input-icon"></i>
                                <div class="invalid-feedback">Computers count is required.</div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label" for="total_staff">Total Staff <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <input type="number" id="total_staff" name="total_staff" class="form-control-premium" placeholder="e.g. 5" min="1" required>
                                <i class="fa-solid fa-users input-icon"></i>
                                <div class="invalid-feedback">Total staff count is required.</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <label class="form-label" for="lab_type">Lab Standards <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <select id="lab_type" name="lab_type" class="form-control-premium form-select form-select-premium" required>
                                    <option value="basic">Basic Lab</option>
                                    <option value="advance">Advance Lab</option>
                                </select>
                                <i class="fa-solid fa-flask input-icon"></i>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label" for="working_hours_from">Working Hours (From) <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <input type="time" id="working_hours_from" name="working_hours_from" class="form-control-premium" required>
                                <i class="fa-regular fa-clock input-icon"></i>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label" for="working_hours_to">Working Hours (To) <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <input type="time" id="working_hours_to" name="working_hours_to" class="form-control-premium" required>
                                <i class="fa-regular fa-clock input-icon"></i>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Amenities & Facilities</label>
                            <div class="d-flex gap-3 pt-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="amenities[]" value="power_backup" id="amenity_power">
                                    <label class="form-check-label font-weight-bold" for="amenity_power" style="font-size:12px; color:#475569;">Power Backup</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="amenities[]" value="cctv" id="amenity_cctv">
                                    <label class="form-check-label font-weight-bold" for="amenity_cctv" style="font-size:12px; color:#475569;">CCTV Surveillance</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="amenities[]" value="internet" id="amenity_net">
                                    <label class="form-check-label font-weight-bold" for="amenity_net" style="font-size:12px; color:#475569;">Internet Connection</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4">
                            <label class="form-label" for="working_days_from">Working Week (From)</label>
                            <div class="field-icon-wrapper">
                                <select id="working_days_from" name="working_days_from" class="form-control-premium form-select form-select-premium">
                                    <option value="Monday">Monday</option>
                                    <option value="Tuesday">Tuesday</option>
                                    <option value="Wednesday">Wednesday</option>
                                    <option value="Thursday">Thursday</option>
                                    <option value="Friday">Friday</option>
                                    <option value="Saturday">Saturday</option>
                                    <option value="Sunday">Sunday</option>
                                </select>
                                <i class="fa-solid fa-calendar-day input-icon"></i>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4">
                            <label class="form-label" for="working_days_to">Working Week (To)</label>
                            <div class="field-icon-wrapper">
                                <select id="working_days_to" name="working_days_to" class="form-control-premium form-select form-select-premium">
                                    <option value="Friday">Friday</option>
                                    <option value="Saturday" selected>Saturday</option>
                                    <option value="Sunday">Sunday</option>
                                    <option value="Monday">Monday</option>
                                </select>
                                <i class="fa-solid fa-calendar-day input-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 4: Image Uploads & Scanned Documents -->
            <div class="card-premium animate__animated animate__fadeInUp">
                <div class="card-premium-header">
                    <i class="fa-solid fa-file-shield"></i> 04. Document Registry & Scanned Verification Uploads
                </div>
                <div class="card-premium-body">
                    
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Center Logo</label>
                            <div class="file-upload-box" onclick="triggerFile('center_logo')">
                                <i class="fa-solid fa-image text-muted d-block mb-1" style="font-size: 20px;"></i>
                                <span style="font-size: 11px; font-weight:700;">Center Logo Scan</span>
                                <input type="file" id="center_logo" name="center_logo" accept="image/*" style="display:none;" onchange="previewText(this, 'lblLogo')">
                                <div class="text-success small mt-1 font-weight-bold" id="lblLogo"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Owner Photo</label>
                            <div class="file-upload-box" onclick="triggerFile('owner_image')">
                                <i class="fa-solid fa-user-gear text-muted d-block mb-1" style="font-size: 20px;"></i>
                                <span style="font-size: 11px; font-weight:700;">Owner Portrait</span>
                                <input type="file" id="owner_image" name="owner_image" accept="image/*" style="display:none;" onchange="previewText(this, 'lblOwner')">
                                <div class="text-success small mt-1 font-weight-bold" id="lblOwner"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Authorized Signatory / Stamp <span class="text-danger">*</span></label>
                            <div class="file-upload-box" onclick="triggerFile('auth_signatory')">
                                <i class="fa-solid fa-signature text-muted d-block mb-1" style="font-size: 20px;"></i>
                                <span style="font-size: 11px; font-weight:700;">Upload Scanned Stamp Copy</span>
                                <input type="file" id="auth_signatory" name="auth_signatory" accept="image/*" style="display:none;" onchange="previewText(this, 'lblSig')" required>
                                <div class="text-success small mt-1 font-weight-bold" id="lblSig"></div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <label class="form-label" for="aadhaar_number">Owner Aadhaar Card Number <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper mb-2">
                                <input type="text" id="aadhaar_number" name="aadhaar_number" class="form-control-premium" placeholder="XXXX XXXX XXXX" required pattern="[0-9]{12}" maxlength="12">
                                <i class="fa-solid fa-id-card input-icon"></i>
                                <div class="invalid-feedback">Enter a valid 12-digit Aadhaar number.</div>
                            </div>
                            <div class="file-upload-box" onclick="triggerFile('aadhaar_card_file')">
                                <i class="fa-solid fa-file-pdf text-danger d-block mb-1" style="font-size: 18px;"></i>
                                <span style="font-size:10px; font-weight:700;">Upload Aadhaar Scan</span>
                                <input type="file" id="aadhaar_card_file" name="aadhaar_card_file" accept=".pdf,image/*" style="display:none;" onchange="previewText(this, 'lblAadhaar')" required>
                                <div class="text-success small mt-1 font-weight-bold" id="lblAadhaar"></div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-4">
                            <label class="form-label" for="pan_number">Owner PAN Card Number <span class="label-helper-text">(Optional)</span></label>
                            <div class="field-icon-wrapper mb-2">
                                <input type="text" id="pan_number" name="pan_number" class="form-control-premium" placeholder="ABCDE1234F" pattern="[A-Z]{5}[0-9]{4}[A-Z]{1}" maxlength="10">
                                <i class="fa-solid fa-id-card input-icon"></i>
                                <div class="invalid-feedback">Enter a valid 10-digit PAN format.</div>
                            </div>
                            <div class="file-upload-box" onclick="triggerFile('pan_card_file')">
                                <i class="fa-solid fa-file-pdf text-danger d-block mb-1" style="font-size: 18px;"></i>
                                <span style="font-size:10px; font-weight:700;">Upload PAN Card Scan</span>
                                <input type="file" id="pan_card_file" name="pan_card_file" accept=".pdf,image/*" style="display:none;" onchange="previewText(this, 'lblPan')">
                                <div class="text-success small mt-1 font-weight-bold" id="lblPan"></div>
                            </div>
                        </div>

                        <div class="col-md-4 mb-4">
                            <label class="form-label" for="gst_number">GST Registration Number <span class="label-helper-text">(Optional)</span></label>
                            <div class="field-icon-wrapper mb-2">
                                <input type="text" id="gst_number" name="gst_number" class="form-control-premium" placeholder="15 Alphanumerics" maxlength="15">
                                <i class="fa-solid fa-percent input-icon"></i>
                            </div>
                            <div class="file-upload-box" onclick="triggerFile('msme_file')">
                                <i class="fa-solid fa-file-pdf text-danger d-block mb-1" style="font-size: 18px;"></i>
                                <span style="font-size:10px; font-weight:700;">Upload MSME Certificate <span class="label-helper-text">(Optional)</span></span>
                                <input type="file" id="msme_file" name="msme_file" accept=".pdf,image/*" style="display:none;" onchange="previewText(this, 'lblMsme')">
                                <div class="text-success small mt-1 font-weight-bold" id="lblMsme"></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- SECTION 5: Financial Metrics & Royalty -->
            <div class="card-premium animate__animated animate__fadeInUp">
                <div class="card-premium-header">
                    <i class="fa-solid fa-indian-rupee-sign"></i> 05. Franchise Financial Structure
                </div>
                <div class="card-premium-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label" for="franchise_fees">Franchise Fee (INR / Year) <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <input type="number" id="franchise_fees" name="franchise_fees" class="form-control-premium" placeholder="e.g. 50000" min="0" required>
                                <i class="fa-solid fa-indian-rupee-sign input-icon"></i>
                                <div class="invalid-feedback">Please enter the yearly franchise fees.</div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label" for="royalty_percentage">Royalty Fee Share (%) <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <input type="number" id="royalty_percentage" name="royalty_percentage" class="form-control-premium" placeholder="e.g. 15" min="0" max="100" step="0.1" required>
                                <i class="fa-solid fa-percent input-icon"></i>
                                <div class="invalid-feedback">Royalty percentage share is required (0 to 100).</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Submit action -->
            <div class="text-center mb-5 mt-2">
                <button type="submit" name="submit_center" class="btn-submit-premium" id="btnSave">
                    <i class="fa-solid fa-circle-check"></i> Register Franchise Center
                </button>
            </div>

        </form>
    </div>
</div>

<script>
    function triggerFile(inputId) {
        document.getElementById(inputId).click();
    }
    
    function previewText(inputEl, labelId) {
        const file = inputEl.files[0];
        if (file) {
            $(`#${labelId}`).text('Selected: ' + file.name + ' (' + (file.size / (1024 * 1024)).toFixed(2) + ' MB)');
        } else {
            $(`#${labelId}`).text('');
        }
    }

    $(document).ready(function() {
        // Auto Pincode Integration via IndiaPost API
        $('#pincode').on('input', function() {
            let pin = this.value.replace(/\D/g, '').substring(0, 6);
            this.value = pin;

            if (pin.length === 6) {
                $('#pinLoader').css('display', 'block');
                
                $.ajax({
                    url: 'https://api.postalpincode.in/pincode/' + pin,
                    method: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        $('#pinLoader').css('display', 'none');
                        if (data && data[0] && data[0].Status === 'Success') {
                            const postOffice = data[0].PostOffice[0];
                            if (postOffice) {
                                $('#city').val(postOffice.District || postOffice.Block);
                                $('#state').val(postOffice.State);
                            }
                        } else {
                            Swal.fire({
                                icon: 'warning',
                                title: 'No Record Found',
                                text: 'Entered pincode was not found in IndiaPost directory.',
                                timer: 2500,
                                showConfirmButton: false
                            });
                        }
                    },
                    error: function() {
                        $('#pinLoader').css('display', 'none');
                    }
                });
            }
        });

        // Numeric constraint inputs
        $('#mobile').on('input', function() {
            this.value = this.value.replace(/\D/g, '').substring(0, 10);
        });
        $('#aadhaar_number').on('input', function() {
            this.value = this.value.replace(/\D/g, '').substring(0, 12);
        });

        // Intercept form submit event to process via modern AJAX FormData
        $('#centerForm').on('submit', function(e) {
            e.preventDefault();
            const form = this;
            if (!form.checkValidity()) {
                e.stopPropagation();
                $(form).addClass('was-validated');
                const firstInvalid = $(form).find(':invalid').first()[0];
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalid.focus();
                }
                return;
            }

            // Show beautiful loading overlay on the same page (no page reload!)
            Swal.fire({
                title: 'Registering Franchise Center...',
                html: 'Securing database credentials & pre-compiling affiliation certificate...<br><span style="font-size:12px; color:#64748b;">Please wait, this will take only a moment.</span>',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            let formData = new FormData(form);
            formData.append('submit_center_ajax', '1'); // Trigger AJAX handler on backend

            $.ajax({
                url: '', // submits to same page
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Registration Successful!',
                            text: response.message,
                            confirmButtonColor: '#28a745'
                        }).then(() => {
                            window.location.href = 'index.php';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Registration Failed',
                            text: response.message,
                            confirmButtonColor: '#ef4444'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Server Connection Error',
                        text: 'An error occurred while communicating with the registration server: ' + error,
                        confirmButtonColor: '#ef4444'
                    });
                }
            });
        });
    });
</script>

<?php include '../footer.php'; ?>
