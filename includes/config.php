<?php
/**
 * MG Education & Social Development Organization
 * Global Configuration & Service Integrations Loader
 * Powered by vlucas/phpdotenv
 */

// Start secure session if not already started
if (session_status() === PHP_SESSION_NONE) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    
    $cookieParams = [
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ];
    session_set_cookie_params($cookieParams);
    session_start();
}

// 1. Load Composer Autoloader
$autoloaderPath = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloaderPath)) {
    require_once $autoloaderPath;
} else {
    die("Composer autoloader not found. Please run 'composer install' in the project root directory.");
}

use Dotenv\Dotenv;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Razorpay\Api\Api as RazorpayApi;

// 2. Load Environment Variables from .env
try {
    $dotenv = Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
} catch (Exception $e) {
    error_log("Error loading .env file: " . $e->getMessage());
}

/**
 * Class MG_Config
 * Singleton for accessing configuration variables and services
 */
class MG_Config {
    private static $dbInstance = null;
    private static $razorpayClient = null;

    /**
     * Get DB PDO Connection Instance
     * @return PDO
     */
    public static function getDBConnection() {
        if (self::$dbInstance === null) {
            $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
            $port = $_ENV['DB_PORT'] ?? '3306';
            $dbname = $_ENV['DB_NAME'] ?? '';
            $username = $_ENV['DB_USER'] ?? '';
            $password = $_ENV['DB_PASS'] ?? '';

            try {
                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];
                self::$dbInstance = new PDO($dsn, $username, $password, $options);
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                throw new Exception("Database connection failed. Please contact the administrator.");
            }
        }
        return self::$dbInstance;
    }

    /**
     * Get Razorpay Api Client Instance
     * @return RazorpayApi
     */
    public static function getRazorpayClient() {
        if (self::$razorpayClient === null) {
            $keyId = $_ENV['RAZORPAY_KEY_ID'] ?? '';
            $keySecret = $_ENV['RAZORPAY_KEY_SECRET'] ?? '';
            if (empty($keyId) || empty($keySecret)) {
                throw new Exception("Razorpay API Key ID or Secret is not configured in .env file.");
            }
            self::$razorpayClient = new RazorpayApi($keyId, $keySecret);
        }
        return self::$razorpayClient;
    }

    /**
     * Send Transactional Email using PHPMailer and SMTP settings from .env
     * @param string $to Recipient Email
     * @param string $subject Email Subject
     * @param string $body Email Body (HTML or Plain Text)
     * @param array $options Optional settings: 'isHTML' (bool), 'fromEmail' (string), 'fromName' (string), 'attachments' (array)
     * @return bool True if mail sent successfully
     */
    public static function sendMail($to, $subject, $body, $options = []) {
        $mail = new PHPMailer(true);

        try {
            $smtpUser = $_ENV['SMTP_USER'] ?? '';
            $smtpPass = $_ENV['SMTP_PASS'] ?? '';
            
            if (empty($smtpUser) || empty($smtpPass)) {
                throw new Exception("SMTP credentials are not configured in your environment (.env) file.");
            }

            // Server settings
            $mail->isSMTP();
            $mail->Timeout    = 6; // Set a low connection timeout to prevent buffering hangs!
            $mail->Host       = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpUser;
            $mail->Password   = $smtpPass;
            
            // SMTP Security
            $secure = strtolower($_ENV['SMTP_SECURE'] ?? 'tls');
            if ($secure === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            $mail->Port = intval($_ENV['SMTP_PORT'] ?? 587);

            // Recipients
            $fromEmail = $options['fromEmail'] ?? ($_ENV['SMTP_FROM_EMAIL'] ?? 'no-reply@mgedu.in');
            $fromName  = $options['fromName'] ?? ($_ENV['SMTP_FROM_NAME'] ?? 'MG Education');
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);

            // Attachments
            if (!empty($options['attachments']) && is_array($options['attachments'])) {
                foreach ($options['attachments'] as $attachment) {
                    if (file_exists($attachment)) {
                        $mail->addAttachment($attachment);
                    }
                }
            }

            // Content
            $isHTML = $options['isHTML'] ?? true;
            $mail->isHTML($isHTML);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            // Plain text alternative if HTML
            if ($isHTML && isset($options['altBody'])) {
                $mail->AltBody = $options['altBody'];
            } elseif ($isHTML) {
                $mail->AltBody = strip_tags($body);
            }

            return $mail->send();
        } catch (Exception $e) {
            error_log("Mailer Error: " . $mail->ErrorInfo);
            throw new Exception("Email sending failed: " . $mail->ErrorInfo);
        }
    }

    /**
     * Get Google Places API Key
     * @return string
     */
    public static function getGooglePlacesKey() {
        return $_ENV['GOOGLE_PLACES_API_KEY'] ?? '';
    }

    /**
     * Get Firebase web config array
     * @return array
     */
    public static function getFirebaseConfig() {
        return [
            'apiKey'            => $_ENV['FIREBASE_API_KEY'] ?? '',
            'authDomain'        => $_ENV['FIREBASE_AUTH_DOMAIN'] ?? '',
            'projectId'         => $_ENV['FIREBASE_PROJECT_ID'] ?? '',
            'storageBucket'     => $_ENV['FIREBASE_STORAGE_BUCKET'] ?? '',
            'messagingSenderId' => $_ENV['FIREBASE_MESSAGING_SENDER_ID'] ?? '',
            'appId'             => $_ENV['FIREBASE_APP_ID'] ?? '',
            'measurementId'     => $_ENV['FIREBASE_MEASUREMENT_ID'] ?? ''
        ];
    }

    /**
     * Get Firebase configuration script tags for frontend JS initialization
     * @return string JS Script Block
     */
    public static function getFirebaseJsSnippet() {
        $config = json_encode(self::getFirebaseConfig(), JSON_PRETTY_PRINT);
        return "
<script src=\"https://www.gstatic.com/firebasejs/10.8.0/firebase-app-compat.js\"></script>
<script src=\"https://www.gstatic.com/firebasejs/10.8.0/firebase-auth-compat.js\"></script>
<script src=\"https://www.gstatic.com/firebasejs/10.8.0/firebase-firestore-compat.js\"></script>
<script>
    // Initialize Firebase using environment configuration
    const firebaseConfig = {$config};
    firebase.initializeApp(firebaseConfig);
    console.log('Firebase initialized successfully!');
</script>
        ";
    }
}

// ==========================================
// 3. REGISTER GLOBAL HELPER FUNCTIONS
// ==========================================

/**
 * Helper to retrieve PDO DB Connection
 * @return PDO
 */
function MG_GetDBConnection() {
    return MG_Config::getDBConnection();
}

/**
 * Helper to retrieve Razorpay API client wrapper
 * @return RazorpayApi
 */
function MG_GetRazorpayClient() {
    return MG_Config::getRazorpayClient();
}

/**
 * Helper to send email using PHPMailer
 * @param string $to
 * @param string $subject
 * @param string $body
 * @param array $options
 * @return bool
 */
function MG_SendMail($to, $subject, $body, $options = []) {
    return MG_Config::sendMail($to, $subject, $body, $options);
}

/**
 * Helper to retrieve Google Places API Key
 * @return string
 */
function MG_GetGooglePlacesKey() {
    return MG_Config::getGooglePlacesKey();
}

/**
 * Helper to retrieve Firebase configuration structure
 * @return array
 */
function MG_GetFirebaseConfig() {
    return MG_Config::getFirebaseConfig();
}

/**
 * Helper to retrieve Firebase JS Initialization Snippet
 * @return string
 */
function MG_GetFirebaseJsSnippet() {
    return MG_Config::getFirebaseJsSnippet();
}

/**
 * Indian Numbers to Words Converter tailored for Rupees and Paise
 * @param float $amount
 * @return string
 */
