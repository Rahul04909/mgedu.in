<?php
/**
 * MG Education & Social Development Organization
 * Premium Franchise Wallet & Transaction Ledger Console
 */

// If this is an AJAX call, we MUST process it BEFORE including header.php to avoid contaminating JSON with HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    require_once dirname(__DIR__) . '/includes/config.php';

    // Route authorization guard
    if (!isset($_SESSION['center_role']) || $_SESSION['center_role'] !== 'franchise' || empty($_SESSION['center_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please login.']);
        exit();
    }

    $db = MG_GetDBConnection();
    $center_id = $_SESSION['center_id'];
    $center_db_id = $_SESSION['center_logged_id'];

    header('Content-Type: application/json');
    $action = $_GET['action'];

    try {
        if ($action === 'create_order') {
            $topup_amount = floatval($_POST['topup_amount'] ?? 0);
            
            if ($topup_amount < 10) {
                echo json_encode(['success' => false, 'message' => 'Minimum top-up amount is ₹10.']);
                exit;
            }

            // Fetch center royalty percentage
            $cStmt = $db->prepare("SELECT royalty_percentage, center_name, email, mobile FROM `franchise_centers` WHERE `center_id` = ? LIMIT 1");
            $cStmt->execute([$center_id]);
            $centerRow = $cStmt->fetch();
            $royalty_pct = $centerRow ? floatval($centerRow['royalty_percentage']) : 10.00;

            // Calculate actual paid amount based on royalty percentage
            $paid_amount = round(($topup_amount * $royalty_pct) / 100, 2);

            if ($paid_amount <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid payment calculation. Please enter a larger top-up amount.']);
                exit;
            }

            // Create Razorpay Order
            $razorpay = MG_GetRazorpayClient();
            $orderData = [
                'receipt'         => 'rcpt_topup_' . time(),
                'amount'          => intval($paid_amount * 100), // amount in paise
                'currency'        => 'INR',
                'payment_capture' => 1 // auto capture payment
            ];

            $razorpayOrder = $razorpay->order->create($orderData);
            $orderId = $razorpayOrder['id'];

            // Log Transaction in database as pending
            $tStmt = $db->prepare("
                INSERT INTO `franchise_transactions` 
                (center_id, amount, paid_amount, royalty_percentage, payment_status, razorpay_order_id) 
                VALUES (?, ?, ?, ?, 'pending', ?)
            ");
            $tStmt->execute([$center_id, $topup_amount, $paid_amount, $royalty_pct, $orderId]);
            $transaction_db_id = $db->lastInsertId();

            echo json_encode([
                'success' => true,
                'key_id' => $_ENV['RAZORPAY_KEY_ID'] ?? '',
                'order_id' => $orderId,
                'amount' => $orderData['amount'],
                'center_name' => $centerRow['center_name'] ?? 'Franchise Center',
                'email' => $centerRow['email'] ?? '',
                'contact' => $centerRow['mobile'] ?? '',
                'transaction_db_id' => $transaction_db_id
            ]);
            exit;

        } elseif ($action === 'verify_payment') {
            $order_id = trim($_POST['razorpay_order_id'] ?? '');
            $payment_id = trim($_POST['razorpay_payment_id'] ?? '');
            $signature = trim($_POST['razorpay_signature'] ?? '');
            $transaction_db_id = intval($_POST['transaction_db_id'] ?? 0);

            if (empty($order_id) || empty($payment_id) || empty($signature) || $transaction_db_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Missing verification parameters.']);
                exit;
            }

            // Verify signature using Razorpay client algorithm
            try {
                $razorpay = MG_GetRazorpayClient();
                $attributes = [
                    'razorpay_order_id' => $order_id,
                    'razorpay_payment_id' => $payment_id,
                    'razorpay_signature' => $signature
                ];
                $razorpay->utility->verifyPaymentSignature($attributes);
            } catch (Throwable $sigEx) {
                echo json_encode(['success' => false, 'message' => 'Payment signature verification failed: ' . $sigEx->getMessage()]);
                exit;
            }

            // Fetch transaction row from database
            $tCheck = $db->prepare("SELECT * FROM `franchise_transactions` WHERE `id` = ? AND `center_id` = ? LIMIT 1");
            $tCheck->execute([$transaction_db_id, $center_id]);
            $txn = $tCheck->fetch();

            if (!$txn) {
                echo json_encode(['success' => false, 'message' => 'Transaction log not found.']);
                exit;
            }

            if ($txn['payment_status'] === 'paid') {
                echo json_encode(['success' => true, 'message' => 'Payment already verified and credited.']);
                exit;
            }

            // Update Transaction Ledger & Credit Wallet balance in a single transaction
            try {
                $db->beginTransaction();

                // 1. Mark transaction as paid
                $tUpd = $db->prepare("UPDATE `franchise_transactions` SET `payment_status` = 'paid', `razorpay_payment_id` = ? WHERE `id` = ?");
                $tUpd->execute([$payment_id, $transaction_db_id]);

                // 2. Add the full Top-up credit amount (amount) to center's wallet balance
                $wUpd = $db->prepare("UPDATE `franchise_wallets` SET `balance` = `balance` + ? WHERE `center_id` = ?");
                $wUpd->execute([$txn['amount'], $center_id]);

                $db->commit();
            } catch (Throwable $dbEx) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Database updates failed: ' . $dbEx->getMessage()]);
                exit;
            }

            // 3. Compile premium tax receipt PDF via mPDF
            $pdfPath = MG_GenerateFranchiseReceiptPDF($transaction_db_id);
            $relativeReceipt = $pdfPath ? 'assets/uploads/franchises/receipts/receipt_' . $transaction_db_id . '.pdf' : NULL;

            if ($relativeReceipt) {
                $rUpd = $db->prepare("UPDATE `franchise_transactions` SET `receipt_path` = ? WHERE `id` = ?");
                $rUpd->execute([$relativeReceipt, $transaction_db_id]);
            }

            // 4. Dispatch Email with attachment via PHPMailer SMTP
            try {
                $cDetails = $db->prepare("SELECT center_name, email FROM `franchise_centers` WHERE `center_id` = ? LIMIT 1");
                $cDetails->execute([$center_id]);
                $cRow = $cDetails->fetch();
                $emailTo = $cRow ? $cRow['email'] : '';
                $centerName = $cRow ? $cRow['center_name'] : 'Accredited Franchise';

                if (!empty($emailTo)) {
                    $subject = "[MG Education] Payment Receipt Confirmation - Wallet Top-Up";
                    $body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; background-color: #ffffff;'>
                        <div style='text-align: center; border-bottom: 2px solid #10b981; padding-bottom: 20px; margin-bottom: 20px;'>
                            <h2 style='color: #10b981; margin: 0; text-transform: uppercase;'>PAYMENT RECEIVED</h2>
                            <p style='color: #475569; font-size: 14px; margin: 5px 0 0 0;'>MG Education Franchise Network</p>
                        </div>
                        <div style='padding: 10px 0;'>
                            <p style='font-size: 16px; color: #1e293b;'>Hello Director / Manager,</p>
                            <p style='font-size: 15px; color: #475569; line-height: 1.6;'>Thank you for your payment! We have successfully received your dynamic royalty deposit and credited your **Franchise Center Wallet**.</p>
                            
                            <div style='background-color: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 15px; margin: 20px 0;'>
                                <table style='width: 100%; font-size: 14px; color: #1e293b; border-collapse: collapse;'>
                                    <tr>
                                        <td style='padding: 6px 0; font-weight: bold; color: #475569;'>Center ID:</td>
                                        <td style='padding: 6px 0; font-family: monospace; font-weight: bold;'>{$center_id}</td>
                                    </tr>
                                    <tr>
                                        <td style='padding: 6px 0; font-weight: bold; color: #475569;'>Credited Top-Up Amount:</td>
                                        <td style='padding: 6px 0; font-weight: bold; color: #10b981;'>₹" . number_format(floatval($txn['amount']), 2) . "</td>
                                    </tr>
                                    <tr>
                                        <td style='padding: 6px 0; font-weight: bold; color: #475569;'>Actual Royalty Paid:</td>
                                        <td style='padding: 6px 0; font-weight: bold;'>₹" . number_format(floatval($txn['paid_amount']), 2) . " (" . floatval($txn['royalty_percentage']) . "% rate)</td>
                                    </tr>
                                    <tr>
                                        <td style='padding: 6px 0; font-weight: bold; color: #475569;'>Transaction Reference:</td>
                                        <td style='padding: 6px 0; font-family: monospace; font-size: 12px;'>{$payment_id}</td>
                                    </tr>
                                </table>
                            </div>

                            <p style='font-size: 14px; color: #475569; line-height: 1.5;'>Your digitally signed **Tax Invoice & Top-Up Receipt** is generated and attached to this email as a PDF. Please retain this copy for your corporate tax records.</p>
                        </div>
                        <div style='border-top: 1px solid #e2e8f0; padding-top: 15px; margin-top: 30px; font-size: 12px; color: #94a3b8; text-align: center;'>
                            &copy; " . date('Y') . " MG Education Org. All rights reserved.<br>
                            This is a transaction verification notice.
                        </div>
                    </div>";

                    $emailOptions = [];
                    if ($pdfPath && file_exists($pdfPath)) {
                        $emailOptions['attachments'] = [$pdfPath];
                    }

                    MG_SendMail($emailTo, $subject, $body, $emailOptions);
                }
            } catch (Throwable $emailEx) {
                error_log("Failed to dispatch topup email notification: " . $emailEx->getMessage());
            }

            echo json_encode(['success' => true, 'message' => 'Wallet balance credited and receipt dispatched.']);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        exit;

    } catch (Throwable $ex) {
        echo json_encode(['success' => false, 'message' => 'Exception: ' . $ex->getMessage()]);
        exit;
    }
}

