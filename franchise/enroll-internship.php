<?php
/**
 * MG Education & Social Development Organization
 * Franchise Portal - Manual Internship Student Enrollment Wizard
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Handle Form Submission (Post Request Processing before Header)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_admission'])) {
    header('Content-Type: application/json');
    require_once dirname(__DIR__) . '/includes/config.php';

    // Route authorization guard
    if (!isset($_SESSION['center_role']) || $_SESSION['center_role'] !== 'franchise' || empty($_SESSION['center_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please login.']);
        exit();
    }

    $db = MG_GetDBConnection();
    $center_id = $_SESSION['center_id'];
    $center_db_id = $_SESSION['center_logged_id'];

    try {
        $internship_id = intval($_POST['internship_id'] ?? 0);
        $student_name = trim($_POST['student_name'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $father_name = trim($_POST['father_name'] ?? '');
        $mother_name = trim($_POST['mother_name'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $pincode = trim($_POST['pincode'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $session_name = trim($_POST['session_name'] ?? '');

        // Mandatory fields check
        if (empty($internship_id) || empty($session_name) || empty($student_name) || empty($dob) || empty($email) || empty($mobile) || empty($gender) ||
            empty($father_name) || empty($mother_name) || empty($state) || empty($city) || empty($district) || 
            empty($pincode) || empty($address)) {
            throw new Exception("All general, parental, and geographic address fields are strictly mandatory.");
        }

        // Fetch selected internship pricing
        $iStmt = $db->prepare("SELECT name, sales_price FROM internships WHERE id = ? LIMIT 1");
        $iStmt->execute([$internship_id]);
        $internshipRow = $iStmt->fetch();
        if (!$internshipRow) {
            throw new Exception("Selected internship is invalid or has been retired.");
        }
        $internship_sales_price = floatval($internshipRow['sales_price']);

        // Check Franchise Wallet balance
        $wStmt = $db->prepare("SELECT balance FROM `franchise_wallets` WHERE `center_id` = ? FOR UPDATE");
        $wStmt->execute([$center_id]);
        $wallet = $wStmt->fetch();
        $balance = $wallet ? floatval($wallet['balance']) : 0.00;

        if ($balance < $internship_sales_price) {
            throw new Exception("Insufficient wallet balance. Internship enrollment fee: ₹" . number_format($internship_sales_price, 2) . ", Current Balance: ₹" . number_format($balance, 2) . ". Please recharge your wallet first.");
        }

        $highest_qualification = trim($_POST['highest_qualification'] ?? '');
        if (empty($highest_qualification)) {
            throw new Exception("Please specify candidate's Highest Educational Qualification.");
        }

        // Stepper files upload configuration
        $docDir = dirname(__DIR__) . '/assets/uploads/admissions/documents/';
        if (!file_exists($docDir)) { mkdir($docDir, 0755, true); }

        $uploadMarksheet = function($fileKey, $prefix) use ($docDir) {
            if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES[$fileKey]['tmp_name'];
                $fileName = $_FILES[$fileKey]['name'];
                
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $tmpName);
                finfo_close($finfo);
                
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if ($ext !== 'pdf' || $mimeType !== 'application/pdf') {
                    throw new Exception("Document upload error: '" . str_replace('_', ' ', $prefix) . "' must be a valid PDF file.");
                }
                
                if (filesize($tmpName) > 2 * 1024 * 1024) {
                    throw new Exception("Document upload error: '" . str_replace('_', ' ', $prefix) . "' PDF exceeds the 2MB size limit.");
                }
                
                $newDocName = $prefix . '_' . bin2hex(random_bytes(8)) . '.pdf';
                if (move_uploaded_file($tmpName, $docDir . $newDocName)) {
                    return 'assets/uploads/admissions/documents/' . $newDocName;
                } else {
                    throw new Exception("Failed to write '" . str_replace('_', ' ', $prefix) . "' PDF file to disk.");
                }
            } else {
                throw new Exception("Please upload a valid scanned PDF of '" . str_replace('_', ' ', $prefix) . "'.");
            }
        };

        // Aadhaar Verification
        $aadhaar_number = trim($_POST['aadhaar_number'] ?? '');
        $aadhaar_number = str_replace(' ', '', $aadhaar_number);
        if (empty($aadhaar_number) || strlen($aadhaar_number) !== 12 || !ctype_digit($aadhaar_number)) {
            throw new Exception("A valid 12-digit Aadhaar number is mandatory.");
        }
        $aadhaar_card = $uploadMarksheet('aadhaar_card', 'aadhaar_card');

        // Qualification details
        $school_name = null;
        $class_10_roll = null;
        $class_10_board = null;
        $class_10_school = null;
        $class_10_marksheet = null;
        $class_12_roll = null;
        $class_12_board = null;
        $class_12_school = null;
        $class_12_marksheet = null;
        $college_name = null;
        $college_roll = null;
        $college_degree = null;
        $college_passing_year = null;
        $college_marks_type = null;
        $college_marks_value = null;
        $college_marksheet = null;

        if ($highest_qualification === 'below_10th_5' || $highest_qualification === 'below_10th_8') {
            $school_name = trim($_POST['school_name'] ?? '');
            if (empty($school_name)) {
                throw new Exception("School Name is required for qualifications below Class 10th.");
            }
        } elseif ($highest_qualification === 'class_10th') {
            $class_10_roll = trim($_POST['class_10_roll'] ?? '');
            $class_10_board = trim($_POST['class_10_board'] ?? '');
            $class_10_school = trim($_POST['class_10_school'] ?? '');
            if (empty($class_10_roll) || empty($class_10_board) || empty($class_10_school)) {
                throw new Exception("Please fill in all Class 10th academic parameters.");
            }
            $class_10_marksheet = $uploadMarksheet('class_10_marksheet', 'class_10_marksheet');
        } elseif ($highest_qualification === 'class_12th') {
            $class_10_roll = trim($_POST['class_10_roll'] ?? '');
            $class_10_board = trim($_POST['class_10_board'] ?? '');
            $class_10_school = trim($_POST['class_10_school'] ?? '');
            $class_12_roll = trim($_POST['class_12_roll'] ?? '');
            $class_12_board = trim($_POST['class_12_board'] ?? '');
            $class_12_school = trim($_POST['class_12_school'] ?? '');
            if (empty($class_10_roll) || empty($class_10_board) || empty($class_10_school) ||
                empty($class_12_roll) || empty($class_12_board) || empty($class_12_school)) {
                throw new Exception("Please complete all academic details for Class 10th and Class 12th.");
            }
            $class_10_marksheet = $uploadMarksheet('class_10_marksheet', 'class_10_marksheet');
            $class_12_marksheet = $uploadMarksheet('class_12_marksheet', 'class_12_marksheet');
        } elseif ($highest_qualification === 'graduation' || $highest_qualification === 'post_graduation' || $highest_qualification === 'phd') {
            $class_10_roll = trim($_POST['class_10_roll'] ?? '');
            $class_10_board = trim($_POST['class_10_board'] ?? '');
            $class_10_school = trim($_POST['class_10_school'] ?? '');
            $class_12_roll = trim($_POST['class_12_roll'] ?? '');
            $class_12_board = trim($_POST['class_12_board'] ?? '');
            $class_12_school = trim($_POST['class_12_school'] ?? '');
            $college_name = trim($_POST['college_name'] ?? '');
            $college_roll = trim($_POST['college_roll'] ?? '');
            $college_degree = trim($_POST['college_degree'] ?? '');
            $college_passing_year = trim($_POST['college_passing_year'] ?? '');
            $college_marks_type = trim($_POST['college_marks_type'] ?? '');
            $college_marks_value = trim($_POST['college_marks_value'] ?? '');
            
            if (empty($class_10_roll) || empty($class_10_board) || empty($class_10_school) ||
                empty($class_12_roll) || empty($class_12_board) || empty($class_12_school) ||
                empty($college_name) || empty($college_roll) || empty($college_degree) || empty($college_passing_year) ||
                empty($college_marks_type) || empty($college_marks_value)) {
                throw new Exception("Please complete all academic parameters from Matriculation to higher College standards.");
            }
            $class_10_marksheet = $uploadMarksheet('class_10_marksheet', 'class_10_marksheet');
            $class_12_marksheet = $uploadMarksheet('class_12_marksheet', 'class_12_marksheet');
            $college_marksheet = $uploadMarksheet('college_marksheet', 'college_marksheet');
        }

        // Portrait photo and signature setups
        $photoDir = dirname(__DIR__) . '/assets/uploads/admissions/photos/';
        $sigDir = dirname(__DIR__) . '/assets/uploads/admissions/signatures/';
        if (!file_exists($photoDir)) { mkdir($photoDir, 0755, true); }
        if (!file_exists($sigDir)) { mkdir($sigDir, 0755, true); }

        // Portrait Photo
        $student_photo_path = '';
        if (isset($_FILES['student_photo']) && $_FILES['student_photo']['error'] === UPLOAD_ERR_OK) {
            $photoTmp = $_FILES['student_photo']['tmp_name'];
            $photoName = $_FILES['student_photo']['name'];
            if (@getimagesize($photoTmp) === false) {
                throw new Exception("Passport Photo is not a valid image file.");
            }
            $ext = strtolower(pathinfo($photoName, PATHINFO_EXTENSION));
            $newPhotoName = 'photo_' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (move_uploaded_file($photoTmp, $photoDir . $newPhotoName)) {
                $student_photo_path = 'assets/uploads/admissions/photos/' . $newPhotoName;
            } else {
                throw new Exception("Failed to save Passport Photo to disk.");
            }
        } else {
            throw new Exception("Please upload a valid Passport Photo.");
        }

        // Signature Image
        $student_signature_path = '';
        if (isset($_FILES['student_signature']) && $_FILES['student_signature']['error'] === UPLOAD_ERR_OK) {
            $sigTmp = $_FILES['student_signature']['tmp_name'];
            $sigName = $_FILES['student_signature']['name'];
            if (@getimagesize($sigTmp) === false) {
                throw new Exception("Signature Scan is not a valid image file.");
            }
            $ext = strtolower(pathinfo($sigName, PATHINFO_EXTENSION));
            $newSigName = 'sig_' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (move_uploaded_file($sigTmp, $sigDir . $newSigName)) {
                $student_signature_path = 'assets/uploads/admissions/signatures/' . $newSigName;
            } else {
                throw new Exception("Failed to save Signature Scan Image to disk.");
            }
        } else {
            throw new Exception("Please upload a scanned copy of Student Signature.");
        }

        // Auto-generate password
        $password = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8);

        // Transaction block for balance debiting & student enrollment
        try {
            $db->beginTransaction();

            // 1. Deduct Internship price from Franchise Center Wallet balance
            $wUpd = $db->prepare("UPDATE `franchise_wallets` SET `balance` = `balance` - ? WHERE `center_id` = ?");
            $wUpd->execute([$internship_sales_price, $center_id]);

            // 2. Log Debit Ledger entry inside franchise transaction history
            $debitTxnId = 'DEBIT_ENROLL_' . time() . '_' . rand(100, 999);
            $remarks = "Student Internship Enrollment - " . $student_name;
            
            $tStmt = $db->prepare("
                INSERT INTO `franchise_transactions` 
                (center_id, amount, paid_amount, royalty_percentage, payment_status, razorpay_payment_id, remarks) 
                VALUES (?, ?, 0.00, 0.00, 'paid', ?, ?)
            ");
            $tStmt->execute([$center_id, -$internship_sales_price, $debitTxnId, $remarks]);

            // 3. Generate secure Candidate Enrollment Number
            $prefix = "MGINT";
            $monthYear = date('my');
            $basePrefix = $prefix . $monthYear;
            
            $stmtSeq = $db->prepare("SELECT enrollment_number FROM internship_admissions WHERE enrollment_number LIKE ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $stmtSeq->execute([$basePrefix . '%']);
            $lastRecord = $stmtSeq->fetch();
            
            $nextSeries = 1;
            if ($lastRecord && !empty($lastRecord['enrollment_number'])) {
                $lastSeriesStr = substr($lastRecord['enrollment_number'], -3);
                $nextSeries = intval($lastSeriesStr) + 1;
            }
            $enrollment_number = $basePrefix . sprintf("%03d", $nextSeries);

            // 4. Write Core student registry profile to internship_admissions table
            $stmt = $db->prepare("
                INSERT INTO `internship_admissions` (
                    added_by, enrollment_number, internship_id, session_name, student_name, dob, email, mobile, gender, 
                    highest_qualification, school_name, aadhaar_number, pan_number, aadhaar_card,
                    class_10_roll, class_10_board, class_10_school, class_10_marksheet,
                    class_12_roll, class_12_board, class_12_school, class_12_marksheet,
                    college_name, college_roll, college_degree, college_passing_year, 
                    college_marks_type, college_marks_value, college_marksheet,
                    father_name, mother_name, state, city, district, pincode, address, 
                    student_photo, student_signature, password, status, payment_status
                ) VALUES (
                    :added_by, :enrollment_number, :internship_id, :session_name, :student_name, :dob, :email, :mobile, :gender,
                    :highest_qualification, :school_name, :aadhaar_number, :pan_number, :aadhaar_card,
                    :class_10_roll, :class_10_board, :class_10_school, :class_10_marksheet,
                    :class_12_roll, :class_12_board, :class_12_school, :class_12_marksheet,
                    :college_name, :college_roll, :college_degree, :college_passing_year,
                    :college_marks_type, :college_marks_value, :college_marksheet,
                    :father_name, :mother_name, :state, :city, :district, :pincode, :address,
                    :student_photo, :student_signature, :password, 'confirmed', 'paid'
                )
            ");
            
            $stmt->execute([
                'added_by' => $center_id,
                'enrollment_number' => $enrollment_number,
                'internship_id' => $internship_id,
                'session_name' => $session_name,
                'student_name' => $student_name,
                'dob' => $dob,
                'email' => $email,
                'mobile' => '+91 ' . $mobile,
                'gender' => $gender,
                'highest_qualification' => $highest_qualification,
                'school_name' => $school_name,
                'aadhaar_number' => $aadhaar_number ? implode(' ', str_split($aadhaar_number, 4)) : null,
                'pan_number' => null,
                'aadhaar_card' => $aadhaar_card,
                'class_10_roll' => $class_10_roll,
                'class_10_board' => $class_10_board,
                'class_10_school' => $class_10_school,
                'class_10_marksheet' => $class_10_marksheet,
                'class_12_roll' => $class_12_roll,
                'class_12_board' => $class_12_board,
                'class_12_school' => $class_12_school,
                'class_12_marksheet' => $class_12_marksheet,
                'college_name' => $college_name,
                'college_roll' => $college_roll,
                'college_degree' => $college_degree,
                'college_passing_year' => $college_passing_year,
                'college_marks_type' => $college_marks_type,
                'college_marks_value' => $college_marks_value,
                'college_marksheet' => $college_marksheet,
                'father_name' => $father_name,
                'mother_name' => $mother_name,
                'state' => $state,
                'city' => $city,
                'district' => $district,
                'pincode' => $pincode,
                'address' => $address,
                'student_photo' => $student_photo_path,
                'student_signature' => $student_signature_path,
                'password' => $password
            ]);
            $admission_id = $db->lastInsertId();

            // 5. Insert audit log inside student_fees ledger
            $relativeReceipt = 'assets/uploads/admissions/receipts/receipt_' . $admission_id . '.pdf';
            $feeIns = $db->prepare("
                INSERT INTO `student_fees` 
                (student_id, student_type, enrollment_number, amount, payment_status, receipt_path, razorpay_payment_id) 
                VALUES (?, 'internship', ?, ?, 'paid', ?, ?)
            ");
            $feeIns->execute([$admission_id, $enrollment_number, $internship_sales_price, $relativeReceipt, 'WALLET_DEDUCT_' . $center_id]);

            $db->commit();
            
        } catch (Exception $dbEx) {
            $db->rollBack();
            throw $dbEx;
        }

        // 6. Compile A4 Tax Invoice Receipt via mPDF & dispatch email
        $receiptFile = MG_GenerateInternshipReceiptPDF($admission_id);

        // Fetch center name for email
        $centerNameStmt = $db->prepare("SELECT center_name FROM `franchise_centers` WHERE `center_id` = ? LIMIT 1");
        $centerNameStmt->execute([$center_id]);
        $centerNameRow = $centerNameStmt->fetch();
        $centerName = $centerNameRow ? htmlspecialchars($centerNameRow['center_name']) : 'our authorized regional franchise center';

        $subject = "Internship Admission Confirmation - MG Education";
        $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>
            <div style='background: linear-gradient(135deg, #10b981 0%, #047857 100%); padding: 20px; text-align: center;'>
                <h2 style='color: #ffffff; margin: 0; font-size: 24px;'>Internship Confirmed</h2>
                <p style='color: #a7f3d0; margin: 5px 0 0 0; font-size: 14px;'>MG Education Learning Network</p>
            </div>
            <div style='padding: 30px; background: #ffffff;'>
                <h3 style='color: #1e293b; margin-top: 0;'>Hello " . htmlspecialchars($student_name) . ",</h3>
                <p style='color: #475569; font-size: 15px; line-height: 1.6;'>Congratulations! Your internship admission has been manually submitted and confirmed by " . $centerName . ". Your student profile is successfully created in our global registry database.</p>
                
                <div style='background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; margin: 20px 0;'>
                    <table style='width: 100%; border-collapse: collapse; font-size: 14px;'>
                        <tr>
                            <td style='padding: 6px 0; color: #64748b;'><strong>Enrollment Number:</strong></td>
                            <td style='padding: 6px 0; color: #1e293b; font-family: monospace; font-weight: bold; font-size: 15px;'>$enrollment_number</td>
                        </tr>
                        <tr>
                            <td style='padding: 6px 0; color: #64748b;'><strong>One-Time Password:</strong></td>
                            <td style='padding: 6px 0; color: #1e293b; font-family: monospace; font-weight: bold;'>$password</td>
                        </tr>
                        <tr>
                            <td style='padding: 6px 0; color: #64748b;'><strong>Enrolled Program:</strong></td>
                            <td style='padding: 6px 0; color: #1e293b; font-weight: bold;'>" . htmlspecialchars($internshipRow['name']) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 6px 0; color: #64748b;'><strong>Fees Status:</strong></td>
                            <td style='padding: 6px 0; color: #10b981; font-weight: bold;'>PAID (Via Center Wallet)</td>
                        </tr>
                    </table>
                </div>
                <p style='color: #475569; font-size: 14px; line-height: 1.6;'>Your digitally signed official **A4 Tax Receipt & Enrollment confirmation PDF** is attached to this email. Please preserve your credentials to login to the student portal.</p>
            </div>
            <div style='background: #f1f5f9; padding: 15px; text-align: center; border-top: 1px solid #e2e8f0;'>
                <p style='color: #94a3b8; font-size: 12px; margin: 0;'>&copy; " . date('Y') . " MG Education Org. All Rights Reserved.</p>
            </div>
        </div>";

        $mailOptions = [];
        if ($receiptFile && file_exists($receiptFile)) {
            $mailOptions['attachments'] = [$receiptFile];
        }
        
        try {
            MG_SendMail($email, $subject, $body, $mailOptions);
        } catch (Exception $mailEx) {
            error_log("Student confirmation mail failed: " . $mailEx->getMessage());
        }

        echo json_encode(['success' => true, 'message' => 'Candidate successfully enrolled in the system under Enrollment Number: ' . $enrollment_number]);
        exit;

    } catch (Exception $ex) {
        echo json_encode(['success' => false, 'message' => $ex->getMessage()]);
        exit;
    }
}

// 2. GET request page rendering
include './header.php';

$db = MG_GetDBConnection();
$center_id = $_SESSION['center_id'];

// Load active internships & sessions
$internships = [];
try {
    $iStmt = $db->query("SELECT id, name, sales_price FROM internships ORDER BY name ASC");
    $internships = $iStmt->fetchAll();
} catch (Exception $e) {
    error_log("Failed to load internships: " . $e->getMessage());
}

$sessions = [];
try {
    $sStmt = $db->query("SELECT session_name FROM academic_sessions WHERE is_active = 1 ORDER BY id DESC");
    $sessions = $sStmt->fetchAll();
} catch (Exception $e) {
    error_log("Failed to load sessions: " . $e->getMessage());
}

// Fetch Wallet Balance
$wStmt = $db->prepare("SELECT balance FROM `franchise_wallets` WHERE `center_id` = ? LIMIT 1");
$wStmt->execute([$center_id]);
$wallet = $wStmt->fetch();
$balance = $wallet ? floatval($wallet['balance']) : 0.00;
?>

<!-- Include Portal Stepper Stylesheet -->
<link rel="stylesheet" href="../assets/css/portal.css">

<style>
    /* Styling overrides for premium integration with AdminLTE WAMP */
    .portal-content {
        width: 100%;
        padding: 0;
        background-color: transparent;
        margin-left: 0 !important; /* Override portal.css split-screen offset */
    }
    .form-section-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    .section-card-title {
        font-size: 15px;
        font-weight: 700;
        text-transform: uppercase;
        color: #047857;
        border-bottom: 1.5px solid #f1f5f9;
        padding-bottom: 10px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .stepper-header-custom {
        display: flex;
        justify-content: space-between;
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        padding: 15px 25px;
        margin-bottom: 25px;
    }
    .stepper-step {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #64748b;
        font-weight: 600;
        font-size: 13px;
    }
    .stepper-step.active {
        color: #047857;
    }
    .stepper-step.active .step-num-custom {
        background-color: #047857;
        color: #fff;
    }
    .step-num-custom {
        width: 25px;
        height: 25px;
        border-radius: 50%;
        background-color: #cbd5e1;
        color: #64748b;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
    }
</style>

<div class="row pt-2">
    <div class="col-12">
        <div class="portal-content">
            <form id="enrollmentForm" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                <input type="hidden" id="email_verified_flag" name="email_verified" value="1">
                <input type="hidden" name="submit_admission" value="1">

                <!-- SECTION 1: Program & Personal Details -->
                <div class="form-step-section" id="section-1">
                    <div class="form-section-card">
                        <h3 class="section-card-title"><i class="fas fa-graduation-cap"></i> 1. Selected Internship Program</h3>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold">Select Target Internship <span class="text-danger">*</span></label>
                                <select name="internship_id" id="internship_id" class="form-select form-control" required onchange="updateInternshipPrice(this)">
                                    <option value="">-- Choose Internship --</option>
                                    <?php foreach ($internships as $i): ?>
                                        <option value="<?= $i['id'] ?>" data-price="<?= $i['sales_price'] ?>"><?= htmlspecialchars($i['name']) ?> (₹<?= number_format($i['sales_price'], 2) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold">Select Active Session <span class="text-danger">*</span></label>
                                <select name="session_name" class="form-select form-control" required>
                                    <option value="">-- Choose Session --</option>
                                    <?php foreach ($sessions as $s): ?>
                                        <option value="<?= htmlspecialchars($s['session_name']) ?>"><?= htmlspecialchars($s['session_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-section-card">
                        <h3 class="section-card-title"><i class="fas fa-user"></i> 2. Candidate Biographical Specifications</h3>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label font-weight-bold">Full Student Name <span class="text-danger">*</span></label>
                                <input type="text" name="student_name" class="form-control" placeholder="As per matriculation standard" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label font-weight-bold">Date of Birth <span class="text-danger">*</span></label>
                                <input type="date" name="dob" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label font-weight-bold">Select Gender <span class="text-danger">*</span></label>
                                <select name="gender" class="form-select form-control" required>
                                    <option value="">-- Choose Gender --</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold">Student Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" id="student_email" class="form-control" placeholder="candidate@example.com" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold">Mobile Phone Number <span class="text-danger">*</span></label>
                                <input type="text" name="mobile" id="student_mobile" class="form-control" placeholder="10-digit number" maxlength="10" required>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- SECTION 2: Academic Qualifications -->
                <div class="form-step-section" id="section-2">
                    <div class="form-section-card">
                        <h3 class="section-card-title"><i class="fas fa-certificate"></i> 3. Highest Academic Qualification</h3>
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Specify Standard <span class="text-danger">*</span></label>
                            <select name="highest_qualification" id="highest_qualification" class="form-select form-control" required onchange="toggleAcademicInputs(this.value)">
                                <option value="">-- Choose Standard --</option>
                                <option value="below_10th_5">Below Class 10th (Passed 5th Standard)</option>
                                <option value="below_10th_8">Below Class 10th (Passed 8th Standard)</option>
                                <option value="class_10th">Matriculation (Class 10th Standard)</option>
                                <option value="class_12th">Senior Secondary (Class 12th Standard)</option>
                                <option value="graduation">Undergraduate (Graduation Standard)</option>
                                <option value="post_graduation">Postgraduate (Master's Standard)</option>
                                <option value="phd">Doctorate (Ph.D. Standard)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Under 10th Inputs -->
                    <div id="div-below-10" class="form-section-card d-none">
                        <h3 class="section-card-title"><i class="fas fa-school"></i> School Profile Details</h3>
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Last Attended School Name <span class="text-danger">*</span></label>
                            <input type="text" name="school_name" id="school_name_input" class="form-control" placeholder="Specify full school registry name">
                        </div>
                    </div>

                    <!-- Matriculation Inputs -->
                    <div id="div-class-10" class="form-section-card d-none">
                        <h3 class="section-card-title"><i class="fas fa-scroll"></i> Matriculation (Class 10th) Records</h3>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label font-weight-bold">Class 10 Roll Number <span class="text-danger">*</span></label>
                                <input type="text" name="class_10_roll" id="c10_roll" class="form-control" placeholder="Seat Roll No">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label font-weight-bold">Board Name <span class="text-danger">*</span></label>
                                <input type="text" name="class_10_board" id="c10_board" class="form-control" placeholder="e.g. CBSE, ICSE, State Board">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label font-weight-bold">School Name <span class="text-danger">*</span></label>
                                <input type="text" name="class_10_school" id="c10_school" class="form-control" placeholder="Specify attending institute">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Upload Scanned 10th Marksheet (PDF format, max 2MB) <span class="text-danger">*</span></label>
                            <input type="file" name="class_10_marksheet" id="c10_file" class="form-control" accept="application/pdf">
                        </div>
                    </div>

                    <!-- Higher Secondary Inputs -->
                    <div id="div-class-12" class="form-section-card d-none">
                        <h3 class="section-card-title"><i class="fas fa-scroll"></i> Senior Secondary (Class 12th) Records</h3>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label font-weight-bold">Class 12 Roll Number <span class="text-danger">*</span></label>
                                <input type="text" name="class_12_roll" id="c12_roll" class="form-control" placeholder="Seat Roll No">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label font-weight-bold">Board Name <span class="text-danger">*</span></label>
                                <input type="text" name="class_12_board" id="c12_board" class="form-control" placeholder="e.g. CBSE, ICSE, State Board">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label font-weight-bold">School Name <span class="text-danger">*</span></label>
                                <input type="text" name="class_12_school" id="c12_school" class="form-control" placeholder="Specify attending institute">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Upload Scanned 12th Marksheet (PDF format, max 2MB) <span class="text-danger">*</span></label>
                            <input type="file" name="class_12_marksheet" id="c12_file" class="form-control" accept="application/pdf">
                        </div>
                    </div>

                    <!-- Collegiate Inputs -->
                    <div id="div-college" class="form-section-card d-none">
                        <h3 class="section-card-title"><i class="fas fa-university"></i> Collegiate / Higher Education Records</h3>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold">University / College Name <span class="text-danger">*</span></label>
                                <input type="text" name="college_name" id="college_name_input" class="form-control" placeholder="Full college title">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold">University Seat Roll Number <span class="text-danger">*</span></label>
                                <input type="text" name="college_roll" id="college_roll_input" class="form-control" placeholder="Enrollment / Roll No">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold">Attained Higher Degree <span class="text-danger">*</span></label>
                                <input type="text" name="college_degree" id="college_degree_input" class="form-control" placeholder="e.g. B.Tech, BCA, B.Sc, MCA">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold">Passing Year <span class="text-danger">*</span></label>
                                <input type="text" name="college_passing_year" id="college_year_input" class="form-control" placeholder="e.g. 2024">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold">Evaluation Standard <span class="text-danger">*</span></label>
                                <select name="college_marks_type" id="college_type_input" class="form-select form-control">
                                    <option value="">-- Choose Type --</option>
                                    <option value="CGPA">CGPA Scale</option>
                                    <option value="Percentage">Percentage (%)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold">Score Value <span class="text-danger">*</span></label>
                                <input type="text" name="college_marks_value" id="college_value_input" class="form-control" placeholder="e.g. 8.5 or 85%">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Upload Scanned Higher Degree Marksheet (PDF format, max 2MB) <span class="text-danger">*</span></label>
                            <input type="file" name="college_marksheet" id="college_file" class="form-control" accept="application/pdf">
                        </div>
                    </div>

                </div>

                <!-- SECTION 3: Parental Details & Geographic Address -->
                <div class="form-step-section" id="section-3">
                    <div class="form-section-card">
                        <h3 class="section-card-title"><i class="fas fa-users"></i> 4. Parental Specifications</h3>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold">Father's Name <span class="text-danger">*</span></label>
                                <input type="text" name="father_name" class="form-control" placeholder="Full legal name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold">Mother's Name <span class="text-danger">*</span></label>
                                <input type="text" name="mother_name" class="form-control" placeholder="Full legal name" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-section-card">
                        <h3 class="section-card-title"><i class="fas fa-map-location-dot"></i> 5. Permanent Geographic Location Address</h3>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label font-weight-bold">State Name <span class="text-danger">*</span></label>
                                <input type="text" name="state" class="form-control" placeholder="State" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label font-weight-bold">City Name <span class="text-danger">*</span></label>
                                <input type="text" name="city" class="form-control" placeholder="City" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label font-weight-bold">District Name <span class="text-danger">*</span></label>
                                <input type="text" name="district" class="form-control" placeholder="District" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label font-weight-bold">Pincode / Zip Code <span class="text-danger">*</span></label>
                                <input type="text" name="pincode" id="pincode" class="form-control" placeholder="6-digit ZIP code" maxlength="6" required>
                            </div>
                            <div class="col-md-8 mb-3">
                                <label class="form-label font-weight-bold">Full Mailing Address <span class="text-danger">*</span></label>
                                <textarea name="address" class="form-control" rows="2" placeholder="Detailed street, landmark, and house details" required></textarea>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- SECTION 4: Scanned Documents Upload -->
                <div class="form-step-section" id="section-4">
                    <div class="form-section-card">
                        <h3 class="section-card-title"><i class="fas fa-image"></i> 6. Scanned Biometrics & Identifiers</h3>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold">Candidate Portrait Photo (JPG/PNG, Max 2MB) <span class="text-danger">*</span></label>
                                <input type="file" name="student_photo" class="form-control" accept="image/*" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold">Scanned Student Signature (JPG/PNG, Max 2MB) <span class="text-danger">*</span></label>
                                <input type="file" name="student_signature" class="form-control" accept="image/*" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold">Aadhaar Card Number <span class="text-danger">*</span></label>
                                <input type="text" name="aadhaar_number" id="aadhaar_number_input" class="form-control" placeholder="12-digit UIDAI number" maxlength="12" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold">Upload Aadhaar PDF (Scanned front & back, Max 2MB) <span class="text-danger">*</span></label>
                                <input type="file" name="aadhaar_card" class="form-control" accept="application/pdf" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-section-card bg-light">
                        <h3 class="section-card-title text-success"><i class="fas fa-shield-halved"></i> 7. System Wallet Billing Particulars</h3>
                        <div class="p-3 border rounded bg-white">
                            <div class="d-flex justify-content-between font-weight-bold mb-2">
                                <span>Selected Internship Enrollment Fee:</span>
                                <span class="text-dark" id="lbl-fee-val">₹0.00</span>
                            </div>
                            <div class="d-flex justify-content-between font-weight-bold mb-2">
                                <span>Center Wallet Balance Status:</span>
                                <span class="text-success" id="lbl-balance-val">₹<?= number_format($balance, 2) ?></span>
                            </div>
                            <hr>
                            <div class="form-check">
                                <input type="checkbox" id="declaration_check" class="form-check-input" required>
                                <label class="form-check-label text-secondary small" for="declaration_check">
                                    I hereby declare that all academic credentials, biometrics, and bio-data submitted for this enrollment are authentic and verified against the student's legal documents. I authorize this portal to debit the specified enrollment fee directly from our center's wallet balance.
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4 mb-4">
                        <button type="submit" class="btn btn-success px-5 rounded-pill font-weight-bold shadow-lg" id="btn-submit-enroll">
                            Complete Enrollment <i class="fas fa-check-circle ml-1"></i>
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
    const centerWalletBalance = parseFloat(<?= json_encode($balance) ?>);

    // Initial load
    document.addEventListener("DOMContentLoaded", function() {
        // Always trigger updateInternshipPrice on load to set initial fee and balance status
        const internshipSelect = document.getElementById('internship_id');
        updateInternshipPrice(internshipSelect);
    });

    function updateInternshipPrice(elem) {
        const option = elem.options[elem.selectedIndex];
        const price = option ? parseFloat(option.getAttribute('data-price')) : 0;
        
        if (!isNaN(price) && price > 0) {
            $('#lbl-fee-val').text("₹" + price.toLocaleString('en-IN', { minimumFractionDigits: 2 }));
            if (centerWalletBalance < price) {
                $('#lbl-balance-val').removeClass('text-success').addClass('text-danger').html("₹" + centerWalletBalance.toLocaleString('en-IN', { minimumFractionDigits: 2 }) + " <span class='badge bg-danger text-white ml-2'>INSUFFICIENT BALANCE</span>");
            } else {
                $('#lbl-balance-val').removeClass('text-danger').addClass('text-success').html("₹" + centerWalletBalance.toLocaleString('en-IN', { minimumFractionDigits: 2 }) + " <span class='badge bg-success text-white ml-2'>FEE COVERED</span>");
            }
        } else {
            $('#lbl-fee-val').text("₹0.00");
            $('#lbl-balance-val').removeClass('text-danger').addClass('text-success').text("₹" + centerWalletBalance.toLocaleString('en-IN', { minimumFractionDigits: 2 }));
        }
    }



    function toggleAcademicInputs(val) {
        // Hide all academic conditional cards first
        $('#div-below-10, #div-class-10, #div-class-12, #div-college').addClass('d-none');
        
        // Remove required properties initially
        $('#school_name_input, #c10_roll, #c10_board, #c10_school, #c10_file, #c12_roll, #c12_board, #c12_school, #c12_file, #college_name_input, #college_roll_input, #college_degree_input, #college_year_input, #college_type_input, #college_value_input, #college_file').prop('required', false);

        if (val === 'below_10th_5' || val === 'below_10th_8') {
            $('#div-below-10').removeClass('d-none');
            $('#school_name_input').prop('required', true);
        } else if (val === 'class_10th') {
            $('#div-class-10').removeClass('d-none');
            $('#c10_roll, #c10_board, #c10_school, #c10_file').prop('required', true);
        } else if (val === 'class_12th') {
            $('#div-class-10, #div-class-12').removeClass('d-none');
            $('#c10_roll, #c10_board, #c10_school, #c10_file, #c12_roll, #c12_board, #c12_school, #c12_file').prop('required', true);
        } else if (val === 'graduation' || val === 'post_graduation' || val === 'phd') {
            $('#div-class-10, #div-class-12, #div-college').removeClass('d-none');
            $('#c10_roll, #c10_board, #c10_school, #c10_file, #c12_roll, #c12_board, #c12_school, #c12_file, #college_name_input, #college_roll_input, #college_degree_input, #college_year_input, #college_type_input, #college_value_input, #college_file').prop('required', true);
        }
    }

    // Capture Form Submission
    $('#enrollmentForm').on('submit', function(e) {
        e.preventDefault();

        const form = this;
        let valid = true;
        let firstInvalid = null;

        $(form).find('input, select, textarea').each(function() {
            if (!$(this).is(':visible') || $(this).attr('type') === 'hidden') return;
            
            $(this).removeClass('is-invalid');

            if ($(this).prop('required') && !this.value.trim()) {
                valid = false;
                $(this).addClass('is-invalid');
                if (!firstInvalid) firstInvalid = this;
            }

            if (this.id === 'aadhaar_number_input' && this.value.length !== 12) {
                valid = false;
                $(this).addClass('is-invalid');
                if (!firstInvalid) firstInvalid = this;
            }

            if (this.id === 'pincode' && this.value.length !== 6) {
                valid = false;
                $(this).addClass('is-invalid');
                if (!firstInvalid) firstInvalid = this;
            }
        });

        if (!valid) {
            Swal.fire({
                icon: 'warning',
                title: 'Validation Incomplete',
                text: 'Please review all steps and fill in all mandatory fields with valid values before submitting.',
                confirmButtonColor: '#047857'
            });
            if (firstInvalid) firstInvalid.focus();
            return;
        }

        if (!$('#declaration_check').is(':checked')) {
            Swal.fire({
                icon: 'warning',
                title: 'Authorization Required',
                text: 'Please accept the declaration terms to authorize center wallet deductions.',
                confirmButtonColor: '#047857'
            });
            return;
        }

        const selectedInternshipSelect = document.getElementById('internship_id');
        const selectedOption = selectedInternshipSelect.options[selectedInternshipSelect.selectedIndex];
        const internshipPrice = selectedOption ? parseFloat(selectedOption.getAttribute('data-price')) : 0;

        if (centerWalletBalance < internshipPrice) {
            Swal.fire({
                icon: 'error',
                title: 'Insufficient Balance',
                text: 'Your current center wallet balance is insufficient to complete this enrollment. Please recharge your wallet first.',
                confirmButtonColor: '#ef4444'
            });
            return;
        }

        Swal.fire({
            title: 'Authorize Wallet Deduction?',
            text: 'Confirm enrolling candidate and deducting ₹' + internshipPrice.toLocaleString('en-IN', { minimumFractionDigits: 2 }) + ' directly from center wallet?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#047857',
            cancelButtonColor: '#ef4444',
            confirmButtonText: 'Yes, Authorize and Enroll!',
            cancelButtonText: 'Review Profile'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Processing Enrollment...',
                    text: 'Debiting center wallet and recording student profile...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                const formData = new FormData(form);

                $.ajax({
                    url: 'enroll-internship.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Enrollment Confirmed!',
                                text: response.message,
                                confirmButtonColor: '#047857'
                            }).then(() => {
                                window.location.href = 'admissions.php';
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Enrollment Failed',
                                text: response.message,
                                confirmButtonColor: '#ef4444'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Server Error',
                            text: 'Failed to communicate with enrollment billing servers. Please try again.',
                            confirmButtonColor: '#ef4444'
                        });
                    }
                });
            }
        });
    });
</script>

<?php include './footer.php'; ?>