function MG_NumberToWords($amount) {
    $amount = round($amount, 2);
    $arr = explode('.', $amount);
    $rupees = intval($arr[0]);
    $paise = isset($arr[1]) ? intval($arr[1]) : 0;
    
    $rupeesStr = MG_ConvertNumberToWordsHelper($rupees);
    $paiseStr = MG_ConvertNumberToWordsHelper($paise);
    
    $output = "";
    if (empty($rupeesStr) || $rupeesStr == "Zero") {
        $output .= "Rupees Zero";
    } else {
        $output .= "Rupees " . $rupeesStr;
    }
    
    if ($paise > 0) {
        $output .= " and " . $paiseStr . " Paise";
    } else {
        $output .= " Only";
    }
    return $output;
}

/**
 * Helper to convert integer to Indian numbering words
 * @param int $number
 * @return string
 */
function MG_ConvertNumberToWordsHelper($number) {
    $no = round($number);
    $hundred = null;
    $digits_length = strlen($no);
    $i = 0;
    $str = array();
    $words = array(
        0 => '', 1 => 'One', 2 => 'Two',
        3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight',
        9 => 'Nine', 10 => 'Ten', 11 => 'Eleven',
        12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen',
        15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen',
        18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty',
        30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty',
        60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty',
        90 => 'Ninety'
    );
    $digits = array('', 'Hundred','Thousand','Lakh', 'Crore');
    while( $i < $digits_length ) {
        $divider = ($i == 2) ? 10 : 100;
        $number = floor($no % $divider);
        $no = floor($no / $divider);
        $i += ($divider == 10) ? 1 : 2;
        if ($number) {
            $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
            $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
            $str [] = ($number < 21) ? $words[$number].' '. $digits[$counter]. $plural.' '.$hundred:$words[floor($number / 10) * 10].' '.$words[$number % 10]. ' '.$digits[$counter].$plural.' '.$hundred;
        } else $str[] = null;
    }
    $Rupees = array_reverse($str);
    $result = implode('', $Rupees);
    return trim($result);
}

/**
 * Generates a premium A4 portrait PDF receipt using mPDF and saves it under assets/uploads/admissions/receipts/
 * @param int $admission_id
 * @return string|bool Absolute file path of generated PDF or false on error
 */