// Normal page GET request flow below
include './header.php';

$db = MG_GetDBConnection();
$center_id = $_SESSION['center_id'];
$center_db_id = $_SESSION['center_logged_id'];

// Self-healing: Ensure wallet record exists for this center
try {
    $wCheck = $db->prepare("SELECT id FROM `franchise_wallets` WHERE `center_id` = ?");
    $wCheck->execute([$center_id]);
    if (!$wCheck->fetch()) {
        $wIns = $db->prepare("INSERT INTO `franchise_wallets` (`center_id`, `balance`) VALUES (?, 0.00)");
        $wIns->execute([$center_id]);
    }
} catch (Throwable $e) {
    error_log("Failed to self-heal franchise wallet: " . $e->getMessage());
}

// Fetch current center details (to get active royalty rate)
$centerStmt = $db->prepare("SELECT royalty_percentage FROM `franchise_centers` WHERE `center_id` = ? LIMIT 1");
$centerStmt->execute([$center_id]);
$centerRow = $centerStmt->fetch();
$royalty_pct = $centerRow ? floatval($centerRow['royalty_percentage']) : 10.00;

// Fetch Wallet Balance
$wStmt = $db->prepare("SELECT balance FROM `franchise_wallets` WHERE `center_id` = ? LIMIT 1");
$wStmt->execute([$center_id]);
$wallet = $wStmt->fetch();
$balance = $wallet ? floatval($wallet['balance']) : 0.00;

