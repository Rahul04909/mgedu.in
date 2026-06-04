<?php
/**
 * MG Education & Social Development Organization
 * Premium Dynamic Internship Admission Portal - Student Enrollment Console
 */

require_once __DIR__ . '/includes/config.php';

$db = MG_GetDBConnection();
$error_message = '';
$success_message = '';

// 1. Self-Healing Database: Ensure internship_admissions table exists
try {
    $db->query("SELECT 1 FROM `internship_admissions` LIMIT 1");
    $newColumns = [
        'session_name' => "VARCHAR(50) NULL AFTER `internship_id`",
        'pan_number'   => "VARCHAR(20) NULL AFTER `aadhaar_number`"
    ];
    foreach ($newColumns as $col => $definition) {
        try {
            $db->query("SELECT `$col` FROM `internship_admissions` LIMIT 1");
        } catch (Exception $e) {
            $db->exec("ALTER TABLE `internship_admissions` ADD COLUMN `$col` $definition");
        }
    }
} catch (Exception $e) {
    try {
        $createTableSQL = "
            CREATE TABLE IF NOT EXISTS `internship_admissions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `enrollment_number` VARCHAR(50) NULL,
                `password` VARCHAR(255) NULL,
                `internship_id` INT NOT NULL,
                `session_name` VARCHAR(50) NULL,
                `student_name` VARCHAR(255) NOT NULL,
                `dob` DATE NOT NULL,
                `email` VARCHAR(255) NOT NULL,
                `mobile` VARCHAR(20) NOT NULL,
                `gender` VARCHAR(20) NOT NULL,
                `highest_qualification` VARCHAR(50) NULL,
                `school_name` VARCHAR(255) NULL,
                `aadhaar_number` VARCHAR(20) NULL,
                `pan_number` VARCHAR(20) NULL,
                `aadhaar_card` VARCHAR(255) NULL,
                `class_10_roll` VARCHAR(50) NULL,
                `class_10_board` VARCHAR(100) NULL,
                `class_10_school` VARCHAR(255) NULL,
                `class_10_marksheet` VARCHAR(255) NULL,
                `class_12_roll` VARCHAR(50) NULL,
                `class_12_board` VARCHAR(100) NULL,
                `class_12_school` VARCHAR(255) NULL,
                `class_12_marksheet` VARCHAR(255) NULL,
                `college_name` VARCHAR(255) NULL,
                `college_roll` VARCHAR(50) NULL,
                `college_degree` VARCHAR(100) NULL,
                `college_passing_year` VARCHAR(10) NULL,
                `college_marks_type` VARCHAR(20) NULL,
                `college_marks_value` VARCHAR(20) NULL,
                `college_marksheet` VARCHAR(255) NULL,
                `father_name` VARCHAR(255) NOT NULL,
                `mother_name` VARCHAR(255) NOT NULL,
                `state` VARCHAR(100) NOT NULL,
                `city` VARCHAR(100) NOT NULL,
                `district` VARCHAR(100) NOT NULL,
                `pincode` VARCHAR(10) NOT NULL,
                `address` TEXT NOT NULL,
                `student_photo` VARCHAR(255) NOT NULL,
                `student_signature` VARCHAR(255) NOT NULL,
                `status` VARCHAR(50) DEFAULT 'pending',
                `razorpay_order_id` VARCHAR(255) NULL,
                `razorpay_payment_id` VARCHAR(255) NULL,
                `payment_status` VARCHAR(50) DEFAULT 'pending',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $db->exec($createTableSQL);
    } catch (Exception $ex) {
        $error_message = "Failed to establish database parameters: " . $ex->getMessage();
    }
}

// 2. Handle AJAX Requests (OTP & Payment Verification)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    if ($_POST['ajax_action'] === 'send_otp') {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Invalid email address.';
            echo json_encode($response);
            exit;
        }
        
        $otp = rand(100000, 999999);
        $_SESSION['email_otp'] = $otp;
        $_SESSION['email_otp_address'] = $email;
        
        $subject = "Your Internship Email Verification OTP";
        $body = "
<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>
    <div style='background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 100%); padding: 20px; text-align: center;'>
        <h2 style='color: #ffffff; margin: 0; font-size: 24px;'>MG Education Portal</h2>
    </div>
    <div style='padding: 30px; background: #ffffff; text-align: center;'>
        <h3 style='color: #1e293b; font-size: 20px; margin-top: 0;'>Email Verification</h3>
        <p style='color: #475569; font-size: 15px; line-height: 1.6;'>Thank you for starting your internship enrollment profile. To securely verify your email address, please use the One-Time Password (OTP) below:</p>
        <div style='background: #f8fafc; border: 2px dashed #cbd5e1; padding: 20px; margin: 25px 0; border-radius: 8px;'>
            <strong style='font-size: 36px; color: #1e3a8a; letter-spacing: 6px;'>$otp</strong>
        </div>
        <p style='color: #64748b; font-size: 13px;'>If you did not request this verification, please safely ignore this email.</p>
    </div>
    <div style='background: #f1f5f9; padding: 15px; text-align: center; border-top: 1px solid #e2e8f0;'>
        <p style='color: #94a3b8; font-size: 12px; margin: 0;'>&copy; " . date('Y') . " MG Education & Social Development Organization. All Rights Reserved.</p>
    </div>
</div>";
        
        try {
            if (MG_SendMail($email, $subject, $body)) {
                $response['success'] = true;
                $response['message'] = 'OTP sent successfully to your email.';
            } else {
                $response['message'] = 'Failed to send OTP email.';
            }
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }
        
        echo json_encode($response);
        exit;
    }
    
    if ($_POST['ajax_action'] === 'verify_otp') {
        $email = trim($_POST['email'] ?? '');
        $otp = trim($_POST['otp'] ?? '');
        
        if (isset($_SESSION['email_otp']) && isset($_SESSION['email_otp_address'])) {
            if ($email === $_SESSION['email_otp_address'] && $otp == $_SESSION['email_otp']) {
                $response['success'] = true;
                $response['message'] = 'Email verified successfully.';
                unset($_SESSION['email_otp']);
            } else {
                $response['message'] = 'Invalid OTP or Email.';
            }
        } else {
            $response['message'] = 'OTP session expired. Please request a new one.';
        }
        
        echo json_encode($response);
        exit;
    }
    
    if ($_POST['ajax_action'] === 'verify_payment') {
        $razorpay_payment_id = $_POST['razorpay_payment_id'] ?? '';
        $razorpay_order_id = $_POST['razorpay_order_id'] ?? '';
        $razorpay_signature = $_POST['razorpay_signature'] ?? '';
        
        if (empty($razorpay_payment_id) || empty($razorpay_order_id) || empty($razorpay_signature)) {
            $response['message'] = 'Missing payment parameters.';
            echo json_encode($response);
            exit;
        }
        
        try {
            $api = MG_GetRazorpayClient();
            $attributes = array(
                'razorpay_order_id' => $razorpay_order_id,
                'razorpay_payment_id' => $razorpay_payment_id,
                'razorpay_signature' => $razorpay_signature
            );
            $api->utility->verifyPaymentSignature($attributes);
            
            // Signature valid, update DB
            $db = MG_GetDBConnection();
            
            $stmtDetails = $db->prepare("SELECT id, student_name, email, password FROM `internship_admissions` WHERE `razorpay_order_id` = ?");
            $stmtDetails->execute([$razorpay_order_id]);
            $admission = $stmtDetails->fetch();
            
            if ($admission) {
                $prefix = "MGINT";
                $monthYear = date('my');
                $basePrefix = $prefix . $monthYear;
                
                $stmtSeq = $db->prepare("SELECT enrollment_number FROM internship_admissions WHERE enrollment_number LIKE ? ORDER BY id DESC LIMIT 1");
                $stmtSeq->execute([$basePrefix . '%']);
                $lastRecord = $stmtSeq->fetch();
                
                $nextSeries = 1;
                if ($lastRecord && !empty($lastRecord['enrollment_number'])) {
                    $lastSeriesStr = substr($lastRecord['enrollment_number'], -3);
                    $nextSeries = intval($lastSeriesStr) + 1;
                }
                $enrollment_number = $basePrefix . sprintf("%03d", $nextSeries);
                
                $stmt = $db->prepare("UPDATE `internship_admissions` SET `payment_status` = 'paid', `status` = 'confirmed', `razorpay_payment_id` = ?, `enrollment_number` = ? WHERE `razorpay_order_id` = ?");
                $stmt->execute([$razorpay_payment_id, $enrollment_number, $razorpay_order_id]);
                
                // Generate premium tax receipt PDF
                $receiptFile = MG_GenerateInternshipReceiptPDF($admission['id']);
                
                // Sync with student_fees table
                try {
                    $relativeReceipt = 'assets/uploads/internships/receipts/receipt_' . $admission['id'] . '.pdf';
                    
                    // Fetch amount from internship_admissions
                    $stmtC = $db->prepare("SELECT sales_price FROM `internship_admissions` WHERE id = ?");
                    $stmtC->execute([$admission['id']]);
                    $admRow = $stmtC->fetch();
                    $amount = $admRow ? floatval($admRow['sales_price']) : 0.00;

                    // Update existing pending record or insert
                    $feeCheck = $db->prepare("SELECT id FROM `student_fees` WHERE student_id = ? AND student_type = 'internship'");
                    $feeCheck->execute([$admission['id']]);
                    if ($feeCheck->fetch()) {
                        $feeUpd = $db->prepare("UPDATE `student_fees` SET enrollment_number = ?, amount = ?, payment_status = 'paid', receipt_path = ?, razorpay_payment_id = ? WHERE student_id = ? AND student_type = 'internship'");
                        $feeUpd->execute([$enrollment_number, $amount, $relativeReceipt, $razorpay_payment_id, $admission['id']]);
                    } else {
                        $feeIns = $db->prepare("INSERT INTO `student_fees` (student_id, student_type, enrollment_number, amount, payment_status, receipt_path, razorpay_payment_id) VALUES (?, 'internship', ?, ?, 'paid', ?, ?)");
                        $feeIns->execute([$admission['id'], $enrollment_number, $amount, $relativeReceipt, $razorpay_payment_id]);
                    }
                } catch (Exception $feeEx) {
                    error_log("Failed to sync internship fee during Razorpay callback: " . $feeEx->getMessage());
                }
                
                $subject = "Internship Confirmed - MG Education";
                $body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>
                    <div style='background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%); padding: 20px; text-align: center;'>
                        <h2 style='color: #ffffff; margin: 0; font-size: 24px;'>Internship Confirmed</h2>
                    </div>
                    <div style='padding: 30px; background: #ffffff;'>
                        <h3 style='color: #1e293b; font-size: 20px; margin-top: 0;'>Welcome to MG Education, {$admission['student_name']}!</h3>
                        <p style='color: #475569; font-size: 15px; line-height: 1.6;'>Your internship profile and fee payment have been successfully processed. We are thrilled to welcome you as our project training intern.</p>
                        
                        <div style='background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; margin: 25px 0; text-align: left;'>
                            <p style='margin: 0 0 12px 0; font-size: 13px; color: #475569; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #cbd5e1; padding-bottom: 6px;'>Your Student Portal Credentials</p>
                            <table style='width: 100%; border-collapse: collapse;'>
                                <tr>
                                    <td style='padding: 6px 0; font-size: 14px; color: #475569; width: 40%;'><strong>Enrollment Number:</strong></td>
                                    <td style='padding: 6px 0; font-size: 15px; color: #0d47a1; font-family: monospace; font-weight: bold; letter-spacing: 1px;'>$enrollment_number</td>
                                </tr>
                                <tr>
                                    <td style='padding: 6px 0; font-size: 14px; color: #475569;'><strong>Portal Password:</strong></td>
                                    <td style='padding: 6px 0; font-size: 15px; color: #dc2626; font-family: monospace; font-weight: bold;'>{$admission['password']}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 6px 0; font-size: 14px; color: #475569;'><strong>Fees Status:</strong></td>
                                    <td style='padding: 6px 0; font-size: 14px; color: #16a34a; font-weight: bold; text-transform: uppercase;'>PAID (Online Receipt Attached)</td>
                                </tr>
                            </table>
                        </div>
                        
                        <p style='color: #475569; font-size: 14px; line-height: 1.6;'>Your official A4 fee receipt has been dynamically generated and attached to this email. Please keep your enrollment number and login password secure for learning portal access.</p>
                        <p style='color: #475569; font-size: 14px; line-height: 1.6; margin-top: 15px;'>Our internship coordinator will contact you shortly with your class schedule and learning portal access instructions.</p>
                    </div>
                    <div style='background: #f1f5f9; padding: 15px; text-align: center; border-top: 1px solid #e2e8f0;'>
                        <p style='color: #94a3b8; font-size: 12px; margin: 0;'>&copy; " . date('Y') . " MG Education & Social Development Organization. All Rights Reserved.</p>
                    </div>
                </div>";
                
                $mailOptions = [];
                if ($receiptFile) {
                    $mailOptions['attachments'] = [$receiptFile];
                }
                MG_SendMail($admission['email'], $subject, $body, $mailOptions);
            }
            
            $response['success'] = true;
            $response['message'] = 'Payment verified and admission confirmed.';
            $_SESSION['admission_success_message'] = "Payment successful! Internship admission confirmed.";
            
        } catch (Exception $e) {
            $response['message'] = 'Payment verification failed: ' . $e->getMessage();
            
            try {
                $db = MG_GetDBConnection();
                $stmt = $db->prepare("UPDATE `internship_admissions` SET `payment_status` = 'failed' WHERE `razorpay_order_id` = ?");
                $stmt->execute([$razorpay_order_id]);
            } catch (Exception $ex) {}
        }
        
        echo json_encode($response);
        exit;
    }
}

// 3. Fetch internships & active sessions
$internships = [];
$active_sessions = [];
try {
    $intern_stmt = $db->query("SELECT id, name, sales_price FROM internships ORDER BY name ASC");
    $internships = $intern_stmt->fetchAll();
    
    $sess_stmt = $db->query("SELECT session_name FROM academic_sessions WHERE is_active = 1 ORDER BY id DESC");
    $active_sessions = $sess_stmt->fetchAll();
} catch (Exception $e) {
    error_log("Admissions internship/session fetch error: " . $e->getMessage());
}

$selected_internship_id = 0;
if (isset($_GET['internship_id'])) {
    $selected_internship_id = intval($_GET['internship_id']);
}

// 4. Process Form Submission
$razorpay_order_id_global = null;
$internship_sales_price_global = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_admission'])) {
    try {
        $internship_id = intval($_POST['internship_id'] ?? 0);
        $session_name = trim($_POST['session_name'] ?? '');
        $pan_number = null; // Removed from UI as requested
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
        $email_verified = trim($_POST['email_verified'] ?? '0');

        if ($email_verified !== '1') {
            throw new Exception("Please verify your email address via OTP before submitting the admission form.");
        }

        // Mandatory fields check
        if (empty($internship_id) || empty($session_name) || empty($student_name) || empty($dob) || empty($email) || empty($mobile) || empty($gender) ||
            empty($father_name) || empty($mother_name) || empty($state) || empty($city) || empty($district) || 
            empty($pincode) || empty($address)) {
            throw new Exception("All biographical, parental, and geographic address fields are strictly mandatory.");
        }

        // Qualification parameters
        $highest_qualification = trim($_POST['highest_qualification'] ?? '');
        $school_name = null;
        $aadhaar_number = null;
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

        if (empty($highest_qualification)) {
            throw new Exception("Please specify your Highest Educational Qualification.");
        }

        // Upload scanned PDF setup
        $docDir = __DIR__ . '/assets/uploads/internships/documents/';
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
                    return 'assets/uploads/internships/documents/' . $newDocName;
                } else {
                    throw new Exception("Failed to write '" . str_replace('_', ' ', $prefix) . "' PDF file to disk.");
                }
            } else {
                throw new Exception("Please upload a valid scanned PDF of '" . str_replace('_', ' ', $prefix) . "'.");
            }
        };

        // Universal Aadhaar verification
        $aadhaar_number = trim($_POST['aadhaar_number'] ?? '');
        $aadhaar_number = str_replace(' ', '', $aadhaar_number);

        if (empty($aadhaar_number) || strlen($aadhaar_number) !== 12 || !ctype_digit($aadhaar_number)) {
            throw new Exception("A valid 12-digit Aadhaar number is mandatory.");
        }
        $aadhaar_card = $uploadMarksheet('aadhaar_card', 'aadhaar_card');

        // Conditional qualifications visibility
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

        // Photo & Signature Setup
        $photoDir = __DIR__ . '/assets/uploads/internships/photos/';
        $sigDir = __DIR__ . '/assets/uploads/internships/signatures/';
        if (!file_exists($photoDir)) { mkdir($photoDir, 0755, true); }
        if (!file_exists($sigDir)) { mkdir($sigDir, 0755, true); }

        $student_photo_path = '';
        if (isset($_FILES['student_photo']) && $_FILES['student_photo']['error'] === UPLOAD_ERR_OK) {
            $imgTmp = $_FILES['student_photo']['tmp_name'];
            $imgName = $_FILES['student_photo']['name'];
            if (@getimagesize($imgTmp) === false) {
                throw new Exception("Student Portrait is not a valid image file.");
            }
            $ext = strtolower(pathinfo($imgName, PATHINFO_EXTENSION));
            $newPhotoName = 'photo_' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (move_uploaded_file($imgTmp, $photoDir . $newPhotoName)) {
                $student_photo_path = 'assets/uploads/internships/photos/' . $newPhotoName;
            } else {
                throw new Exception("Failed to save Student Portrait Image to disk.");
            }
        } else {
            throw new Exception("Please upload a high-resolution Student Portrait Image.");
        }

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
                $student_signature_path = 'assets/uploads/internships/signatures/' . $newSigName;
            } else {
                throw new Exception("Failed to save Signature Scan Image to disk.");
            }
        } else {
            throw new Exception("Please upload a scanned copy of Student Signature.");
        }

        $password = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8);

        // Insert database
        $stmt = $db->prepare("
            INSERT INTO `internship_admissions` (
                internship_id, session_name, student_name, dob, email, mobile, gender, 
                highest_qualification, school_name, aadhaar_number, pan_number, aadhaar_card,
                class_10_roll, class_10_board, class_10_school, class_10_marksheet,
                class_12_roll, class_12_board, class_12_school, class_12_marksheet,
                college_name, college_roll, college_degree, college_passing_year, 
                college_marks_type, college_marks_value, college_marksheet,
                father_name, mother_name, state, city, district, pincode, address, 
                student_photo, student_signature, password
            ) VALUES (
                :internship_id, :session_name, :student_name, :dob, :email, :mobile, :gender,
                :highest_qualification, :school_name, :aadhaar_number, :pan_number, :aadhaar_card,
                :class_10_roll, :class_10_board, :class_10_school, :class_10_marksheet,
                :class_12_roll, :class_12_board, :class_12_school, :class_12_marksheet,
                :college_name, :college_roll, :college_degree, :college_passing_year,
                :college_marks_type, :college_marks_value, :college_marksheet,
                :father_name, :mother_name, :state, :city, :district, :pincode, :address,
                :student_photo, :student_signature, :password
            )
        ");
        $stmt->execute([
            'internship_id' => $internship_id,
            'session_name' => $session_name,
            'student_name' => $student_name,
            'dob' => $dob,
            'email' => $email,
            'mobile' => '+91 ' . $mobile,
            'gender' => $gender,
            'highest_qualification' => $highest_qualification,
            'school_name' => $school_name,
            'aadhaar_number' => $aadhaar_number,
            'pan_number' => null, // Omitted
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

        // Pricing Calculation
        $internship_sales_price = 0;
        foreach ($internships as $intern) {
            if ($intern['id'] == $internship_id) {
                $internship_sales_price = floatval($intern['sales_price']);
                break;
            }
        }
        
        $internship_sales_price_global = $internship_sales_price;
        
        // Insert into student_fees table initially as pending
        try {
            $stmtFee = $db->prepare("INSERT INTO `student_fees` (student_id, student_type, enrollment_number, amount, payment_status, receipt_path, razorpay_payment_id) VALUES (?, 'internship', 'PENDING', ?, 'pending', NULL, NULL)");
            $stmtFee->execute([$admission_id, $internship_sales_price]);
        } catch (Exception $feeEx) {
            error_log("Failed to insert initial pending internship fee record: " . $feeEx->getMessage());
        }

        $amount_in_paise = (int)($internship_sales_price * 100);

        if ($amount_in_paise > 0) {
            $api = MG_GetRazorpayClient();
            $orderData = [
                'receipt'         => 'rcptid_int_' . $admission_id,
                'amount'          => $amount_in_paise,
                'currency'        => 'INR',
                'payment_capture' => 1
            ];
            
            $razorpayOrder = $api->order->create($orderData);
            $razorpay_order_id_global = $razorpayOrder['id'];
            
            $stmt = $db->prepare("UPDATE `internship_admissions` SET `razorpay_order_id` = ? WHERE id = ?");
            $stmt->execute([$razorpay_order_id_global, $admission_id]);
        } else {
            $prefix = "MGINT";
            $monthYear = date('my');
            $basePrefix = $prefix . $monthYear;
            
            $stmtSeq = $db->prepare("SELECT enrollment_number FROM internship_admissions WHERE enrollment_number LIKE ? ORDER BY id DESC LIMIT 1");
            $stmtSeq->execute([$basePrefix . '%']);
            $lastRecord = $stmtSeq->fetch();
            
            $nextSeries = 1;
            if ($lastRecord && !empty($lastRecord['enrollment_number'])) {
                $lastSeriesStr = substr($lastRecord['enrollment_number'], -3);
                $nextSeries = intval($lastSeriesStr) + 1;
            }
            $enrollment_number = $basePrefix . sprintf("%03d", $nextSeries);

            $stmt = $db->prepare("UPDATE `internship_admissions` SET `payment_status` = 'free', `status` = 'confirmed', `enrollment_number` = ? WHERE id = ?");
            $stmt->execute([$enrollment_number, $admission_id]);
            
            $receiptFile = MG_GenerateInternshipReceiptPDF($admission_id);
            
            // Sync with student_fees table for scholarship / free
            try {
                $relativeReceipt = 'assets/uploads/internships/receipts/receipt_' . $admission_id . '.pdf';
                $feeCheck = $db->prepare("SELECT id FROM `student_fees` WHERE student_id = ? AND student_type = 'internship'");
                $feeCheck->execute([$admission_id]);
                if ($feeCheck->fetch()) {
                    $feeUpd = $db->prepare("UPDATE `student_fees` SET enrollment_number = ?, amount = 0.00, payment_status = 'free', receipt_path = ?, razorpay_payment_id = 'SCHOLARSHIP' WHERE student_id = ? AND student_type = 'internship'");
                    $feeUpd->execute([$enrollment_number, $relativeReceipt, $admission_id]);
                } else {
                    $feeIns = $db->prepare("INSERT INTO `student_fees` (student_id, student_type, enrollment_number, amount, payment_status, receipt_path, razorpay_payment_id) VALUES (?, 'internship', ?, 0.00, 'free', ?, 'SCHOLARSHIP')");
                    $feeIns->execute([$admission_id, $enrollment_number, $relativeReceipt]);
                }
            } catch (Exception $feeEx) {
                error_log("Failed to sync internship fee during free registration: " . $feeEx->getMessage());
            }

            $subject = "Internship Confirmed - MG Education";
            $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>
                <div style='background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%); padding: 20px; text-align: center;'>
                    <h2 style='color: #ffffff; margin: 0; font-size: 24px;'>Internship Confirmed</h2>
                </div>
                <div style='padding: 30px; background: #ffffff;'>
                    <h3 style='color: #1e293b; font-size: 20px; margin-top: 0;'>Welcome to MG Education, {$student_name}!</h3>
                    <p style='color: #475569; font-size: 15px; line-height: 1.6;'>Your internship profile has been successfully processed. We are thrilled to welcome you as our project training intern.</p>
                    <div style='background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; margin: 25px 0; text-align: left;'>
                        <p style='margin: 0 0 12px 0; font-size: 13px; color: #475569; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #cbd5e1; padding-bottom: 6px;'>Your Student Portal Credentials</p>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 6px 0; font-size: 14px; color: #475569; width: 40%;'><strong>Enrollment Number:</strong></td>
                                <td style='padding: 6px 0; font-size: 15px; color: #0d47a1; font-family: monospace; font-weight: bold; letter-spacing: 1px;'>$enrollment_number</td>
                            </tr>
                            <tr>
                                <td style='padding: 6px 0; font-size: 14px; color: #475569;'><strong>Portal Password:</strong></td>
                                <td style='padding: 6px 0; font-size: 15px; color: #dc2626; font-family: monospace; font-weight: bold;'>$password</td>
                            </tr>
                            <tr>
                                <td style='padding: 6px 0; font-size: 14px; color: #475569;'><strong>Fees Status:</strong></td>
                                <td style='padding: 6px 0; font-size: 14px; color: #2563eb; font-weight: bold; text-transform: uppercase;'>FREE REGISTRATION / SCHOLARSHIP</td>
                            </tr>
                        </table>
                    </div>
                    <p style='color: #475569; font-size: 14px; line-height: 1.6;'>Your official A4 fee receipt has been dynamically generated and attached to this email. Please keep your enrollment number and login password secure for learning portal access.</p>
                    <p style='color: #475569; font-size: 14px; line-height: 1.6; margin-top: 15px;'>Our internship coordinator will contact you shortly with your class schedule and learning portal access instructions.</p>
                </div>
                <div style='background: #f1f5f9; padding: 15px; text-align: center; border-top: 1px solid #e2e8f0;'>
                    <p style='color: #94a3b8; font-size: 12px; margin: 0;'>&copy; " . date('Y') . " MG Education & Social Development Organization. All Rights Reserved.</p>
                </div>
            </div>";
            
            $mailOptions = [];
            if ($receiptFile) {
                $mailOptions['attachments'] = [$receiptFile];
            }
            MG_SendMail($email, $subject, $body, $mailOptions);
            
            $success_message = "Admissions registration submitted successfully! Portal credentials and receipt have been dispatched to your email.";
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

$dynamic_title = "Online Internship Admission Portal | MG Education";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($dynamic_title) ?></title>
    
    <!-- Bootstrap 5 & Shared Premium UI Stylesheet -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/css/portal.css">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<div class="portal-wrapper">
    <!-- Left Hand side: Static Dashboard Sidebar -->
    <div class="portal-sidebar">
        <div class="sidebar-header">
            <h1 class="sidebar-title">MG Education <span class="d-block text-warning" style="font-size: 20px;">Internship Portal</span></h1>
            <p class="sidebar-subtitle">Join our premium project training and start your journey to success. Fill out the application form to proceed.</p>
        </div>
        
        <div class="sidebar-steps" id="formStepProgress">
            <div class="step-item active" id="step-1">
                <span class="step-num">1</span>
                <div>
                    <span class="step-title d-block">Program & Bio</span>
                    <span class="step-desc">Internship & Personal Info</span>
                </div>
            </div>
            <div class="step-item" id="step-2">
                <span class="step-num">2</span>
                <div>
                    <span class="step-title d-block">Academic Profile</span>
                    <span class="step-desc">Qualification Details</span>
                </div>
            </div>
            <div class="step-item" id="step-3">
                <span class="step-num">3</span>
                <div>
                    <span class="step-title d-block">Family & Contact</span>
                    <span class="step-desc">Parents & Address Info</span>
                </div>
            </div>
            <div class="step-item" id="step-4">
                <span class="step-num">4</span>
                <div>
                    <span class="step-title d-block">Upload & Submit</span>
                    <span class="step-desc">Upload Portrait & Scans</span>
                </div>
            </div>
        </div>
        
        <div class="sidebar-footer">
            <p class="mb-1">© <?= date('Y') ?> MG Skill. All rights reserved.</p>
            <p class="small text-white-50">Designed by Rahul Dhiman</p>
        </div>
    </div>

    <!-- Right Hand side: Scrollable Form Workspace -->
    <div class="portal-content">
        <form method="POST" action="" enctype="multipart/form-data" id="admissionForm" novalidate>
            <div class="admission-form-body">
                            
                <!-- SECTION 1: Internship Selection & Biographical details -->
                <div class="form-section-card" id="sect-1">
                    <h3 class="section-card-title">
                        <i class="fa-solid fa-laptop-code"></i> 01. Internship Program & Bio Details
                    </h3>
                    <div class="section-card-body">
                        
                        <!-- Row 1: Internship Selection | Session | Gender -->
                        <div class="row">
                            <div class="col-md-5 mb-4">
                                <label class="form-label" for="internship_id">Select Internship Program <span class="text-danger">*</span></label>
                                <div class="field-icon-wrapper">
                                    <select name="internship_id" id="internship_id" class="form-control-premium form-select form-select-premium" required>
                                        <option value="">Choose Internship</option>
                                        <?php foreach ($internships as $i): ?>
                                            <option value="<?= $i['id'] ?>" <?= ($selected_internship_id == $i['id']) ? 'selected' : '' ?>><?= htmlspecialchars($i['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fa-solid fa-laptop-code input-icon"></i>
                                    <div class="invalid-feedback">Please select a valid internship program.</div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <label class="form-label" for="session_name">Academic Session <span class="text-danger">*</span></label>
                                <div class="field-icon-wrapper">
                                    <select name="session_name" id="session_name" class="form-control-premium form-select form-select-premium" required>
                                        <option value="">Choose Session</option>
                                        <?php foreach ($active_sessions as $s): ?>
                                            <option value="<?= htmlspecialchars($s['session_name']) ?>"><?= htmlspecialchars($s['session_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fa-solid fa-calendar-check input-icon"></i>
                                    <div class="invalid-feedback">Academic Session selection is required.</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-4">
                                <label class="form-label" for="gender">Gender <span class="text-danger">*</span></label>
                                <div class="field-icon-wrapper">
                                    <select name="gender" id="gender" class="form-control-premium form-select form-select-premium" required>
                                        <option value="">Select Gender</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                    <i class="fa-solid fa-venus-mars input-icon"></i>
                                    <div class="invalid-feedback">Please select your gender.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: Candidate Name | Date of Birth -->
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="form-label" for="student_name">Applicant's Full Name <span class="text-danger">*</span> <span class="label-helper-text">(As per Matriculation)</span></label>
                                <div class="field-icon-wrapper">
                                    <input type="text" name="student_name" id="student_name" class="form-control-premium" placeholder="e.g. Rahul Sharma" required minlength="3">
                                    <i class="fa-solid fa-user input-icon"></i>
                                    <div class="invalid-feedback">Applicant's full name is required (min 3 chars).</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label" for="dob">Date of Birth <span class="text-danger">*</span> <span class="label-helper-text">(As per Matriculation)</span></label>
                                <div class="field-icon-wrapper">
                                    <input type="date" name="dob" id="dob" class="form-control-premium" required max="<?= date('Y-m-d') ?>">
                                    <i class="fa-solid fa-calendar-days input-icon"></i>
                                    <div class="invalid-feedback">Please enter a valid Date of Birth.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 3: Mobile | Email -->
                        <div class="row">
                            <div class="col-md-5 mb-4">
                                <label class="form-label" for="mobile">Mobile Number <span class="text-danger">*</span> <span class="label-helper-text">(Indian Standard)</span></label>
                                <div class="mobile-input-wrapper">
                                    <div class="indian-flag-prefix">
                                        <img src="https://flagcdn.com/w20/in.png" alt="IN" width="16"> +91
                                    </div>
                                    <input type="tel" name="mobile" id="mobile" class="form-control-premium" placeholder="10-Digit Mobile" required pattern="[6-9][0-9]{9}" maxlength="10">
                                    <div class="invalid-feedback" style="position:absolute; bottom:-20px; left:0;">Enter a valid 10-digit Indian Mobile.</div>
                                </div>
                            </div>
                            <div class="col-md-7 mb-4">
                                <label class="form-label" for="email">Active Email Address <span class="text-danger">*</span> <span class="badge badge-warning" id="email-status-text" style="background:#ffc107; color:#1e293b; font-size:10px; padding:2px 6px; border-radius:4px; margin-left:6px;">Needs Verification</span></label>
                                <div class="email-input-wrapper">
                                    <div class="field-icon-wrapper" style="flex-grow: 1;">
                                        <input type="email" name="email" id="email" class="form-control-premium" placeholder="student@example.com" required>
                                        <i class="fa-solid fa-envelope input-icon"></i>
                                        <div class="invalid-feedback">Please provide a valid active email.</div>
                                    </div>
                                    <button type="button" class="btn-verify-email" id="btnVerifyEmail" onclick="verifyStudentEmail()">Verify Email</button>
                                    <div class="email-verified-badge" id="emailVerifiedBadge" style="display:none;">
                                        <i class="fa-solid fa-circle-check"></i> Verified
                                    </div>
                                </div>
                                <input type="hidden" id="email_verified_flag" name="email_verified" value="0">
                            </div>
                        </div>

                    </div>
                </div>

                <!-- SECTION 2: Academic Qualification Parameters -->
                <div class="form-section-card" id="sect-2">
                    <h3 class="section-card-title">
                        <i class="fa-solid fa-graduation-cap"></i> 02. Academic Qualification Profile
                    </h3>
                    <div class="section-card-body">
                        
                        <!-- Aadhaar identity validation card -->
                        <div class="mb-4" id="aadhaar-details-box" style="border: 1px solid #cbd5e1; padding: 22px; border-radius: 8px;">
                            <div class="qual-subsection-header" style="background: linear-gradient(135deg,#fff5f5 0%,#fef2f2 100%); border-color: #fecaca; margin-bottom: 16px;">
                                <div class="qual-icon" style="background: linear-gradient(135deg,#dc2626,#ef4444);"><i class="fa-solid fa-id-card"></i></div>
                                <span class="qual-label" style="color:#b91c1c;">Aadhaar Identity Verification</span>
                                <span class="qual-badge" style="background:#dc2626;">UIDAI</span>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label class="form-label" for="aadhaar_number_input">12-Digit Aadhaar Number <span class="text-danger">*</span></label>
                                    <div class="field-icon-wrapper">
                                        <input type="text" id="aadhaar_number_input" name="aadhaar_number_visible" class="form-control-premium" placeholder="XXXX XXXX XXXX" maxlength="14" required style="letter-spacing: 0.5px; font-weight: 600;">
                                        <i class="fa-solid fa-id-card input-icon"></i>
                                        <div class="invalid-feedback">Please enter a valid 12-digit Aadhaar number.</div>
                                    </div>
                                    <p style="font-size: 10.5px; color: #64748b; margin-top: 5px;">Your 12-digit Aadhaar number.</p>
                                    <input type="hidden" id="aadhaar_number" name="aadhaar_number" value="">
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">Upload Aadhaar Scanned PDF <span class="text-danger">*</span></label>
                                    <div class="pdf-upload-card" onclick="triggerFileInput('aadhaar_card')">
                                        <i class="fa-solid fa-file-pdf pdf-upload-icon" style="margin-bottom: 4px;"></i>
                                        <span class="pdf-upload-title" style="font-size: 11px;">Aadhaar PDF</span>
                                        <input type="file" id="aadhaar_card" name="aadhaar_card" accept="application/pdf" style="display:none !important;" onchange="previewPdfFile(this, 'aadhaar')" required>
                                        <div class="pdf-preview-container" id="aadhaar-pdf-preview" style="display:none;">
                                            <button type="button" class="btn-remove-pdf" onclick="removePdfFile('aadhaar_card', 'aadhaar', event)"><i class="fa-solid fa-xmark"></i></button>
                                            <i class="fa-solid fa-circle-check pdf-success-icon"></i>
                                            <span class="pdf-file-name" id="aadhaar-pdf-name">pdf</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Highest Qualification Select Box -->
                        <div class="row">
                            <div class="col-md-12 mb-4">
                                <label class="form-label" for="highest_qualification">Highest Educational Qualification <span class="text-danger">*</span></label>
                                <div class="field-icon-wrapper">
                                    <select id="highest_qualification" name="highest_qualification" class="form-control-premium form-select form-select-premium" required onchange="handleQualificationChange(this)">
                                        <option value="">Choose Highest Qualification</option>
                                        <option value="below_10th_5">Class 5th (Primary)</option>
                                        <option value="below_10th_8">Class 8th (Middle School)</option>
                                        <option value="class_10th">Class 10th (Matriculation)</option>
                                        <option value="class_12th">Class 12th (Intermediate)</option>
                                        <option value="graduation">Graduation (Bachelor's Degree)</option>
                                        <option value="post_graduation">Post Graduation (Master's Degree)</option>
                                        <option value="phd">Ph.D (Doctorate Degree)</option>
                                    </select>
                                    <i class="fa-solid fa-graduation-cap input-icon"></i>
                                    <div class="invalid-feedback">Highest educational qualification is required.</div>
                                </div>
                            </div>
                        </div>

                        <!-- CONDITION A: Below Class 10th -->
                        <div id="qual-below-10th-box" class="qualification-conditional-box">
                            <div class="qual-subsection-header">
                                <div class="qual-icon"><i class="fa-solid fa-school-flag"></i></div>
                                <span class="qual-label">School Level — Primary / Middle School Details</span>
                                <span class="qual-badge" style="background:#64748b;">School</span>
                            </div>
                            <div class="row">
                                <div class="col-md-12 mb-4">
                                    <label class="form-label" for="school_name">Current / Last Attended School Name <span class="text-danger">*</span></label>
                                    <div class="field-icon-wrapper">
                                        <input type="text" id="school_name" name="school_name" class="form-control-premium" placeholder="e.g. Govt Sr. Sec. School" required>
                                        <i class="fa-solid fa-school input-icon"></i>
                                        <div class="invalid-feedback">School Name is required.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CONDITION B: Class 10th -->
                        <div id="qual-10th-box" class="qualification-conditional-box">
                            <div class="qual-subsection-header">
                                <div class="qual-icon"><i class="fa-solid fa-award"></i></div>
                                <span class="qual-label">Matriculation — Class 10th Details</span>
                                <span class="qual-badge">Board Exam</span>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-4">
                                    <label class="form-label" for="class_10_roll">10th Roll Number <span class="text-danger">*</span></label>
                                    <div class="field-icon-wrapper">
                                        <input type="text" id="class_10_roll" name="class_10_roll" class="form-control-premium" placeholder="10th Roll No." required>
                                        <i class="fa-solid fa-hashtag input-icon"></i>
                                        <div class="invalid-feedback">Matriculation Roll Number is required.</div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-4">
                                    <label class="form-label" for="class_10_board">10th Board Name <span class="text-danger">*</span></label>
                                    <div class="field-icon-wrapper">
                                        <select id="class_10_board" name="class_10_board" class="form-control-premium form-select form-select-premium" required>
                                            <option value="">Choose Board</option>
                                            <option value="CBSE">CBSE (Central Board)</option>
                                            <option value="ICSE">CISCE (ICSE/ISC)</option>
                                            <option value="UP Board">UP Board</option>
                                            <option value="Other Board">Other State Board</option>
                                        </select>
                                        <i class="fa-solid fa-building-columns input-icon"></i>
                                        <div class="invalid-feedback">Please choose a valid High School board.</div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-4">
                                    <label class="form-label" for="class_10_school">10th School Name <span class="text-danger">*</span></label>
                                    <div class="field-icon-wrapper">
                                        <input type="text" id="class_10_school" name="class_10_school" class="form-control-premium" placeholder="10th School Name" required>
                                        <i class="fa-solid fa-school input-icon"></i>
                                        <div class="invalid-feedback">Matriculation school name is required.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12 mb-4">
                                    <label class="form-label">Upload Class 10th Marksheet <span class="text-danger">*</span> <span class="label-helper-text">(Scanned PDF copy under 2MB)</span></label>
                                    <div class="pdf-upload-card" onclick="triggerFileInput('class_10_marksheet')">
                                        <i class="fa-solid fa-file-pdf pdf-upload-icon"></i>
                                        <span class="pdf-upload-title">Class 10th Marksheet Scan</span>
                                        <span class="pdf-upload-desc">Click or Drag & Drop PDF Scan (Max 2MB)</span>
                                        <input type="file" id="class_10_marksheet" name="class_10_marksheet" accept="application/pdf" style="display:none !important;" onchange="previewPdfFile(this, 'class_10')" required>
                                        <div class="pdf-preview-container" id="class_10-pdf-preview" style="display:none;">
                                            <button type="button" class="btn-remove-pdf" onclick="removePdfFile('class_10_marksheet', 'class_10', event)"><i class="fa-solid fa-xmark"></i></button>
                                            <i class="fa-solid fa-circle-check pdf-success-icon"></i>
                                            <span class="pdf-file-name" id="class_10-pdf-name">marksheet_10th.pdf</span>
                                            <span class="pdf-file-size" id="class_10-pdf-size">0.0 MB</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CONDITION C: Class 12th -->
                        <div id="qual-12th-box" class="qualification-conditional-box">
                            <div class="qual-subsection-header">
                                <div class="qual-icon"><i class="fa-solid fa-school"></i></div>
                                <span class="qual-label">Intermediate — Class 12th Details</span>
                                <span class="qual-badge">Board Exam</span>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-4">
                                    <label class="form-label" for="class_12_roll">12th Roll Number <span class="text-danger">*</span></label>
                                    <div class="field-icon-wrapper">
                                        <input type="text" id="class_12_roll" name="class_12_roll" class="form-control-premium" placeholder="12th Roll No." required>
                                        <i class="fa-solid fa-hashtag input-icon"></i>
                                        <div class="invalid-feedback">Intermediate Roll Number is required.</div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-4">
                                    <label class="form-label" for="class_12_board">12th Board Name <span class="text-danger">*</span></label>
                                    <div class="field-icon-wrapper">
                                        <select id="class_12_board" name="class_12_board" class="form-control-premium form-select form-select-premium" required>
                                            <option value="">Choose Board</option>
                                            <option value="CBSE">CBSE (Central Board)</option>
                                            <option value="ICSE">CISCE (ICSE/ISC)</option>
                                            <option value="UP Board">UP Board</option>
                                            <option value="Other Board">Other State Board</option>
                                        </select>
                                        <i class="fa-solid fa-building-columns input-icon"></i>
                                        <div class="invalid-feedback">Please choose a valid Intermediate board.</div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-4">
                                    <label class="form-label" for="class_12_school">12th School Name <span class="text-danger">*</span></label>
                                    <div class="field-icon-wrapper">
                                        <input type="text" id="class_12_school" name="class_12_school" class="form-control-premium" placeholder="12th School Name" required>
                                        <i class="fa-solid fa-school input-icon"></i>
                                        <div class="invalid-feedback">Intermediate school name is required.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12 mb-4">
                                    <label class="form-label">Upload Class 12th Marksheet <span class="text-danger">*</span> <span class="label-helper-text">(Scanned PDF copy under 2MB)</span></label>
                                    <div class="pdf-upload-card" onclick="triggerFileInput('class_12_marksheet')">
                                        <i class="fa-solid fa-file-pdf pdf-upload-icon"></i>
                                        <span class="pdf-upload-title">Class 12th Marksheet Scan</span>
                                        <span class="pdf-upload-desc">Click or Drag & Drop PDF Scan (Max 2MB)</span>
                                        <input type="file" id="class_12_marksheet" name="class_12_marksheet" accept="application/pdf" style="display:none !important;" onchange="previewPdfFile(this, 'class_12')" required>
                                        <div class="pdf-preview-container" id="class_12-pdf-preview" style="display:none;">
                                            <button type="button" class="btn-remove-pdf" onclick="removePdfFile('class_12_marksheet', 'class_12', event)"><i class="fa-solid fa-xmark"></i></button>
                                            <i class="fa-solid fa-circle-check pdf-success-icon"></i>
                                            <span class="pdf-file-name" id="class_12-pdf-name">marksheet_12th.pdf</span>
                                            <span class="pdf-file-size" id="class_12-pdf-size">0.0 MB</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CONDITION D: Higher College standard -->
                        <div id="qual-college-box" class="qualification-conditional-box">
                            <div class="qual-subsection-header">
                                <div class="qual-icon"><i class="fa-solid fa-university"></i></div>
                                <span class="qual-label">Higher Education — College / University Details</span>
                                <span class="qual-badge">Degree</span>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label class="form-label" for="college_name">College / University Name <span class="text-danger">*</span></label>
                                    <div class="field-icon-wrapper">
                                        <input type="text" id="college_name" name="college_name" class="form-control-premium" placeholder="e.g. Delhi University" required>
                                        <i class="fa-solid fa-university input-icon"></i>
                                        <div class="invalid-feedback">College name is required.</div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="form-label" for="college_roll">Roll / Enrollment Number <span class="text-danger">*</span></label>
                                    <div class="field-icon-wrapper">
                                        <input type="text" id="college_roll" name="college_roll" class="form-control-premium" placeholder="College Roll/Enroll No." required>
                                        <i class="fa-solid fa-id-card-clip input-icon"></i>
                                        <div class="invalid-feedback">College Roll/Enrollment Number is required.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-4">
                                    <label class="form-label" for="college_degree">Degree & Course Name <span class="text-danger">*</span></label>
                                    <div class="field-icon-wrapper">
                                        <input type="text" id="college_degree" name="college_degree" class="form-control-premium" placeholder="e.g. B.Tech Computer Science" required>
                                        <i class="fa-solid fa-award input-icon"></i>
                                        <div class="invalid-feedback">Degree and Course Name is required.</div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-4">
                                    <label class="form-label" for="college_passing_year">Passing Year <span class="text-danger">*</span></label>
                                    <div class="field-icon-wrapper">
                                        <select id="college_passing_year" name="college_passing_year" class="form-control-premium form-select form-select-premium" required>
                                            <option value="">Choose Year</option>
                                            <?php for ($y = 2026; $y >= 2005; $y--): ?>
                                                <option value="<?= $y ?>"><?= $y ?></option>
                                            <?php endfor; ?>
                                        </select>
                                        <i class="fa-regular fa-calendar input-icon"></i>
                                        <div class="invalid-feedback">Please choose a valid passing year.</div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-4">
                                    <label class="form-label" for="college_marks_type">Evaluation Method <span class="text-danger">*</span></label>
                                    <div class="field-icon-wrapper">
                                        <select id="college_marks_type" name="college_marks_type" class="form-control-premium form-select form-select-premium" required onchange="toggleMarksPlaceholder(this)">
                                            <option value="">Choose Method</option>
                                            <option value="percentage">Percentage (%)</option>
                                            <option value="cgpa">CGPA (10-Point Scale)</option>
                                        </select>
                                        <i class="fa-solid fa-square-poll-vertical input-icon"></i>
                                        <div class="invalid-feedback">Please choose evaluation method.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="row" style="align-items: flex-end;">
                                <div class="col-md-4 mb-4">
                                    <label class="form-label" for="college_marks_value" id="lbl-marks-eval">Obtained Value <span class="text-danger">*</span></label>
                                    <div class="field-icon-wrapper">
                                        <input type="text" id="college_marks_value" name="college_marks_value" class="form-control-premium" placeholder="Evaluation value" required>
                                        <i class="fa-solid fa-chart-line input-icon"></i>
                                        <div class="invalid-feedback">Obtained marks value is required.</div>
                                    </div>
                                </div>
                                <div class="col-md-8 mb-4">
                                    <label class="form-label">Upload Graduation Marksheet <span class="text-danger">*</span> <span class="label-helper-text">(Scanned PDF under 2MB)</span></label>
                                    <div class="pdf-upload-card" onclick="triggerFileInput('college_marksheet')">
                                        <i class="fa-solid fa-file-pdf pdf-upload-icon"></i>
                                        <span class="pdf-upload-title">Graduation Marksheet Scan</span>
                                        <span class="pdf-upload-desc">Click or Drag & Drop PDF Scan (Max 2MB)</span>
                                        <input type="file" id="college_marksheet" name="college_marksheet" accept="application/pdf" style="display:none !important;" onchange="previewPdfFile(this, 'college')" required>
                                        <div class="pdf-preview-container" id="college-pdf-preview" style="display:none;">
                                            <button type="button" class="btn-remove-pdf" onclick="removePdfFile('college_marksheet', 'college', event)"><i class="fa-solid fa-xmark"></i></button>
                                            <i class="fa-solid fa-circle-check pdf-success-icon"></i>
                                            <span class="pdf-file-name" id="college-pdf-name">marksheet_college.pdf</span>
                                            <span class="pdf-file-size" id="college-pdf-size">0.0 MB</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- SECTION 3: Parental & Location Profile -->
                <div class="form-section-card" id="sect-3">
                    <h3 class="section-card-title">
                        <i class="fa-solid fa-map-location-dot"></i> 03. Parental & Location Profile
                    </h3>
                    <div class="section-card-body">
                        
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="form-label" for="father_name">Father's Full Name <span class="text-danger">*</span> <span class="label-helper-text">(Strictly as per Matriculation)</span></label>
                                <div class="field-icon-wrapper">
                                    <input type="text" name="father_name" id="father_name" class="form-control-premium" placeholder="Father's Name" required>
                                    <i class="fa-solid fa-user-tie input-icon"></i>
                                    <div class="invalid-feedback">Father's full name is required.</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label" for="mother_name">Mother's Full Name <span class="text-danger">*</span> <span class="label-helper-text">(Strictly as per Matriculation)</span></label>
                                <div class="field-icon-wrapper">
                                    <input type="text" name="mother_name" id="mother_name" class="form-control-premium" placeholder="Mother's Name" required>
                                    <i class="fa-solid fa-user input-icon"></i>
                                    <div class="invalid-feedback">Mother's full name is required.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: State | District | City | Pincode -->
                        <div class="row">
                            <div class="col-md-3 mb-4">
                                <label class="form-label" for="state">State / Union Territory <span class="text-danger">*</span></label>
                                <div class="field-icon-wrapper">
                                    <select name="state" id="state" class="form-control-premium form-select form-select-premium" required>
                                        <option value="">Choose State</option>
                                        <optgroup label="States">
                                            <option value="Andhra Pradesh">Andhra Pradesh</option>
                                            <option value="Arunachal Pradesh">Arunachal Pradesh</option>
                                            <option value="Assam">Assam</option>
                                            <option value="Bihar">Bihar</option>
                                            <option value="Chhattisgarh">Chhattisgarh</option>
                                            <option value="Goa">Goa</option>
                                            <option value="Gujarat">Gujarat</option>
                                            <option value="Haryana">Haryana</option>
                                            <option value="Himachal Pradesh">Himachal Pradesh</option>
                                            <option value="Jharkhand">Jharkhand</option>
                                            <option value="Karnataka">Karnataka</option>
                                            <option value="Kerala">Kerala</option>
                                            <option value="Madhya Pradesh">Madhya Pradesh</option>
                                            <option value="Maharashtra">Maharashtra</option>
                                            <option value="Manipur">Manipur</option>
                                            <option value="Meghalaya">Meghalaya</option>
                                            <option value="Mizoram">Mizoram</option>
                                            <option value="Nagaland">Nagaland</option>
                                            <option value="Odisha">Odisha</option>
                                            <option value="Punjab">Punjab</option>
                                            <option value="Rajasthan">Rajasthan</option>
                                            <option value="Sikkim">Sikkim</option>
                                            <option value="Tamil Nadu">Tamil Nadu</option>
                                            <option value="Telangana">Telangana</option>
                                            <option value="Tripura">Tripura</option>
                                            <option value="Uttar Pradesh">Uttar Pradesh</option>
                                            <option value="Uttarakhand">Uttarakhand</option>
                                            <option value="West Bengal">West Bengal</option>
                                        </optgroup>
                                        <optgroup label="Union Territories">
                                            <option value="Andaman and Nicobar Islands">Andaman & Diu</option>
                                            <option value="Chandigarh">Chandigarh</option>
                                            <option value="Dadra and Nagar Haveli and Daman and Diu">Dadra & Diu</option>
                                            <option value="Delhi">Delhi (NCT)</option>
                                            <option value="Jammu and Kashmir">Jammu & Kashmir</option>
                                            <option value="Ladakh">Ladakh</option>
                                            <option value="Lakshadweep">Lakshadweep</option>
                                            <option value="Puducherry">Puducherry</option>
                                        </optgroup>
                                    </select>
                                    <i class="fa-solid fa-map input-icon"></i>
                                    <div class="invalid-feedback">State selection is mandatory.</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-4">
                                <label class="form-label" for="district">District <span class="text-danger">*</span></label>
                                <div class="field-icon-wrapper">
                                    <input type="text" name="district" id="district" class="form-control-premium" placeholder="District" required>
                                    <i class="fa-solid fa-map-pin input-icon"></i>
                                    <div class="invalid-feedback">District is required.</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-4">
                                <label class="form-label" for="city">City / Town <span class="text-danger">*</span></label>
                                <div class="field-icon-wrapper">
                                    <input type="text" name="city" id="city" class="form-control-premium" placeholder="City" required>
                                    <i class="fa-solid fa-city input-icon"></i>
                                    <div class="invalid-feedback">City/Town is required.</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-4">
                                <label class="form-label" for="pincode">Pincode <span class="text-danger">*</span></label>
                                <div class="field-icon-wrapper">
                                    <input type="text" name="pincode" id="pincode" class="form-control-premium" placeholder="6-Digit Pin" required pattern="[0-9]{6}" maxlength="6">
                                    <i class="fa-solid fa-location-arrow input-icon"></i>
                                    <div class="invalid-feedback">Provide a valid 6-digit Pincode.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 3: Permanent Address -->
                        <div class="mb-4">
                            <label class="form-label" for="address">Full Permanent Address <span class="text-danger">*</span></label>
                            <div class="field-icon-wrapper">
                                <input type="text" name="address" id="address" class="form-control-premium" placeholder="House/Flat No, Landmark, Sector, Street Address" required>
                                <i class="fa-solid fa-house-chimney input-icon"></i>
                                <div class="invalid-feedback">Full permanent address is required.</div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- SECTION 4: Scanned Portrait & Signature Uploads -->
                <div class="form-section-card" id="sect-4">
                    <h3 class="section-card-title">
                        <i class="fa-solid fa-file-arrow-up"></i> 04. Scanned Portrait & Signature Uploads
                    </h3>
                    <div class="section-card-body">
                        <div class="alert alert-info mb-4" style="border-radius:8px; font-size:13px; color:#1e3a8a; background:#eff6ff; border:1px solid #bfdbfe; text-align: left;">
                            <i class="fa-solid fa-circle-info"></i> Please upload passport-sized portrait and signature scans in portrait-oriented image files (JPG, PNG) under 2MB size parameters.
                        </div>

                        <div class="qual-subsection-header" style="background: linear-gradient(135deg,#fffbeb 0%,#fef3c7 100%); border-color: #fcd34d; margin-bottom: 20px;">
                            <div class="qual-icon" style="background: linear-gradient(135deg,#d97706,#f59e0b);"><i class="fa-solid fa-camera-retro"></i></div>
                            <span class="qual-label" style="color:#92400e;">Portrait Photo &amp; Scanned Signature</span>
                            <span class="qual-badge" style="background:#d97706;">Required</span>
                        </div>

                        <div class="upload-card-wrapper">
                            <!-- Student Portrait -->
                            <div class="upload-card" onclick="triggerFileInput('student_photo')">
                                <i class="fa-regular fa-image" id="photo-icon"></i>
                                <span class="upload-card-title">Student Portrait Photo <span class="text-danger">*</span></span>
                                <span class="upload-card-desc">Click or Drag & Drop Scan (JPG / PNG)</span>
                                <span class="upload-spec-guide">Size: 3.5cm x 4.5cm | Clear Front Face</span>
                                <input type="file" id="student_photo" name="student_photo" accept="image/*" style="display: none !important;" onchange="previewUploadFile(this, 'photo')" required>
                                <div class="upload-preview-container" id="photo-preview-box">
                                    <button type="button" class="btn-remove-upload" onclick="removeUploadFile('student_photo', 'photo', event)"><i class="fa-solid fa-xmark"></i></button>
                                    <img src="" id="photo-preview-img" alt="Student Photo">
                                </div>
                            </div>

                            <!-- Student Signature -->
                            <div class="upload-card" onclick="triggerFileInput('student_signature')">
                                <i class="fa-solid fa-signature" id="sig-icon"></i>
                                <span class="upload-card-title">Student Scanned Signature <span class="text-danger">*</span></span>
                                <span class="upload-card-desc">Click or Drag & Drop Scan (JPG / PNG)</span>
                                <span class="upload-spec-guide">Signed in Black Ink on White Paper</span>
                                <input type="file" id="student_signature" name="student_signature" accept="image/*" style="display: none !important;" onchange="previewUploadFile(this, 'sig')" required>
                                <div class="upload-preview-container" id="sig-preview-box">
                                    <button type="button" class="btn-remove-upload" onclick="removeUploadFile('student_signature', 'sig', event)"><i class="fa-solid fa-xmark"></i></button>
                                    <img src="" id="sig-preview-img" alt="Student Signature">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- SECTION 5: Declaration & Submit -->
            <div class="admission-form-body pt-0">
                <div class="form-section-card" style="border-color: #a7f3d0;">
                    <h3 class="section-card-title" style="background: #ecfdf5; color: #059669; border-bottom-color: #a7f3d0;">
                        <i class="fa-solid fa-shield-halved" style="color: #059669;"></i> 05. Declaration & Submission
                    </h3>
                    <div class="section-card-body pb-3">
                        <div style="display: flex; align-items: flex-start; gap: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px;">
                            <input type="checkbox" id="declaration_check" style="width: 20px; height: 20px; margin-top: 2px; accent-color: #1e3a8a; cursor: pointer; flex-shrink: 0;">
                            <label for="declaration_check" style="font-size: 12.5px; color: #475569; line-height: 1.65; cursor: pointer; text-align: left;">
                                I hereby declare that all the information furnished in this application form is true, complete and correct to the best of my knowledge and belief. I understand that in the event of any information being found false or incorrect, my admission shall be liable for cancellation without any refund of fees paid.
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="admission-form-actions">
                <button type="submit" name="submit_admission" class="form-btn-submit-premium" id="btnSubmit">
                    <i class="fa-solid fa-circle-check"></i> Submit Admission Application
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function triggerFileInput(inputId) {
        const el = document.getElementById(inputId);
        if (el) el.click();
    }

    function previewUploadFile(inputEl, type) {
        const file = inputEl.files[0];
        if (file) {
            if (!file.type.match('image.*')) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Format',
                    text: 'Please select a valid portrait image scan format (JPG, PNG).',
                    confirmButtonColor: '#1e3a8a'
                });
                inputEl.value = '';
                return;
            }
            if (file.size > 2 * 1024 * 1024) {
                Swal.fire({
                    icon: 'warning',
                    title: 'File Too Large',
                    text: 'The selected scan exceeds the 2MB size limit.',
                    confirmButtonColor: '#1e3a8a'
                });
                inputEl.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = function(e) {
                $(`#${type}-preview-img`).attr('src', e.target.result);
                $(`#${type}-preview-box`).css('display', 'flex').addClass('animate__animated animate__fadeIn');
            };
            reader.readAsDataURL(file);
        }
    }

    function removeUploadFile(inputId, type, event) {
        if (event) event.stopPropagation();
        $('#' + inputId).val('');
        $(`#${type}-preview-box`).hide();
        $(`#${type}-preview-img`).attr('src', '');
    }

    function previewPdfFile(inputEl, idPrefix) {
        const file = inputEl.files[0];
        if (file) {
            if (file.type !== 'application/pdf') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Format',
                    text: 'Please select a valid scanned academic marksheet in PDF format only.',
                    confirmButtonColor: '#1e3a8a'
                });
                inputEl.value = '';
                return;
            }
            if (file.size > 2 * 1024 * 1024) {
                Swal.fire({
                    icon: 'warning',
                    title: 'File Too Large',
                    text: 'The marksheet PDF scan exceeds the 2MB size limit.',
                    confirmButtonColor: '#1e3a8a'
                });
                inputEl.value = '';
                return;
            }
            const sizeInMB = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
            $(`#${idPrefix}-pdf-name`).text(file.name);
            $(`#${idPrefix}-pdf-size`).text(sizeInMB);
            $(`#${idPrefix}-pdf-preview`).css('display', 'flex');
        }
    }

    function removePdfFile(inputId, idPrefix, event) {
        if (event) event.stopPropagation();
        $('#' + inputId).val('');
        $(`#${idPrefix}-pdf-preview`).hide();
    }

    function handleQualificationChange(selectEl) {
        const val = selectEl.value;
        $('.qualification-conditional-box').hide();
        
        if (val === 'below_10th_5' || val === 'below_10th_8') {
            $('#qual-below-10th-box').slideDown(250);
        } else if (val === 'class_10th') {
            $('#qual-10th-box').slideDown(250);
        } else if (val === 'class_12th') {
            $('#qual-10th-box').slideDown(250);
            $('#qual-12th-box').slideDown(250);
        } else if (val === 'graduation' || val === 'post_graduation' || val === 'phd') {
            $('#qual-10th-box').slideDown(250);
            $('#qual-12th-box').slideDown(250);
            $('#qual-college-box').slideDown(250);
        }
        
        setTimeout(updateStepProgress, 300);
    }

    function toggleMarksPlaceholder(selectEl) {
        const val = selectEl.value;
        const input = document.getElementById('college_marks_value');
        const lbl = document.getElementById('lbl-marks-eval');
        if (!input || !lbl) return;
        if (val === 'percentage') {
            lbl.innerHTML = 'Obtained Percentage (%) <span class="text-danger">*</span>';
            input.placeholder = 'e.g. 84.5';
        } else if (val === 'cgpa') {
            lbl.innerHTML = 'Obtained CGPA <span class="text-danger">*</span>';
            input.placeholder = 'e.g. 8.5';
        } else {
            lbl.innerHTML = 'Obtained Value <span class="text-danger">*</span>';
            input.placeholder = 'Evaluation value';
        }
    }

    function updateStepProgress() {
        const sections = $('.form-section-card');
        let lastActive = 0;
        sections.each(function(index) {
            const rect = this.getBoundingClientRect();
            if (rect.top < window.innerHeight * 0.5) {
                lastActive = index;
            }
        });

        for (let i = 1; i <= 4; i++) {
            const step = $('#step-' + i);
            if (!step.length) continue;
            step.removeClass('active completed');
            if (i - 1 < lastActive) {
                step.addClass('completed');
            } else if (i - 1 === lastActive) {
                step.addClass('active');
            }
        }
    }

    $(window).on('scroll', updateStepProgress);

    $(document).ready(function() {
        updateStepProgress();

        // Aadhaar mask XXXX XXXX XXXX
        $('#aadhaar_number_input').on('input', function() {
            let val = this.value.replace(/\D/g, '');
            if (val.length > 12) val = val.substring(0, 12);
            let formatted = '';
            for (let i = 0; i < val.length; i++) {
                if (i > 0 && i % 4 === 0) formatted += ' ';
                formatted += val[i];
            }
            this.value = formatted;
            $('#aadhaar_number').val(val);
        });

        $('#mobile').on('input', function() {
            this.value = this.value.replace(/\D/g, '').substring(0, 10);
        });

        $('#pincode').on('input', function() {
            this.value = this.value.replace(/\D/g, '').substring(0, 6);
        });

        // Drag & Drop Handling
        const dragZones = [
            { inputId: 'student_photo', type: 'photo', isPdf: false },
            { inputId: 'student_signature', type: 'sig', isPdf: false },
            { inputId: 'aadhaar_card', type: 'aadhaar', isPdf: true },
            { inputId: 'class_10_marksheet', type: 'class_10', isPdf: true },
            { inputId: 'class_12_marksheet', type: 'class_12', isPdf: true },
            { inputId: 'college_marksheet', type: 'college', isPdf: true }
        ];

        dragZones.forEach(item => {
            const inputEl = document.getElementById(item.inputId);
            if (!inputEl) return;
            const area = $(inputEl).closest(item.isPdf ? '.pdf-upload-card' : '.upload-card');
            if (!area.length) return;
            
            area.on('dragover', function(e) {
                e.preventDefault();
                $(this).addClass('drag-over');
            });
            area.on('dragleave', function() {
                $(this).removeClass('drag-over');
            });
            area.on('drop', function(e) {
                e.preventDefault();
                $(this).removeClass('drag-over');
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    inputEl.files = files;
                    if (item.isPdf) {
                        previewPdfFile(inputEl, item.type);
                    } else {
                        previewUploadFile(inputEl, item.type);
                    }
                }
            });
        });

        // Submit form validation
        $('#btnSubmit').on('click', function(e) {
            e.preventDefault();
            const form = $('#admissionForm')[0];
            $(form).addClass('was-validated');

            const verified = $('#email_verified_flag').val();
            if (verified !== '1') {
                Swal.fire({
                    icon: 'error',
                    title: 'Email Verification Mandatory',
                    text: 'Please complete the OTP verification for your email address first.',
                    confirmButtonColor: '#dc2626'
                });
                return;
            }

            let formValid = true;
            let firstInvalidField = null;

            $(form).find('input, select, textarea').each(function() {
                if ($(this).attr('type') === 'hidden' || !$(this).is(':visible')) {
                    return;
                }

                $(this).removeClass('is-invalid');

                if ($(this).prop('required') && !this.value.trim()) {
                    formValid = false;
                    $(this).addClass('is-invalid');
                    if (!firstInvalidField) firstInvalidField = this;
                }

                if (this.id === 'aadhaar_number_input' && this.value.replace(/\s/g, '').length !== 12) {
                    formValid = false;
                    $(this).addClass('is-invalid');
                    if (!firstInvalidField) firstInvalidField = this;
                }

                if (this.id === 'mobile' && this.value.length !== 10) {
                    formValid = false;
                    $(this).addClass('is-invalid');
                    if (!firstInvalidField) firstInvalidField = this;
                }

                if (this.id === 'pincode' && this.value.length !== 6) {
                    formValid = false;
                    $(this).addClass('is-invalid');
                    if (!firstInvalidField) firstInvalidField = this;
                }
            });

            if (!formValid) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Validation Incomplete',
                    text: 'Please fill in all mandatory fields with valid values before submitting.',
                    confirmButtonColor: '#1e3a8a'
                });
                if (firstInvalidField) {
                    firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalidField.focus();
                }
                return;
            }

            if (!$('#declaration_check').is(':checked')) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Declaration Required',
                    text: 'Please accept the declaration terms by checking the checkbox.',
                    confirmButtonColor: '#1e3a8a'
                });
                return;
            }

            Swal.fire({
                title: 'Confirm Admission Profile?',
                text: 'Ensure all names and academic credentials perfectly match your records before submission.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#ef4444',
                confirmButtonText: 'Yes, Submit Registry!',
                cancelButtonText: 'Review Profile'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Registering Profile...',
                        text: 'Submitting secure student profiles to MG Education registry...',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });
                    setTimeout(() => {
                        const hiddenSubmit = $('<input type="hidden" name="submit_admission" value="1">');
                        $(form).append(hiddenSubmit);
                        form.submit();
                    }, 1000);
                }
            });
        });
    });

    function verifyStudentEmail() {
        const emailInput = $('#email');
        const email = emailInput.val().trim();
        
        if (!email) {
            Swal.fire('Email Missing', 'Please enter your email address first.', 'warning');
            return;
        }
        
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            Swal.fire('Invalid Email', 'Please enter a valid email address.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Sending OTP...',
            text: 'Please wait while we send a verification code to your email.',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: { ajax_action: 'send_otp', email: email },
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    Swal.fire({
                        title: 'Enter Verification Code',
                        text: 'A 6-digit OTP has been sent to ' + email,
                        input: 'text',
                        inputAttributes: {
                            autocapitalize: 'off',
                            placeholder: 'Enter 6-digit OTP',
                            maxlength: 6,
                            style: 'text-align:center; font-size: 24px; letter-spacing: 4px; font-weight: bold;'
                        },
                        showCancelButton: true,
                        confirmButtonText: 'Verify OTP',
                        confirmButtonColor: '#1e3a8a',
                        showLoaderOnConfirm: true,
                        preConfirm: (otp) => {
                            return $.ajax({
                                url: window.location.href,
                                method: 'POST',
                                data: { ajax_action: 'verify_otp', email: email, otp: otp },
                                dataType: 'json'
                            })
                            .then(verifyData => {
                                if (!verifyData.success) {
                                    throw new Error(verifyData.message || 'Invalid OTP');
                                }
                                return verifyData;
                            })
                            .catch(error => {
                                Swal.showValidationMessage(error.message || 'Invalid OTP');
                            });
                        },
                        allowOutsideClick: () => !Swal.isLoading()
                    }).then((result) => {
                        if (result.isConfirmed && result.value.success) {
                            Swal.fire('Verified!', 'Your email has been successfully verified.', 'success');
                            
                            $('#email_verified_flag').val('1');
                            emailInput.prop('readonly', true);
                            emailInput.css('background-color', '#f8fafc');
                            $('#btnVerifyEmail').hide();
                            $('#emailVerifiedBadge').show();
                            $('#email-status-text').hide();
                        }
                    });
                } else {
                    Swal.fire('Error', data.message || 'Failed to send OTP.', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'An unexpected error occurred. Please try again.', 'error');
            }
        });
    }
