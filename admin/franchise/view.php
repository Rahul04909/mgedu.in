<?php
/**
 * MG Education & Social Development Organization
 * Franchise Center Detailed Profile Registry Viewer Console
 */

include '../header.php';

$db = MG_GetDBConnection();
$error_message = '';
$center = null;

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
try {
    $stmt = $db->prepare("SELECT * FROM franchise_centers WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $center = $stmt->fetch();
    if (!$center) {
        throw new Exception("Franchise center profile not found or has been removed from registry.");
    }
} catch (Exception $e) {
    $error_message = $e->getMessage();
}

if ($error_message) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Record Error',
                text: '" . htmlspecialchars($error_message) . "',
                confirmButtonColor: '#ef4444'
            }).then(() => {
                window.location.href = 'index.php';
            });
        });
    </script>";
    include '../footer.php';
    exit;
}

// Convert amenities string to array
$center_amenities = !empty($center['amenities']) ? explode(',', $center['amenities']) : [];
?>

<style>
    /* Card Styles */
    .card-profile-premium {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.02);
        overflow: hidden;
        margin-bottom: 28px;
        transition: all 0.3s ease;
    }
    .card-profile-premium:hover {
        box-shadow: 0 6px 24px rgba(0,0,0,0.04);
        border-color: #cbd5e1;
    }
    .card-profile-header {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 18px 24px;
        font-size: 13.5px;
        font-weight: 800;
        color: #0f172a;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .card-profile-header i {
        color: #0d47a1;
        font-size: 16px;
    }
    .card-profile-body {
        padding: 24px;
    }

    /* Action Toolbar Row */
    .action-toolbar-row {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 24px;
    }
    .action-toolbar-title h4 {
        margin: 0 0 4px 0;
        font-weight: 800;
        color: #0f172a;
        font-size: 18px;
    }
    .action-toolbar-title span {
        font-size: 12.5px;
        color: #64748b;
        font-weight: 600;
    }
    .btn-action-premium {
        border-radius: 50px;
        padding: 8px 20px;
        font-size: 13px;
        font-weight: 750;
        text-transform: uppercase;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.25s ease;
        text-decoration: none;
    }
    .btn-action-back {
        background-color: #ffffff;
        color: #475569;
        border: 1px solid #cbd5e1;
    }
    .btn-action-back:hover {
        background-color: #f8fafc;
        color: #0f172a;
        border-color: #94a3b8;
    }
    .btn-action-edit {
        background-color: #1e293b;
        color: #ffffff;
        border: 1px solid #1e293b;
    }
    .btn-action-edit:hover {
        background-color: #0f172a;
        transform: translateY(-1px);
    }
    .btn-action-print {
        background-color: #28a745;
        color: #ffffff;
        border: 1px solid #28a745;
        box-shadow: 0 4px 10px rgba(40,167,69,0.15);
    }
    .btn-action-print:hover {
        background-color: #218838;
        box-shadow: 0 6px 14px rgba(40,167,69,0.25);
        transform: translateY(-1px);
    }

    /* Left Column Assets styling */
    .profile-avatar-wrapper {
        text-align: center;
        padding: 15px 0 25px 0;
        border-bottom: 1px solid #f1f5f9;
    }
    .center-logo-frame {
        width: 110px;
        height: 110px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid #fff;
        box-shadow: 0 4px 14px rgba(0,0,0,0.08);
        background: #fff;
        margin-bottom: 16px;
    }
    .owner-image-frame {
        width: 90px;
        height: 90px;
        border-radius: 12px;
        object-fit: cover;
        border: 3px solid #fff;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        background: #fff;
        margin-top: 10px;
    }
    .center-title-tag {
        font-weight: 800;
        font-size: 16px;
        color: #0f172a;
        margin-bottom: 6px;
    }
    .center-id-badge {
        background-color: #eff6ff;
        color: #2563eb;
        font-family: monospace;
        font-weight: 700;
        font-size: 13.5px;
        padding: 4px 12px;
        border-radius: 50px;
        display: inline-block;
        border: 1px solid #bfdbfe;
    }

    /* Description list lists styling */
    .info-label {
        font-size: 10.5px;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        margin-bottom: 4px;
    }
    .info-value {
        font-size: 14px;
        font-weight: 650;
        color: #0f172a;
        margin-bottom: 20px;
    }
    .info-value-highlight {
        color: #0d47a1;
    }

    /* Password reveal board */
    .password-reveal-board {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .btn-reveal-pwd {
        background: none;
        border: none;
        color: #64748b;
        cursor: pointer;
        padding: 0 6px;
        font-size: 15px;
        transition: color 0.2s ease;
    }
    .btn-reveal-pwd:hover {
        color: #0d47a1;
    }

    /* Amenities check badges */
    .amenity-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 50px;
        font-size: 12.5px;
        font-weight: 700;
        margin-right: 8px;
        margin-bottom: 8px;
    }
    .amenity-active {
        background-color: #f0fdf4;
        color: #166534;
        border: 1px solid #bbf7d0;
    }
    .amenity-inactive {
        background-color: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
        opacity: 0.6;
    }

    /* Metric Finance Cards */
    .metric-finance-card {
        border-radius: 12px;
        padding: 16px 20px;
        color: #fff;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .metric-finance-green {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
    }
    .metric-finance-purple {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.15);
    }
    .metric-finance-label {
        font-size: 10.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        opacity: 0.85;
        margin-bottom: 6px;
    }
    .metric-finance-val {
        font-size: 20px;
        font-weight: 800;
    }

    /* Document Button Links */
    .btn-doc-download {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        color: #334155;
        font-weight: 700;
        font-size: 12.5px;
        text-decoration: none;
        transition: all 0.2s ease;
        margin-bottom: 12px;
    }
    .btn-doc-download:hover {
        background-color: #eff6ff;
        color: #1e3a8a;
        border-color: #bfdbfe;
        transform: translateY(-1px);
    }
    .btn-doc-download i.file-icon {
        font-size: 18px;
        margin-right: 10px;
    }

    /* Signature & Stamp preview box */
    .signatory-preview-box {
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 14px;
        background-color: #fafbfb;
        text-align: center;
        overflow: hidden;
    }
    .signatory-image {
        max-height: 110px;
        max-width: 100%;
        object-fit: contain;
        transition: transform 0.3s ease;
        mix-blend-mode: multiply; /* clean scanned blend background */
    }
    .signatory-preview-box:hover .signatory-image {
        transform: scale(1.04);
    }

    /* Badge standard lab overrides */
    .badge-lab {
        font-size: 11px;
        font-weight: 800;
        padding: 4px 12px;
        border-radius: 50px;
        text-transform: uppercase;
        display: inline-block;
    }
    .badge-lab-basic {
        background-color: #eff6ff;
        color: #2563eb;
        border: 1px solid #bfdbfe;
    }
    .badge-lab-advance {
        background-color: #f5f3ff;
        color: #7c3aed;
        border: 1px solid #ddd6fe;
    }

    /* Print Styles overrides */
    @media print {
        body {
            background-color: #ffffff !important;
            color: #000000 !important;
            font-size: 12px !important;
        }
        .main-header, .main-sidebar, .main-footer, .action-toolbar-row, .btn-reveal-pwd, .btn-doc-download i:last-child {
            display: none !important;
        }
        .content-wrapper {
            margin-left: 0 !important;
            padding: 0 !important;
            background-color: #ffffff !important;
        }
        .card-profile-premium {
            border: none !important;
            box-shadow: none !important;
            margin-bottom: 15px !important;
        }
        .card-profile-header {
            background: none !important;
            border-bottom: 2px solid #000000 !important;
            padding: 5px 0 !important;
            color: #000000 !important;
        }
        .card-profile-header i {
            display: none !important;
        }
        .metric-finance-card {
            background: none !important;
            border: 1px solid #cbd5e1 !important;
            color: #000000 !important;
            box-shadow: none !important;
        }
        .metric-finance-val {
            color: #000000 !important;
        }
        .password-reveal-board {
            border: none !important;
            padding: 0 !important;
            background: none !important;
        }
        .btn-reveal-pwd {
            display: none !important;
        }
        #pwdVal {
            font-family: monospace !important;
        }
        .btn-doc-download {
            background: none !important;
            border: 1px solid #cbd5e1 !important;
            color: #000000 !important;
            pointer-events: none !important;
        }
        .amenity-badge {
            border: 1px solid #cbd5e1 !important;
            color: #000000 !important;
            background: none !important;
        }
    }