// Fetch Transaction History
$tHistory = $db->prepare("SELECT * FROM `franchise_transactions` WHERE `center_id` = ? ORDER BY id DESC");
$tHistory->execute([$center_id]);
$transactions = $tHistory->fetchAll();
?>

<!-- Premium Visual styling for green-themed financial dashboard -->
<style>
    .wallet-card-premium {
        background: linear-gradient(135deg, #064e3b 0%, #022c22 100%);
        color: #ffffff;
        border-radius: 16px;
        padding: 30px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(255,255,255,0.08);
        position: relative;
        overflow: hidden;
    }

    .wallet-card-premium::before {
        content: '';
        position: absolute;
        top: -40%;
        right: -10%;
        width: 250px;
        height: 250px;
        background: radial-gradient(circle, rgba(16, 185, 129, 0.18) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .wallet-label {
        font-size: 12.5px;
        font-weight: 700;
        color: #34d399;
        text-transform: uppercase;
        letter-spacing: 0.75px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 8px;
    }

    .wallet-balance {
        font-family: 'Outfit', 'Inter', sans-serif;
        font-size: 38px;
        font-weight: 800;
        letter-spacing: -0.5px;
        margin: 0 0 4px 0;
    }

    .wallet-subtitle {
        font-size: 13.5px;
        color: #a7f3d0;
    }

    .ledger-card-premium {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.02);
        padding: 25px;
        margin-bottom: 40px;
    }

    .ledger-title-premium {
        font-weight: 700;
        font-size: 18px;
        color: #1e293b;
        border-bottom: 2px solid #f1f5f9;
        padding-bottom: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .ledger-title-premium i {
        color: #059669;
    }

    .btn-green-topup {
        background: linear-gradient(135deg, #10b981, #059669);
        color: #ffffff !important;
        font-weight: 700;
        font-size: 13.5px;
        padding: 10px 24px;
        border-radius: 50px;
        border: none;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-green-topup:hover {
        transform: translateY(-1.5px);
        box-shadow: 0 6px 18px rgba(16, 185, 129, 0.4);
        filter: brightness(1.05);
    }

    /* Top-Up calculator grid */
    .calculator-box {
        background-color: #f0fdf4;
        border: 1.5px dashed #34d399;
        border-radius: 12px;
        padding: 15px;
        margin-top: 15px;
        display: none;
        animation: slideDownFade 0.3s ease forwards;
    }

    @keyframes slideDownFade {
        from { opacity: 0; transform: translateY(-5px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .calc-row {
        display: flex;
        justify-content: space-between;
        font-size: 13px;
        color: #475569;
        padding: 4px 0;
    }

    .calc-row.final-due {
        border-top: 1px solid #a7f3d0;
        margin-top: 8px;
        padding-top: 10px;
        font-size: 14.5px;
        font-weight: 700;
        color: #064e3b;
    }

    .form-control-premium {
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 14px;
        font-weight: 600;
        color: #1e293b;
        transition: all 0.3s ease;
    }

    .form-control-premium:focus {
        border-color: #10b981;
        outline: none;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }
</style>

<div class="row pt-4">
    <!-- Left Column: Balance Overview -->
    <div class="col-md-4">
        <div class="small-box bg-success">
            <div class="inner">
                <h3>₹<?= number_format($balance, 2) ?></h3>
                <p>Current Balance</p>
            </div>
            <div class="icon">
                <i class="fas fa-wallet"></i>
            </div>
            <a href="javascript:void(0);" class="small-box-footer" data-bs-toggle="modal" data-bs-target="#topupModal" style="cursor: pointer;">
                Recharge Wallet <i class="fas fa-plus-circle ml-1"></i>
            </a>
        </div>
    </div>

    <!-- Right Column: Ledger Grid -->
    <div class="col-md-8">
        <div class="ledger-card-premium">
            <div class="ledger-title-premium">
                <i class="fa-solid fa-file-invoice-dollar"></i> Transaction History & Ledgers
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0" style="font-size: 13.5px;">
                    <thead class="table-light text-secondary font-weight-bold">
                        <tr>
                            <th>Receipt ID</th>
                            <th>Billing Date</th>
                            <th class="text-end">Top-Up Credit (₹)</th>
                            <th class="text-end">Royalty Paid (₹)</th>
                            <th>Reference ID</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Invoice</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="fa-solid fa-receipt d-block mb-3" style="font-size: 40px; opacity: 0.3;"></i>
                                    No financial transaction history found on ledger.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $t): ?>
                                <tr>
                                    <td class="font-weight-bold" style="font-family: monospace; font-size: 12.5px;">
                                        MGEDU/FR-REC/<?= sprintf("%05d", $t['id']) ?>
                                    </td>
                                    <td><?= date('d M Y', strtotime($t['created_at'])) ?></td>
                                    <td class="text-end font-weight-bold text-success">
                                        ₹<?= number_format($t['amount'], 2) ?>
                                    </td>
                                    <td class="text-end font-weight-bold text-dark">
                                        ₹<?= number_format($t['paid_amount'], 2) ?>
                                    </td>
                                    <td class="text-muted" style="font-family: monospace; font-size: 11.5px; max-width: 130px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?= htmlspecialchars($t['razorpay_payment_id'] ?: 'N/A') ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($t['payment_status'] === 'paid'): ?>
                                            <span class="badge bg-success text-white px-2 py-1" style="font-size: 10px; border-radius: 4px;"><i class="fa-solid fa-circle-check"></i> PAID</span>
                                        <?php elseif ($t['payment_status'] === 'failed'): ?>
                                            <span class="badge bg-danger text-white px-2 py-1" style="font-size: 10px; border-radius: 4px;"><i class="fa-solid fa-circle-xmark"></i> FAILED</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark px-2 py-1" style="font-size: 10px; border-radius: 4px;"><i class="fa-solid fa-circle-exclamation"></i> PENDING</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($t['payment_status'] === 'paid' && !empty($t['receipt_path'])): ?>
                                            <a href="../<?= htmlspecialchars($t['receipt_path']) ?>" target="_blank" class="text-danger font-weight-bold text-decoration-none" style="font-size: 13px;">
                                                <i class="far fa-file-pdf"></i> Download
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ================= TOP-UP SECURE MODAL ================= -->
<div class="modal fade" id="topupModal" tabindex="-1" aria-labelledby="topupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; overflow: hidden; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #064e3b 0%, #022c22 100%); padding: 18px 24px;">
                <h5 class="modal-title font-weight-bold" id="topupModalLabel"><i class="fa-solid fa-circle-dollar-to-slot mr-2"></i> Recharge Center Wallet</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="topupRequestForm" onsubmit="handleTopUpRequest(event)">
                <div class="modal-body" style="padding: 24px;">
                    
                    <div class="mb-3">
                        <label class="form-label font-weight-bold text-secondary" style="font-size: 12.5px; text-transform: uppercase;">Top-Up Value (₹)<span class="text-danger">*</span></label>
                        <input type="number" class="form-control form-control-premium w-100" id="topup_amount_field" min="10" placeholder="Minimum ₹10" required oninput="calculateRoyaltyPayment(this.value)">
                        <small class="text-muted mt-1 d-block">Minimum reload credit limit is securely set at ₹10.</small>
                    </div>

                    <!-- Dynamic Math Calculator block -->
                    <div class="calculator-box" id="calc-result-box">
                        <div class="calc-row">
                            <span>Requested Top-Up Credits:</span>
                            <strong class="text-dark">₹<span id="lbl-topup-amt">0.00</span></strong>
                        </div>
                        <div class="calc-row">
                            <span>Accredited Center Royalty Rate:</span>
                            <strong class="text-success"><span id="lbl-royalty-rate"><?= floatval($royalty_pct) ?></span>%</strong>
                        </div>
                        <div class="calc-row final-due">
                            <span>Net Payment Due (Royalty Share):</span>
                            <span>₹<span id="lbl-pay-amt">0.00</span></span>
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-light px-4 py-3 d-flex justify-content-between" style="border: none;">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-green-topup" id="btn-topup-confirm" disabled>
                        Launch Checkout <i class="fa-solid fa-arrow-right-long"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Secure Razorpay Integrations Checkout Library -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<script>
    const centerRoyaltyPct = parseFloat(<?= json_encode($royalty_pct) ?>);

    // Live royalty calculator
    function calculateRoyaltyPayment(val) {
        const topup = parseFloat(val);
        const calcBox = $('#calc-result-box');
        const submitBtn = $('#btn-topup-confirm');

        if (isNaN(topup) || topup < 10) {
            calcBox.hide();
            submitBtn.prop('disabled', true);
            return;
        }

        const payAmount = (topup * centerRoyaltyPct) / 100;

        $('#lbl-topup-amt').text(topup.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        $('#lbl-pay-amt').text(payAmount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        
        calcBox.slideDown();
        submitBtn.prop('disabled', false);
    }

    // Submit and launch checkout process
    function handleTopUpRequest(event) {
        event.preventDefault();
        const topupAmount = $('#topup_amount_field').val().trim();
        const submitBtn = $('#btn-topup-confirm');

        if (!topupAmount || parseFloat(topupAmount) < 10) {
            Swal.fire({
                title: 'Attention',
                text: 'Minimum top-up value is ₹10.',
                icon: 'warning',
                confirmButtonColor: '#10b981'
            });
            return;
        }

        submitBtn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Preparing Checkout...');

        // 1. Create order on the server
        $.ajax({
            type: 'POST',
            url: 'wallet.php?action=create_order',
            data: { topup_amount: topupAmount },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Hide Modal smoothly
                    const modalEl = document.getElementById('topupModal');
                    const modalInst = bootstrap.Modal.getInstance(modalEl);
                    modalInst.hide();

                    // 2. Open Razorpay secure checkout panel
                    const options = {
                        key: response.key_id,
                        amount: response.amount,
                        currency: 'INR',
                        name: 'MG Education',
                        description: 'Franchise Wallet Top-Up (Royalty recharge)',
                        image: '../assets/logo/logo.jpg',
                        order_id: response.order_id,
                        handler: function(gatewayRes) {
                            // 3. Callback verified successfully
                            verifyTopUpPayment(
                                response.order_id,
                                gatewayRes.razorpay_payment_id,
                                gatewayRes.razorpay_signature,
                                response.transaction_db_id
                            );
                        },
                        prefill: {
                            name: response.center_name,
                            email: response.email,
                            contact: response.contact
                        },
                        theme: {
                            color: '#10b981'
                        },
                        modal: {
                            ondismiss: function() {
                                Swal.fire({
                                    title: 'Transaction Aborted',
                                    text: 'You dismissed the online gateway check. Top-up not completed.',
                                    icon: 'info',
                                    confirmButtonColor: '#10b981'
                                });
                            }
                        }
                    };
                    const rzp = new Razorpay(options);
                    rzp.open();
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: response.message,
                        icon: 'error',
                        confirmButtonColor: '#10b981'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'Error',
                    text: 'Wallet gateway initialization failed. Connection issues.',
                    icon: 'error',
                    confirmButtonColor: '#10b981'
                });
            },
            complete: function() {
                submitBtn.prop('disabled', false).html('Launch Checkout <i class="fa-solid fa-arrow-right-long"></i>');
            }
        });
    }

    // Verify callback payment parameters on server
    function verifyTopUpPayment(orderId, paymentId, signature, txnDbId) {
        Swal.fire({
            title: 'Verifying Receipt...',
            text: 'Validating bank transaction signatures...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            type: 'POST',
            url: 'wallet.php?action=verify_payment',
            data: {
                razorpay_order_id: orderId,
                razorpay_payment_id: paymentId,
                razorpay_signature: signature,
                transaction_db_id: txnDbId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'Top-Up Successful!',
                        text: 'Your wallet has been credited and payment invoice has been sent to your email address!',
                        icon: 'success',
                        confirmButtonColor: '#28a745'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Verification Failed',
                        text: response.message,
                        icon: 'error',
                        confirmButtonColor: '#ef4444'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'Verification Fails',
                    text: 'Unable to communicate with payment validation server. Wallet balance check needed.',
                    icon: 'warning',
                    confirmButtonColor: '#ef4444'
                });
            }
        });
    }
</script>

<?php include './footer.php'; ?>
