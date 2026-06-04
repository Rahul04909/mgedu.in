<?php
/**
 * MG Education & Social Development Organization
 * Admin Profile Management Portal
 * Super secure update handling for admin information, file uploads, and passwords.
 */

include './header.php';

$db = MG_GetDBConnection();
$admin_id = $_SESSION['admin_id'];

$success_message = '';
$error_message = '';

// Generate CSRF token if empty
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 1. Fetch current admin details from database
try {
    $stmt = $db->prepare("SELECT * FROM admins WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $admin_id]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        throw new Exception("Administrator account not found.");
    }
} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// 2. Handle Profile Details & Image Update Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_details'])) {
    try {
        // Validate CSRF
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Security mismatch: CSRF validation failed.");
        }

        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');

        // Validation
        if (empty($name) || empty($username) || empty($email) || empty($mobile)) {
            throw new Exception("All standard profile fields are required.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please provide a valid email address.");
        }

        if (!preg_match('/^[0-9]{10,15}$/', $mobile)) {
            throw new Exception("Mobile number must be between 10 and 15 digits.");
        }

        // Check for duplicate username or email in other accounts
        $checkStmt = $db->prepare("SELECT id FROM admins WHERE (username = :username OR email = :email) AND id != :id");
        $checkStmt->execute(['username' => $username, 'email' => $email, 'id' => $admin_id]);
        if ($checkStmt->fetch()) {
            throw new Exception("Username or Email is already taken by another account.");
        }

        $profile_image_path = $admin['profile_image'];

        // Handle Secure Profile Image Upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['profile_image']['tmp_name'];
            $fileName = $_FILES['profile_image']['name'];
            $fileSize = $_FILES['profile_image']['size'];
            $fileType = $_FILES['profile_image']['type'];
            
            // Limit file size (e.g., 2MB)
            if ($fileSize > 2 * 1024 * 1024) {
                throw new Exception("Profile picture must be under 2MB.");
            }

            // Verify real image mime-type via getimagesize
            $imageInfo = @getimagesize($fileTmpPath);
            if ($imageInfo === false) {
                throw new Exception("Uploaded file is not a valid image.");
            }

            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($imageInfo['mime'], $allowedMimeTypes)) {
                throw new Exception("Only JPG, PNG, GIF, and WEBP formats are allowed.");
            }

            // Sanitize file extension
            $pathParts = pathinfo($fileName);
            $fileExtension = strtolower($pathParts['extension'] ?? 'png');
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($fileExtension, $allowedExtensions)) {
                throw new Exception("Invalid file extension.");
            }

            // Secure new filename generation
            $newFileName = bin2hex(random_bytes(16)) . '.' . $fileExtension;
            
            // Target upload directory
            $uploadDir = dirname(__DIR__) . '/assets/uploads/profiles/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $dest_path = $uploadDir . $newFileName;
            
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                // Delete old profile picture if exists and not default
                if (!empty($admin['profile_image']) && strpos($admin['profile_image'], 'uploads/') !== false) {
                    $old_file = dirname(__DIR__) . '/' . $admin['profile_image'];
                    if (file_exists($old_file)) {
                        @unlink($old_file);
                    }
                }
                $profile_image_path = 'assets/uploads/profiles/' . $newFileName;
            } else {
                throw new Exception("Error uploading file. Please try again.");
            }
        }

        // Update database using Prepared Statements
        $updateStmt = $db->prepare("
            UPDATE admins 
            SET name = :name, username = :username, email = :email, mobile = :mobile, profile_image = :profile_image
            WHERE id = :id
        ");
        
        $updateStmt->execute([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'mobile' => $mobile,
            'profile_image' => $profile_image_path,
            'id' => $admin_id
        ]);

        // Refresh Session details
        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_name'] = $name;
        $_SESSION['admin_email'] = $email;
        $_SESSION['admin_mobile'] = $mobile;
        $_SESSION['admin_profile_image'] = $profile_image_path;

        // Force reload page to show fresh details
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Profile Updated',
                    text: 'Your personal details have been saved successfully.',
                    confirmButtonColor: '#28a745'
                }).then(() => {
                    window.location.href = 'profile.php';
                });
            });
        </script>";
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// 3. Handle Secure Password Change Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    try {
        // Validate CSRF
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Security mismatch: CSRF validation failed.");
        }

        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            throw new Exception("All password fields are required.");
        }

        // Verify current password first
        if (!password_verify($current_password, $admin['password'])) {
            throw new Exception("Current password verification failed.");
        }

        // Verify new password complexity / mismatch
        if ($new_password === $current_password) {
            throw new Exception("New password cannot be the same as your current password.");
        }

        if (strlen($new_password) < 8) {
            throw new Exception("New password must be at least 8 characters long.");
        }

        if ($new_password !== $confirm_password) {
            throw new Exception("New password and confirmation password do not match.");
        }

        // Hash new password securely with bcrypt
        $new_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);

        // Update Database using Prepared Statement
        $passStmt = $db->prepare("UPDATE admins SET password = :password WHERE id = :id");
        $passStmt->execute(['password' => $new_hash, 'id' => $admin_id]);

        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Password Securely Changed',
                    text: 'Your security password was updated. Please re-authenticate.',
                    confirmButtonColor: '#28a745'
                }).then(() => {
                    window.location.href = 'logout.php';
                });
            });
        </script>";

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<style>
    .profile-card {
        background: #ffffff;
        border: 1px solid #d1d7dc;
        border-radius: 12px;
        transition: all 0.3s ease;
        overflow: hidden;
    }
    .profile-card:hover {
        border-color: #8c9096;
        box-shadow: 0 6px 16px rgba(0,0,0,0.05);
    }
    .profile-header {
        border-bottom: 1px solid #f0f0f1;
        padding: 20px 25px;
    }
    .profile-header h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
        color: #2c3e50;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .profile-body {
        padding: 30px 25px;
    }
    .avatar-wrapper {
        text-align: center;
        margin-bottom: 25px;
    }
    .avatar-wrapper img {
        width: 110px;
        height: 110px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid #f8f9fa;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .avatar-wrapper h4 {
        margin: 15px 0 5px 0;
        font-weight: 700;
        font-size: 18px;
    }
    .avatar-wrapper p {
        margin: 0;
        font-size: 13px;
        color: #6c757d;
    }
    .form-label {
        font-size: 12px;
        font-weight: 600;
        color: #2c3e50;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
    }
    .form-control {
        border: 1px solid #d1d7dc;
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 14px;
        color: #2c3e50;
    }
    .form-control:focus {
        border-color: #8c9096;
        box-shadow: 0 0 0 3px rgba(140, 144, 150, 0.1);
        outline: none;
    }
    .btn-green {
        background-color: #28a745;
        border-color: #28a745;
        color: #ffffff;
        font-weight: 600;
        border-radius: 8px;
        padding: 10px 20px;
        font-size: 14px;
        transition: all 0.2s ease;
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.15);
    }
    .btn-green:hover {
        background-color: #218838;
        border-color: #1e7e34;
        box-shadow: 0 6px 16px rgba(40, 167, 69, 0.25);
        color: #ffffff;
    }
