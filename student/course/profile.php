<?php
/**
 * MG Education & Social Development Organization
 * Student Profile & Password Management - Course Dashboard
 */

include './header.php';

$db = MG_GetDBConnection();
$student_id = $_SESSION['student_id'];

// AJAX update password request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_password') {
    header('Content-Type: application/json');
    $newPassword = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if (empty($newPassword) || empty($confirmPassword)) {
        echo json_encode(['success' => false, 'message' => 'Please enter and confirm your new password.']);
        exit;
    }

    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long.']);
        exit;
    }

    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'New password and confirmation password do not match.']);
        exit;
    }

    try {
        $stmtUpd = $db->prepare("UPDATE `admissions` SET `password` = ? WHERE `id` = ? AND `status` = 'confirmed'");
        $stmtUpd->execute([$newPassword, $student_id]);
        
        echo json_encode(['success' => true, 'message' => 'Your secure portal password has been updated successfully!']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
        exit;
    }
}

// Fetch complete student and course details
try {
    $stmt = $db->prepare("
        SELECT a.*, c.name as course_name, c.duration, c.duration_unit
        FROM admissions a
        LEFT JOIN courses c ON a.course_id = c.id
        WHERE a.id = ? AND a.status = 'confirmed'
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        echo "<div class='alert alert-danger'>Unable to load student profile details. Please log in again.</div>";
        include './footer.php';
        exit;
    }
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    include './footer.php';
    exit;
}
?>

