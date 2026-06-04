<?php
/**
 * MG Education & Social Development Organization
 * Dedicated Student Admission File Profile Viewer
 */

include '../header.php';

$db = MG_GetDBConnection();
$error_message = '';
$admission = null;

try {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id <= 0) {
        throw new Exception("Invalid Student admission identifier.");
    }
    
    $stmt = $db->prepare("
        SELECT a.*, c.name as course_name 
        FROM admissions a
        LEFT JOIN courses c ON a.course_id = c.id
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $admission = $stmt->fetch();
    
    if (!$admission) {
        throw new Exception("The requested candidate record could not be found in active databases.");
    }
} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<style>
    .profile-hero-card {
        background: linear-gradient(135deg, #0d47a1 0%, #1a73e8 100%);
        border: none;
        border-radius: 16px;
        color: #ffffff;
        padding: 30px;
        margin-bottom: 30px;
        box-shadow: 0 4px 20px rgba(13, 71, 161, 0.15);
    }
    .profile-avatar-frame {
        width: 130px;
        height: 130px;
        border-radius: 50%;
        border: 4px solid rgba(255, 255, 255, 0.85);
        overflow: hidden;
        background-color: #f1f5f9;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        display: inline-block;
    }
    .profile-avatar-frame img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .profile-header-title {
        font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
        font-weight: 800;
        font-size: 26px;
        margin-bottom: 6px;
        letter-spacing: -0.5px;
    }
    .profile-header-subtitle {
        font-size: 15px;
        opacity: 0.9;
        font-weight: 600;
    }
    .profile-badge-paid {
        background-color: #28a745;
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: #ffffff;
        border-radius: 50px;
        padding: 4px 16px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        display: inline-block;
    }
    .profile-badge-pending {
        background-color: #fd7e14;
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: #ffffff;
        border-radius: 50px;
        padding: 4px 16px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        display: inline-block;
    }
    .profile-badge-failed {
        background-color: #dc3545;
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: #ffffff;
        border-radius: 50px;
        padding: 4px 16px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        display: inline-block;
    }
    .profile-data-card {
        background: #ffffff;
        border: 1px solid #d1d7dc;
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 30px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.02);
    }
    .profile-sect-title {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 14px;
        font-weight: 800;
        color: #0d47a1;
        text-transform: uppercase;
        letter-spacing: 0.75px;
        border-bottom: 2px solid #e7f1ff;
        padding-bottom: 8px;
        margin-bottom: 20px;
    }
    .profile-label-txt {
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        margin-bottom: 3px;
        letter-spacing: 0.25px;
    }
    .profile-val-txt {
        font-size: 14px;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 16px;
    }
    .cred-box {
        background-color: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 18px;
        margin-bottom: 20px;
    }
    .cred-title {
        font-size: 11px;
        font-weight: 800;
        color: #475569;
        text-transform: uppercase;
        margin-bottom: 12px;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 6px;
    }
    .cred-val {
        font-family: monospace;
        font-size: 16px;
        font-weight: bold;
        letter-spacing: 0.5px;
    }
    .qualification-tile {
        background-color: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 16px;
        height: 100%;
        transition: all 0.3s ease;
    }
    .qualification-tile:hover {
        border-color: #0d47a1;
        box-shadow: 0 4px 12px rgba(13, 71, 161, 0.05);
    }
    .qualification-title {
        font-size: 12px;
        font-weight: 800;
        color: #0d47a1;
        text-transform: uppercase;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 6px;
        margin-bottom: 10px;
    }
    .doc-tile {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 18px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        background-color: #ffffff;
        text-decoration: none !important;
        transition: all 0.3s ease;
        margin-bottom: 12px;
    }
    .doc-tile:hover {
        border-color: #0d47a1;
        background-color: #f7faff;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(13, 71, 161, 0.06);
    }
    .doc-icon-box {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }
    .doc-icon-aadhaar {
        background-color: rgba(26, 115, 232, 0.1);
        color: #1a73e8;
    }
    .doc-icon-sheet {
        background-color: rgba(40, 167, 69, 0.1);
        color: #28a745;
    }
    .doc-icon-receipt {
        background-color: rgba(220, 53, 69, 0.1);
        color: #dc3545;
    }
    .doc-meta-title {
        font-size: 13px;
        font-weight: 700;
        color: #1e293b;
    }
    .doc-meta-subtitle {
        font-size: 11px;
        color: #64748b;
        text-transform: uppercase;
        font-weight: 600;
    }
    .back-btn-pill {
        border-radius: 50px;
        padding: 10px 24px;
        font-size: 14px;
        font-weight: 700;
        transition: all 0.3s ease;
    }
