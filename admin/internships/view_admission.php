<?php
/**
 * MG Education & Social Development Organization
 * Dedicated Internship Student File Profile Viewer
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
        SELECT a.*, i.name as internship_name 
        FROM internship_admissions a
        LEFT JOIN internships i ON a.internship_id = i.id
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
    .profile-badge-free {
        background-color: #2563eb;
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
        letter-spacing: 0.5px;
        border-bottom: 1px solid #cbd5e1;
        padding-bottom: 4px;
        margin-bottom: 8px;
    }
    .cred-val {
        font-family: 'Courier New', Courier, monospace;
        font-size: 16px;
        font-weight: 800;
    }

    /* Document cards style */
    .doc-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 15px;
    }
    .doc-tile {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 14px;
        text-align: center;
        background-color: #f8fafc;
        transition: all 0.3s ease;
        text-decoration: none;
        display: block;
    }
    .doc-tile:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        border-color: #94a3b8;
    }
    .doc-icon-box {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 18px;
        margin-bottom: 10px;
    }
    .doc-icon-pdf { background-color: #ef4444; }
    .doc-icon-img { background-color: #3b82f6; }
    .doc-icon-receipt { background-color: #059669; }
    .doc-meta-title {
        font-size: 11.5px;
        font-weight: 750;
        color: #1e293b;
        text-transform: uppercase;
        margin-bottom: 4px;
        letter-spacing: 0.3px;
    }
    .doc-meta-sub {
        font-size: 10.5px;
        color: #64748b;
    }
</style>

<div class="row pt-4">
    <?php if (!empty($error_message)): ?>
        <div class="col-12 text-center py-5">
            <div class="alert alert-danger shadow rounded-3 d-inline-block px-5 py-4">
                <i class="fa-solid fa-triangle-exclamation mb-3 d-block" style="font-size: 45px;"></i>
                <h4 class="font-weight-bold">Profile Fetch Failed</h4>
                <p class="mb-0"><?= htmlspecialchars($error_message) ?></p>
                <a href="admissions.php" class="btn btn-secondary mt-3 rounded-pill px-4">Back to Admissions</a>
            </div>
        </div>
    <?php else: ?>
        
        <!-- Profile Banner / Hero Box -->
        <div class="col-12">
            <div class="profile-hero-card d-flex align-items-center flex-wrap gap-4 justify-content-between">
                <div class="d-flex align-items-center flex-wrap gap-4">
                    <div class="profile-avatar-frame">
                        <?php if (!empty($admission['student_photo'])): ?>
                            <img src="<?= $project_base . $admission['student_photo'] ?>" alt="Student Photo">
                        <?php else: ?>
                            <img src="https://via.placeholder.com/150?text=Photo" alt="Placeholder">
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="profile-header-title"><?= htmlspecialchars($admission['student_name']) ?></div>
                        <div class="profile-header-subtitle mb-2">
                            <i class="fa-solid fa-laptop-code mr-1"></i> <?= htmlspecialchars($admission['internship_name'] ?: 'Professional Internship Program') ?>
                        </div>
                        <div>
                            <?php if ($admission['payment_status'] === 'paid'): ?>
                                <span class="profile-badge-paid"><i class="fa-solid fa-circle-check mr-1"></i> Paid Verified</span>
                            <?php elseif ($admission['payment_status'] === 'free'): ?>
                                <span class="profile-badge-free"><i class="fa-solid fa-graduation-cap mr-1"></i> Scholarship / Free</span>
                            <?php elseif ($admission['payment_status'] === 'failed'): ?>
                                <span class="profile-badge-failed"><i class="fa-solid fa-circle-xmark mr-1"></i> Payment Failed</span>
                            <?php else: ?>
                                <span class="profile-badge-pending"><i class="fa-solid fa-clock mr-1"></i> Verification Pending</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div>
                    <a href="edit_admission.php?id=<?= $admission['id'] ?>" class="btn btn-light text-primary font-weight-bold rounded-pill px-4 py-2 mr-2 shadow-sm"><i class="fa-solid fa-user-pen mr-1"></i> Edit Profile</a>
                    <a href="admissions.php" class="btn btn-outline-light rounded-pill px-4 py-2 shadow-sm"><i class="fa-solid fa-circle-chevron-left mr-1"></i> Back to List</a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Account Credentials details -->
            <div class="profile-data-card">
                <div class="profile-sect-title"><i class="fa-solid fa-user-shield mr-1"></i> Portal Account Credentials</div>
                
                <div class="cred-box">
                    <div class="cred-title">Enrollment Number</div>
                    <div class="cred-val text-primary"><?= htmlspecialchars($admission['enrollment_number'] ?: 'Unassigned') ?></div>
                </div>
                
                <div class="cred-box">
                    <div class="cred-title">Portal Account Password</div>
                    <div class="cred-val text-danger" style="letter-spacing:1px;"><?= htmlspecialchars($admission['password'] ?: 'Unassigned') ?></div>
                </div>

                <div style="font-size:12px; color:#64748b; line-height:1.5; padding: 5px;">
                    <i class="fa-solid fa-circle-info mr-1 text-primary"></i> These details allow the student to access class schedules and project directories in their portal.
                </div>
            </div>

            <!-- Profile Summary Metadata -->
            <div class="profile-data-card">
                <div class="profile-sect-title"><i class="fa-solid fa-server mr-1"></i> Admission Metadata</div>
                
                <div class="profile-label-txt">Admission ID</div>
                <div class="profile-val-txt">#MGINT-<?= sprintf("%05d", $admission['id']) ?></div>

                <div class="profile-label-txt">Enrollment Registry Date</div>
                <div class="profile-val-txt"><?= date('d M Y - H:i A', strtotime($admission['created_at'])) ?></div>

                <div class="profile-label-txt">Razorpay Order ID</div>
                <div class="profile-val-txt"><code class="text-secondary"><?= htmlspecialchars($admission['razorpay_order_id'] ?: 'N/A') ?></code></div>

                <div class="profile-label-txt">Razorpay Payment ID</div>
                <div class="profile-val-txt"><code class="text-secondary"><?= htmlspecialchars($admission['razorpay_payment_id'] ?: 'N/A') ?></code></div>
            </div>
        </div>

        <div class="col-md-8">
            <!-- Candidate Biographical Details -->
            <div class="profile-data-card">
                <div class="profile-sect-title"><i class="fa-solid fa-address-card mr-1"></i> Candidate Biographical details</div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="profile-label-txt">Applicant Name</div>
                        <div class="profile-val-txt"><?= htmlspecialchars($admission['student_name']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="profile-label-txt">Date of Birth</div>
                        <div class="profile-val-txt"><?= date('d-M-Y', strtotime($admission['dob'])) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="profile-label-txt">Mobile / WhatsApp Number</div>
                        <div class="profile-val-txt"><?= htmlspecialchars($admission['mobile']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="profile-label-txt">Email Address</div>
                        <div class="profile-val-txt"><?= htmlspecialchars($admission['email']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="profile-label-txt">Father's Name</div>
                        <div class="profile-val-txt"><?= htmlspecialchars($admission['father_name']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="profile-label-txt">Mother's Name</div>
                        <div class="profile-val-txt"><?= htmlspecialchars($admission['mother_name']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="profile-label-txt">Gender</div>
                        <div class="profile-val-txt" style="text-transform: capitalize;"><?= htmlspecialchars($admission['gender']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="profile-label-txt">12-Digit Aadhaar Card Number</div>
                        <div class="profile-val-txt"><code class="text-secondary font-weight-bold" style="font-size:14px;"><?= htmlspecialchars($admission['aadhaar_number'] ?: 'N/A') ?></code></div>
                    </div>
                </div>
            </div>

            <!-- Candidate Permanent Address -->
            <div class="profile-data-card">
                <div class="profile-sect-title"><i class="fa-solid fa-house-chimney mr-1"></i> Permanent Address Details</div>
                
                <div class="row">
                    <div class="col-12">
                        <div class="profile-label-txt">Street Address Details</div>
                        <div class="profile-val-txt" style="line-height:1.5;"><?= htmlspecialchars($admission['address']) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="profile-label-txt">City / Town</div>
                        <div class="profile-val-txt"><?= htmlspecialchars($admission['city']) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="profile-label-txt">District</div>
                        <div class="profile-val-txt"><?= htmlspecialchars($admission['district']) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="profile-label-txt">State Province</div>
                        <div class="profile-val-txt"><?= htmlspecialchars($admission['state']) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="profile-label-txt font-weight-bold">ZIP / Pincode</div>
                        <div class="profile-val-txt font-weight-bold" style="letter-spacing: 0.5px;"><?= htmlspecialchars($admission['pincode']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Qualification Milestones details -->
            <div class="profile-data-card">
                <div class="profile-sect-title"><i class="fa-solid fa-graduation-cap mr-1"></i> Educational Qualifications & Academic Milestones</div>
                
                <div class="row">
                    <div class="col-12">
                        <div class="profile-label-txt">Stated Highest Academic Qualification</div>
                        <div class="profile-val-txt" style="color: #0d47a1; font-weight:700; text-transform: uppercase;">
                            <?= str_replace('_', ' ', htmlspecialchars($admission['highest_qualification'])) ?>
                        </div>
                    </div>
                </div>

                <?php if ($admission['highest_qualification'] === 'below_10th_5' || $admission['highest_qualification'] === 'below_10th_8'): ?>
                    <div class="row border-top pt-3">
                        <div class="col-12">
                            <div class="profile-label-txt">School Name</div>
                            <div class="profile-val-txt"><?= htmlspecialchars($admission['school_name'] ?: 'N/A') ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (in_array($admission['highest_qualification'], ['class_10th', 'class_12th', 'graduation', 'post_graduation', 'phd'])): ?>
                    <!-- Class 10 Details -->
                    <div class="row border-top pt-3">
                        <div class="col-12 mb-2"><strong style="font-size:12.5px; color:#475569;">Matriculation Class 10th Details</strong></div>
                        <div class="col-md-4">
                            <div class="profile-label-txt">Class 10 Roll No</div>
                            <div class="profile-val-txt"><?= htmlspecialchars($admission['class_10_roll'] ?: 'N/A') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="profile-label-txt">Class 10 Board</div>
                            <div class="profile-val-txt"><?= htmlspecialchars($admission['class_10_board'] ?: 'N/A') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="profile-label-txt">Class 10 School</div>
                            <div class="profile-val-txt"><?= htmlspecialchars($admission['class_10_school'] ?: 'N/A') ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (in_array($admission['highest_qualification'], ['class_12th', 'graduation', 'post_graduation', 'phd'])): ?>
                    <!-- Class 12 Details -->
                    <div class="row border-top pt-3">
                        <div class="col-12 mb-2"><strong style="font-size:12.5px; color:#475569;">Intermediate Class 12th Details</strong></div>
                        <div class="col-md-4">
                            <div class="profile-label-txt">Class 12 Roll No</div>
                            <div class="profile-val-txt"><?= htmlspecialchars($admission['class_12_roll'] ?: 'N/A') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="profile-label-txt">Class 12 Board</div>
                            <div class="profile-val-txt"><?= htmlspecialchars($admission['class_12_board'] ?: 'N/A') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="profile-label-txt">Class 12 School</div>
                            <div class="profile-val-txt"><?= htmlspecialchars($admission['class_12_school'] ?: 'N/A') ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (in_array($admission['highest_qualification'], ['graduation', 'post_graduation', 'phd'])): ?>
                    <!-- College Details -->
                    <div class="row border-top pt-3">
                        <div class="col-12 mb-2"><strong style="font-size:12.5px; color:#475569;">Higher Graduation / College details</strong></div>
                        <div class="col-md-4">
                            <div class="profile-label-txt">University / College Name</div>
                            <div class="profile-val-txt"><?= htmlspecialchars($admission['college_name'] ?: 'N/A') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="profile-label-txt">College Roll Number</div>
                            <div class="profile-val-txt"><?= htmlspecialchars($admission['college_roll'] ?: 'N/A') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="profile-label-txt">Awarded Degree</div>
                            <div class="profile-val-txt"><?= htmlspecialchars($admission['college_degree'] ?: 'N/A') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="profile-label-txt">Year of Passing</div>
                            <div class="profile-val-txt"><?= htmlspecialchars($admission['college_passing_year'] ?: 'N/A') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="profile-label-txt">Marks Standard</div>
                            <div class="profile-val-txt" style="text-transform: uppercase;"><?= htmlspecialchars($admission['college_marks_type'] ?: 'N/A') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="profile-label-txt">Scored Marks / Value</div>
                            <div class="profile-val-txt"><?= htmlspecialchars($admission['college_marks_value'] ?: 'N/A') ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Scanned sheets and receipts -->
            <div class="profile-data-card">
                <div class="profile-sect-title"><i class="fa-solid fa-file-pdf mr-1"></i> Scanned Attachments & Dynamic Receipts</div>
                
                <div class="doc-grid">
                    <!-- Aadhaar scan -->
                    <?php if (!empty($admission['aadhaar_card'])): ?>
                        <a href="<?= $project_base . $admission['aadhaar_card'] ?>" target="_blank" class="doc-tile">
                            <div class="doc-icon-box doc-icon-pdf"><i class="fa-solid fa-file-pdf"></i></div>
                            <div class="doc-meta-title">Aadhaar Card</div>
                            <div class="doc-meta-sub">Scanned PDF</div>
                        </a>
                    <?php endif; ?>

                    <!-- Class 10 marksheet -->
                    <?php if (!empty($admission['class_10_marksheet'])): ?>
                        <a href="<?= $project_base . $admission['class_10_marksheet'] ?>" target="_blank" class="doc-tile">
                            <div class="doc-icon-box doc-icon-pdf"><i class="fa-solid fa-file-pdf"></i></div>
                            <div class="doc-meta-title">Class 10th Sheet</div>
                            <div class="doc-meta-sub">Scanned PDF</div>
                        </a>
                    <?php endif; ?>

                    <!-- Class 12 marksheet -->
                    <?php if (!empty($admission['class_12_marksheet'])): ?>
                        <a href="<?= $project_base . $admission['class_12_marksheet'] ?>" target="_blank" class="doc-tile">
                            <div class="doc-icon-box doc-icon-pdf"><i class="fa-solid fa-file-pdf"></i></div>
                            <div class="doc-meta-title">Class 12th Sheet</div>
                            <div class="doc-meta-sub">Scanned PDF</div>
                        </a>
                    <?php endif; ?>

                    <!-- College sheet -->
                    <?php if (!empty($admission['college_marksheet'])): ?>
                        <a href="<?= $project_base . $admission['college_marksheet'] ?>" target="_blank" class="doc-tile">
                            <div class="doc-icon-box doc-icon-pdf"><i class="fa-solid fa-file-pdf"></i></div>
                            <div class="doc-meta-title">College Sheet</div>
                            <div class="doc-meta-sub">Scanned PDF</div>
                        </a>
                    <?php endif; ?>

                    <!-- Student Portrait photo -->
                    <?php if (!empty($admission['student_photo'])): ?>
                        <a href="<?= $project_base . $admission['student_photo'] ?>" target="_blank" class="doc-tile">
                            <div class="doc-icon-box doc-icon-img"><i class="fa-solid fa-file-image"></i></div>
                            <div class="doc-meta-title">Passport Photo</div>
                            <div class="doc-meta-sub">Image File</div>
                        </a>
                    <?php endif; ?>

                    <!-- Signature Scan -->
                    <?php if (!empty($admission['student_signature'])): ?>
                        <a href="<?= $project_base . $admission['student_signature'] ?>" target="_blank" class="doc-tile">
                            <div class="doc-icon-box doc-icon-img"><i class="fa-solid fa-file-signature"></i></div>
                            <div class="doc-meta-title">Signature Scan</div>
                            <div class="doc-meta-sub">Image File</div>
                        </a>
                    <?php endif; ?>

                    <!-- Dynamic Fee Receipt -->
                    <?php if ($admission['payment_status'] === 'paid' || $admission['payment_status'] === 'free'): ?>
                        <a href="<?= $project_base ?>assets/uploads/internships/receipts/receipt_<?= $admission['id'] ?>.pdf" target="_blank" class="doc-tile" style="border-color: #059669; background-color: #f0fdf4;">
                            <div class="doc-icon-box doc-icon-receipt"><i class="fa-solid fa-file-invoice"></i></div>
                            <div class="doc-meta-title" style="color: #059669;">Official Fee Receipt</div>
                            <div class="doc-meta-sub" style="color: #047857;">A4 Portrait PDF</div>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<?php include '../footer.php'; ?>