<!-- Custom visual styles for premium profile view -->
<style>
    .profile-card {
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
        overflow: hidden;
        text-align: center;
        padding: 30px 20px;
    }

    .profile-avatar-frame {
        width: 130px;
        height: 130px;
        border-radius: 50%;
        border: 4px solid #f1f5f9;
        box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        object-fit: cover;
        margin: 0 auto 15px auto;
        display: block;
    }

    .profile-card h3 {
        font-weight: 700;
        font-size: 20px;
        color: #1e293b;
        margin-bottom: 5px;
    }

    .profile-card .enrollment-badge {
        font-family: monospace;
        font-size: 14px;
        font-weight: 700;
        color: #0ea5e9;
        background-color: rgba(14, 165, 233, 0.08);
        padding: 4px 12px;
        border-radius: 50px;
        display: inline-block;
        margin-bottom: 15px;
    }

    .profile-details-card {
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
        padding: 25px;
        margin-bottom: 30px;
    }

    .card-title-premium {
        font-weight: 700;
        font-size: 18px;
        color: #1e293b;
        border-bottom: 2px solid #f1f5f9;
        padding-bottom: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .card-title-premium i {
        color: #0ea5e9;
    }

    .details-table th {
        color: #64748b;
        font-weight: 600;
        font-size: 14px;
        border-bottom: 1px solid #f1f5f9;
        padding: 12px 8px;
        width: 35%;
    }

    .details-table td {
        color: #1e293b;
        font-weight: 500;
        font-size: 14px;
        border-bottom: 1px solid #f1f5f9;
        padding: 12px 8px;
    }

    .form-group-custom {
        margin-bottom: 20px;
        position: relative;
    }

    .form-label-custom {
        font-weight: 600;
        font-size: 13.5px;
        color: #475569;
        margin-bottom: 8px;
        display: block;
    }

    .form-control-custom {
        width: 100%;
        padding: 10px 14px;
        border: 1.2px solid #cbd5e1;
        background-color: #ffffff;
        color: #1e293b;
        font-size: 14px;
        border-radius: 8px;
        transition: all 0.2s ease;
        outline: none;
        box-shadow: none;
    }

    .form-control-custom:focus {
        border-color: #0ea5e9;
        box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
    }

    .eye-toggle-icon {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #64748b;
        cursor: pointer;
        z-index: 10;
        transition: color 0.2s ease;
    }
    .eye-toggle-icon:hover {
        color: #1e293b;
    }

    .btn-premium-action {
        background: linear-gradient(135deg, #0ea5e9, #0284c7);
        color: #ffffff;
        font-weight: 600;
        font-size: 14.5px;
        padding: 11px 24px;
        border-radius: 8px;
        border: none;
        box-shadow: 0 4px 12px rgba(14, 165, 233, 0.25);
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .btn-premium-action:hover {
        transform: translateY(-1.5px);
        box-shadow: 0 6px 18px rgba(14, 165, 233, 0.35);
        filter: brightness(1.05);
    }
</style>

<div class="row">
    <!-- Left Column: Quick Profile Card -->
    <div class="col-lg-4 mb-4">
        <div class="profile-card">
            <!-- Profile Photo -->
            <?php 
                $photoPath = '../../' . htmlspecialchars($student['student_photo']);
                if (empty($student['student_photo']) || !file_exists(dirname(dirname(__DIR__)) . '/' . $student['student_photo'])) {
                    $photoPath = './src/images/user-avtar.png';
                }
            ?>
            <img src="<?= $photoPath ?>" alt="Student Photo" class="profile-avatar-frame">
            
            <h3><?= htmlspecialchars($student['student_name']) ?></h3>
            <div class="enrollment-badge">
                <i class="fa-solid fa-hashtag"></i> <?= htmlspecialchars($student['enrollment_number']) ?>
            </div>
            
            <hr style="border-top: 1px solid #f1f5f9; margin: 20px 0;">
            
            <div class="text-start" style="font-size: 13.5px;">
                <div class="mb-2">
                    <strong class="text-secondary">Enrolled Program:</strong>
                    <div class="text-dark font-weight-bold mt-1"><?= htmlspecialchars($student['course_name']) ?></div>
                </div>
                <div class="mb-2">
                    <strong class="text-secondary">Course Duration:</strong>
                    <div class="text-dark mt-1"><?= htmlspecialchars($student['duration'] . ' ' . $student['duration_unit']) ?></div>
                </div>
                <div>
                    <strong class="text-secondary">Academic Session:</strong>
                    <div class="text-dark mt-1"><?= htmlspecialchars($student['session_name']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Personal details & Password updates -->
    <div class="col-lg-8">
        
        <!-- Personal details card -->
        <div class="profile-details-card">
            <div class="card-title-premium">
                <i class="fa-solid fa-address-card"></i> Personal Profile Information
            </div>
            
            <table class="table details-table mb-0">
                <tbody>
                    <tr>
                        <th>Father's Name</th>
                        <td><?= htmlspecialchars($student['father_name']) ?></td>
                    </tr>
                    <tr>
                        <th>Mother's Name</th>
                        <td><?= htmlspecialchars($student['mother_name']) ?></td>
                    </tr>
                    <tr>
                        <th>Date of Birth</th>
                        <td><?= date('d-M-Y', strtotime($student['dob'])) ?></td>
                    </tr>
                    <tr>
                        <th>Registered Email</th>
                        <td><?= htmlspecialchars($student['email']) ?></td>
                    </tr>
                    <tr>
                        <th>Mobile Number</th>
                        <td><?= htmlspecialchars($student['mobile']) ?></td>
                    </tr>
                    <tr>
                        <th>Permanent Address</th>
                        <td><?= htmlspecialchars($student['address']) . ', ' . htmlspecialchars($student['city']) . ', ' . htmlspecialchars($student['district']) . ' - ' . htmlspecialchars($student['pincode']) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Password Change Card -->
        <div class="profile-details-card">
            <div class="card-title-premium">
                <i class="fa-solid fa-key"></i> Manage Password Security
            </div>
            
            <form id="passwordUpdateForm" onsubmit="handlePasswordUpdate(event)">
                
                <!-- Current Password check (Show read-only or reveal option) -->
                <div class="form-group-custom">
                    <label class="form-label-custom">Current Portal Password</label>
                    <div style="position: relative;">
                        <input type="password" class="form-control-custom" id="current_password" value="<?= htmlspecialchars($student['password']) ?>" readonly style="background-color: #f8fafc; font-family: monospace;">
                        <i class="fa-solid fa-eye-slash eye-toggle-icon" onclick="toggleInputVisibility('current_password', this)"></i>
                    </div>
                    <small class="text-muted mt-1 d-block">This is your current password assigned to your enrollment account.</small>
                </div>

                <div class="row">
                    <!-- New password input -->
                    <div class="col-md-6 mb-3">
                        <div class="form-group-custom mb-0">
                            <label class="form-label-custom">New Portal Password</label>
                            <div style="position: relative;">
                                <input type="password" class="form-control-custom" name="new_password" id="new_password" placeholder="Minimum 6 characters" required>
                                <i class="fa-solid fa-eye-slash eye-toggle-icon" onclick="toggleInputVisibility('new_password', this)"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Confirm new password -->
                    <div class="col-md-6 mb-3">
                        <div class="form-group-custom mb-0">
                            <label class="form-label-custom">Confirm New Password</label>
                            <div style="position: relative;">
                                <input type="password" class="form-control-custom" name="confirm_password" id="confirm_password" placeholder="Re-enter new password" required>
                                <i class="fa-solid fa-eye-slash eye-toggle-icon" onclick="toggleInputVisibility('confirm_password', this)"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn-premium-action" id="pwd-submit-btn">
                        <i class="fa-solid fa-shield-halved"></i> Update Account Password
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

<script>
    // Reveal password field values
    function toggleInputVisibility(inputId, iconElement) {
        const input = document.getElementById(inputId);
        if (input.type === 'password') {
            input.type = 'text';
            $(iconElement).removeClass('fa-eye-slash').addClass('fa-eye');
        } else {
            input.type = 'password';
            $(iconElement).removeClass('fa-eye').addClass('fa-eye-slash');
        }
    }

    // Submit password update via AJAX
    function handlePasswordUpdate(event) {
        event.preventDefault();
        const newPassword = $('#new_password').val().trim();
        const confirmPassword = $('#confirm_password').val().trim();
        const submitBtn = $('#pwd-submit-btn');

        if (newPassword.length < 6) {
            Swal.fire({
                title: 'Invalid Entry',
                text: 'New password must be at least 6 characters long.',
                icon: 'warning',
                confirmButtonColor: '#0ea5e9'
            });
            return;
        }

        if (newPassword !== confirmPassword) {
            Swal.fire({
                title: 'Password Mismatch',
                text: 'The new password and confirmation password do not match.',
                icon: 'warning',
                confirmButtonColor: '#0ea5e9'
            });
            return;
        }

        submitBtn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Saving Password...');

        $.ajax({
            type: 'POST',
            url: 'profile.php',
            data: {
                action: 'update_password',
                new_password: newPassword,
                confirm_password: confirmPassword
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'Password Saved',
                        text: response.message,
                        icon: 'success',
                        confirmButtonColor: '#28a745'
                    }).then(() => {
                        // Update the current password check field display
                        $('#current_password').val(newPassword);
                        // Reset the update form
                        $('#passwordUpdateForm')[0].reset();
                        // Make sure fields are hidden again
                        $('#new_password, #confirm_password').attr('type', 'password');
                        $('.eye-toggle-icon').removeClass('fa-eye').addClass('fa-eye-slash');
                    });
                } else {
                    Swal.fire({
                        title: 'Update Failed',
                        text: response.message,
                        icon: 'error',
                        confirmButtonColor: '#ef4444'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'Server Error',
                    text: 'Security system failed to communicate with the updates database.',
                    icon: 'error',
                    confirmButtonColor: '#ef4444'
                });
            },
            complete: function() {
                submitBtn.prop('disabled', false).html('<i class="fa-solid fa-shield-halved"></i> Update Account Password');
            }
        });
    }
</script>

<?php include './footer.php'; ?>