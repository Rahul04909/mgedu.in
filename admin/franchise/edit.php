<?php
/**
 * MG Education & Social Development Organization
 * Franchise Center Modification Console
 */

// Intercept AJAX modification request prior to rendering standard template shells
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_center_ajax'])) {
    header('Content-Type: application/json');
    try {
        require_once '../auth_check.php';
        require_once '../../includes/config.php';
        
        $db = MG_GetDBConnection();
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        $stmt = $db->prepare("SELECT * FROM franchise_centers WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $center = $stmt->fetch();
        if (!$center) {
            throw new Exception("Franchise center profile not found.");
        }
        
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

        // 1. Intercept Duplicate Email (excluding this center)
        $checkEmail = $db->prepare("SELECT id, center_name FROM franchise_centers WHERE email = :email AND id != :id LIMIT 1");
        $checkEmail->execute(['email' => $email, 'id' => $id]);
        $existEmail = $checkEmail->fetch();
        if ($existEmail) {
            throw new Exception("The email address '" . htmlspecialchars($email) . "' is already registered to another center: '" . htmlspecialchars($existEmail['center_name']) . "'. Please specify a unique active email.");
        }

        // 2. Intercept Duplicate Mobile (excluding this center)
        $checkMobile = $db->prepare("SELECT id, center_name FROM franchise_centers WHERE mobile = :mobile AND id != :id LIMIT 1");
        $checkMobile->execute(['mobile' => $mobile, 'id' => $id]);
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
        
        $center_logo = $center['center_logo'];
        $owner_image = $center['owner_image'];
        $auth_signatory = $center['auth_signatory'];
        $aadhaar_card_file = $center['aadhaar_card_file'];
        $pan_card_file = $center['pan_card_file'];
        $msme_file = $center['msme_file'];

        $root = dirname(dirname(__DIR__));

        $uploadAndUpdate = function($fileKey, $targetDir, $prefix, $currentValue) use ($root) {
            if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES[$fileKey]['tmp_name'];
                $fileName = $_FILES[$fileKey]['name'];
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
                    throw new Exception("Format error on '$fileKey'. Supported values: PDF, JPG, PNG.");
                }
                if (filesize($tmpName) > 2 * 1024 * 1024) {
                    throw new Exception("File too large: '$fileKey' exceeds the 2MB cap.");
                }

                // Delete old file if exists
                if (!empty($currentValue)) {
                    $oldFilePath = $root . '/' . $currentValue;
                    if (file_exists($oldFilePath)) { @unlink($oldFilePath); }
                }

                $newFileName = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                if (move_uploaded_file($tmpName, $targetDir . $newFileName)) {
                    return str_replace('../../', '', $targetDir) . $newFileName;
                }
            }
            return $currentValue;
        };

        $center_logo = $uploadAndUpdate('center_logo', $logoDir, 'logo', $center_logo);
        $owner_image = $uploadAndUpdate('owner_image', $ownerDir, 'owner', $owner_image);
        $auth_signatory = $uploadAndUpdate('auth_signatory', $sigDir, 'sig', $auth_signatory);
        $aadhaar_card_file = $uploadAndUpdate('aadhaar_card_file', $docDir, 'aadhaar', $aadhaar_card_file);
        $pan_card_file = $uploadAndUpdate('pan_card_file', $docDir, 'pan', $pan_card_file);
        $msme_file = $uploadAndUpdate('msme_file', $docDir, 'msme', $msme_file);

        // Update database
        $stmtUpdate = $db->prepare("
            UPDATE `franchise_centers` SET
                password = :password,
                center_name = :center_name,
                email = :email,
                mobile = :mobile,
                pincode = :pincode,
                city = :city,
                state = :state,
                full_address = :full_address,
                center_logo = :center_logo,
                owner_image = :owner_image,
                auth_signatory = :auth_signatory,
                classrooms = :classrooms,
                computers = :computers,
                total_staff = :total_staff,
                lab_type = :lab_type,
                working_hours_from = :working_hours_from,
                working_hours_to = :working_hours_to,
                amenities = :amenities,
                working_days_from = :working_days_from,
                working_days_to = :working_days_to,
                gst_number = :gst_number,
                aadhaar_number = :aadhaar_number,
                aadhaar_card_file = :aadhaar_card_file,
                pan_number = :pan_number,
                pan_card_file = :pan_card_file,
                msme_file = :msme_file,
                franchise_fees = :franchise_fees,
                royalty_percentage = :royalty_percentage
            WHERE id = :id
        ");
        $stmtUpdate->execute([
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
            'royalty_percentage' => $royalty_percentage,
            'id' => $id
        ]);

        // Proactively regenerate certificate PDF in case core info changed
        try {
            MG_GenerateAffiliationCertificatePDF($center['center_id']);
        } catch (Exception $certEx) {
            error_log("Proactive cert regeneration error: " . $certEx->getMessage());
        }

        echo json_encode(['success' => true, 'message' => "Franchise Center modifications saved successfully!"]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

include '../header.php';

$db = MG_GetDBConnection();
$error_message = '';
$success_message = '';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
try {
    $stmt = $db->prepare("SELECT * FROM franchise_centers WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $center = $stmt->fetch();
    if (!$center) {
        throw new Exception("Franchise center profile not found.");
    }
} catch (Exception $e) {
    echo "<script>window.location.href = 'index.php';</script>";
    exit;
}

// Convert amenities string back to array for checkboxes pre-fill
$center_amenities = explode(',', $center['amenities'] ?? '');
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
        background-color: #1e3a8a;
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
        box-shadow: 0 4px 12px rgba(30, 58, 138, 0.18);
        transition: all 0.25s ease;
    }
    .btn-submit-premium:hover {
        background-color: #1d4ed8;
        box-shadow: 0 6px 18px rgba(30, 58, 138, 0.28);
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
                                <input type="text" id="center_id" name="center_id" class="form-control-premium" value="<?= htmlspecialchars($center['center_id'] ?? '') ?>" readonly style="background-color: #f1f5f9; font-family: monospace; font-weight: bold; letter-spacing: 0.5px;">
                                <i class="fa-solid fa-fingerprint input-icon"></i>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label" for="password">Authentication Password <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <input type="text" id="password" name="password" class="form-control-premium" value="<?= htmlspecialchars($center['password'] ?? '') ?>" required style="font-family: monospace; font-weight: bold;">
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
                                <input type="text" id="center_name" name="center_name" class="form-control-premium" placeholder="e.g. MG Skill Development Center" value="<?= htmlspecialchars($center['center_name'] ?? '') ?>" required>
                                <i class="fa-solid fa-building input-icon"></i>
                                <div class="invalid-feedback">Please enter the center name.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label" for="email">Center Email Address <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <input type="email" id="email" name="email" class="form-control-premium" placeholder="center@mgedu.in" value="<?= htmlspecialchars($center['email'] ?? '') ?>" required>
                                <i class="fa-solid fa-envelope input-icon"></i>
                                <div class="invalid-feedback">Please enter a valid email.</div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label" for="mobile">Center Mobile Number <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <input type="tel" id="mobile" name="mobile" class="form-control-premium" placeholder="10-Digit Mobile" value="<?= htmlspecialchars($center['mobile'] ?? '') ?>" required pattern="[6-9][0-9]{9}" maxlength="10">
                                <i class="fa-solid fa-phone input-icon"></i>
                                <div class="invalid-feedback">Enter a valid 10-digit mobile number.</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <label class="form-label" for="pincode">Pincode <span class="text-danger">*</span> <span class="badge bg-info text-white text-lowercase" style="font-size:9.5px;">Auto-fill active</span></label>
                            <div class="field-icon-wrapper">
                                <input type="text" id="pincode" name="pincode" class="form-control-premium" placeholder="6-Digit Pin" value="<?= htmlspecialchars($center['pincode'] ?? '') ?>" required pattern="[0-9]{6}" maxlength="6">
                                <i class="fa-solid fa-location-crosshairs input-icon"></i>
                                <div class="spinner-border spinner-border-sm text-primary pincode-spinner" id="pinLoader" role="status"></div>
                                <div class="invalid-feedback">A valid 6-digit Pincode is required.</div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label" for="city">City / District <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <input type="text" id="city" name="city" class="form-control-premium" placeholder="City" value="<?= htmlspecialchars($center['city'] ?? '') ?>" required>
                                <i class="fa-solid fa-city input-icon"></i>
                                <div class="invalid-feedback">Please specify city.</div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label" for="state">State <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <input type="text" id="state" name="state" class="form-control-premium" placeholder="State" value="<?= htmlspecialchars($center['state'] ?? '') ?>" required>
                                <i class="fa-solid fa-map input-icon"></i>
                                <div class="invalid-feedback">Please specify state.</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <label class="form-label" for="full_address">Full Address <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <input type="text" id="full_address" name="full_address" class="form-control-premium" placeholder="Building No, Street, Landmark" value="<?= htmlspecialchars($center['full_address'] ?? '') ?>" required>
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
                                <input type="number" id="classrooms" name="classrooms" class="form-control-premium" placeholder="e.g. 3" min="1" value="<?= htmlspecialchars($center['classrooms'] ?? '') ?>" required>
                                <i class="fa-solid fa-chalkboard-user input-icon"></i>
                                <div class="invalid-feedback">Classrooms count is required.</div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label" for="computers">Number of Computers <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <input type="number" id="computers" name="computers" class="form-control-premium" placeholder="e.g. 15" min="1" value="<?= htmlspecialchars($center['computers'] ?? '') ?>" required>
                                <i class="fa-solid fa-desktop input-icon"></i>
                                <div class="invalid-feedback">Computers count is required.</div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label" for="total_staff">Total Staff <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <input type="number" id="total_staff" name="total_staff" class="form-control-premium" placeholder="e.g. 5" min="1" value="<?= htmlspecialchars($center['total_staff'] ?? '') ?>" required>
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
                                    <option value="basic" <?= ($center['lab_type'] === 'basic') ? 'selected' : '' ?>>Basic Lab</option>
                                    <option value="advance" <?= ($center['lab_type'] === 'advance') ? 'selected' : '' ?>>Advance Lab</option>
                                </select>
                                <i class="fa-solid fa-flask input-icon"></i>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label" for="working_hours_from">Working Hours (From) <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <input type="time" id="working_hours_from" name="working_hours_from" class="form-control-premium" value="<?= htmlspecialchars($center['working_hours_from'] ?? '') ?>" required>
                                <i class="fa-regular fa-clock input-icon"></i>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label" for="working_hours_to">Working Hours (To) <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <input type="time" id="working_hours_to" name="working_hours_to" class="form-control-premium" value="<?= htmlspecialchars($center['working_hours_to'] ?? '') ?>" required>
                                <i class="fa-regular fa-clock input-icon"></i>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Amenities & Facilities</label>
                            <div class="d-flex gap-3 pt-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="amenities[]" value="power_backup" id="amenity_power" <?= in_array('power_backup', $center_amenities) ? 'checked' : '' ?>>
                                    <label class="form-check-label font-weight-bold" for="amenity_power" style="font-size:12px; color:#475569;">Power Backup</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="amenities[]" value="cctv" id="amenity_cctv" <?= in_array('cctv', $center_amenities) ? 'checked' : '' ?>>
                                    <label class="form-check-label font-weight-bold" for="amenity_cctv" style="font-size:12px; color:#475569;">CCTV Surveillance</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="amenities[]" value="internet" id="amenity_net" <?= in_array('internet', $center_amenities) ? 'checked' : '' ?>>
                                    <label class="form-check-label font-weight-bold" for="amenity_net" style="font-size:12px; color:#475569;">Internet Connection</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4">
                            <label class="form-label" for="working_days_from">Working Week (From)</label>
                            <div class="field-icon-wrapper">
                                <select id="working_days_from" name="working_days_from" class="form-control-premium form-select form-select-premium">
                                    <option value="Monday" <?= ($center['working_days_from'] === 'Monday') ? 'selected' : '' ?>>Monday</option>
                                    <option value="Tuesday" <?= ($center['working_days_from'] === 'Tuesday') ? 'selected' : '' ?>>Tuesday</option>
                                    <option value="Wednesday" <?= ($center['working_days_from'] === 'Wednesday') ? 'selected' : '' ?>>Wednesday</option>
                                    <option value="Thursday" <?= ($center['working_days_from'] === 'Thursday') ? 'selected' : '' ?>>Thursday</option>
                                    <option value="Friday" <?= ($center['working_days_from'] === 'Friday') ? 'selected' : '' ?>>Friday</option>
                                    <option value="Saturday" <?= ($center['working_days_from'] === 'Saturday') ? 'selected' : '' ?>>Saturday</option>
                                    <option value="Sunday" <?= ($center['working_days_from'] === 'Sunday') ? 'selected' : '' ?>>Sunday</option>
                                </select>
                                <i class="fa-solid fa-calendar-day input-icon"></i>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4">
                            <label class="form-label" for="working_days_to">Working Week (To)</label>
                            <div class="field-icon-wrapper">
                                <select id="working_days_to" name="working_days_to" class="form-control-premium form-select form-select-premium">
                                    <option value="Friday" <?= ($center['working_days_to'] === 'Friday') ? 'selected' : '' ?>>Friday</option>
                                    <option value="Saturday" <?= ($center['working_days_to'] === 'Saturday') ? 'selected' : '' ?>>Saturday</option>
                                    <option value="Sunday" <?= ($center['working_days_to'] === 'Sunday') ? 'selected' : '' ?>>Sunday</option>
                                    <option value="Monday" <?= ($center['working_days_to'] === 'Monday') ? 'selected' : '' ?>>Monday</option>
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
                            <label class="form-label">Center Logo <span class="label-helper-text">(Leave blank to keep current)</span></label>
                            <div class="file-upload-box" onclick="triggerFile('center_logo')">
                                <i class="fa-solid fa-image text-muted d-block mb-1" style="font-size: 20px;"></i>
                                <span style="font-size: 11px; font-weight:700;">Center Logo Scan</span>
                                <input type="file" id="center_logo" name="center_logo" accept="image/*" style="display:none;" onchange="previewText(this, 'lblLogo')">
                                <div class="text-success small mt-1 font-weight-bold" id="lblLogo">
                                    <?= !empty($center['center_logo']) ? 'File: ' . basename($center['center_logo']) : '' ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Owner Photo <span class="label-helper-text">(Leave blank to keep current)</span></label>
                            <div class="file-upload-box" onclick="triggerFile('owner_image')">
                                <i class="fa-solid fa-user-gear text-muted d-block mb-1" style="font-size: 20px;"></i>
                                <span style="font-size: 11px; font-weight:700;">Owner Portrait</span>
                                <input type="file" id="owner_image" name="owner_image" accept="image/*" style="display:none;" onchange="previewText(this, 'lblOwner')">
                                <div class="text-success small mt-1 font-weight-bold" id="lblOwner">
                                    <?= !empty($center['owner_image']) ? 'File: ' . basename($center['owner_image']) : '' ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Authorized Signatory / Stamp <span class="label-helper-text">(Leave blank to keep current)</span></label>
                            <div class="file-upload-box" onclick="triggerFile('auth_signatory')">
                                <i class="fa-solid fa-signature text-muted d-block mb-1" style="font-size: 20px;"></i>
                                <span style="font-size: 11px; font-weight:700;">Upload Scanned Stamp Copy</span>
                                <input type="file" id="auth_signatory" name="auth_signatory" accept="image/*" style="display:none;" onchange="previewText(this, 'lblSig')">
                                <div class="text-success small mt-1 font-weight-bold" id="lblSig">
                                    <?= !empty($center['auth_signatory']) ? 'File: ' . basename($center['auth_signatory']) : '' ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <label class="form-label" for="aadhaar_number">Owner Aadhaar Card Number <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper mb-2">
                                <input type="text" id="aadhaar_number" name="aadhaar_number" class="form-control-premium" placeholder="XXXX XXXX XXXX" value="<?= htmlspecialchars($center['aadhaar_number'] ?? '') ?>" required pattern="[0-9]{12}" maxlength="12">
                                <i class="fa-solid fa-id-card input-icon"></i>
                                <div class="invalid-feedback">Enter a valid 12-digit Aadhaar number.</div>
                            </div>
                            <div class="file-upload-box" onclick="triggerFile('aadhaar_card_file')">
                                <i class="fa-solid fa-file-pdf text-danger d-block mb-1" style="font-size: 18px;"></i>
                                <span style="font-size:10px; font-weight:700;">Upload Aadhaar Scan <span class="label-helper-text">(Optional to update)</span></span>
                                <input type="file" id="aadhaar_card_file" name="aadhaar_card_file" accept=".pdf,image/*" style="display:none;" onchange="previewText(this, 'lblAadhaar')">
                                <div class="text-success small mt-1 font-weight-bold" id="lblAadhaar">
                                    <?= !empty($center['aadhaar_card_file']) ? 'File: ' . basename($center['aadhaar_card_file']) : '' ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-4">
                            <label class="form-label" for="pan_number">Owner PAN Card Number <span class="label-helper-text">(Optional)</span></label>
                            <div class="field-icon-wrapper mb-2">
                                <input type="text" id="pan_number" name="pan_number" class="form-control-premium" placeholder="ABCDE1234F" value="<?= htmlspecialchars($center['pan_number'] ?? '') ?>" pattern="[A-Z]{5}[0-9]{4}[A-Z]{1}" maxlength="10">
                                <i class="fa-solid fa-id-card input-icon"></i>
                                <div class="invalid-feedback">Enter a valid 10-digit PAN format.</div>
                            </div>
                            <div class="file-upload-box" onclick="triggerFile('pan_card_file')">
                                <i class="fa-solid fa-file-pdf text-danger d-block mb-1" style="font-size: 18px;"></i>
                                <span style="font-size:10px; font-weight:700;">Upload PAN Card Scan <span class="label-helper-text">(Optional to update)</span></span>
                                <input type="file" id="pan_card_file" name="pan_card_file" accept=".pdf,image/*" style="display:none;" onchange="previewText(this, 'lblPan')">
                                <div class="text-success small mt-1 font-weight-bold" id="lblPan">
                                    <?= !empty($center['pan_card_file']) ? 'File: ' . basename($center['pan_card_file']) : '' ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4 mb-4">
                            <label class="form-label" for="gst_number">GST Registration Number <span class="label-helper-text">(Optional)</span></label>
                            <div class="field-icon-wrapper mb-2">
                                <input type="text" id="gst_number" name="gst_number" class="form-control-premium" placeholder="15 Alphanumerics" value="<?= htmlspecialchars($center['gst_number'] ?? '') ?>" maxlength="15">
                                <i class="fa-solid fa-percent input-icon"></i>
                            </div>
                            <div class="file-upload-box" onclick="triggerFile('msme_file')">
                                <i class="fa-solid fa-file-pdf text-danger d-block mb-1" style="font-size: 18px;"></i>
                                <span style="font-size:10px; font-weight:700;">Upload MSME Certificate <span class="label-helper-text">(Optional to update)</span></span>
                                <input type="file" id="msme_file" name="msme_file" accept=".pdf,image/*" style="display:none;" onchange="previewText(this, 'lblMsme')">
                                <div class="text-success small mt-1 font-weight-bold" id="lblMsme">
                                    <?= !empty($center['msme_file']) ? 'File: ' . basename($center['msme_file']) : '' ?>
                                </div>
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
                                <input type="number" id="franchise_fees" name="franchise_fees" class="form-control-premium" placeholder="e.g. 50000" min="0" value="<?= htmlspecialchars($center['franchise_fees'] ?? '') ?>" required>
                                <i class="fa-solid fa-indian-rupee-sign input-icon"></i>
                                <div class="invalid-feedback">Please enter the yearly franchise fees.</div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label" for="royalty_percentage">Royalty Fee Share (%) <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <input type="number" id="royalty_percentage" name="royalty_percentage" class="form-control-premium" placeholder="e.g. 15" min="0" max="100" step="0.1" value="<?= htmlspecialchars($center['royalty_percentage'] ?? '') ?>" required>
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
                    <i class="fa-solid fa-floppy-disk"></i> Save Modifications
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
                title: 'Saving Center Profile...',
                html: 'Updating franchise configuration logs & pre-compiling affiliation certificate...<br><span style="font-size:12px; color:#64748b;">Please wait, this will take only a moment.</span>',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            let formData = new FormData(form);
            formData.append('submit_center_ajax', '1'); // Trigger AJAX handler on backend
            formData.append('id', '<?= $id ?>'); // Inject current center record ID safely

            $.ajax({
                url: '', // submits to same page (retains GET params)
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Center Profile Updated!',
                            text: response.message,
                            confirmButtonColor: '#28a745'
                        }).then(() => {
                            window.location.href = 'index.php';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Update Failed',
                            text: response.message,
                            confirmButtonColor: '#ef4444'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Server Connection Error',
                        text: 'An error occurred while communicating with the update server: ' + error,
                        confirmButtonColor: '#ef4444'
                    });
                }
            });
        });
    });
</script>

<?php include '../footer.php'; ?>