</style>

<div class="row pt-3">
    <!-- Left Column: Avatar & Summary -->
    <div class="col-lg-4 mb-4">
        <div class="profile-card">
            <div class="profile-body">
                <div class="avatar-wrapper">
                    <?php
                    $prof_raw = $admin['profile_image'] ?? '';
                    $prof_img = './src/images/user-avtar.png';
                    if (!empty($prof_raw)) {
                        if (strpos($prof_raw, 'assets/') === 0) {
                            $prof_img = '../' . $prof_raw;
                        } else {
                            $prof_img = $prof_raw;
                        }
                    }
                    ?>
                    <img src="<?= $prof_img ?>" alt="Avatar image">
                    <h4><?= htmlspecialchars($admin['name']) ?></h4>
                    <p><i class="fa-solid fa-user-shield"></i> System Administrator</p>
                </div>
                
                <hr style="border-top: 1px solid #f0f0f1;">
                
                <div style="font-size: 13px; line-height: 1.8;">
                    <div class="d-flex justify-content-between mb-2">
                        <strong class="text-muted">Username:</strong>
                        <span><?= htmlspecialchars($admin['username']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <strong class="text-muted">Email:</strong>
                        <span><?= htmlspecialchars($admin['email']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <strong class="text-muted">Mobile:</strong>
                        <span><?= htmlspecialchars($admin['mobile']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <strong class="text-muted">Registered:</strong>
                        <span><?= date('M d, Y', strtotime($admin['created_at'])) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Forms -->
    <div class="col-lg-8 mb-4">
        <!-- Tabbed Forms card -->
        <div class="profile-card mb-4">
            <div class="profile-header">
                <h3><i class="fa-solid fa-user-pen"></i> Update Personal Credentials</h3>
            </div>
            <div class="profile-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="name">Display Name</label>
                            <input type="text" name="name" id="name" class="form-control" value="<?= htmlspecialchars($admin['name']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="username">Admin Username</label>
                            <input type="text" name="username" id="username" class="form-control" value="<?= htmlspecialchars($admin['username']) ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="email">Email Address</label>
                            <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($admin['email']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="mobile">Mobile Number</label>
                            <input type="tel" name="mobile" id="mobile" class="form-control" value="<?= htmlspecialchars($admin['mobile']) ?>" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="profile_image">Change Avatar Photo</label>
                        <input type="file" name="profile_image" id="profile_image" class="form-control" accept="image/*">
                        <small class="text-muted d-block mt-1">Accepts JPG, PNG, GIF, or WEBP. Max size 2MB.</small>
                    </div>

                    <button type="submit" name="update_details" class="btn btn-green">
                        <i class="fa-solid fa-floppy-disk"></i> Save Profile Details
                    </button>
                </form>
            </div>
        </div>

        <div class="profile-card">
            <div class="profile-header">
                <h3><i class="fa-solid fa-key"></i> Security Password Configuration</h3>
            </div>
            <div class="profile-body">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="mb-3">
                        <label class="form-label" for="current_password">Current Password</label>
                        <input type="password" name="current_password" id="current_password" class="form-control" placeholder="••••••••••••" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="new_password">New Password</label>
                            <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Min. 8 characters" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="confirm_password">Verify New Password</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Match new password" required>
                        </div>
                    </div>

                    <button type="submit" name="change_password" class="btn btn-green">
                        <i class="fa-solid fa-lock"></i> Update Security Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Handle error SweetAlert if present
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($error_message)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Operation Interrupted',
                text: <?= json_encode($error_message) ?>,
                confirmButtonColor: '#28a745'
            });
        <?php endif; ?>
    });
</script>

<?php include './footer.php'; ?>