function MG_GenerateReceiptPDF($admission_id) {
    $db = MG_GetDBConnection();
    $stmt = $db->prepare("
        SELECT a.*, c.name as course_name, c.sales_price, fc.center_name, fc.full_address, fc.city, fc.state, fc.pincode
        FROM admissions a
        LEFT JOIN courses c ON a.course_id = c.id
        LEFT JOIN franchise_centers fc ON a.added_by = fc.center_id COLLATE utf8mb4_unicode_ci
        WHERE a.id = ?
    ");
    $stmt->execute([$admission_id]);
    $admission = $stmt->fetch();
    
    if (!$admission) {
        return false;
    }
    
    // Create folders
    $receiptsDir = dirname(__DIR__) . '/assets/uploads/admissions/receipts/';
    if (!file_exists($receiptsDir)) {
        mkdir($receiptsDir, 0755, true);
    }
    
    $filePath = $receiptsDir . 'receipt_' . $admission_id . '.pdf';
    
    // Calculate inclusive GST
    $totalAmount = 0;
    if ($admission['payment_status'] === 'paid') {
        $totalAmount = floatval($admission['sales_price']);
    } else {
        // for free or pending registration, it is 0
        $totalAmount = 0;
    }
    
    $subtotal = round($totalAmount / 1.18, 2);
    $totalGst = round($totalAmount - $subtotal, 2);
    $cgst = round($totalGst / 2, 2);
    $sgst = round($totalGst / 2, 2);
    
    // Adjust values to exactly sum up
    $subtotal = $totalAmount - $cgst - $sgst;
    
    // Resolve sign & stamp image to base64
    $signPath = dirname(__DIR__) . '/assets/sign-and-stamp/mg-sign.png';
    $signBase64 = '';
    if (file_exists($signPath)) {
        $signData = file_get_contents($signPath);
        $signBase64 = 'data:image/png;base64,' . base64_encode($signData);
    }
    
    // Resolve the logo image
    $logoPath = dirname(__DIR__) . '/assets/logo/logo.jpg';
    $logoBase64 = '';
    if (file_exists($logoPath)) {
        $logoData = file_get_contents($logoPath);
        $logoBase64 = 'data:image/jpeg;base64,' . base64_encode($logoData);
    }
    
    $receiptNo = 'MGEDU/REC/' . sprintf("%05d", $admission_id);
    
    // Franchise Center Affiliation Details
    $centerName = htmlspecialchars($admission['center_name'] ?? 'N/A');
    $centerAddress = htmlspecialchars($admission['full_address'] ?? '');
    $centerCity = htmlspecialchars($admission['city'] ?? '');
    $centerState = htmlspecialchars($admission['state'] ?? '');
    $centerPincode = htmlspecialchars($admission['pincode'] ?? '');
    
    $payMode = ($admission['payment_status'] === 'paid') ? 'Online (Razorpay)' : (($admission['payment_status'] === 'free') ? 'Scholarship / Free' : 'N/A');
    $billingDate = date('d-M-Y', strtotime($admission['created_at']));
    
    // Pre-assign safe HTML text elements to clean variables to prevent string concatenation breaks
    $studentName = htmlspecialchars($admission['student_name']);
    $fatherName = htmlspecialchars($admission['father_name']);
    $enrollmentNo = htmlspecialchars($admission['enrollment_number'] ? $admission['enrollment_number'] : 'Pending Confirmation');
    $mobile = htmlspecialchars($admission['mobile']);
    $email = htmlspecialchars($admission['email']);
    
    $courseName = htmlspecialchars($admission['course_name'] ? $admission['course_name'] : 'Academic Program Fee');
    $sessionName = htmlspecialchars($admission['session_name'] ? $admission['session_name'] : 'Academic Session');
    
    $subtotalStr = number_format($subtotal, 2);
    $cgstStr = number_format($cgst, 2);
    $sgstStr = number_format($sgst, 2);
    $totalAmountStr = number_format($totalAmount, 2);
    
    $paymentStatusUpper = strtoupper($admission['payment_status']);
    $paymentStatusColor = ($admission['payment_status'] === 'paid') ? '#16a34a' : (($admission['payment_status'] === 'free') ? '#2563eb' : '#d97706');

    // Conditionally build Transaction ID row
    $txnRow = '';
    if (!empty($admission['razorpay_payment_id']) && strtoupper($admission['razorpay_payment_id']) !== 'N/A') {
        $txnRow = '
            <tr>
                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; vertical-align: top;">Transaction ID:</td>
                <td style="font-size: 8.5pt; color: #1e293b; padding: 2px 0; vertical-align: top; word-break: break-all;">' . htmlspecialchars($admission['razorpay_payment_id']) . '</td>
            </tr>';
    }
    
    // HTML structure for premium single-page A4
    $html = '
    <html>
    <head>
    <style>
        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 11pt;
            line-height: 1.4;
        }
        .container {
            padding: 10px;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #0d47a1;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .logo-cell {
            width: 25%;
            vertical-align: middle;
        }
        .org-details-cell {
            width: 75%;
            text-align: right;
            vertical-align: middle;
        }
        .org-title {
            font-size: 16pt;
            font-weight: bold;
            color: #0d47a1;
            margin: 0 0 4px 0;
            text-transform: uppercase;
        }
        .org-subtitle {
            font-size: 9pt;
            color: #475569;
            margin: 0;
        }
        .receipt-title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            letter-spacing: 1px;
            color: #0d47a1;
            margin-bottom: 20px;
            text-transform: uppercase;
        }
        .info-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .info-box {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 12px;
            background-color: #f8fafc;
            height: 115px;
        }
        .info-box-title {
            font-size: 9pt;
            font-weight: bold;
            color: #0d47a1;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        .info-item {
            font-size: 8.5pt;
            margin-bottom: 3px;
        }
        .info-label {
            font-weight: bold;
            color: #475569;
            display: inline-block;
            width: 100px;
        }
        .item-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .item-table th {
            background-color: #0d47a1;
            color: #ffffff;
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            padding: 8px 10px;
            border: 1px solid #0d47a1;
            text-align: left;
        }
        .item-table td {
            font-size: 9pt;
            padding: 10px;
            border: 1px solid #e2e8f0;
        }
        .item-table tr.total-row td {
            font-weight: bold;
            background-color: #f1f5f9;
            border-top: 2px solid #0d47a1;
        }
        .footer-table {
            width: 100%;
            margin-top: 30px;
        }
        .terms-cell {
            width: 60%;
            font-size: 8pt;
            color: #64748b;
            line-height: 1.4;
        }
        .sig-cell {
            width: 40%;
            text-align: right;
            vertical-align: bottom;
        }
        .sig-box {
            display: inline-block;
            text-align: center;
            width: 200px;
        }
        .sig-line {
            border-top: 1.5px solid #0d47a1;
            padding-top: 4px;
            font-size: 9pt;
            font-weight: bold;
            color: #0d47a1;
        }
        .sig-subtitle {
            font-size: 7.5pt;
            color: #64748b;
        }
    </style>
    </head>
    <body>
    <div class="container">
        <!-- Header -->
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    ' . ($logoBase64 ? '<img src="' . $logoBase64 . '" style="height: 65px;" />' : '<h2 style="color:#0d47a1; margin:0;">MG EDU</h2>') . '
                </td>
                <td class="org-details-cell">
                    <div class="org-title">MG Education</div>
                    <div class="org-subtitle">& Social Development Organization</div>
                    <div style="font-size: 8pt; color: #475569; margin-top: 4px; line-height: 1.3;">
                        <strong>Head Office:</strong> 2nd Floor, MG Tower, Civil Lines, Prayagraj, UP - 211001<br>
                        <strong>GSTIN:</strong> 09AAGTM0622G1Z3 | <strong>Helpline:</strong> +91 9450001234, +91 9450005678<br>
                        <strong>Website:</strong> www.mgedu.in | <strong>Email:</strong> support@mgedu.in
                    </div>
                </td>
            </tr>
        </table>
        
        <!-- Document Title -->
        <div class="receipt-title">Official Tax Receipt & Fees Confirmation</div>
        
        <!-- Info boxes -->
        <table class="info-table">
            <tr>
                <td style="width: 50%; padding-right: 10px; vertical-align: top;">
                    <div class="info-box">
                        <div class="info-box-title">Student Profile Details (Billed To)</div>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; width: 32%; vertical-align: top;">Name:</td>
                                <td style="font-size: 8.5pt; color: #1e293b; padding: 2px 0; width: 68%; vertical-align: top;">' . $studentName . '</td>
                            </tr>
                            <tr>
                                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; vertical-align: top;">Father\'s Name:</td>
                                <td style="font-size: 8.5pt; color: #1e293b; padding: 2px 0; vertical-align: top;">' . $fatherName . '</td>
                            </tr>
                            <tr>
                                <td style="font-size: 8.5pt; color: #0d47a1; font-weight: bold; padding: 2px 0; vertical-align: top;">Enrollment No:</td>
                                <td style="font-size: 8.5pt; color: #0d47a1; font-weight: bold; padding: 2px 0; vertical-align: top;">' . $enrollmentNo . '</td>
                            </tr>
                            <tr>
                                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; vertical-align: top;">Mobile:</td>
                                <td style="font-size: 8.5pt; color: #1e293b; padding: 2px 0; vertical-align: top;">' . $mobile . '</td>
                            </tr>
                            <tr>
                                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; vertical-align: top;">Email:</td>
                                <td style="font-size: 8.5pt; color: #1e293b; padding: 2px 0; vertical-align: top; word-break: break-all;">' . $email . '</td>
                            </tr>
                        </table>
                    </div>
                </td>
                <td style="width: 50%; padding-left: 10px; vertical-align: top;">
                    <div class="info-box">
                        <div class="info-box-title">Transaction Particulars</div>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; width: 32%; vertical-align: top;">Receipt No:</td>
                                <td style="font-size: 8.5pt; color: #1e293b; padding: 2px 0; width: 68%; vertical-align: top;">' . $receiptNo . '</td>
                            </tr>
                            <tr>
                                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; vertical-align: top;">Billing Date:</td>
                                <td style="font-size: 8.5pt; color: #1e293b; padding: 2px 0; vertical-align: top;">' . $billingDate . '</td>
                            </tr>
                            <tr>
                                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; vertical-align: top;">Payment Mode:</td>
                                <td style="font-size: 8.5pt; color: #1e293b; padding: 2px 0; vertical-align: top;">' . $payMode . '</td>
                            </tr>
                            ' . $txnRow . '
                            <tr>
                                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; vertical-align: top;">Fees Status:</td>
                                <td style="font-size: 8.5pt; color: ' . $paymentStatusColor . '; font-weight: bold; padding: 2px 0; vertical-align: top; text-transform: uppercase;">' . $paymentStatusUpper . '</td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
        
        <!-- Affiliated Center details if enrolled through center -->
        ' . ($centerName !== 'N/A' ? '
        <table style="width: 100%; margin-bottom: 20px; border-collapse: collapse;">
            <tr>
                <td>
                    <div style="border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px; background-color: #f8fafc; font-size: 8.5pt;">
                        <div style="font-size: 9pt; font-weight: bold; color: #0d47a1; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; margin-bottom: 8px;">Affiliated Training Center</div>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="font-weight: bold; color: #475569; padding: 2px 0; width: 20%; vertical-align: top;">Center Name:</td>
                                <td style="color: #1e293b; padding: 2px 0; width: 80%; vertical-align: top; font-weight: bold;">' . $centerName . '</td>
                            </tr>
                            <tr>
                                <td style="font-weight: bold; color: #475569; padding: 2px 0; vertical-align: top;">Address:</td>
                                <td style="color: #1e293b; padding: 2px 0; vertical-align: top;">' . $centerAddress . ', ' . $centerCity . ' - ' . $centerPincode . ', ' . $centerState . '</td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
        ' : '') . '
        
        <!-- Table for items -->
        <table class="item-table">
            <thead>
                <tr>
                    <th style="width: 65%;">Description of Course / Program</th>
                    <th style="width: 15%; text-align: right;">Taxable Val (₹)</th>
                    <th style="width: 10%; text-align: right;">CGST 9%</th>
                    <th style="width: 10%; text-align: right;">SGST 9%</th>
                    <th style="width: 15%; text-align: right;">Total (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>' . $courseName . '</strong><br>
                        <span style="font-size: 8pt; color:#64748b;">Registration and Tuition fee for term ' . $sessionName . '</span>
                    </td>
                    <td style="text-align: right; vertical-align: middle;">' . $subtotalStr . '</td>
                    <td style="text-align: right; vertical-align: middle;">' . $cgstStr . '</td>
                    <td style="text-align: right; vertical-align: middle;">' . $sgstStr . '</td>
                    <td style="text-align: right; vertical-align: middle; font-weight: bold;">' . $totalAmountStr . '</td>
                </tr>
                <tr class="total-row">
                    <td colspan="1">GRAND TOTAL (INCLUSIVE OF GST)</td>
                    <td style="text-align: right;">₹' . $subtotalStr . '</td>
                    <td style="text-align: right;">₹' . $cgstStr . '</td>
                    <td style="text-align: right;">₹' . $sgstStr . '</td>
                    <td style="text-align: right; color:#0d47a1;">₹' . $totalAmountStr . '</td>
                </tr>
            </tbody>
        </table>
        
        <!-- Footer Terms & Signature -->
        <table class="footer-table">
            <tr>
                <td class="terms-cell">
                    <strong>Terms & Conditions:</strong><br>
                    1. Fee once paid is non-refundable and non-transferable under any circumstances.<br>
                    2. Admission is subject to validation of the academic documents and marksheet uploaded.<br>
                    3. Standard CGST and SGST calculated at 9% each on inclusive GST basis.<br>
                    4. For any support or helpline, contact info@mgedu.in or call helpline numbers.<br>
                    5. All disputes are subject to Prayagraj jurisdiction only.
                </td>
                <td class="sig-cell">
                    <div class="sig-box">
                        ' . ($signBase64 ? '<img src="' . $signBase64 . '" style="height: 52px; mix-blend-mode: multiply; margin-bottom: 5px;" />' : '<div style="height: 52px;"></div>') . '
                        <div class="sig-line">Authorized Signatory</div>
                        <div class="sig-subtitle">MG Education Org.</div>
                    </div>
                </td>
            </tr>
        </table>
        
        <!-- Divider & Stamp Mockup -->
        <div style="text-align: center; margin-top: 40px; font-size: 7.5pt; color: #94a3b8; border-top: 1px dashed #cbd5e1; padding-top: 8px;">
            This is an digitally certified payment receipt issued by MG Education portal. No physical signature is required.
        </div>
    </div>
    </body>
    </html>
    ';
    
    try {
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 12,
            'margin_bottom' => 12,
            'margin_header' => 8,
            'margin_footer' => 8
        ]);
        $mpdf->WriteHTML($html);
        $mpdf->Output($filePath, 'F');
        return $filePath;
    } catch (Exception $e) {
        error_log("Receipt generation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Generates a premium A4 portrait PDF receipt for internships using mPDF and saves it under assets/uploads/internships/receipts/
 * @param int $admission_id
 * @return string|bool Absolute file path of generated PDF or false on error
 */
function MG_GenerateInternshipReceiptPDF($admission_id) {
    $db = MG_GetDBConnection();
    $stmt = $db->prepare("
        SELECT a.*, i.name as internship_name, i.sales_price, fc.center_name, fc.full_address, fc.city, fc.state, fc.pincode
        FROM internship_admissions a
        LEFT JOIN internships i ON a.internship_id = i.id
        LEFT JOIN franchise_centers fc ON a.added_by = fc.center_id COLLATE utf8mb4_unicode_ci
        WHERE a.id = ?
    ");
    $stmt->execute([$admission_id]);
    $admission = $stmt->fetch();
    
    if (!$admission) {
        return false;
    }
    
    // Create folders
    $receiptsDir = dirname(__DIR__) . '/assets/uploads/internships/receipts/';
    if (!file_exists($receiptsDir)) {
        mkdir($receiptsDir, 0755, true);
    }
    
    $filePath = $receiptsDir . 'receipt_' . $admission_id . '.pdf';
    
    // Calculate inclusive GST
    $totalAmount = 0;
    if ($admission['payment_status'] === 'paid') {
        $totalAmount = floatval($admission['sales_price']);
    } else {
        $totalAmount = 0;
    }
    
    $subtotal = round($totalAmount / 1.18, 2);
    $totalGst = round($totalAmount - $subtotal, 2);
    $cgst = round($totalGst / 2, 2);
    $sgst = round($totalGst / 2, 2);
    
    // Adjust values to exactly sum up
    $subtotal = $totalAmount - $cgst - $sgst;
    
    // Resolve sign & stamp image to base64
    $signPath = dirname(__DIR__) . '/assets/sign-and-stamp/mg-sign.png';
    $signBase64 = '';
    if (file_exists($signPath)) {
        $signData = file_get_contents($signPath);
        $signBase64 = 'data:image/png;base64,' . base64_encode($signData);
    }
    
    // Resolve the logo image
    $logoPath = dirname(__DIR__) . '/assets/logo/logo.jpg';
    $logoBase64 = '';
    if (file_exists($logoPath)) {
        $logoData = file_get_contents($logoPath);
        $logoBase64 = 'data:image/jpeg;base64,' . base64_encode($logoData);
    }
    
    $receiptNo = 'MGINT/REC/' . sprintf("%05d", $admission_id);
    
    // Franchise Center Affiliation Details
    $centerName = htmlspecialchars($admission['center_name'] ?? 'N/A');
    $centerAddress = htmlspecialchars($admission['full_address'] ?? '');
    $centerCity = htmlspecialchars($admission['city'] ?? '');
    $centerState = htmlspecialchars($admission['state'] ?? '');
    $centerPincode = htmlspecialchars($admission['pincode'] ?? '');

    $payMode = ($admission['payment_status'] === 'paid') ? 'Online (Razorpay)' : (($admission['payment_status'] === 'free') ? 'Scholarship / Free' : 'N/A');
    if ($centerName !== 'N/A') {
        $payMode = 'Paid to Center';
    }
    $billingDate = date('d-M-Y', strtotime($admission['created_at']));
    
    $studentName = htmlspecialchars($admission['student_name']);
    $fatherName = htmlspecialchars($admission['father_name']);
    $enrollmentNo = htmlspecialchars($admission['enrollment_number'] ? $admission['enrollment_number'] : 'Pending Confirmation');
    $mobile = htmlspecialchars($admission['mobile']);
    $email = htmlspecialchars($admission['email']);
    
    $internshipName = htmlspecialchars($admission['internship_name'] ? $admission['internship_name'] : 'Professional Internship Program');
    
    $subtotalStr = number_format($subtotal, 2);
    $cgstStr = number_format($cgst, 2);
    $sgstStr = number_format($sgst, 2);
    $totalAmountStr = number_format($totalAmount, 2);
    
    $paymentStatusUpper = strtoupper($admission['payment_status']);
    $paymentStatusColor = ($admission['payment_status'] === 'paid') ? '#16a34a' : (($admission['payment_status'] === 'free') ? '#2563eb' : '#d97706');

    // Conditionally build Transaction ID row
    $txnRow = '';
    if (!empty($admission['razorpay_payment_id']) && strtoupper($admission['razorpay_payment_id']) !== 'N/A') {
        $txnRow = '
            <tr>
                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; vertical-align: top;">Transaction ID:</td>
                <td style="font-size: 8.5pt; color: #1e293b; padding: 2px 0; vertical-align: top; word-break: break-all;">' . htmlspecialchars($admission['razorpay_payment_id']) . '</td>
            </tr>';
    }
    
    $html = '
    <html>
    <head>
    <style>
        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 11pt;
            line-height: 1.4;
        }
        .container {
            padding: 10px;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #0d47a1;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .logo-cell {
            width: 25%;
            vertical-align: middle;
        }
        .org-details-cell {
            width: 75%;
            text-align: right;
            vertical-align: middle;
        }
        .org-title {
            font-size: 16pt;
            font-weight: bold;
            color: #0d47a1;
            margin: 0 0 4px 0;
            text-transform: uppercase;
        }
        .org-subtitle {
            font-size: 9pt;
            color: #475569;
            margin: 0;
        }
        .receipt-title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            letter-spacing: 1px;
            color: #0d47a1;
            margin-bottom: 20px;
            text-transform: uppercase;
        }
        .info-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .info-box {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 12px;
            background-color: #f8fafc;
            height: 115px;
        }
        .info-box-title {
            font-size: 9pt;
            font-weight: bold;
            color: #0d47a1;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        .info-item {
            font-size: 8.5pt;
            margin-bottom: 3px;
        }
        .info-label {
            font-weight: bold;
            color: #475569;
            display: inline-block;
            width: 100px;
        }
        .item-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .item-table th {
            background-color: #0d47a1;
            color: #ffffff;
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            padding: 8px 10px;
            border: 1px solid #0d47a1;
            text-align: left;
        }
        .item-table td {
            font-size: 9pt;
            padding: 10px;
            border: 1px solid #e2e8f0;
        }
        .item-table tr.total-row td {
            font-weight: bold;
            background-color: #f1f5f9;
            border-top: 2px solid #0d47a1;
        }
        .footer-table {
            width: 100%;
            margin-top: 30px;
        }
        .terms-cell {
            width: 60%;
            font-size: 8pt;
            color: #64748b;
            line-height: 1.4;
        }
        .sig-cell {
            width: 40%;
            text-align: right;
            vertical-align: bottom;
        }
        .sig-box {
            display: inline-block;
            text-align: center;
            width: 200px;
        }
        .sig-line {
            border-top: 1.5px solid #0d47a1;
            padding-top: 4px;
            font-size: 9pt;
            font-weight: bold;
            color: #0d47a1;
        }
        .sig-subtitle {
            font-size: 7.5pt;
            color: #64748b;
        }
    </style>
    </head>
    <body>
    <div class="container">
        <!-- Header -->
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    ' . ($logoBase64 ? '<img src="' . $logoBase64 . '" style="height: 65px;" />' : '<h2 style="color:#0d47a1; margin:0;">MG EDU</h2>') . '
                </td>
                <td class="org-details-cell">
                    <div class="org-title">MG Education</div>
                    <div class="org-subtitle">& Social Development Organization</div>
                    <div style="font-size: 8pt; color: #475569; margin-top: 4px; line-height: 1.3;">
                        <strong>Head Office:</strong> 2nd Floor, MG Tower, Civil Lines, Prayagraj, UP - 211001<br>
                        <strong>GSTIN:</strong> 09AAGTM0622G1Z3 | <strong>Helpline:</strong> +91 9450001234, +91 9450005678<br>
                        <strong>Website:</strong> www.mgedu.in | <strong>Email:</strong> support@mgedu.in
                    </div>
                </td>
            </tr>
        </table>
        
        <!-- Document Title -->
        <div class="receipt-title">Official Internship Tax Receipt & Confirmation</div>
        
        <!-- Info boxes -->
        <table class="info-table">
            <tr>
                <td style="width: 50%; padding-right: 10px; vertical-align: top;">
                    <div class="info-box">
                        <div class="info-box-title">Student Profile Details (Billed To)</div>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; width: 32%; vertical-align: top;">Name:</td>
                                <td style="font-size: 8.5pt; color: #1e293b; padding: 2px 0; width: 68%; vertical-align: top;">' . $studentName . '</td>
                            </tr>
                            <tr>
                                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; vertical-align: top;">Father\'s Name:</td>
                                <td style="font-size: 8.5pt; color: #1e293b; padding: 2px 0; vertical-align: top;">' . $fatherName . '</td>
                            </tr>
                            <tr>
                                <td style="font-size: 8.5pt; color: #0d47a1; font-weight: bold; padding: 2px 0; vertical-align: top;">Enrollment No:</td>
                                <td style="font-size: 8.5pt; color: #0d47a1; font-weight: bold; padding: 2px 0; vertical-align: top;">' . $enrollmentNo . '</td>
                            </tr>
                            <tr>
                                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; vertical-align: top;">Mobile:</td>
                                <td style="font-size: 8.5pt; color: #1e293b; padding: 2px 0; vertical-align: top;">' . $mobile . '</td>
                            </tr>
                            <tr>
                                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; vertical-align: top;">Email:</td>
                                <td style="font-size: 8.5pt; color: #1e293b; padding: 2px 0; vertical-align: top; word-break: break-all;">' . $email . '</td>
                            </tr>
                        </table>
                    </div>
                </td>
                <td style="width: 50%; padding-left: 10px; vertical-align: top;">
                    <div class="info-box">
                        <div class="info-box-title">Transaction Particulars</div>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; width: 32%; vertical-align: top;">Receipt No:</td>
                                <td style="font-size: 8.5pt; color: #1e293b; padding: 2px 0; width: 68%; vertical-align: top;">' . $receiptNo . '</td>
                            </tr>
                            <tr>
                                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; vertical-align: top;">Billing Date:</td>
                                <td style="font-size: 8.5pt; color: #1e293b; padding: 2px 0; vertical-align: top;">' . $billingDate . '</td>
                            </tr>
                            <tr>
                                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; vertical-align: top;">Payment Mode:</td>
                                <td style="font-size: 8.5pt; color: #1e293b; padding: 2px 0; vertical-align: top;">' . $payMode . '</td>
                            </tr>
                            ' . $txnRow . '
                            <tr>
                                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; vertical-align: top;">Fees Status:</td>
                                <td style="font-size: 8.5pt; color: ' . $paymentStatusColor . '; font-weight: bold; padding: 2px 0; vertical-align: top; text-transform: uppercase;">' . $paymentStatusUpper . '</td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
        
        <!-- Affiliated Center details if enrolled through center -->
        ' . ($centerName !== 'N/A' ? '
        <table style="width: 100%; margin-bottom: 20px; border-collapse: collapse;">
            <tr>
                <td>
                    <div style="border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px; background-color: #f8fafc; font-size: 8.5pt;">
                        <div style="font-size: 9pt; font-weight: bold; color: #0d47a1; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; margin-bottom: 8px;">Affiliated Training Center</div>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="font-weight: bold; color: #475569; padding: 2px 0; width: 20%; vertical-align: top;">Center Name:</td>
                                <td style="color: #1e293b; padding: 2px 0; width: 80%; vertical-align: top; font-weight: bold;">' . $centerName . '</td>
                            </tr>
                            <tr>
                                <td style="font-weight: bold; color: #475569; padding: 2px 0; vertical-align: top;">Address:</td>
                                <td style="color: #1e293b; padding: 2px 0; vertical-align: top;">' . $centerAddress . ', ' . $centerCity . ' - ' . $centerPincode . ', ' . $centerState . '</td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
        ' : '') . '
        
        <!-- Table for items -->
        <table class="item-table">
            <thead>
                <tr>
                    <th style="width: 65%;">Description of Internship / Training Program</th>
                    <th style="width: 15%; text-align: right;">Taxable Val (₹)</th>
                    <th style="width: 10%; text-align: right;">CGST 9%</th>
                    <th style="width: 10%; text-align: right;">SGST 9%</th>
                    <th style="width: 15%; text-align: right;">Total (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>' . $internshipName . '</strong><br>
                        <span style="font-size: 8pt; color:#64748b;">Professional Practical Internship & Training Fee</span>
                    </td>
                    <td style="text-align: right; vertical-align: middle;">' . $subtotalStr . '</td>
                    <td style="text-align: right; vertical-align: middle;">' . $cgstStr . '</td>
                    <td style="text-align: right; vertical-align: middle;">' . $sgstStr . '</td>
                    <td style="text-align: right; vertical-align: middle; font-weight: bold;">' . $totalAmountStr . '</td>
                </tr>
                <tr class="total-row">
                    <td colspan="1">GRAND TOTAL (INCLUSIVE OF GST)</td>
                    <td style="text-align: right;">₹' . $subtotalStr . '</td>
                    <td style="text-align: right;">₹' . $cgstStr . '</td>
                    <td style="text-align: right;">₹' . $sgstStr . '</td>
                    <td style="text-align: right; color:#0d47a1;">₹' . $totalAmountStr . '</td>
                </tr>
            </tbody>
        </table>
        
        <!-- Footer Terms & Signature -->
        <table class="footer-table">
            <tr>
                <td class="terms-cell">
                    <strong>Terms & Conditions:</strong><br>
                    1. Fee once paid is non-refundable and non-transferable under any circumstances.<br>
                    2. Admission is subject to validation of the academic documents and marksheet uploaded.<br>
                    3. Standard CGST and SGST calculated at 9% each on inclusive GST basis.<br>
                    4. For any support or helpline, contact info@mgedu.in or call helpline numbers.<br>
                    5. All disputes are subject to Prayagraj jurisdiction only.
                </td>
                <td class="sig-cell">
                    <div class="sig-box">
                        ' . ($signBase64 ? '<img src="' . $signBase64 . '" style="height: 52px; mix-blend-mode: multiply; margin-bottom: 5px;" />' : '<div style="height: 52px;"></div>') . '
                        <div class="sig-line">Authorized Signatory</div>
                        <div class="sig-subtitle">MG Education Org.</div>
                    </div>
                </td>
            </tr>
        </table>
        
        <!-- Divider & Stamp Mockup -->
        <div style="text-align: center; margin-top: 40px; font-size: 7.5pt; color: #94a3b8; border-top: 1px dashed #cbd5e1; padding-top: 8px;">
            This is an digitally certified payment receipt issued by MG Education portal. No physical signature is required.
        </div>
    </div>
    </body>
    </html>
    ';
    
    try {
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 12,
            'margin_bottom' => 12,
            'margin_header' => 8,
            'margin_footer' => 8
        ]);
        $mpdf->WriteHTML($html);
        $mpdf->Output($filePath, 'F');
        return $filePath;
    } catch (Exception $e) {
        error_log("Internship Receipt generation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Generates a premium A4 portrait PDF tax receipt for franchise wallet top-ups using mPDF and saves it under assets/uploads/franchises/receipts/
 * @param int $transaction_id
 * @return string|bool Absolute file path of generated PDF or false on error
 */
function MG_GenerateFranchiseReceiptPDF($transaction_id) {
    $db = MG_GetDBConnection();
    $stmt = $db->prepare("
        SELECT t.*, c.center_name, c.email, c.mobile, c.full_address, c.city, c.state, c.pincode
        FROM franchise_transactions t
        LEFT JOIN franchise_centers c ON t.center_id = c.center_id COLLATE utf8mb4_unicode_ci
        WHERE t.id = ?
    ");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        return false;
    }
    
    // Create folders
    $receiptsDir = dirname(__DIR__) . '/assets/uploads/franchises/receipts/';
    if (!file_exists($receiptsDir)) {
        mkdir($receiptsDir, 0755, true);
    }
    
    $filePath = $receiptsDir . 'receipt_' . $transaction_id . '.pdf';
    
    // Calculate inclusive GST for the actual paid amount (Royalty pay)
    $totalAmount = 0;
    if ($transaction['payment_status'] === 'paid') {
        $totalAmount = floatval($transaction['paid_amount']);
    } else {
        $totalAmount = 0;
    }
    
    $subtotal = round($totalAmount / 1.18, 2);
    $totalGst = round($totalAmount - $subtotal, 2);
    $cgst = round($totalGst / 2, 2);
    $sgst = round($totalGst / 2, 2);
    
    // Adjust values to exactly sum up
    $subtotal = $totalAmount - $cgst - $sgst;
    
    $amountInWords = MG_NumberToWords($totalAmount);
    
    // Resolve the logo image
    $logoPath = dirname(__DIR__) . '/assets/logo/logo.jpg';
    $logoBase64 = '';
    if (file_exists($logoPath)) {
        $logoData = file_get_contents($logoPath);
        $logoBase64 = 'data:image/jpeg;base64,' . base64_encode($logoData);
    }
    
    $receiptNo = 'MGEDU/FR-REC/' . sprintf("%05d", $transaction_id);
    $txnId = !empty($transaction['razorpay_payment_id']) ? $transaction['razorpay_payment_id'] : 'N/A';
    $payMode = ($transaction['payment_status'] === 'paid') ? 'Online (Razorpay)' : 'N/A';
    $billingDate = date('d-M-Y', strtotime($transaction['created_at']));
    
    $centerName = htmlspecialchars($transaction['center_name']);
    $centerId = htmlspecialchars($transaction['center_id']);
    $mobile = htmlspecialchars($transaction['mobile']);
    $email = htmlspecialchars($transaction['email']);
    $address = htmlspecialchars($transaction['full_address'] . ', ' . $transaction['city'] . ' - ' . $transaction['pincode']);
    
    $topUpAmountStr = number_format(floatval($transaction['amount']), 2);
    $royaltyPctStr = number_format(floatval($transaction['royalty_percentage']), 2);
    
    $subtotalStr = number_format($subtotal, 2);
    $cgstStr = number_format($cgst, 2);
    $sgstStr = number_format($sgst, 2);
    $totalAmountStr = number_format($totalAmount, 2);
    
    $paymentStatusUpper = strtoupper($transaction['payment_status']);
    $paymentStatusColor = ($transaction['payment_status'] === 'paid') ? '#16a34a' : '#d97706';
    
    // HTML structure for premium single-page A4
    $html = '
    <html>
    <head>
    <style>
        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 11pt;
            line-height: 1.4;
        }
        .container {
            padding: 10px;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #10b981;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .logo-cell {
            width: 25%;
            vertical-align: middle;
        }
        .org-details-cell {
            width: 75%;
            text-align: right;
            vertical-align: middle;
        }
        .org-title {
            font-size: 16pt;
            font-weight: bold;
            color: #10b981;
            margin: 0 0 4px 0;
            text-transform: uppercase;
        }
        .org-subtitle {
            font-size: 9pt;
            color: #475569;
            margin: 0;
        }
        .receipt-title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            letter-spacing: 1px;
            color: #10b981;
            margin-bottom: 20px;
            text-transform: uppercase;
        }
        .info-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .info-box {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 12px;
            background-color: #f8fafc;
            height: 140px;
        }
        .info-box-title {
            font-size: 9pt;
            font-weight: bold;
            color: #10b981;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        .info-item {
            font-size: 8.5pt;
            margin-bottom: 3px;
        }
        .info-label {
            font-weight: bold;
            color: #475569;
            display: inline-block;
            width: 100px;
        }
        .item-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .item-table th {
            background-color: #10b981;
            color: #ffffff;
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            padding: 8px 10px;
            border: 1px solid #10b981;
            text-align: left;
        }
        .item-table td {
            font-size: 9pt;
            padding: 10px;
            border: 1px solid #e2e8f0;
        }
        .item-table tr.total-row td {
            font-weight: bold;
            background-color: #f1f5f9;
            border-top: 2px solid #10b981;
        }
        .words-box {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 10px;
            background-color: #f8fafc;
            font-size: 9pt;
            margin-bottom: 25px;
        }
        .words-title {
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
            font-size: 8pt;
            margin-bottom: 3px;
        }
        .words-value {
            font-weight: bold;
            color: #10b981;
        }
        .footer-table {
            width: 100%;
            margin-top: 30px;
        }
        .terms-cell {
            width: 60%;
            font-size: 8pt;
            color: #64748b;
            line-height: 1.4;
        }
        .sig-cell {
            width: 40%;
            text-align: right;
            vertical-align: bottom;
        }
        .sig-box {
            display: inline-block;
            text-align: center;
            width: 200px;
        }
        .sig-line {
            border-top: 1px solid #475569;
            margin-top: 50px;
            padding-top: 4px;
            font-size: 9pt;
            font-weight: bold;
            color: #1e293b;
        }
        .sig-subtitle {
            font-size: 7.5pt;
            color: #64748b;
        }
    </style>
    </head>
    <body>
    <div class="container">
        <!-- Header -->
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    ' . ($logoBase64 ? '<img src="' . $logoBase64 . '" style="height: 65px;" />' : '<h2 style="color:#10b981; margin:0;">MG EDU</h2>') . '
                </td>
                <td class="org-details-cell">
                    <div class="org-title">MG Education</div>
                    <div class="org-subtitle">& Social Development Organization</div>
                    <div style="font-size: 8pt; color: #475569; margin-top: 4px; line-height: 1.3;">
                        <strong>Head Office:</strong> 2nd Floor, MG Tower, Civil Lines, Prayagraj, UP - 211001<br>
                        <strong>GSTIN:</strong> 09AAGTM0622G1Z3 | <strong>Helpline:</strong> +91 9450001234, +91 9450005678<br>
                        <strong>Website:</strong> www.mgedu.in | <strong>Email:</strong> support@mgedu.in
                    </div>
                </td>
            </tr>
        </table>
        
        <!-- Document Title -->
        <div class="receipt-title">Accredited Center Wallet Top-Up Tax Invoice</div>
        
        <!-- Info boxes -->
        <table class="info-table">
            <tr>
                <td style="width: 50%; padding-right: 10px; vertical-align: top;">
                    <div class="info-box">
                        <div class="info-box-title">Franchise Center Details (Billed To)</div>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; width: 32%; vertical-align: top;">Center Name:</td>
                                <td style="font-size: 8.5pt; color: #1e293b; padding: 2px 0; width: 68%; vertical-align: top;">' . $centerName . '</td>
                            </tr>
                            <tr>
                                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; vertical-align: top;">Center ID:</td>
                                <td style="font-size: 8.5pt; color: #10b981; font-weight: bold; padding: 2px 0; vertical-align: top;">' . $centerId . '</td>
                            </tr>
                            <tr>
                                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; vertical-align: top;">Mobile:</td>
                                <td style="font-size: 8.5pt; color: #1e293b; padding: 2px 0; vertical-align: top;">' . $mobile . '</td>
                            </tr>
                            <tr>
                                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; vertical-align: top;">Email:</td>
                                <td style="font-size: 8.5pt; color: #1e293b; padding: 2px 0; vertical-align: top; word-break: break-all;">' . $email . '</td>
                            </tr>
                            <tr>
                                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; vertical-align: top;">Address:</td>
                                <td style="font-size: 8.5pt; color: #1e293b; padding: 2px 0; vertical-align: top; word-break: break-all;">' . $address . '</td>
                            </tr>
                        </table>
                    </div>
                </td>
                <td style="width: 50%; padding-left: 10px; vertical-align: top;">
                    <div class="info-box">
                        <div class="info-box-title">Top-Up Particulars</div>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; width: 32%; vertical-align: top;">Receipt No:</td>
                                <td style="font-size: 8.5pt; color: #1e293b; padding: 2px 0; width: 68%; vertical-align: top;">' . $receiptNo . '</td>
                            </tr>
                            <tr>
                                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; vertical-align: top;">Billing Date:</td>
                                <td style="font-size: 8.5pt; color: #1e293b; padding: 2px 0; vertical-align: top;">' . $billingDate . '</td>
                            </tr>
                            <tr>
                                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; vertical-align: top;">Payment Mode:</td>
                                <td style="font-size: 8.5pt; color: #1e293b; padding: 2px 0; vertical-align: top;">' . $payMode . '</td>
                            </tr>
                            <tr>
                                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; vertical-align: top;">Transaction ID:</td>
                                <td style="font-size: 8.5pt; color: #1e293b; padding: 2px 0; vertical-align: top; word-break: break-all;">' . $txnId . '</td>
                            </tr>
                            <tr>
                                <td style="font-size: 8.5pt; font-weight: bold; color: #475569; padding: 2px 0; vertical-align: top;">Payment Status:</td>
                                <td style="font-size: 8.5pt; color: ' . $paymentStatusColor . '; font-weight: bold; padding: 2px 0; vertical-align: top; text-transform: uppercase;">' . $paymentStatusUpper . '</td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
        
        <!-- Table for items -->
        <table class="item-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Description of Service Ledger</th>
                    <th style="width: 15%; text-align: center;">Royalty Rate</th>
                    <th style="width: 15%; text-align: right;">Top-Up Value (₹)</th>
                    <th style="width: 10%; text-align: right;">CGST 9%</th>
                    <th style="width: 10%; text-align: right;">SGST 9%</th>
                    <th style="width: 15%; text-align: right;">Paid Amt (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>Franchise Wallet Balance Recharge</strong><br>
                        <span style="font-size: 8pt; color:#64748b;">Credited Top-Up funds to accredited Center Wallet. Billed value represents dynamic royalty rate percentage.</span>
                    </td>
                    <td style="text-align: center; vertical-align: middle;">' . $royaltyPctStr . '%</td>
                    <td style="text-align: right; vertical-align: middle;">' . $topUpAmountStr . '</td>
                    <td style="text-align: right; vertical-align: middle;">' . $cgstStr . '</td>
                    <td style="text-align: right; vertical-align: middle;">' . $sgstStr . '</td>
                    <td style="text-align: right; vertical-align: middle; font-weight: bold; color:#10b981;">' . $totalAmountStr . '</td>
                </tr>
                <tr class="total-row">
                    <td colspan="2">GRAND TOTAL (INCLUSIVE OF GST)</td>
                    <td style="text-align: right;">₹' . $topUpAmountStr . '</td>
                    <td style="text-align: right;">₹' . $cgstStr . '</td>
                    <td style="text-align: right;">₹' . $sgstStr . '</td>
                    <td style="text-align: right; color:#10b981;">₹' . $totalAmountStr . '</td>
                </tr>
            </tbody>
        </table>
        
        <!-- Amount in Words -->
        <div class="words-box">
            <div class="words-title">Amount In Words (Paid Royalty):</div>
            <div class="words-value">' . $amountInWords . '</div>
        </div>
        
        <!-- Footer Terms & Signature -->
        <table class="footer-table">
            <tr>
                <td class="terms-cell">
                    <strong>Terms & Conditions:</strong><br>
                    1. Billed value represents ' . $royaltyPctStr . '% royalty rate calculation on entered ₹' . $topUpAmountStr . ' top-up request.<br>
                    2. Credited wallet amount (₹' . $topUpAmountStr . ') can only be spent inside official MG Education center operations.<br>
                    3. Fee once recharged is non-refundable and non-transferable under any circumstances.<br>
                    4. Standard CGST and SGST calculated at 9% each on inclusive GST basis for actual paid amount.<br>
                    5. All disputes are subject to Prayagraj jurisdiction only.
                </td>
                <td class="sig-cell">
                    <div class="sig-box">
                        <div style="font-size: 7.5pt; color: #64748b; font-style: italic; margin-bottom: 20px;">[Digitally Certified Center Receipt]</div>
                        <div class="sig-line">Authorized Signatory</div>
                        <div class="sig-subtitle">MG Education Org.</div>
                    </div>
                </td>
            </tr>
        </table>
        
        <!-- Divider & Stamp Mockup -->
        <div style="text-align: center; margin-top: 40px; font-size: 7.5pt; color: #94a3b8; border-top: 1px dashed #cbd5e1; padding-top: 8px;">
            This is an digitally certified tax receipt issued by MG Education portal. No physical signature is required.
        </div>
    </div>
    </body>
    </html>
    ';
    
    try {
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 12,
            'margin_bottom' => 12,
            'margin_header' => 8,
            'margin_footer' => 8
        ]);
        $mpdf->WriteHTML($html);
        $mpdf->Output($filePath, 'F');
        return $filePath;
    } catch (Exception $e) {
        error_log("Franchise Topup Receipt generation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Generates a premium A4 portrait Affiliation Certificate for a franchise center using mPDF
 * and saves it under assets/uploads/franchise/certificates/
 * @param string $center_id
 * @return string|bool Absolute file path of generated PDF or false on error
 */
function MG_GenerateAffiliationCertificatePDF($center_id) {
    $db = MG_GetDBConnection();
    $stmt = $db->prepare("SELECT * FROM franchise_centers WHERE center_id = ? LIMIT 1");
    $stmt->execute([$center_id]);
    $center = $stmt->fetch();
    
    if (!$center) {
        return false;
    }
    
    // Create folders
    $certDir = dirname(__DIR__) . '/assets/uploads/franchise/certificates/';
    if (!file_exists($certDir)) {
        mkdir($certDir, 0755, true);
    }
    
    $filePath = $certDir . 'cert_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $center_id) . '.pdf';
    
    // Resolve logo image to base64
    $logoPath = dirname(__DIR__) . '/assets/logo/logo.jpg';
    $logoBase64 = '';
    if (file_exists($logoPath)) {
        $logoData = file_get_contents($logoPath);
        $logoBase64 = 'data:image/jpeg;base64,' . base64_encode($logoData);
    }
    
    // Resolve banner image to base64
    $bannerPath = dirname(__DIR__) . '/assets/smtp-images/skill-india.png';
    $bannerBase64 = '';
    if (file_exists($bannerPath)) {
        $bannerData = file_get_contents($bannerPath);
        $bannerBase64 = 'data:image/png;base64,' . base64_encode($bannerData);
    }
    
    // Resolve sign & stamp image to base64
    $signPath = dirname(__DIR__) . '/assets/sign-and-stamp/mg-sign.png';
    $signBase64 = '';
    if (file_exists($signPath)) {
        $signData = file_get_contents($signPath);
        $signBase64 = 'data:image/png;base64,' . base64_encode($signData);
    }
    
    $centerName = htmlspecialchars(strtoupper($center['center_name']));
    $fullAddress = htmlspecialchars($center['full_address']);
    $city = htmlspecialchars($center['city']);
    $state = htmlspecialchars($center['state']);
    $pincode = htmlspecialchars($center['pincode']);
    
    $certNo = 'MGEDU/AFF/' . date('Y') . '/' . sprintf("%04d", $center['id']);
    $issueDate = date('d-M-Y', strtotime($center['created_at']));
    
    $html = '
    <html>
    <head>
    <style>
        body {
            font-family: "Georgia", "Times New Roman", Times, serif;
            color: #1e293b;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }
        .cert-border {
            border: 15px double #1e3a8a;
            padding: 5px 20px 15px 20px;
            background-color: #fafbfc;
            box-sizing: border-box;
            position: relative;
        }
        .cert-inner-border {
            border: 2px solid #b45309;
            padding: 0px;
            box-sizing: border-box;
        }
        .cert-content {
            padding: 10px 15px 15px 15px;
        }
        .cert-header {
            text-align: center;
            margin-top: 0px;
            margin-bottom: 10px;
        }
        .cert-org-logo {
            height: 55px;
            margin-bottom: 8px;
        }
        .cert-org-title {
            font-size: 18pt;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            margin-bottom: 2px;
            letter-spacing: 1px;
        }
        .cert-org-subtitle {
            font-size: 8.5pt;
            font-weight: bold;
            color: #b45309;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 3px;
        }
        .cert-org-reg {
            font-size: 8pt;
            font-style: italic;
            color: #475569;
        }
        .cert-title {
            font-size: 21pt;
            font-weight: bold;
            color: #1e3a8a;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 15px;
        }
        .cert-intro {
            font-size: 11pt;
            font-style: italic;
            text-align: center;
            color: #475569;
            margin-bottom: 10px;
        }
        .cert-center-name {
            font-size: 15pt;
            font-weight: bold;
            color: #b45309;
            text-align: center;
            margin: 8px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
            line-height: 1.3;
        }
        .cert-address {
            font-size: 10.5pt;
            text-align: center;
            color: #1e293b;
            line-height: 1.5;
            margin: 0 auto 15px auto;
            max-width: 85%;
        }
        .cert-body-text {
            font-size: 10pt;
            text-align: center;
            line-height: 1.5;
            color: #475569;
            margin-bottom: 15px;
        }
        .cert-id-box-wrapper {
            text-align: center;
            margin-bottom: 15px;
        }
        .cert-id-box {
            display: inline-block;
            background-color: #eff6ff;
            border: 2px dashed #1e3a8a;
            border-radius: 8px;
            padding: 6px 25px;
            font-size: 12pt;
            font-weight: bold;
            color: #1e3a8a;
            font-family: "Courier New", Courier, monospace;
            letter-spacing: 1px;
        }
        .cert-footer-table {
            width: 100%;
            margin-top: 15px;
        }
        .cert-meta-cell {
            width: 50%;
            font-size: 8.5pt;
            color: #475569;
            vertical-align: bottom;
            line-height: 1.5;
        }
        .cert-sig-cell {
            width: 50%;
            text-align: right;
            vertical-align: bottom;
        }
        .cert-sig-box {
            display: inline-block;
            text-align: center;
            width: 200px;
        }
        .cert-sig-image {
            height: 52px;
            margin-bottom: 25px;
            mix-blend-mode: multiply;
        }
        .cert-sig-line {
            border-top: 1.5px solid #1e3a8a;
            padding-top: 5px;
            font-size: 9.5pt;
            font-weight: bold;
            color: #1e3a8a;
        }
        .cert-sig-title {
            font-size: 7.5pt;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 1px;
            margin-top: 2px;
        }
    </style>
    </head>
    <body>
    <div class="cert-border">
        <div class="cert-inner-border">
            
            <!-- Top Banner spanning full width inside orange/golden border -->
            ' . ($bannerBase64 ? '<img src="' . $bannerBase64 . '" style="width: 100%; display: block; margin: 0; padding: 0; border: none;" />' : '') . '
            
            <div class="cert-content">
                
                <!-- Header -->
                <div class="cert-header" style="margin-top: 10px;">
                    ' . ($logoBase64 ? '<img src="' . $logoBase64 . '" class="cert-org-logo" />' : '') . '
                    <div class="cert-org-title">MG Education</div>
                    <div class="cert-org-subtitle">Social Development & Educational Organization</div>
                    <div class="cert-org-reg">Registered under Act XXI of 1860, Government of India</div>
                </div>
                
                <hr style="border: 0; border-top: 1px solid #e2e8f0; margin-bottom: 12px; margin-top: 4px;">
                
                <!-- Certificate Title -->
                <div style="text-align: center;">
                    <div class="cert-title">Certificate of Affiliation</div>
                </div>
                
                <!-- Certificate Body -->
                <div class="cert-intro">This is to officially certify and record that the institution named</div>
                
                <div class="cert-center-name">' . $centerName . '</div>
                
                <div class="cert-address">
                    Located at: <strong>' . $fullAddress . ', ' . $city . ', ' . $state . ' - ' . $pincode . '</strong>
                </div>
                
                <div class="cert-body-text">
                    has satisfied all standard operating parameters, infrastructure requirements, and regulatory benchmarks established by the central board of the organization and is hereby accredited as an <strong>Authorized Training Center (ATC)</strong>.
                </div>
                
                <!-- Center ID Box -->
                <div class="cert-id-box-wrapper">
                    <div style="font-size: 8pt; font-weight: bold; color: #475569; text-transform: uppercase; margin-bottom: 4px; letter-spacing: 1px;">Accreditation Center ID</div>
                    <div class="cert-id-box">' . htmlspecialchars($center_id) . '</div>
                </div>
                
                <div class="cert-body-text" style="font-size: 8.5pt; font-style: italic; margin-bottom: 20px;">
                    This affiliation is granted to conduct all approved vocational, technical, computer education, and skill development programs, subject to annual audits and standard guidelines compliance.
                </div>
                
                <!-- Footer -->
                <table class="cert-footer-table">
                    <tr>
                        <td class="cert-meta-cell">
                            <strong>Certificate No:</strong> ' . $certNo . '<br>
                            <strong>Date of Issue:</strong> ' . $issueDate . '<br>
                            <strong>Validity:</strong> December 31, 2026 (Renewable)<br>
                            <span style="font-size:7pt; color:#94a3b8; font-style:italic;">[Verify this credentials online at www.mgedu.in]</span>
                        </td>
                        <td class="cert-sig-cell">
                            <div class="cert-sig-box">
                                ' . ($signBase64 ? '<img src="' . $signBase64 . '" class="cert-sig-image" />' : '<div style="height: 45px;"></div>') . '
                                <div class="cert-sig-line">Director / Registrar</div>
                                <div class="cert-sig-title">MG Education Central Board</div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
            
        </div>
    </div>
    </body>
    </html>
    ';
    
    try {
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 5,
            'margin_bottom' => 10
        ]);
        $mpdf->WriteHTML($html);
        $mpdf->Output($filePath, 'F');
        return $filePath;
    } catch (Exception $e) {
        error_log("Affiliation Certificate PDF generation error: " . $e->getMessage());
        return false;
    }
}

