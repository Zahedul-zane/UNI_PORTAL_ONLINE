<?php
$pageTitle = "Semester Fee Assessment & Payment";
require_once __DIR__ . '/../config/auth.php';
require_role('student');

$studentId = $_SESSION['user_id'];
$msg = '';
$msgType = '';

$currentSemester = 'Summer';
$currentYear = 2026;

// ─── POST: Handle Student Semester Fee Payment Submission ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_semester_fee'])) {
    $amount    = floatval($_POST['amount'] ?? 0);
    $semester  = sanitize($_POST['semester'] ?? $currentSemester);
    $year      = intval($_POST['year'] ?? $currentYear);
    $method    = sanitize($_POST['payment_method'] ?? 'bKash');
    $senderRef = sanitize($_POST['sender_ref'] ?? '');
    $userTxnId = sanitize($_POST['transaction_id'] ?? '');
    $remarks   = sanitize($_POST['remarks'] ?? '');

    // Generate clean transaction ID if student didn't provide external TrxID
    $txnId = !empty($userTxnId) ? $userTxnId : ('TXN-' . $year . '-EWU-' . rand(10000, 99999));
    $payDate = date('Y-m-d');

    if ($amount <= 0) {
        $msg = 'Please enter a valid payment amount greater than 0.';
        $msgType = 'danger';
    } else {
        try {
            // Check if transaction ID already exists
            $stmtCheck = $pdo->prepare("SELECT Payment_Id FROM Payment WHERE Transaction_Id = ?");
            $stmtCheck->execute([$txnId]);
            if ($stmtCheck->fetch()) {
                $txnId .= '-' . rand(10, 99); // Auto-disambiguate
            }

            $remarkFull = "Method: $method" . ($senderRef ? " | Sender/Acc: $senderRef" : "") . ($remarks ? " | Note: $remarks" : "");

            // Insert as 'Pending' status so Admin / Accounts Office can review and verify
            $stmt = $pdo->prepare("
                INSERT INTO Payment (Transaction_Id, Payment_Status, Amount, Semester, Year, Payment_Date, Student_ID)
                VALUES (?, 'Pending', ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$txnId, $amount, $semester, $year, $payDate, $studentId]);
            $newPayId = $pdo->lastInsertId();

            $msg = "🎉 <strong>Payment Submission Received!</strong> Your semester fee submission of <strong>৳ " . number_format($amount, 2) . "</strong> (TrxID: <code>$txnId</code>) has been submitted successfully. Status is currently <strong>⏳ Pending Admin Verification</strong>.";
            $msgType = 'success';
        } catch (Exception $e) {
            $msg = 'Payment submission failed: ' . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

// ─── Calculate Course-by-Course Semester Fee Assessment ─────────────────────
$feeAssessment = ewu_calculate_student_semester_fee($pdo, $studentId, $currentSemester, $currentYear);

// Fetch All Payment Records for this Student across all semesters
$stmtAllPay = $pdo->prepare("
    SELECT * FROM Payment
    WHERE Student_ID = ?
    ORDER BY Payment_Id DESC
");
$stmtAllPay->execute([$studentId]);
$allPayments = $stmtAllPay->fetchAll();

// Totals across student account
$totalPaidAllTime = 0.0;
$totalPendingAllTime = 0.0;
foreach ($allPayments as $p) {
    if ($p['Payment_Status'] === 'Paid') $totalPaidAllTime += floatval($p['Amount']);
    elseif ($p['Payment_Status'] === 'Pending') $totalPendingAllTime += floatval($p['Amount']);
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <h1>💳 Semester Fee Assessment & Payments</h1>
    <p>Course-based tuition calculation, online semester fee payment submission, and financial clearance tracking.</p>
</div>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>" style="margin-bottom: 24px;">
        <span><?= $msg ?></span>
    </div>
<?php endif; ?>

<!-- Financial Summary Cards -->
<div class="stats-grid" style="margin-bottom: 28px;">
    <div class="stat-card">
        <div class="stat-icon primary">📚</div>
        <div class="stat-details">
            <h3>৳ <?= number_format($feeAssessment['total_payable'], 2) ?></h3>
            <p><?= $currentSemester ?> <?= $currentYear ?> Fee (<?= $feeAssessment['total_credits'] ?> Credits)</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success">✅</div>
        <div class="stat-details">
            <h3>৳ <?= number_format($feeAssessment['total_paid'], 2) ?></h3>
            <p>Verified Paid (Summer Term)</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning">⏳</div>
        <div class="stat-details">
            <h3>৳ <?= number_format($feeAssessment['total_pending'], 2) ?></h3>
            <p>Pending Admin Verification</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon <?= $feeAssessment['net_due'] > 0 ? 'danger' : 'success' ?>">
            <?= $feeAssessment['net_due'] > 0 ? '⚠️' : '🎉' ?>
        </div>
        <div class="stat-details">
            <h3>৳ <?= number_format($feeAssessment['net_due'], 2) ?></h3>
            <p>Outstanding Due Balance</p>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 25px; margin-bottom: 28px; align-items: start;">

    <!-- ─── LEFT: Course-by-Course Semester Fee Assessment Invoice ────────────── -->
    <div class="panel">
        <div class="panel-header" style="background: linear-gradient(135deg, var(--primary) 0%, #1e3a8a 100%); color: white;">
            <div class="panel-title" style="color: white; display: flex; align-items: center; gap: 8px;">
                <span>🧾</span> <?= $currentSemester ?> <?= $currentYear ?> Course Fee Assessment
            </div>
            <span class="badge <?= $feeAssessment['status_badge'] ?>" style="font-size: 12px; padding: 6px 12px;">
                <?= $feeAssessment['status'] ?>
            </span>
        </div>

        <div class="panel-body" style="padding: 0;">
            <?php if (empty($feeAssessment['courses'])): ?>
                <div style="padding: 40px 20px; text-align: center; color: var(--text-muted);">
                    <div style="font-size: 42px; margin-bottom: 10px;">📋</div>
                    <h4 style="margin: 0 0 6px 0; color: var(--text-primary);">No Registered Courses for Summer 2026</h4>
                    <p style="font-size: 13px;">Complete your course advising first in the <a href="advising.php">Official Advising Section</a> to generate your semester fee statement.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table" style="font-size: 13px;">
                        <thead>
                            <tr>
                                <th>Course Details</th>
                                <th style="text-align: center;">Section</th>
                                <th style="text-align: center;">Credits</th>
                                <th style="text-align: right;">Rate / Cr</th>
                                <th style="text-align: right;">Course Tuition</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feeAssessment['courses'] as $c): ?>
                                <tr>
                                    <td>
                                        <strong style="color: var(--primary);"><?= htmlspecialchars($c['course_id']) ?></strong><br>
                                        <small style="color: var(--text-muted);"><?= htmlspecialchars($c['title']) ?></small>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge badge-info">Sec <?= $c['section_no'] ?></span>
                                    </td>
                                    <td style="text-align: center; font-weight: 600;">
                                        <?= number_format($c['credits'], 1) ?> Cr
                                    </td>
                                    <td style="text-align: right; color: var(--text-muted);">
                                        ৳ <?= number_format($c['rate_per_credit'], 2) ?>
                                    </td>
                                    <td style="text-align: right; font-weight: 700;">
                                        ৳ <?= number_format($c['course_fee'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Calculation Breakdown Box -->
                <div style="padding: 18px 24px; background: #f8fafc; border-top: 1px solid var(--border-color);">
                    <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px;">
                        <span style="color: var(--text-muted);">Course Tuition Subtotal (<?= $feeAssessment['total_credits'] ?> Credits × ৳5,500):</span>
                        <strong style="font-size: 14px;">৳ <?= number_format($feeAssessment['tuition_subtotal'], 2) ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 10px;">
                        <span style="color: var(--text-muted);">Semester Student Activity & Lab Resource Fee:</span>
                        <strong style="font-size: 14px;">৳ <?= number_format($feeAssessment['facility_fee'], 2) ?></strong>
                    </div>
                    <div style="border-top: 2px dashed #cbd5e1; padding-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 15px; font-weight: 700; color: var(--primary);">Grand Total Payable Fee:</span>
                        <span style="font-size: 18px; font-weight: 800; color: var(--primary);">৳ <?= number_format($feeAssessment['total_payable'], 2) ?></span>
                    </div>

                    <div style="display: flex; justify-content: space-between; font-size: 13px; margin-top: 10px; color: #047857;">
                        <span>Verified Paid Amount (Admin Approved):</span>
                        <strong>- ৳ <?= number_format($feeAssessment['total_paid'], 2) ?></strong>
                    </div>

                    <?php if ($feeAssessment['total_pending'] > 0): ?>
                        <div style="display: flex; justify-content: space-between; font-size: 13px; margin-top: 4px; color: #b45309;">
                            <span>Submissions Under Admin Verification:</span>
                            <strong>৳ <?= number_format($feeAssessment['total_pending'], 2) ?> (⏳ Pending)</strong>
                        </div>
                    <?php endif; ?>

                    <div style="border-top: 1px solid var(--border-color); margin-top: 10px; padding-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 14px; font-weight: 700; color: <?= $feeAssessment['net_due'] > 0 ? '#b91c1c' : '#047857' ?>;">
                            Remaining Due Balance:
                        </span>
                        <span style="font-size: 17px; font-weight: 800; color: <?= $feeAssessment['net_due'] > 0 ? '#b91c1c' : '#047857' ?>;">
                            ৳ <?= number_format($feeAssessment['net_due'], 2) ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ─── RIGHT: Submit Semester Fee Payment Form ───────────────────────────── -->
    <div class="panel">
        <div class="panel-header" style="background: linear-gradient(135deg, var(--gold-dark) 0%, var(--gold) 100%); color: var(--primary-dark);">
            <div class="panel-title" style="color: var(--primary-dark); font-weight: 700;">
                💳 Submit Semester Fee Payment
            </div>
        </div>
        <div class="panel-body">
            <form action="payments.php" method="POST" id="submit_fee_form">
                <input type="hidden" name="semester" value="<?= $currentSemester ?>">
                <input type="hidden" name="year" value="<?= $currentYear ?>">

                <div class="form-group">
                    <label for="amount">Payment Amount (BDT) (*)</label>
                    <input type="number" step="0.01" name="amount" id="amount" class="form-control"
                           value="<?= $feeAssessment['net_due'] > 0 ? $feeAssessment['net_due'] : '' ?>"
                           placeholder="e.g. <?= $feeAssessment['net_due'] > 0 ? number_format($feeAssessment['net_due'], 2, '.', '') : '18500.00' ?>"
                           required>
                    <small style="color: var(--text-muted); font-size: 11.5px; display: block; margin-top: 3px;">
                        Calculated term due: <strong>৳ <?= number_format($feeAssessment['net_due'], 2) ?></strong>
                    </small>
                </div>

                <!-- Payment Method -->
                <div class="form-group">
                    <label for="payment_method">Payment Method (*)</label>
                    <select name="payment_method" id="payment_method" class="form-control" required onchange="handlePaymentMethodChange(this.value)">
                        <option value="bKash">bKash Online Merchant (01711-XXXXXX)</option>
                        <option value="Nagad">Nagad Direct Payment (01811-XXXXXX)</option>
                        <option value="Rocket">Rocket / DBBL Mobile (01911-XXXXXX)</option>
                        <option value="DBBL NexusPay / Card">DBBL NexusPay / Visa / MasterCard</option>
                        <option value="Bank Deposit Slip">Bank Deposit (Mutual Trust / Bank Asia / City Bank)</option>
                    </select>
                </div>

                <!-- Transaction Reference / TrxID -->
                <div class="form-group">
                    <label for="transaction_id">Transaction ID (TrxID / Bank Scroll No) (*)</label>
                    <input type="text" name="transaction_id" id="transaction_id" class="form-control" placeholder="e.g. 9J48KLM7Q or TXN-2026-EWU-9821" required>
                    <small style="color: var(--text-muted); font-size: 11px;">The unique receipt or transaction reference provided by your bank or mobile wallet.</small>
                </div>

                <!-- Sender Phone / Account -->
                <div class="form-group">
                    <label for="sender_ref">Sender Phone No / Bank Branch</label>
                    <input type="text" name="sender_ref" id="sender_ref" class="form-control" placeholder="e.g. +88017... or Aftabnagar Branch">
                </div>

                <!-- Additional Remarks -->
                <div class="form-group">
                    <label for="remarks">Student Notes / Remarks (Optional)</label>
                    <input type="text" name="remarks" id="remarks" class="form-control" placeholder="e.g. Summer 2026 2nd Installment / Full Fee">
                </div>

                <div style="background: #f0fdf4; border: 1px dashed #86efac; padding: 10px 14px; border-radius: 6px; font-size: 12px; color: #166534; margin-bottom: 16px;">
                    🛡️ <strong>Verification Flow:</strong> Upon submission, this payment will be dispatched to the <strong>Admin & Accounts Verification Portal</strong> with status <code>Pending</code>. Once verified by accounts, your clearance and ledger will be updated immediately.
                </div>

                <button type="submit" name="submit_semester_fee" class="btn btn-gold" style="width: 100%; padding: 13px; font-weight: 700; font-size: 14px;">
                    🚀 Submit Payment for Verification
                </button>
            </form>
        </div>
    </div>

</div>

<!-- ─── Payment History & Official Receipts Table ────────────────────────────── -->
<div class="panel">
    <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div class="panel-title">💳 Official Financial Payment History & Money Receipts</div>
        <span style="font-size: 13px; color: var(--text-muted);">Lifetime Recorded Transactions: <?= count($allPayments) ?></span>
    </div>
    <div class="panel-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Semester / Term</th>
                        <th>Amount (BDT)</th>
                        <th>Payment Date</th>
                        <th style="text-align: center;">Verification Status</th>
                        <th style="text-align: center;">Official Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($allPayments) > 0): ?>
                        <?php foreach ($allPayments as $pay): ?>
                            <tr>
                                <td>
                                    <code style="font-size: 13px; font-weight: 700; color: var(--primary);">
                                        <?= htmlspecialchars($pay['Transaction_Id']) ?>
                                    </code>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($pay['Semester']) ?> <?= htmlspecialchars($pay['Year']) ?></strong>
                                </td>
                                <td>
                                    <strong style="font-size: 14px;">৳ <?= number_format($pay['Amount'], 2) ?></strong>
                                </td>
                                <td>
                                    <?= $pay['Payment_Date'] ? date('d M Y', strtotime($pay['Payment_Date'])) : '<span style="color:var(--text-muted);">Under Review</span>' ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($pay['Payment_Status'] === 'Paid'): ?>
                                        <span class="badge badge-success" style="padding: 6px 12px; font-size: 12px;">
                                            ✅ Verified & Paid
                                        </span>
                                    <?php elseif ($pay['Payment_Status'] === 'Pending'): ?>
                                        <span class="badge badge-warning" style="padding: 6px 12px; font-size: 12px;">
                                            ⏳ Pending Admin Verification
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-danger" style="padding: 6px 12px; font-size: 12px;">
                                            ❌ <?= htmlspecialchars($pay['Payment_Status']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <button type="button" class="btn btn-sm btn-secondary"
                                            onclick='openReceiptModal(<?= json_encode($pay) ?>, <?= json_encode($feeAssessment) ?>)'>
                                        🧾 View Receipt
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 35px; color: var(--text-muted);">
                                No payment records found in your financial ledger.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ─── MODAL: Official EWU Digital Money Receipt ───────────────────────────── -->
<div class="modal-overlay" id="receipt_modal">
    <div class="modal-card" style="max-width: 620px;">
        <div class="modal-header" style="background: linear-gradient(135deg, var(--primary) 0%, #1e3a8a 100%); color: white;">
            <h3>🧾 East West University - Official Money Receipt</h3>
            <button type="button" class="modal-close" onclick="closeModal('receipt_modal')">&times;</button>
        </div>
        <div class="modal-body" style="padding: 24px;" id="receipt_modal_content">
            <!-- Populated dynamically via JS -->
        </div>
        <div class="modal-footer" style="padding: 14px 24px; background: #f8fafc; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <button type="button" class="btn btn-primary" onclick="window.print()">🖨️ Print Receipt</button>
            <button type="button" class="btn btn-secondary" onclick="closeModal('receipt_modal')">Close</button>
        </div>
    </div>
</div>

<script>
function handlePaymentMethodChange(val) {
    const txnInput = document.getElementById('transaction_id');
    if (val.includes('bKash') || val.includes('Nagad') || val.includes('Rocket')) {
        txnInput.placeholder = 'e.g. 9J48KLM7Q (Wallet Transaction TrxID)';
    } else if (val.includes('Bank')) {
        txnInput.placeholder = 'e.g. SCROLL-8921 / Deposit Slip Number';
    } else {
        txnInput.placeholder = 'e.g. TXN-2026-EWU-9821';
    }
}

function openReceiptModal(pay, assessment) {
    const content = document.getElementById('receipt_modal_content');

    const statusBadge = (pay.Payment_Status === 'Paid')
        ? `<span class="badge badge-success" style="font-size:13px; padding:6px 12px;">✅ PAID & VERIFIED</span>`
        : `<span class="badge badge-warning" style="font-size:13px; padding:6px 12px;">⏳ PENDING ADMIN VERIFICATION</span>`;

    content.innerHTML = `
        <div style="text-align:center; padding-bottom:16px; border-bottom:2px solid var(--primary); margin-bottom:16px;">
            <h2 style="margin:0; color:var(--primary); font-size:20px; font-weight:800;">EAST WEST UNIVERSITY</h2>
            <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin-top:2px;">Office of the Accounts & Financial Administration</div>
            <div style="font-size:11px; color:var(--text-muted);">Plot No-A/2, Jahurul Islam City, Aftabnagar, Dhaka-1212</div>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; background:#f8fafc; padding:12px 16px; border-radius:8px; border:1px solid var(--border-color);">
            <div>
                <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase;">Receipt / Trx ID</div>
                <div style="font-weight:800; font-size:14px; color:var(--primary);">${pay.Transaction_Id}</div>
            </div>
            <div>
                ${statusBadge}
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; font-size:13px; margin-bottom:16px;">
            <div><strong>Student ID:</strong> ${pay.Student_ID}</div>
            <div><strong>Academic Term:</strong> ${pay.Semester} ${pay.Year}</div>
            <div><strong>Payment Date:</strong> ${pay.Payment_Date ? pay.Payment_Date : 'Pending Approval'}</div>
            <div><strong>Payment Amount:</strong> <span style="font-weight:800; color:var(--primary);">BDT ৳ ${parseFloat(pay.Amount).toLocaleString('en-US', {minimumFractionDigits:2})}</span></div>
        </div>

        <div style="background:#f1f5f9; padding:12px 16px; border-radius:6px; font-size:12px; color:var(--text-muted); margin-top:16px;">
            📌 <em>This document is an electronic money receipt generated by the EWU University Portal System. All fees submitted are validated by the University Finance Office.</em>
        </div>
    `;

    openModal('receipt_modal');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