</style>

<div class="container-fluid pt-3">
    
    <!-- TOP TOOLBAR -->
    <div class="action-toolbar-row">
        <div class="action-toolbar-title">
            <h4><?= htmlspecialchars($center['center_name']) ?></h4>
            <span>Registry Status: <span class="badge bg-success text-uppercase" style="font-size:9.5px; padding:3px 8px; font-weight:700;">Active</span> | Created on: <?= date('d M Y, h:i A', strtotime($center['created_at'])) ?></span>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="index.php" class="btn-action-premium btn-action-back">
                <i class="fa-solid fa-arrow-left-long"></i> Back to Console
            </a>
            <a href="edit.php?id=<?= $center['id'] ?>" class="btn-action-premium btn-action-edit">
                <i class="fa-solid fa-pen-to-square"></i> Edit Profile
            </a>
            <button onclick="window.print()" class="btn-action-premium btn-action-print">
                <i class="fa-solid fa-print"></i> Print Profile
            </button>
        </div>
    </div>

    <!-- MAIN GRID SECTION -->
    <div class="row">
        
        <!-- LEFT COLUMN: Center Brand Identity & Documents -->
        <div class="col-lg-4">
            
            <!-- BRAND IDENTITY -->
            <div class="card-profile-premium">
                <div class="card-profile-header">
                    <i class="fa-solid fa-id-card-clip"></i> 01. Brand & Identity
                </div>
                <div class="card-profile-body">
                    <div class="profile-avatar-wrapper">
                        <!-- Center Logo -->
                        <?php if (!empty($center['center_logo'])): ?>
                            <img src="<?= '../../' . htmlspecialchars($center['center_logo']) ?>" class="center-logo-frame" alt="Center Logo">
                        <?php else: ?>
                            <img src="https://via.placeholder.com/150?text=NO+LOGO" class="center-logo-frame" alt="Placeholder Logo">
                        <?php endif; ?>
                        
                        <div class="center-title-tag"><?= htmlspecialchars($center['center_name']) ?></div>
                        <div class="center-id-badge"><?= htmlspecialchars($center['center_id']) ?></div>
                        
                        <!-- Owner Portrait -->
                        <div class="mt-4 pt-3 border-top w-100 text-center">
                            <div class="info-label">Franchise Proprietor</div>
                            <?php if (!empty($center['owner_image'])): ?>
                                <img src="<?= '../../' . htmlspecialchars($center['owner_image']) ?>" class="owner-image-frame" alt="Owner portrait">
                            <?php else: ?>
                                <img src="https://via.placeholder.com/150?text=PROPRIETOR" class="owner-image-frame" alt="Placeholder portrait">
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Login Credentials Reveal -->
                    <div class="mt-3">
                        <div class="info-label mb-2">Portal Access Password</div>
                        <div class="password-reveal-board">
                            <code style="font-size: 14.5px; font-weight:700;" class="text-dark" id="pwdVal">••••••••</code>
                            <button class="btn-reveal-pwd" id="btnReveal" title="Reveal Password">
                                <i class="fa-solid fa-eye" id="revealIcon"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DOCUMENTS VAULT -->
            <div class="card-profile-premium">
                <div class="card-profile-header">
                    <i class="fa-solid fa-folder-closed"></i> 02. Verified Documents
                </div>
                <div class="card-profile-body">
                    
                    <!-- Aadhaar Scan -->
                    <div class="info-label">Aadhaar Card (Scanned)</div>
                    <div class="info-value text-muted" style="margin-bottom: 8px;">
                        Number: <code class="text-dark" style="font-weight:700;"><?= htmlspecialchars($center['aadhaar_number']) ?></code>
                    </div>
                    <?php if (!empty($center['aadhaar_card_file'])): ?>
                        <a href="<?= '../../' . htmlspecialchars($center['aadhaar_card_file']) ?>" target="_blank" class="btn-doc-download">
                            <span><i class="fa-solid fa-file-pdf text-danger file-icon"></i> Aadhaar_Card.pdf</span>
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>
                    <?php else: ?>
                        <div class="text-danger small font-weight-bold mb-3"><i class="fa-solid fa-triangle-exclamation"></i> Scan not uploaded.</div>
                    <?php endif; ?>

                    <!-- PAN Scan -->
                    <div class="info-label mt-3">PAN Card (Scanned)</div>
                    <div class="info-value text-muted" style="margin-bottom: 8px;">
                        Number: <code class="text-dark" style="font-weight:700;"><?= !empty($center['pan_number']) ? htmlspecialchars($center['pan_number']) : 'N/A' ?></code>
                    </div>
                    <?php if (!empty($center['pan_card_file'])): ?>
                        <a href="<?= '../../' . htmlspecialchars($center['pan_card_file']) ?>" target="_blank" class="btn-doc-download">
                            <span><i class="fa-solid fa-file-pdf text-danger file-icon"></i> PAN_Card.pdf</span>
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>
                    <?php else: ?>
                        <div class="text-muted small font-weight-bold mb-3"><i class="fa-solid fa-circle-info"></i> PAN Card not registered.</div>
                    <?php endif; ?>

                    <!-- MSME Certificate -->
                    <div class="info-label mt-3">MSME Registration</div>
                    <?php if (!empty($center['msme_file'])): ?>
                        <a href="<?= '../../' . htmlspecialchars($center['msme_file']) ?>" target="_blank" class="btn-doc-download">
                            <span><i class="fa-solid fa-file-pdf text-danger file-icon"></i> MSME_Certificate.pdf</span>
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>
                    <?php else: ?>
                        <div class="text-muted small font-weight-bold mb-3"><i class="fa-solid fa-circle-info"></i> MSME Certificate not registered.</div>
                    <?php endif; ?>

                    <!-- Signatory & Stamp verification -->
                    <div class="info-label mt-4">Authorized Signatory & Stamp</div>
                    <?php if (!empty($center['auth_signatory'])): ?>
                        <div class="signatory-preview-box mt-2">
                            <img src="<?= '../../' . htmlspecialchars($center['auth_signatory']) ?>" class="signatory-image" alt="Authorized Signatory Scan with Stamp">
                        </div>
                    <?php else: ?>
                        <div class="text-danger small font-weight-bold mt-2"><i class="fa-solid fa-triangle-exclamation"></i> Signatory Stamp scan missing.</div>
                    <?php endif; ?>

                </div>
            </div>

        </div>

        <!-- RIGHT COLUMN: Contact, Geographical, Facilities & Financials -->
        <div class="col-lg-8">
            
            <!-- BIOGRAPHICAL & CONTACT -->
            <div class="card-profile-premium">
                <div class="card-profile-header">
                    <i class="fa-solid fa-map-location-dot"></i> 03. Contact & Geographic Profile
                </div>
                <div class="card-profile-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-label">Email Address</div>
                            <div class="info-value">
                                <a href="mailto:<?= htmlspecialchars($center['email']) ?>" class="text-decoration-none info-value-highlight">
                                    <i class="fa-solid fa-envelope mr-1"></i> <?= htmlspecialchars($center['email']) ?>
                                </a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">Mobile Number</div>
                            <div class="info-value">
                                <a href="tel:<?= htmlspecialchars($center['mobile']) ?>" class="text-decoration-none info-value-highlight">
                                    <i class="fa-solid fa-phone mr-1"></i> +91 <?= htmlspecialchars($center['mobile']) ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row border-top pt-3 mt-1">
                        <div class="col-md-4">
                            <div class="info-label">Pincode Mapping</div>
                            <div class="info-value"><?= htmlspecialchars($center['pincode']) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">City / District</div>
                            <div class="info-value"><?= htmlspecialchars($center['city']) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">State Jurisdiction</div>
                            <div class="info-value"><?= htmlspecialchars($center['state']) ?></div>
                        </div>
                    </div>

                    <div class="row border-top pt-3 mt-1">
                        <div class="col-md-12">
                            <div class="info-label">Full Registry Address</div>
                            <div class="info-value mb-0" style="line-height: 1.5; font-weight: 700; color: #334155;">
                                <i class="fa-solid fa-house-chimney text-muted mr-1"></i> <?= htmlspecialchars($center['full_address']) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PHYSICAL INFRASTRUCTURE & STAFFING -->
            <div class="card-profile-premium">
                <div class="card-profile-header">
                    <i class="fa-solid fa-cubes"></i> 04. Infrastructure & Training Capability
                </div>
                <div class="card-profile-body">
                    <div class="row">
                        <div class="col-md-4 text-center border-right py-2">
                            <div style="font-size: 28px; color: #0d47a1; margin-bottom: 6px;"><i class="fa-solid fa-chalkboard-user"></i></div>
                            <div class="info-label">Classrooms Available</div>
                            <div style="font-size:20px; font-weight:800; color:#0f172a;"><?= intval($center['classrooms']) ?> Units</div>
                        </div>
                        <div class="col-md-4 text-center border-right py-2">
                            <div style="font-size: 28px; color: #0d47a1; margin-bottom: 6px;"><i class="fa-solid fa-desktop"></i></div>
                            <div class="info-label">Computers Installed</div>
                            <div style="font-size:20px; font-weight:800; color:#0f172a;"><?= intval($center['computers']) ?> Active</div>
                        </div>
                        <div class="col-md-4 text-center py-2">
                            <div style="font-size: 28px; color: #0d47a1; margin-bottom: 6px;"><i class="fa-solid fa-users-gear"></i></div>
                            <div class="info-label">Active Staff Members</div>
                            <div style="font-size:20px; font-weight:800; color:#0f172a;"><?= intval($center['total_staff']) ?> Staff</div>
                        </div>
                    </div>

                    <div class="row border-top pt-4 mt-3">
                        <div class="col-md-4">
                            <div class="info-label">Lab Standard Rating</div>
                            <div class="info-value">
                                <span class="badge-lab badge-lab-<?= htmlspecialchars($center['lab_type']) ?>">
                                    <?= ucfirst($center['lab_type']) ?> Standard
                                </span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">Weekly Working Days</div>
                            <div class="info-value" style="font-weight: 700;">
                                <i class="fa-regular fa-calendar-days text-muted mr-1"></i> 
                                <?= htmlspecialchars($center['working_days_from'] ?? 'Monday') ?> to <?= htmlspecialchars($center['working_days_to'] ?? 'Saturday') ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">Daily Training Hours</div>
                            <div class="info-value" style="font-weight: 700;">
                                <i class="fa-regular fa-clock text-muted mr-1"></i>
                                <?php
                                $from = !empty($center['working_hours_from']) ? date('h:i A', strtotime($center['working_hours_from'])) : '09:00 AM';
                                $to = !empty($center['working_hours_to']) ? date('h:i A', strtotime($center['working_hours_to'])) : '06:00 PM';
                                echo $from . " - " . $to;
                                ?>
                            </div>
                        </div>
                    </div>

                    <div class="row border-top pt-3 mt-1">
                        <div class="col-md-12">
                            <div class="info-label mb-2">Amenities & Campus Facilities</div>
                            <div>
                                <!-- Power Backup -->
                                <span class="amenity-badge <?= in_array('power_backup', $center_amenities) ? 'amenity-active' : 'amenity-inactive' ?>">
                                    <i class="fa-solid <?= in_array('power_backup', $center_amenities) ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i> Power Backup
                                </span>
                                <!-- CCTV -->
                                <span class="amenity-badge <?= in_array('cctv', $center_amenities) ? 'amenity-active' : 'amenity-inactive' ?>">
                                    <i class="fa-solid <?= in_array('cctv', $center_amenities) ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i> CCTV Surveillance
                                </span>
                                <!-- Internet -->
                                <span class="amenity-badge <?= in_array('internet', $center_amenities) ? 'amenity-active' : 'amenity-inactive' ?>">
                                    <i class="fa-solid <?= in_array('internet', $center_amenities) ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i> Internet Connection
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FINANCIAL STRUCTURE & METRICS -->
            <div class="card-profile-premium">
                <div class="card-profile-header">
                    <i class="fa-solid fa-indian-rupee-sign"></i> 05. Franchise Financial Structure & Revenue Share
                </div>
                <div class="card-profile-body">
                    
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="metric-finance-card metric-finance-green">
                                <span class="metric-finance-label">Yearly Franchise Fee</span>
                                <span class="metric-finance-val">₹<?= number_format($center['franchise_fees'], 2) ?> <span style="font-size:11px; opacity:0.85; font-weight:600;">/ Year</span></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="metric-finance-card metric-finance-purple">
                                <span class="metric-finance-label">Royalty Charge Share</span>
                                <span class="metric-finance-val"><?= htmlspecialchars($center['royalty_percentage']) ?>% <span style="font-size:11px; opacity:0.85; font-weight:600;">of Total Receipts</span></span>
                            </div>
                        </div>
                    </div>

                    <div class="row border-top pt-3">
                        <div class="col-md-6">
                            <div class="info-label">GSTIN ID Registered</div>
                            <div class="info-value mb-0" style="font-family: monospace; font-weight:700;">
                                <?= !empty($center['gst_number']) ? htmlspecialchars($center['gst_number']) : 'No GST Registration Linked (Optional)' ?>
                            </div>
                        </div>
                        <div class="col-md-6 text-md-right d-flex align-items-center justify-content-md-end mt-3 mt-md-0">
                            <div class="text-muted small" style="font-weight: 600;">
                                <i class="fa-solid fa-shield-halved text-success mr-1"></i> MGEDU Franchise Agreement Compliant
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
        
    </div>
</div>

<script>
    $(document).ready(function() {
        let passwordHidden = true;
        const plainPassword = "<?= htmlspecialchars($center['password']) ?>";
        const maskedPassword = "••••••••";

        $('#btnReveal').on('click', function() {
            if (passwordHidden) {
                $('#pwdVal').text(plainPassword);
                $('#revealIcon').removeClass('fa-eye').addClass('fa-eye-slash');
                $(this).attr('title', 'Hide Password');
                passwordHidden = false;
            } else {
                $('#pwdVal').text(maskedPassword);
                $('#revealIcon').removeClass('fa-eye-slash').addClass('fa-eye');
                $(this).attr('title', 'Reveal Password');
                passwordHidden = true;
            }
        });
    });
</script>

<?php include '../footer.php'; ?>
