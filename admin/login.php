<?php
/**
 * MG Education & Social Development Organization
 * Secure Admin Login Portal
 * Dual-Panel High-Fidelity Design Matching User Mockup
 */

require_once dirname(__DIR__) . '/includes/config.php';

// Redirect if already authenticated
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index.php");
    exit();
}

$error_message = '';
$block_remaining = 0;
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

// Generate cryptographically strong anti-CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    $db = MG_GetDBConnection();

    // 1. Rate Limiting Protection (5 failed attempts per 15 minutes)
    $stmt = $db->prepare("
        SELECT COUNT(*) as failed_count, MIN(attempt_time) as first_attempt
        FROM login_attempts 
        WHERE ip_address = :ip AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute(['ip' => $ip_address]);
    $rate_limit = $stmt->fetch();
    $failed_attempts = $rate_limit['failed_count'] ?? 0;

    if ($failed_attempts >= 5) {
        $first_time = strtotime($rate_limit['first_attempt']);
        $time_diff = time() - $first_time;
        $block_remaining = max(0, 900 - $time_diff);
    }

    // 2. Process Login Request
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF Token
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Security mismatch: CSRF validation failed.");
        }

        // Check if currently rate-limited
        if ($block_remaining > 0) {
            $minutes = ceil($block_remaining / 60);
            throw new Exception("Too many failed attempts. You are temporarily locked out for {$minutes} more minutes.");
        }

        $username_email = trim($_POST['username_email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username_email) || empty($password)) {
            throw new Exception("Please enter both username/email and password.");
        }

        // Fetch administrator credentials (using unique named parameters to prevent SQLSTATE[HY093] on non-emulated queries)
        $query = "SELECT * FROM admins WHERE username = :username OR email = :email LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute([
            'username' => $username_email,
            'email' => $username_email
        ]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            // Success: Reset rate limiting logs for this IP
            $delete_stmt = $db->prepare("DELETE FROM login_attempts WHERE ip_address = :ip");
            $delete_stmt->execute(['ip' => $ip_address]);

            // Regenerate session ID to prevent Session Fixation
            session_regenerate_id(true);

            // Establish Session Parameters
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_mobile'] = $admin['mobile'];
            $_SESSION['admin_profile_image'] = $admin['profile_image'];
            
            // Session Hijacking Protections
            $_SESSION['auth_user_agent'] = md5($_SERVER['HTTP_USER_AGENT'] ?? '');
            $_SESSION['auth_ip_address'] = md5($ip_address);
            $_SESSION['last_activity'] = time();

            // Success Redirect
            $_SESSION['login_success_alert'] = true;
            header("Location: index.php");
            exit();
        } else {
            // Log failed attempt
            $log_stmt = $db->prepare("INSERT INTO login_attempts (ip_address) VALUES (:ip)");
            $log_stmt->execute(['ip' => $ip_address]);
            
            // Recalculate rate limits
            $stmt->execute(['ip' => $ip_address]);
            $rate_limit = $stmt->fetch();
            $failed_attempts = $rate_limit['failed_count'] ?? 0;
            if ($failed_attempts >= 5) {
                $block_remaining = 900;
            }

            throw new Exception("Invalid username/email or password.");
        }
    }
} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - MG Education</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Animate CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-green: #165c2b;
            --light-green-bg: #e2f0e6;
            --primary-hover: #0d3819;
            --text-dark: #2c3e50;
            --text-muted: #7f8c8d;
            --card-shadow: 0 20px 60px rgba(0, 0, 0, 0.08);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .login-container {
            display: flex;
            width: 960px;
            height: 520px;
            background-color: #ffffff;
            border-radius: 24px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            animation: fadeIn 0.8s ease;
        }

        .login-container:hover {
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.12);
        }

        /* LEFT PANEL - GREEN PANEL */
        .panel-left {
            flex: 1;
            background: linear-gradient(135deg, #165c2b 0%, #0d3819 100%);
            color: #ffffff;
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        /* Curved abstract background shapes (matches user mockup) */
        .panel-left::before {
            content: '';
            position: absolute;
            width: 380px;
            height: 380px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0) 70%);
            top: -100px;
            left: -150px;
            border-radius: 50%;
        }

        .panel-left::after {
            content: '';
            position: absolute;
            width: 450px;
            height: 450px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0) 70%);
            bottom: -150px;
            right: -100px;
            border-radius: 50%;
        }

        .panel-left-content {
            z-index: 2;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
        }

        /* Circular Logo Frame (matches mockup) */
        .logo-circle {
            width: 72px;
            height: 72px;
            background-color: #ffffff;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 12px auto; /* Explicitly centered */
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .logo-circle img {
            max-height: 44px;
            max-width: 54px;
            object-fit: contain;
        }

        .brand-name {
            font-family: 'Georgia', serif;
            font-style: italic;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-top: 8px;
            margin-bottom: 30px;
            text-align: center;
            color: #ffffff;
        }

        .welcome-back-heading {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 14px;
            letter-spacing: -0.5px;
        }

        .welcome-back-text {
            font-size: 14px;
            opacity: 0.85;
            line-height: 1.5;
            max-width: 280px;
            margin: 0 auto 30px auto;
        }

        /* Pill-shaped Outline Button (matches mockup) */
        .btn-outline-sign {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255, 255, 255, 0.7);
            background-color: transparent;
            color: #ffffff;
            padding: 12px 48px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-outline-sign:hover {
            background-color: #ffffff;
            color: var(--primary-green);
            border-color: #ffffff;
            transform: scale(1.03);
        }

        .creator-credits {
            font-size: 10px;
            opacity: 0.6;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* RIGHT PANEL - WHITE PANEL */
        .panel-right {
            flex: 1.1;
            background-color: #ffffff;
            padding: 50px 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .right-header {
            margin-bottom: 35px;
            text-align: center;
        }

        .right-header h2 {
            font-size: 34px;
            font-weight: 700;
            color: var(--primary-green);
            margin: 0 0 8px 0;
            letter-spacing: -0.5px;
        }

        .right-header p {
            font-size: 13px;
            color: var(--text-muted);
            margin: 0;
        }

        /* Form styling */
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        /* Fully Pill-shaped Pastel Input Wrapper (matches mockup) */
        .input-wrapper {
            position: relative;
            background-color: var(--light-green-bg);
            border-radius: 50px;
            height: 50px;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }

        .input-wrapper:focus-within {
            background-color: #ffffff;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(22, 92, 43, 0.1);
        }

        .input-wrapper i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-green);
            font-size: 15px;
            pointer-events: none;
            transition: color 0.3s ease;
        }

        .form-control {
            width: 100%;
            height: 100%;
            border: none;
            background: transparent;
            padding: 0 54px 0 52px; /* Increased right padding to prevent password text overlap with eye icon */
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            color: var(--text-dark);
            border-radius: 50px;
        }

        .form-control:focus {
            outline: none;
        }

        .password-toggle {
            position: absolute;
            right: 22px; /* Comfortable padding inside pill rounded boundary */
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-green);
            cursor: pointer;
            font-size: 16px;
            user-select: none;
            z-index: 10; /* Ensures toggle is strictly rendered on top of the input text boundary */
            display: flex;
            align-items: center;
            justify-content: center;
            height: 32px;
            width: 32px;
            border-radius: 50%;
            transition: all 0.2s ease;
        }

        .password-toggle:hover {
            background-color: rgba(22, 92, 43, 0.08); /* Premium circular hover feedback */
            color: #0d3819;
        }

        /* Pill-shaped Solid Button (matches mockup) */
        .btn-solid-log {
            width: 100%;
            height: 50px;
            background-color: var(--primary-green);
            color: #ffffff;
            border: none;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(22, 92, 43, 0.2);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 15px;
        }

        .btn-solid-log:hover {
            background-color: var(--primary-hover);
            box-shadow: 0 8px 24px rgba(22, 92, 43, 0.3);
            transform: scale(1.01);
        }

        .btn-solid-log:disabled {
            background-color: #bdc3c7;
            box-shadow: none;
            cursor: not-allowed;
        }

        .forgot-pass {
            display: block;
            text-align: center;
            margin-top: 14px;
            font-size: 12px;
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .forgot-pass:hover {
            color: var(--primary-green);
        }

        .lockout-warning {
            background-color: rgba(231, 76, 60, 0.05);
            border: 1px dashed rgba(231, 76, 60, 0.2);
            border-radius: 12px;
            padding: 12px;
            text-align: center;
            font-size: 12px;
            color: #e74c3c;
            margin-bottom: 20px;
            line-height: 1.4;
        }

        .lockout-warning i {
            margin-right: 4px;
        }

        /* MOBILE RESPONSIVE LAYOUT (MATCHES MOBILE MOCKUPS IN IMAGE) */
        @media (max-width: 768px) {
            body {
                background-color: #ffffff;
                padding: 0;
                display: block;
            }

            .login-container {
                flex-direction: column;
                width: 100%;
                height: 100vh;
                border-radius: 0;
                box-shadow: none;
            }

            .panel-left {
                flex: 0 0 38%;
                padding: 40px 20px 30px 20px;
                box-sizing: border-box;
                border-bottom-left-radius: 100px; /* Swiped curved sweep! */
            }

            .brand-name {
                margin-bottom: 10px;
            }

            .welcome-back-heading {
                font-size: 26px;
                margin-bottom: 8px;
            }

            .welcome-back-text, .btn-outline-sign, .creator-credits {
                display: none; /* simplified for clean mobile header layout */
            }

            .panel-right {
                flex: 1;
                padding: 40px 30px;
                justify-content: flex-start;
            }

            .right-header {
                margin-bottom: 25px;
            }

            .right-header h2 {
                font-size: 28px;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.96);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
    </style>
</head>
<body>

<div class="login-container">
    <!-- LEFT PANEL: Welcome Banner (Green) -->
    <div class="panel-left">
        <div class="panel-left-content">
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%;">
                <div class="logo-circle">
                    <img src="../assets/logo/logo.jpg" alt="MG Logo" onerror="this.src='https://via.placeholder.com/80?text=MG+EDU'">
                </div>
                <div class="brand-name">MG Education</div>
            </div>
            
            <div>
                <h1 class="welcome-back-heading">Welcome Back!</h1>
                <p class="welcome-back-text">To stay connected with us, please validate your administrator credentials.</p>
                <a href="../index.php" class="btn-outline-sign">Website Home</a>
            </div>
            
            <div class="creator-credits">
                MG Education & Social Development
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL: Authentication Panel (White) -->
    <div class="panel-right">
        <div class="right-header">
            <h2>welcome</h2>
            <p>Login to your account to continue</p>
        </div>

        <?php if ($block_remaining > 0): ?>
            <div class="lockout-warning animate__animated animate__headShake">
                <i class="fa-solid fa-user-lock"></i>
                <strong>Brute-Force Lockout Active</strong><br>
                Too many failed attempts. Try again in <span id="countdown"><?= ceil($block_remaining / 60) ?></span> minutes.
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <!-- Anti-CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <!-- Username/Email input wrapper (pill shape) -->
            <div class="form-group">
                <div class="input-wrapper">
                    <input type="text" name="username_email" id="username_email" class="form-control" placeholder="Username or Email" required <?= $block_remaining > 0 ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-user-shield"></i>
                </div>
            </div>

            <!-- Password input wrapper (pill shape) -->
            <div class="form-group">
                <div class="input-wrapper">
                    <input type="password" name="password" id="password" class="form-control" placeholder="Password" required <?= $block_remaining > 0 ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-key"></i>
                    <span class="password-toggle" id="togglePasswordBtn"><i class="fa-solid fa-eye" id="toggleIcon"></i></span>
                </div>
            </div>

            <!-- Solid pill Log In button -->
            <button type="submit" name="submit_login" id="loginBtn" class="btn-solid-log" <?= $block_remaining > 0 ? 'disabled' : '' ?>>
                LOG IN
            </button>
        </form>

        <a href="tel:+918059982049" class="forgot-pass">Forgot your password? Contact Director</a>
    </div>
</div>

<script>


    // Lockout countdown timer
    <?php if ($block_remaining > 0): ?>
    let secondsLeft = <?= $block_remaining ?>;
    const countdownEl = document.getElementById('countdown');

    const timer = setInterval(() => {
        secondsLeft--;
        if (secondsLeft <= 0) {
            clearInterval(timer);
            location.reload();
        } else {
            const mins = Math.ceil(secondsLeft / 60);
            countdownEl.textContent = mins;
        }
    }, 1000);
    <?php endif; ?>

    // Handle SweetAlert Alerts & Page Load Listeners
    document.addEventListener('DOMContentLoaded', function() {
        // Secure click toggle for password field
        const togglePasswordBtn = document.getElementById('togglePasswordBtn');
        if (togglePasswordBtn) {
            togglePasswordBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation(); // Prevents click bubbles or focus conflicts
                const passwordInput = document.getElementById('password');
                const toggleIcon = document.getElementById('toggleIcon');
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    toggleIcon.className = 'fa-solid fa-eye-slash';
                } else {
                    passwordInput.type = 'password';
                    toggleIcon.className = 'fa-solid fa-eye';
                }
            });
        }

        <?php if (!empty($error_message)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Authentication Denied',
                text: <?= json_encode($error_message) ?>,
                confirmButtonColor: '#165c2b',
                background: '#ffffff'
            });
        <?php endif; ?>

        // Parameterized errors
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('error') === 'sec') {
            Swal.fire({
                icon: 'warning',
                title: 'Session Revoked',
                text: 'Your session has been terminated due to potential configuration fluctuations. Please log in again.',
                confirmButtonColor: '#165c2b'
            });
        } else if (urlParams.get('error') === 'timeout') {
            Swal.fire({
                icon: 'info',
                title: 'Session Expired',
                text: 'You have been logged out due to inactivity to protect organization records.',
                confirmButtonColor: '#165c2b'
            });
        } else if (urlParams.get('logout') === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Logged Out',
                text: 'You have safely signed out of the dashboard.',
                confirmButtonColor: '#165c2b',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });

    // Loading indicator on form submit
    const form = document.getElementById('loginForm');
    form.addEventListener('submit', function() {
        const btn = document.getElementById('loginBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> AUTHENTICATING...';
    });
</script>

</body>
</html>