</script>

<?php if (!empty($razorpay_order_id_global)): ?>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var options = {
                "key": "<?= $_ENV['RAZORPAY_KEY_ID'] ?? '' ?>",
                "amount": "<?= (int)($internship_sales_price_global * 100) ?>",
                "currency": "INR",
                "name": "MG Education",
                "description": "Internship Admission Fee",
                "image": "assets/images/logo.png",
                "order_id": "<?= $razorpay_order_id_global ?>",
                "handler": function (response){
                    Swal.fire({
                        title: 'Verifying Payment...',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });
                    
                    $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: {
                            ajax_action: 'verify_payment',
                            razorpay_payment_id: response.razorpay_payment_id,
                            razorpay_order_id: response.razorpay_order_id,
                            razorpay_signature: response.razorpay_signature
                        },
                        dataType: 'json',
                        success: function(data) {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Payment Successful!',
                                    text: 'Your internship admission profile has been registered.',
                                    confirmButtonColor: '#10b981'
                                }).then(() => {
                                    window.location.href = 'index.php';
                                });
                            } else {
                                Swal.fire('Verification Failed', data.message, 'error');
                            }
                        }
                    });
                },
                "prefill": {
                    "name": "<?= htmlspecialchars($_POST['student_name'] ?? '') ?>",
                    "email": "<?= htmlspecialchars($_POST['email'] ?? '') ?>",
                    "contact": "<?= htmlspecialchars($_POST['mobile'] ?? '') ?>"
                },
                "theme": { "color": "#1e3a8a" },
                "modal": {
                    "ondismiss": function() {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Payment Cancelled',
                            text: 'Your payment was cancelled. Your internship enrollment is pending payment.',
                            confirmButtonColor: '#f59e0b'
                        });
                    }
                }
            };
            var rzp1 = new Razorpay(options);
            rzp1.open();
        });
    </script>
<?php elseif (!empty($success_message)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Internship Submitted!',
                text: 'Your internship admission profile has been registered in the MG Education registry.',
                confirmButtonColor: '#10b981',
                confirmButtonText: 'Great, Thank You!'
            }).then(() => {
                window.location.href = 'index.php';
            });
        });
    </script>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Database Submission Alert',
                text: <?= json_encode($error_message) ?>,
                confirmButtonColor: '#ef4444'
            });
        });
    </script>
<?php endif; ?>

</body>
</html>
