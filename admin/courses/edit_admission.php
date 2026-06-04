<?php
/**
 * MG Education & Social Development Organization
 * Dedicated Student Admission File Editor Dashboard
 */

include '../header.php';

$db = MG_GetDBConnection();
$error_message = '';
$success_message = '';
$admission = null;
$courses_list = [];
$active_sessions = [];

try {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id <= 0) {
        throw new Exception("Invalid Student admission identifier.");
    }

    // 1. Fetch Dynamic Courses List
    $course_stmt = $db->query("SELECT id, name FROM courses ORDER BY name ASC");
    $courses_list = $course_stmt->fetchAll();

    // 2. Fetch Dynamic Sessions List
    $sess_stmt = $db->query("SELECT session_name FROM academic_sessions WHERE is_active = 1 ORDER BY id DESC");
    $active_sessions = $sess_stmt->fetchAll();

    // 3. Process POST Edit Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_admission') {
        $student_name = trim($_POST['student_name'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        $father_name = trim($_POST['father_name'] ?? '');
        $mother_name = trim($_POST['mother_name'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $aadhaar_number = trim($_POST['aadhaar_number'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $pincode = trim($_POST['pincode'] ?? '');
        
        $course_id = intval($_POST['course_id'] ?? 0);
        $session_name = trim($_POST['session_name'] ?? '');
        $enrollment_number = trim($_POST['enrollment_number'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $payment_status = trim($_POST['payment_status'] ?? 'pending');
        $status = trim($_POST['status'] ?? 'pending');
        
        $highest_qualification = trim($_POST['highest_qualification'] ?? '');
        $school_name = trim($_POST['school_name'] ?? '');
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

        if (empty($student_name) || empty($dob) || empty($email) || empty($mobile) || empty($course_id)) {
            throw new Exception("Core fields (Name, Date of Birth, Email, Mobile, and Course) are strictly mandatory.");
        }

        $update_sql = "
            UPDATE admissions 
            SET 
                student_name = :student_name, dob = :dob, father_name = :father_name, mother_name = :mother_name, 
                gender = :gender, aadhaar_number = :aadhaar_number, email = :email, mobile = :mobile, 
                address = :address, state = :state, city = :city, district = :district, pincode = :pincode,
                course_id = :course_id, session_name = :session_name, enrollment_number = :enrollment_number, 
                password = :password, payment_status = :payment_status, status = :status,
                highest_qualification = :highest_qualification, school_name = :school_name,
                class_10_roll = :class_10_roll, class_10_board = :class_10_board, class_10_school = :class_10_school,
                class_12_roll = :class_12_roll, class_12_board = :class_12_board, class_12_school = :class_12_school,
                college_name = :college_name, college_roll = :college_roll, college_degree = :college_degree,
                college_passing_year = :college_passing_year, college_marks_type = :college_marks_type,
                college_marks_value = :college_marks_value
            WHERE id = :id
        ";
        
        $stmt_update = $db->prepare($update_sql);
        $stmt_update->execute([
            'student_name' => $student_name,
            'dob' => $dob,
            'father_name' => $father_name,
            'mother_name' => $mother_name,
            'gender' => $gender,
            'aadhaar_number' => $aadhaar_number,
            'email' => $email,
            'mobile' => $mobile,
            'address' => $address,
            'state' => $state,
            'city' => $city,
            'district' => $district,
            'pincode' => $pincode,
            'course_id' => $course_id,
            'session_name' => $session_name,
            'enrollment_number' => $enrollment_number,
            'password' => $password,
            'payment_status' => $payment_status,
            'status' => $status,
            'highest_qualification' => $highest_qualification,
            'school_name' => $school_name,
            'class_10_roll' => $class_10_roll,
            'class_10_board' => $class_10_board,
            'class_10_school' => $class_10_school,
            'class_12_roll' => $class_12_roll,
            'class_12_board' => $class_12_board,
            'class_12_school' => $class_12_school,
            'college_name' => $college_name,
            'college_roll' => $college_roll,
            'college_degree' => $college_degree,
            'college_passing_year' => $college_passing_year,
            'college_marks_type' => $college_marks_type,
            'college_marks_value' => $college_marks_value,
            'id' => $id
        ]);
        
        // If payment status is marked paid or free, automatically compile A4 receipt PDF
        if ($payment_status === 'paid' || $payment_status === 'free') {
            MG_GenerateReceiptPDF($id);
        }

        // Synchronize with student_fees table
        try {
            // Check if fee record already exists for this student
            $feeCheck = $db->prepare("SELECT id FROM `student_fees` WHERE student_id = ? AND student_type = 'course'");
            $feeCheck->execute([$id]);
            $exists = $feeCheck->fetch();

            // Fetch course sales_price to get the exact amount
            $admCheck = $db->prepare("SELECT a.sales_price FROM admissions a WHERE a.id = ?");
            $admCheck->execute([$id]);
            $salesRow = $admCheck->fetch();
            $feeAmount = $salesRow ? floatval($salesRow['sales_price']) : 0.00;

            if ($payment_status === 'free') {
                $feeAmount = 0.00;
            }

            $relativeReceipt = ($payment_status === 'paid' || $payment_status === 'free') ? 'assets/uploads/admissions/receipts/receipt_' . $id . '.pdf' : NULL;

            if ($exists) {
                $feeUpd = $db->prepare("UPDATE `student_fees` SET enrollment_number = ?, amount = ?, payment_status = ?, receipt_path = ? WHERE student_id = ? AND student_type = 'course'");
                $feeUpd->execute([$enrollment_number ?: 'PENDING', $feeAmount, $payment_status, $relativeReceipt, $id]);
            } else {
                $feeIns = $db->prepare("INSERT INTO `student_fees` (student_id, student_type, enrollment_number, amount, payment_status, receipt_path) VALUES (?, 'course', ?, ?, ?, ?)");
                $feeIns->execute([$id, $enrollment_number ?: 'PENDING', $feeAmount, $payment_status, $relativeReceipt]);
            }
        } catch (Exception $feeEx) {
            error_log("Failed to sync course fee during admin edit: " . $feeEx->getMessage());
        }

        $success_message = "Student admission record updated successfully!";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Record Saved',
                    text: 'The candidate profile details have been successfully written to registry.',
                    confirmButtonColor: '#0d47a1'
                }).then(() => {
                    window.location.href = 'view_admission.php?id=" . $id . "';
                });
            });
        </script>";
    }

    // 4. Fetch Candidate Details
    $stmt = $db->prepare("SELECT * FROM admissions WHERE id = ?");
    $stmt->execute([$id]);
    $admission = $stmt->fetch();
    
    if (!$admission) {
        throw new Exception("The requested candidate record could not be found.");
    }
} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<style>
    .edit-card {
        background: #ffffff;
        border: 1px solid #d1d7dc;
        border-radius: 16px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        margin-bottom: 40px;
        overflow: hidden;
    }
    .edit-header {
        background: linear-gradient(135deg, #0d47a1 0%, #1a73e8 100%);
        color: #ffffff;
        padding: 24px 30px;
    }
    .edit-header h3 {
        font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
        font-weight: 800;
        font-size: 20px;
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .edit-body {
        padding: 30px;
    }
    .edit-sect-title {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 13px;
        font-weight: 800;
        color: #0d47a1;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e7f1ff;
        padding-bottom: 6px;
        margin-top: 30px;
        margin-bottom: 20px;
    }
    .edit-sect-title:first-of-type {
        margin-top: 0;
    }
    .form-label-premium {
        font-size: 12px;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        margin-bottom: 6px;
        letter-spacing: 0.25px;
    }
    .form-control-premium {
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 13.5px;
        font-weight: 600;
        color: #1e293b;
        transition: all 0.3s ease;
    }
    .form-control-premium:focus {
        border-color: #0d47a1;
        outline: none;
        box-shadow: 0 0 0 3px rgba(13, 71, 161, 0.1);
    }
    .btn-action-pill {
        border-radius: 50px;
        padding: 12px 30px;
        font-size: 14px;
        font-weight: 700;
        transition: all 0.3s ease;
    }
</style>

<div class="row pt-4">
    <?php if (!empty($error_message) && empty($success_message)): ?>
        <div class="col-12 text-center py-5">
            <div class="alert alert-danger shadow rounded-3 d-inline-block px-5 py-4">
                <i class="fa-solid fa-triangle-exclamation mb-3 d-block" style="font-size: 45px;"></i>
                <h4 class="font-weight-bold">File Access Denied</h4>
                <p class="mb-0"><?= htmlspecialchars($error_message) ?></p>
                <a href="admissions.php" class="btn btn-secondary mt-3 rounded-pill px-4">Back to Registry</a>
            </div>
        </div>
    <?php else: ?>
        <div class="col-12">
            <div class="edit-card">
                <div class="edit-header">
                    <h3><i class="fa-solid fa-user-pen mr-2"></i> Modify Candidate Registry Record</h3>
                </div>
                
                <form action="" method="POST" class="mb-0">
                    <input type="hidden" name="action" value="edit_admission">
                    
                    <div class="edit-body">
                        <!-- SECTION 1: REGISTRATION & PORTAL CREDENTIALS -->
                        <div class="edit-sect-title">Academic Registration & Credentials</div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label-premium">Selected Program / Course</label>
                                <select name="course_id" class="form-control form-control-premium form-select" required>
                                    <option value="">-- Choose Course --</option>
                                    <?php foreach ($courses_list as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= ($admission['course_id'] == $c['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label-premium">Academic Term / Session</label>
                                <select name="session_name" class="form-control form-control-premium form-select">
                                    <option value="">-- Choose Session --</option>
                                    <?php foreach ($active_sessions as $s): ?>
                                        <option value="<?= htmlspecialchars($s['session_name']) ?>" <?= ($admission['session_name'] === $s['session_name']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($s['session_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label-premium">Admission Processing Status</label>
                                <select name="status" class="form-control form-control-premium form-select">
                                    <option value="pending" <?= ($admission['status'] === 'pending') ? 'selected' : '' ?>>Pending</option>
                                    <option value="confirmed" <?= ($admission['status'] === 'confirmed') ? 'selected' : '' ?>>Confirmed</option>
                                    <option value="rejected" <?= ($admission['status'] === 'rejected') ? 'selected' : '' ?>>Rejected</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label-premium">Official Enrollment Number</label>
                                <input type="text" class="form-control form-control-premium" name="enrollment_number" value="<?= htmlspecialchars($admission['enrollment_number'] ?? '') ?>" placeholder="e.g. MGEDU0526001">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label-premium">Student Portal Account Password</label>
                                <input type="text" class="form-control form-control-premium" name="password" value="<?= htmlspecialchars($admission['password'] ?? '') ?>" placeholder="Create student password" required>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label-premium">Fees Payment Status</label>
                                <select name="payment_status" class="form-control form-control-premium form-select">
                                    <option value="pending" <?= ($admission['payment_status'] === 'pending') ? 'selected' : '' ?>>Pending</option>
                                    <option value="paid" <?= ($admission['payment_status'] === 'paid') ? 'selected' : '' ?>>Paid</option>
                                    <option value="free" <?= ($admission['payment_status'] === 'free') ? 'selected' : '' ?>>Free Registration / Scholarship</option>
                                    <option value="failed" <?= ($admission['payment_status'] === 'failed') ? 'selected' : '' ?>>Failed</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- SECTION 2: PERSONAL IDENTITY DETAILS -->
                        <div class="edit-sect-title">Student Profile & Personal Identity</div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label-premium">Candidate Full Name</label>
                                <input type="text" class="form-control form-control-premium" name="student_name" value="<?= htmlspecialchars($admission['student_name']) ?>" required>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label-premium">Date of Birth</label>
                                <input type="date" class="form-control form-control-premium" name="dob" value="<?= htmlspecialchars($admission['dob']) ?>" required>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label-premium">Gender Choice</label>
                                <select name="gender" class="form-control form-control-premium form-select">
                                    <option value="male" <?= ($admission['gender'] === 'male') ? 'selected' : '' ?>>Male</option>
                                    <option value="female" <?= ($admission['gender'] === 'female') ? 'selected' : '' ?>>Female</option>
                                    <option value="other" <?= ($admission['gender'] === 'other') ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label-premium">Father's Name</label>
                                <input type="text" class="form-control form-control-premium" name="father_name" value="<?= htmlspecialchars($admission['father_name']) ?>" required>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label-premium">Mother's Name</label>
                                <input type="text" class="form-control form-control-premium" name="mother_name" value="<?= htmlspecialchars($admission['mother_name'] ?? '') ?>" required>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label-premium">12-Digit Aadhaar Card Number</label>
                                <input type="text" class="form-control form-control-premium" name="aadhaar_number" value="<?= htmlspecialchars($admission['aadhaar_number'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <!-- SECTION 3: GEOGRAPHICAL & CONTACT -->
                        <div class="edit-sect-title">Contact & Permanent Address</div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label-premium">Registered Email Address</label>
                                <input type="email" class="form-control form-control-premium" name="email" value="<?= htmlspecialchars($admission['email']) ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label-premium">Mobile / WhatsApp Number</label>
                                <input type="tel" class="form-control form-control-premium" name="mobile" value="<?= htmlspecialchars($admission['mobile']) ?>" required>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label-premium">Street Address Details</label>
                                <textarea class="form-control form-control-premium" name="address" rows="2" style="resize:none;" required><?= htmlspecialchars($admission['address']) ?></textarea>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label-premium">City / Town</label>
                                <input type="text" class="form-control form-control-premium" name="city" value="<?= htmlspecialchars($admission['city']) ?>" required>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label-premium">District</label>
                                <input type="text" class="form-control form-control-premium" name="district" value="<?= htmlspecialchars($admission['district']) ?>" required>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label-premium">State Province</label>
                                <input type="text" class="form-control form-control-premium" name="state" value="<?= htmlspecialchars($admission['state']) ?>" required>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label-premium">ZIP / Pincode</label>
                                <input type="text" class="form-control form-control-premium" name="pincode" value="<?= htmlspecialchars($admission['pincode']) ?>" required>
                            </div>
                        </div>
                        
                        <!-- SECTION 4: QUALIFICATION DETAILS -->
                        <div class="edit-sect-title">Educational Qualifications & Milestones</div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label-premium">Highest Academic Qualification Stated</label>
                                <select name="highest_qualification" class="form-control form-control-premium form-select" id="highest_qualification" onchange="toggleQualForms()">
                                    <option value="below_10th_5" <?= ($admission['highest_qualification'] === 'below_10th_5') ? 'selected' : '' ?>>Below 10th (Class 5th Stated)</option>
                                    <option value="below_10th_8" <?= ($admission['highest_qualification'] === 'below_10th_8') ? 'selected' : '' ?>>Below 10th (Class 8th Stated)</option>
                                    <option value="class_10th" <?= ($admission['highest_qualification'] === 'class_10th') ? 'selected' : '' ?>>10th Matriculation Standard</option>
                                    <option value="class_12th" <?= ($admission['highest_qualification'] === 'class_12th') ? 'selected' : '' ?>>12th Intermediate Standard</option>
                                    <option value="graduation" <?= ($admission['highest_qualification'] === 'graduation') ? 'selected' : '' ?>>Graduation Degree</option>
                                    <option value="post_graduation" <?= ($admission['highest_qualification'] === 'post_graduation') ? 'selected' : '' ?>>Post Graduation Degree</option>
                                    <option value="phd" <?= ($admission['highest_qualification'] === 'phd') ? 'selected' : '' ?>>PHD / Doctorate</option>
                                </select>
                            </div>
                            
                            <div class="col-md-8 mb-3 school_name_wrapper d-none">
                                <label class="form-label-premium">School Name</label>
                                <input type="text" class="form-control form-control-premium" name="school_name" value="<?= htmlspecialchars($admission['school_name'] ?? '') ?>" placeholder="School name details">
                            </div>
                        </div>
                        
                        <!-- 10th matriculation details -->
                        <div class="row class_10_wrapper d-none">
                            <div class="col-12 mt-2">
                                <div class="alert alert-light border font-weight-bold" style="font-size: 12px; color: #0d47a1; background-color: #f7faff;">Matriculation Class 10th Details</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label-premium">Class 10th Board Name</label>
                                <input type="text" class="form-control form-control-premium" name="class_10_board" value="<?= htmlspecialchars($admission['class_10_board'] ?? '') ?>" placeholder="e.g. CBSE, ICSE, UP BOARD">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label-premium">Class 10th Roll Number</label>
                                <input type="text" class="form-control form-control-premium" name="class_10_roll" value="<?= htmlspecialchars($admission['class_10_roll'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label-premium">Class 10th School Stated</label>
                                <input type="text" class="form-control form-control-premium" name="class_10_school" value="<?= htmlspecialchars($admission['class_10_school'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <!-- 12th Intermediate details -->
                        <div class="row class_12_wrapper d-none">
                            <div class="col-12 mt-2">
                                <div class="alert alert-light border font-weight-bold" style="font-size: 12px; color: #0d47a1; background-color: #f7faff;">Intermediate Class 12th Details</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label-premium">Class 12th Board Name</label>
                                <input type="text" class="form-control form-control-premium" name="class_12_board" value="<?= htmlspecialchars($admission['class_12_board'] ?? '') ?>" placeholder="e.g. CBSE, ICSE, UP BOARD">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label-premium">Class 12th Roll Number</label>
                                <input type="text" class="form-control form-control-premium" name="class_12_roll" value="<?= htmlspecialchars($admission['class_12_roll'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label-premium">Class 12th School Stated</label>
                                <input type="text" class="form-control form-control-premium" name="class_12_school" value="<?= htmlspecialchars($admission['class_12_school'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <!-- College graduation details -->
                        <div class="row college_wrapper d-none">
                            <div class="col-12 mt-2">
                                <div class="alert alert-light border font-weight-bold" style="font-size: 12px; color: #0d47a1; background-color: #f7faff;">Graduation College Details</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label-premium">University / College Name</label>
                                <input type="text" class="form-control form-control-premium" name="college_name" value="<?= htmlspecialchars($admission['college_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label-premium">College Roll Number</label>
                                <input type="text" class="form-control form-control-premium" name="college_roll" value="<?= htmlspecialchars($admission['college_roll'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label-premium">Awarded Degree Name</label>
                                <input type="text" class="form-control form-control-premium" name="college_degree" value="<?= htmlspecialchars($admission['college_degree'] ?? '') ?>" placeholder="e.g. BCA, B.Tech, B.Sc">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label-premium">Year of Passing</label>
                                <input type="text" class="form-control form-control-premium" name="college_passing_year" value="<?= htmlspecialchars($admission['college_passing_year'] ?? '') ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label-premium">Scoring Standards (CGPA / Marks)</label>
                                <select name="college_marks_type" class="form-control form-control-premium form-select">
                                    <option value="cgpa" <?= (($admission['college_marks_type'] ?? '') === 'cgpa') ? 'selected' : '' ?>>CGPA Scale</option>
                                    <option value="percentage" <?= (($admission['college_marks_type'] ?? '') === 'percentage') ? 'selected' : '' ?>>Percentage (%)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label-premium">Total Scored Marks / CGPA Value</label>
                                <input type="text" class="form-control form-control-premium" name="college_marks_value" value="<?= htmlspecialchars($admission['college_marks_value'] ?? '') ?>" placeholder="e.g. 8.4 or 78.5%">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bottom control actions -->
                    <div class="card-footer bg-light border-0 px-4 py-3 d-flex justify-content-between">
                        <a href="view_admission.php?id=<?= $admission['id'] ?>" class="btn btn-secondary btn-action-pill"><i class="fa-solid fa-xmark mr-2"></i> Cancel changes</a>
                        <button type="submit" class="btn btn-primary btn-action-pill" style="background-color: #0d47a1; border-color: #0d47a1;"><i class="fa-solid fa-floppy-disk mr-2"></i> Save Admission Data</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    function toggleQualForms() {
        let highest = document.getElementById('highest_qualification').value;
        
        // Hide all initially
        document.querySelector('.school_name_wrapper').classList.add('d-none');
        document.querySelector('.class_10_wrapper').classList.add('d-none');
        document.querySelector('.class_12_wrapper').classList.add('d-none');
        document.querySelector('.college_wrapper').classList.add('d-none');
        
        if (highest === 'below_10th_5' || highest === 'below_10th_8') {
            document.querySelector('.school_name_wrapper').classList.remove('d-none');
        } else if (highest === 'class_10th') {
            document.querySelector('.class_10_wrapper').classList.remove('d-none');
        } else if (highest === 'class_12th') {
            document.querySelector('.class_10_wrapper').classList.remove('d-none');
            document.querySelector('.class_12_wrapper').classList.remove('d-none');
        } else if (highest === 'graduation' || highest === 'post_graduation' || highest === 'phd') {
            document.querySelector('.class_10_wrapper').classList.remove('d-none');
            document.querySelector('.class_12_wrapper').classList.remove('d-none');
            document.querySelector('.college_wrapper').classList.remove('d-none');
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        toggleQualForms();
    });
</script>

<?php include '../footer.php'; ?>
