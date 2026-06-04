<?php
/**
 * MG Education & Social Development Organization
 * Enrolled Internship Details Portal
 */

include './header.php';

$db = MG_GetDBConnection();
$student_id = $_SESSION['student_id'];

try {
    // Fetch student's internship details with enrollment and admission details
    $stmt = $db->prepare("
        SELECT a.*, 
               i.name as internship_name, 
               i.duration, 
               i.duration_unit, 
               i.description as internship_description, 
               i.internship_image, 
               i.mode as internship_mode, 
               i.mrp, 
               i.sales_price,
               i.brochure_enabled,
               i.brochure_pdf
        FROM internship_admissions a
        LEFT JOIN internships i ON a.internship_id = i.id
        WHERE a.id = ? AND a.status = 'confirmed'
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();

    if (!$student) {
        echo "<div class='alert alert-danger shadow-sm rounded-lg p-4'><i class='fas fa-exclamation-circle mr-2'></i>Unable to load internship enrollment information. Please log in again.</div>";
        include './footer.php';
        exit;
    }
} catch (Exception $e) {
    echo "<div class='alert alert-danger shadow-sm rounded-lg p-4'><i class='fas fa-exclamation-triangle mr-2'></i>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    include './footer.php';
    exit;
}
?>

<style>
    .internship-details-container {
        font-family: 'Source Sans Pro', 'Inter', sans-serif;
    }
    
    .internship-hero-banner {
        background: linear-gradient(135deg, #064e3b 0%, #022c22 100%);
        color: #ffffff;
        border-radius: 16px;
        padding: 30px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(255,255,255,0.08);
    }

    .internship-hero-banner::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .internship-cover-box {
        width: 100%;
        max-width: 260px;
        height: 170px;
        border-radius: 12px;
        overflow: hidden;
        border: 3px solid rgba(255, 255, 255, 0.15);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        background-color: #064e3b;
    }

    .internship-cover-box img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .internship-badge-premium {
        font-size: 12px;
        font-weight: 700;
        color: #34d399;
        background-color: rgba(52, 211, 153, 0.12);
        border: 1.5px solid rgba(52, 211, 153, 0.3);
        padding: 5px 14px;
        border-radius: 50px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .details-card-premium {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.02);
        padding: 25px;
        margin-bottom: 30px;
        height: 100%;
        transition: all 0.3s ease;
    }

    .details-card-premium:hover {
        box-shadow: 0 12px 28px rgba(0,0,0,0.04);
        transform: translateY(-2px);
    }

    .section-title-premium {
        font-size: 18px;
        font-weight: 700;
        color: #1e293b;
        border-bottom: 2px solid #f1f5f9;
        padding-bottom: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-title-premium i {
        color: #059669;
    }

    .info-grid-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px 15px;
    }

    @media (max-width: 576px) {
        .info-grid-row {
            grid-template-columns: 1fr;
        }
    }

    .info-item-label {
        font-size: 11.5px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }

    .info-item-value {
        font-size: 14.5px;
        font-weight: 600;
        color: #1e293b;
    }

    .syllabus-text-box {
        font-size: 14.5px;
        color: #334155;
        line-height: 1.7;
        background-color: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: 10px;
        padding: 20px;
        max-height: 380px;
        overflow-y: auto;
    }

    .btn-brochure-download {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        background-color: #059669;
        color: #ffffff !important;
        font-weight: 600;
        font-size: 14px;
        padding: 12px 24px;
        border-radius: 8px;
        text-decoration: none !important;
        transition: all 0.2s ease;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
        border: none;
    }

    .btn-brochure-download:hover {
        background-color: #047857;
        box-shadow: 0 6px 18px rgba(5, 150, 105, 0.35);
        transform: translateY(-1.5px);
    }
</style>

<div class="internship-details-container pb-5">
    <!-- Hero Banner Card -->
    <div class="internship-hero-banner">
        <div class="row align-items-center">
            <div class="col-md-auto mb-4 mb-md-0 text-center text-md-left">
                <div class="internship-cover-box mx-auto">
                    <?php 
                        $internshipCover = '../../' . htmlspecialchars($student['internship_image']);
                        if (empty($student['internship_image']) || !file_exists(dirname(dirname(__DIR__)) . '/' . $student['internship_image'])) {
                            $internshipCover = 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?q=80&w=600&auto=format&fit=crop';
                        }
                    ?>
                    <img src="<?= $internshipCover ?>" alt="Internship Cover Image">
                </div>
            </div>
            <div class="col-md text-center text-md-left">
                <div class="mb-2">
                    <span class="internship-badge-premium">
                        <i class="fas fa-briefcase"></i> Enrolled Internship Program
                    </span>
                </div>
                <h1 class="font-weight-bold mb-3" style="font-size: 28px; font-family: 'Plus Jakarta Sans', sans-serif;"><?= htmlspecialchars($student['internship_name']) ?></h1>
                
                <div class="d-flex flex-wrap justify-content-center justify-content-md-start align-items-center gap-3 text-secondary" style="font-size: 14.5px;">
                    <div>
                        <i class="far fa-clock text-info mr-1"></i> Duration: <strong><?= htmlspecialchars($student['duration'] . ' ' . $student['duration_unit']) ?></strong>
                    </div>
                    <div class="d-none d-md-block" style="color: rgba(255,255,255,0.25);">|</div>
                    <div>
                        <i class="fas fa-laptop text-success mr-1"></i> Mode: <strong style="text-transform: capitalize;"><?= htmlspecialchars($student['internship_mode']) ?></strong>
                    </div>
                    <div class="d-none d-md-block" style="color: rgba(255,255,255,0.25);">|</div>
                    <div>
                        <i class="fas fa-calendar-check text-warning mr-1"></i> Session: <strong><?= htmlspecialchars($student['session_name']) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Details Grid -->
    <div class="row">
        <!-- Left: Internship Specifications -->
        <div class="col-lg-6 mb-4">
            <div class="details-card-premium">
                <h3 class="section-title-premium">
                    <i class="fas fa-circle-info"></i> Internship Enrollment Specifications
                </h3>
                
                <div class="info-grid-row">
                    <div>
                        <div class="info-item-label">Full Internship Title</div>
                        <div class="info-item-value"><?= htmlspecialchars($student['internship_name']) ?></div>
                    </div>
                    <div>
                        <div class="info-item-label">Duration of Assignment</div>
                        <div class="info-item-value"><?= htmlspecialchars($student['duration'] . ' ' . $student['duration_unit']) ?></div>
                    </div>
                    <div>
                        <div class="info-item-label">Academic Batch Session</div>
                        <div class="info-item-value"><?= htmlspecialchars($student['session_name']) ?></div>
                    </div>
                    <div>
                        <div class="info-item-label">Work Delivery Mode</div>
                        <div class="info-item-value" style="text-transform: capitalize;"><?= htmlspecialchars($student['internship_mode']) ?></div>
                    </div>
                    <div>
                        <div class="info-item-label">Admission Center ID</div>
                        <div class="info-item-value" style="font-family: monospace; font-size: 13.5px;"><?= htmlspecialchars($student['enrollment_number']) ?></div>
                    </div>
                    <div>
                        <div class="info-item-label">Account Verification Status</div>
                        <div class="info-item-value">
                            <span class="badge bg-success text-white px-2 py-1" style="font-size: 11px; border-radius: 4px;">ACTIVE (CONFIRMED)</span>
                        </div>
                    </div>
                    <div>
                        <div class="info-item-label">Admission Date</div>
                        <div class="info-item-value"><?= date('d F Y', strtotime($student['created_at'])) ?></div>
                    </div>
                    <div>
                        <div class="info-item-label">Category Group</div>
                        <div class="info-item-value">Professional & Industry Training</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Syllabus and Financial Summary -->
        <div class="col-lg-6 mb-4">
            <div class="details-card-premium d-flex flex-column justify-content-between">
                <div>
                    <h3 class="section-title-premium">
                        <i class="fas fa-file-invoice-dollar"></i> Financial Summary
                    </h3>
                    
                    <div class="info-grid-row mb-4">
                        <div>
                            <div class="info-item-label">Standard Training Fee (MRP)</div>
                            <div class="info-item-value text-secondary" style="text-decoration: line-through;">₹<?= number_format($student['mrp'], 2) ?></div>
                        </div>
                        <div>
                            <div class="info-item-label">Accredited Special Price</div>
                            <div class="info-item-value text-success font-weight-bold">₹<?= number_format($student['sales_price'], 2) ?></div>
                        </div>
                        <div>
                            <div class="info-item-label">Tuition Payment Status</div>
                            <div class="info-item-value">
                                <?php if ($student['payment_status'] === 'paid'): ?>
                                    <span class="badge bg-success text-white px-3 py-1.5 rounded-pill font-weight-bold" style="font-size: 11px;"><i class="fas fa-check-circle mr-1"></i> FULLY PAID</span>
                                <?php elseif ($student['payment_status'] === 'free'): ?>
                                    <span class="badge bg-primary text-white px-3 py-1.5 rounded-pill font-weight-bold" style="font-size: 11px;"><i class="fas fa-gift mr-1"></i> SCHOLARSHIP</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark px-3 py-1.5 rounded-pill font-weight-bold" style="font-size: 11px;"><i class="fas fa-exclamation-circle mr-1"></i> <?= strtoupper($student['payment_status']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <div class="info-item-label">Dynamic Invoice</div>
                            <div class="info-item-value">
                                <?php if ($student['payment_status'] === 'paid' || $student['payment_status'] === 'free'): ?>
                                    <a href="../../assets/uploads/internships/receipts/receipt_<?= $student['id'] ?>.pdf" target="_blank" class="text-success font-weight-bold text-decoration-none" style="font-size: 13.5px;">
                                        <i class="far fa-file-pdf mr-1"></i> Download PDF
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Not Available</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Brochure download / details if syllabus brochure is enabled -->
                <?php if ($student['brochure_enabled'] && !empty($student['brochure_pdf'])): ?>
                    <div class="border-top pt-4 text-center text-md-left">
                        <div class="info-item-label mb-2">Training Curriculum</div>
                        <a href="../../<?= htmlspecialchars($student['brochure_pdf']) ?>" target="_blank" class="btn-brochure-download w-100">
                            <i class="fas fa-file-arrow-down"></i> Download Syllabus & Brochure (PDF)
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Curriculum Syllabus / Detailed Description Card -->
    <?php if (!empty($student['internship_description'])): ?>
        <div class="row mt-2">
            <div class="col-12">
                <div class="details-card-premium">
                    <h3 class="section-title-premium">
                        <i class="fas fa-book-bookmark"></i> Internship Scope & Detailed Curriculum
                    </h3>
                    <div class="syllabus-text-box">
                        <?= html_entity_decode($student['internship_description'] ?? '') ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php 
include './footer.php'; 
?>