</style>

<div class="row pt-4">
    <?php if (!empty($error_message)): ?>
        <div class="col-12 text-center py-5">
            <div class="alert alert-danger shadow rounded-3 d-inline-block px-5 py-4">
                <i class="fa-solid fa-triangle-exclamation mb-3 d-block" style="font-size: 45px;"></i>
                <h4 class="font-weight-bold">Profile Load Failure</h4>
                <p class="mb-0"><?= htmlspecialchars($error_message) ?></p>
                <a href="admissions.php" class="btn btn-secondary mt-3 rounded-pill px-4">Back to Admissions Registry</a>
            </div>
        </div>
    <?php else: ?>
        <div class="col-12">
            <!-- Hero Header Card -->
            <div class="profile-hero-card">
                <div class="row align-items-center">
                    <div class="col-md-auto text-center text-md-left mb-3 mb-md-0">
                        <div class="profile-avatar-frame">
                            <?php if (!empty($admission['student_photo'])): ?>
                                <img src="<?= $project_base . htmlspecialchars($admission['student_photo']) ?>" alt="Student Photo">
                            <?php else: ?>
                                <img src="<?= $project_base ?>assets/uploads/admissions/photos/default-placeholder.png" alt="Student Photo">
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md text-center text-md-left">
                        <h2 class="profile-header-title"><?= htmlspecialchars($admission['student_name']) ?></h2>
                        <p class="profile-header-subtitle mb-2">
                            <i class="fa-solid fa-graduation-cap mr-1"></i> Applied Course: <strong><?= htmlspecialchars($admission['course_name'] ?: 'N/A') ?></strong>
                        </p>
                        <p class="profile-header-subtitle mb-3">
                            <i class="fa-solid fa-calendar-days mr-1"></i> Registration Session: <strong><?= htmlspecialchars($admission['session_name'] ?: 'N/A') ?></strong>
                        </p>
                        <div>
                            <?php if ($admission['payment_status'] === 'paid'): ?>
                                <span class="profile-badge-paid"><i class="fa-solid fa-circle-check mr-1"></i> Fees Paid</span>
                            <?php elseif ($admission['payment_status'] === 'free'): ?>
                                <span class="profile-badge-paid" style="background-color: #2563eb;"><i class="fa-solid fa-gift mr-1"></i> Scholarship/Free</span>
                            <?php else: ?>
                                <span class="profile-badge-pending"><i class="fa-solid fa-circle-notch fa-spin mr-1"></i> <?= htmlspecialchars(strtoupper($admission['payment_status'])) ?></span>
                            <?php endif; ?>
                            
                            <span class="badge ml-2 px-3 py-2 rounded-pill font-weight-bold" style="background-color: rgba(255,255,255,0.25); color: #fff; font-size:12px; border: 1px solid rgba(255,255,255,0.2);">
                                STATUS: <?= strtoupper($admission['status']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-auto text-center text-md-right mt-3 mt-md-0">
                        <a href="edit_admission.php?id=<?= $admission['id'] ?>" class="btn btn-light text-primary font-weight-bold rounded-pill px-4 py-2 mr-2 shadow-sm"><i class="fa-solid fa-user-pen mr-1"></i> Edit Profile</a>
                        <a href="admissions.php" class="btn btn-dark text-white font-weight-bold rounded-pill px-4 py-2 shadow-sm"><i class="fa-solid fa-list mr-1"></i> Registry</a>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Left Column: Personal, Location & Credentials -->
                <div class="col-lg-6">
                    <div class="profile-data-card">
                        <div class="profile-sect-title"><i class="fa-solid fa-user mr-1"></i> Personal Profile & Identity</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="profile-label-txt">Candidate's Full Name</div>
                                <div class="profile-val-txt"><?= htmlspecialchars($admission['student_name']) ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="profile-label-txt">Date of Birth</div>
                                <div class="profile-val-txt"><?= date('d F Y', strtotime($admission['dob'])) ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="profile-label-txt">Father's Name</div>
                                <div class="profile-val-txt"><?= htmlspecialchars($admission['father_name']) ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="profile-label-txt">Mother's Name</div>
                                <div class="profile-val-txt"><?= htmlspecialchars($admission['mother_name'] ?: 'N/A') ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="profile-label-txt">Gender</div>
                                <div class="profile-val-txt" style="text-transform: capitalize;"><?= htmlspecialchars($admission['gender']) ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="profile-label-txt">Aadhaar Card Number</div>
                                <div class="profile-val-txt"><?= htmlspecialchars($admission['aadhaar_number'] ?: 'N/A') ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="profile-data-card">
                        <div class="profile-sect-title"><i class="fa-solid fa-address-book mr-1"></i> Contact & Communication</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="profile-label-txt">Email Address</div>
                                <div class="profile-val-txt"><a href="mailto:<?= htmlspecialchars($admission['email']) ?>" class="text-decoration-none font-weight-bold text-primary"><?= htmlspecialchars($admission['email']) ?></a></div>
                            </div>
                            <div class="col-md-6">
                                <div class="profile-label-txt">Mobile / WhatsApp No</div>
                                <div class="profile-val-txt"><a href="tel:<?= htmlspecialchars($admission['mobile']) ?>" class="text-decoration-none font-weight-bold text-primary"><?= htmlspecialchars($admission['mobile']) ?></a></div>
                            </div>
                            <div class="col-12">
                                <div class="profile-label-txt">Permanent Full Address</div>
                                <div class="profile-val-txt" style="line-height: 1.5; font-size:14px; font-weight:600;">
                                    <?= htmlspecialchars($admission['address']) ?><br>
                                    City: <strong><?= htmlspecialchars($admission['city']) ?></strong> | 
                                    District: <strong><?= htmlspecialchars($admission['district']) ?></strong><br>
                                    State: <strong><?= htmlspecialchars($admission['state']) ?></strong> - <strong><?= htmlspecialchars($admission['pincode']) ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Academic history, Credentials & Verification files -->
                <div class="col-lg-6">
                    <div class="profile-data-card">
                        <div class="profile-sect-title"><i class="fa-solid fa-key mr-1"></i> Portal Access Credentials</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="cred-box">
                                    <div class="cred-title">Portal Enrollment Number</div>
                                    <div class="cred-val text-primary"><?= htmlspecialchars($admission['enrollment_number'] ?: 'Unassigned') ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="cred-box">
                                    <div class="cred-title">Account Portal Password</div>
                                    <div class="cred-val text-danger"><?= htmlspecialchars($admission['password'] ?: 'Unassigned') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="profile-data-card">
                        <div class="profile-sect-title"><i class="fa-solid fa-user-graduate mr-1"></i> Qualifications & Education History</div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="qualification-tile">
                                    <div class="qualification-title">Matriculation (10th)</div>
                                    <div style="font-size:12px; line-height: 1.6; color:#475569;">
                                        Board: <strong><?= htmlspecialchars($admission['class_10_board'] ?: 'N/A') ?></strong><br>
                                        Roll No: <strong><?= htmlspecialchars($admission['class_10_roll'] ?: 'N/A') ?></strong><br>
                                        School: <strong><?= htmlspecialchars($admission['class_10_school'] ?: 'N/A') ?></strong>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="qualification-tile">
                                    <div class="qualification-title">Intermediate (12th)</div>
                                    <div style="font-size:12px; line-height: 1.6; color:#475569;">
                                        Board: <strong><?= htmlspecialchars($admission['class_12_board'] ?: 'N/A') ?></strong><br>
                                        Roll No: <strong><?= htmlspecialchars($admission['class_12_roll'] ?: 'N/A') ?></strong><br>
                                        School: <strong><?= htmlspecialchars($admission['class_12_school'] ?: 'N/A') ?></strong>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($admission['highest_qualification'] === 'graduation' || $admission['highest_qualification'] === 'post_graduation' || $admission['highest_qualification'] === 'phd'): ?>
                                <div class="col-12 mt-2">
                                    <div class="qualification-tile">
                                        <div class="qualification-title">Graduation College Details</div>
                                        <div class="row" style="font-size:12.5px; line-height: 1.6; color:#475569;">
                                            <div class="col-md-6">
                                                College: <strong><?= htmlspecialchars($admission['college_name'] ?: 'N/A') ?></strong><br>
                                                Roll No: <strong><?= htmlspecialchars($admission['college_roll'] ?: 'N/A') ?></strong><br>
                                                Degree: <strong><?= htmlspecialchars($admission['college_degree'] ?: 'N/A') ?></strong>
                                            </div>
                                            <div class="col-md-6">
                                                Passing Year: <strong><?= htmlspecialchars($admission['college_passing_year'] ?: 'N/A') ?></strong><br>
                                                Scored Marks: <strong><?= htmlspecialchars($admission['college_marks_value'] ?: 'N/A') ?> (<?= strtoupper($admission['college_marks_type'] ?: 'N/A') ?>)</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif ($admission['highest_qualification'] === 'below_10th_5' || $admission['highest_qualification'] === 'below_10th_8'): ?>
                                <div class="col-12 mt-2">
                                    <div class="qualification-tile">
                                        <div class="qualification-title">School Level Detail</div>
                                        <div style="font-size:12.5px; color:#475569;">
                                            Highest School Stated: <strong><?= htmlspecialchars($admission['school_name'] ?: 'N/A') ?></strong>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="profile-data-card">
                        <div class="profile-sect-title"><i class="fa-solid fa-file-pdf mr-1"></i> Scanned Attachments & Dynamic Receipts</div>
                        
                        <!-- Aadhaar Card PDF -->
                        <?php if (!empty($admission['aadhaar_card'])): ?>
                            <a href="<?= $project_base . htmlspecialchars($admission['aadhaar_card']) ?>" target="_blank" class="doc-tile">
                                <div class="doc-icon-box doc-icon-aadhaar">
                                    <i class="fa-solid fa-id-card"></i>
                                </div>
                                <div style="flex-grow: 1;">
                                    <div class="doc-meta-title">Aadhaar Card Scan Copy</div>
                                    <div class="doc-meta-subtitle">Candidate Identity PDF</div>
                                </div>
                                <i class="fa-solid fa-download text-muted"></i>
                            </a>
                        <?php endif; ?>
                        
                        <!-- Class 10 Marksheet -->
                        <?php if (!empty($admission['class_10_marksheet'])): ?>
                            <a href="<?= $project_base . htmlspecialchars($admission['class_10_marksheet']) ?>" target="_blank" class="doc-tile">
                                <div class="doc-icon-box doc-icon-sheet">
                                    <i class="fa-solid fa-file-shield"></i>
                                </div>
                                <div style="flex-grow: 1;">
                                    <div class="doc-meta-title">Matriculation (10th) Marksheet</div>
                                    <div class="doc-meta-subtitle">Verified Board Certificate</div>
                                </div>
                                <i class="fa-solid fa-download text-muted"></i>
                            </a>
                        <?php endif; ?>
                        
                        <!-- Class 12 Marksheet -->
                        <?php if (!empty($admission['class_12_marksheet'])): ?>
                            <a href="<?= $project_base . htmlspecialchars($admission['class_12_marksheet']) ?>" target="_blank" class="doc-tile">
                                <div class="doc-icon-box doc-icon-sheet">
                                    <i class="fa-solid fa-file-shield"></i>
                                </div>
                                <div style="flex-grow: 1;">
                                    <div class="doc-meta-title">Intermediate (12th) Marksheet</div>
                                    <div class="doc-meta-subtitle">Verified Board Certificate</div>
                                </div>
                                <i class="fa-solid fa-download text-muted"></i>
                            </a>
                        <?php endif; ?>
                        
                        <!-- College Degree Marksheet -->
                        <?php if (!empty($admission['college_marksheet'])): ?>
                            <a href="<?= $project_base . htmlspecialchars($admission['college_marksheet']) ?>" target="_blank" class="doc-tile">
                                <div class="doc-icon-box doc-icon-sheet" style="background-color: rgba(23, 162, 184, 0.1); color: #17a2b8;">
                                    <i class="fa-solid fa-graduation-cap"></i>
                                </div>
                                <div style="flex-grow: 1;">
                                    <div class="doc-meta-title">College Degree Marksheet</div>
                                    <div class="doc-meta-subtitle">University Certificate</div>
                                </div>
                                <i class="fa-solid fa-download text-muted"></i>
                            </a>
                        <?php endif; ?>
                        
                        <!-- Dynamic Fee Receipt -->
                        <?php if ($admission['payment_status'] === 'paid' || $admission['payment_status'] === 'free'): ?>
                            <a href="<?= $project_base ?>assets/uploads/admissions/receipts/receipt_<?= $admission['id'] ?>.pdf" target="_blank" class="doc-tile" style="border-color: #dc3545; background-color: #fffbfa;">
                                <div class="doc-icon-box doc-icon-receipt">
                                    <i class="fa-solid fa-file-pdf"></i>
                                </div>
                                <div style="flex-grow: 1;">
                                    <div class="doc-meta-title" style="color: #c82333;">Official Fee Receipt (A4 PDF)</div>
                                    <div class="doc-meta-subtitle" style="color: #e06d78;">Generated Dynamic Invoice</div>
                                </div>
                                <i class="fa-solid fa-file-arrow-down text-danger"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Bottom Navigation controls -->
            <div class="row mb-5">
                <div class="col-12 text-center">
                    <a href="admissions.php" class="btn btn-secondary back-btn-pill shadow-sm"><i class="fa-solid fa-arrow-left-long mr-2"></i> Return to Registry Console</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../footer.php'; ?>
