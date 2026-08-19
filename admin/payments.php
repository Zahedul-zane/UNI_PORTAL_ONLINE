<?php
$pageTitle = "Financial Payments Ledger & Fee Verification";
require_once __DIR__ . '/../config/auth.php';
require_role('admin');

$msg = '';
$msgType = '';

// ─── POST: Handle Status Update or Quick Verification ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $payId  = intval($_POST['payment_id'] ?? 0);
    $status = sanitize($_POST['payment_status'] ?? 'Paid');

    if ($payId > 0 && in_array($status, ['Paid', 'Pending', 'Failed'])) {
        try {
            $payDate = ($status === 'Paid') ? date('Y-m-d') : null;
            $stmt = $pdo->prepare("UPDATE Payment SET Payment_Status = ?, Payment_Date = ? WHERE Payment_Id = ?");
            $stmt->execute([$status, $payDate, $payId]);

            $msg = "Payment transaction #$payId successfully updated to <strong>$status</strong>.";
            $msgType = 'success';
        } catch (Exception $e) {
            $msg = 'Update failed: ' . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

// ─── GET: Quick 1-Click Approve / Verify ───────────────────────────────────
if (isset($_GET['quick_approve'])) {
    $payId = intval($_GET['quick_approve']);
    if ($payId > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE Payment SET Payment_Status = 'Paid', Payment_Date = ? WHERE Payment_Id = ?");
            $stmt->execute([date('Y-m-d'), $payId]);
            $msg = "🎉 Payment #$payId officially <strong>Verified & Approved</strong> as Paid!";
            $msgType = 'success';
        } catch (Exception $e) {
            $msg = 'Approval failed: ' . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

// ─── GET: Quick 1-Click Reject ─────────────────────────────────────────────
if (isset($_GET['quick_reject'])) {
    $payId = intval($_GET['quick_reject']);
    if ($payId > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE Payment SET Payment_Status = 'Failed' WHERE Payment_Id = ?");
            $stmt->execute([$payId]);
            $msg = "Payment #$payId marked as <strong>Failed / Rejected</strong>.";
            $msgType = 'warning';
        } catch (Exception $e) {
            $msg = 'Rejection failed: ' . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

// ─── Fetch Master Payments with Student & Enrolled Info ─────────────────────
$payments = $pdo->query("
    SELECT p.*, s.First_name, s.Last_name, s.Student_ID, s.E_mail, d.Dept_Name,
           (SELECT COUNT(*) FROM Enrollment e WHERE e.Student_ID = p.Student_ID AND e.Semester = p.Semester AND e.Year = p.Year) AS CourseCount,
           (SELECT SUM(c.Credits) FROM Enrollment e JOIN Section sec ON e.Section_Id = sec.Section_Id JOIN Course c ON sec.Course_ID = c.Course_ID WHERE e.Student_ID = p.Student_ID AND e.Semester = p.Semester AND e.Year = p.Year) AS EnrolledCredits
    FROM Payment p
    JOIN Student s ON p.Student_ID = s.Student_ID
    LEFT JOIN Department d ON s.Dept_ID = d.Dept_ID
    ORDER BY p.Payment_Id DESC
")->fetchAll();

// Statistics
$totalCollected = 0.0;
$totalPendingAmount = 0.0;
$pendingCount = 0;
$approvedCount = 0;

foreach ($payments as $p) {
    if ($p['Payment_Status'] === 'Paid') {
        $totalCollected += floatval($p['Amount']);
        $approvedCount++;
    } elseif ($p['Payment_Status'] === 'Pending') {
        $totalPendingAmount += floatval($p['Amount']);
        $pendingCount++;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <h1>💳 Financial Ledger & Fee Verification Portal</h1>
    <p>Review student semester fee submissions, inspect course-based assessments, and manage payment clearance statuses.</p>
</div>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>" style="margin-bottom: 24px;">
        <span><?= $msg ?></span>
    </div>
<?php endif; ?>

<!-- Summary Stats Cards -->
<div class="stats-grid" style="margin-bottom: 28px;">
    <div class="stat-card">
        <div class="stat-icon success">💰</div>
        <div class="stat-details">
            <h3>৳ <?= number_format($totalCollected, 2) ?></h3>
            <p>Total Revenue Verified & Collected</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning">⏳</div>
        <div class="stat-details">
            <h3><?= $pendingCount ?> <span style="font-size:14px; font-weight:normal; opacity:.7;">(৳ <?= number_format($totalPendingAmount, 2) ?>)</span></h3>
            <p>Submissions Awaiting Verification</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gold">✅</div>
        <div class="stat-details">
            <h3><?= $approvedCount ?></h3>
            <p>Verified Transactions</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon primary">📊</div>
        <div class="stat-details">
            <h3><?= count($payments) ?></h3>
            <p>Total Transactions Recorded</p>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
        <div class="panel-title">💳 Student Semester Fee Submissions & Verification Queue</div>
        
        <!-- Live Search -->
        <input type="text" id="admin_pay_search" class="form-control" placeholder="🔍 Search student, TrxID..." style="width: 250px; font-size: 13px;" onkeyup="filterAdminPayments()">
    </div>

    <!-- Filter Pills Bar -->
    <div style="background: #f8fafc; padding: 12px 20px; border-bottom: 1px solid var(--border-color); display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
        <span style="font-size: 13px; font-weight: 600; color: var(--text-muted);">Status Filter:</span>
        <button type="button" class="btn btn-sm btn-primary filter-tab-btn active" onclick="setPaymentStatusFilter('', this)">
            All Transactions (<?= count($payments) ?>)
        </button>
        <button type="button" class="btn btn-sm btn-secondary filter-tab-btn" onclick="setPaymentStatusFilter('Pending', this)">
            ⏳ Pending Verification (<?= $pendingCount ?>)
        </button>
        <button type="button" class="btn btn-sm btn-secondary filter-tab-btn" onclick="setPaymentStatusFilter('Paid', this)">
            ✅ Verified / Paid (<?= $approvedCount ?>)
        </button>
        <button type="button" class="btn btn-sm btn-secondary filter-tab-btn" onclick="setPaymentStatusFilter('Failed', this)">
            ❌ Failed / Rejected
        </button>
    </div>

    <div class="panel-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="data-table" id="admin_payments_table">
                <thead>
                    <tr>
                        <th>Trx ID & Date</th>
                        <th>Student Information</th>
                        <th>Semester / Term</th>
                        <th>Enrolled Courses</th>
                        <th style="text-align: right;">Amount (BDT)</th>
                        <th style="text-align: center;">Status</th>
                        <th style="text-align: center; width: 220px;">Verification Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                No payment transactions recorded yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $p):
                            $credits = floatval($p['EnrolledCredits'] ?? 0);
                            $estTuition = ($credits * EWU_PER_CREDIT_RATE) + ($credits > 0 ? EWU_FACILITY_FEE : 0);
                        ?>
                            <tr data-student-id="<?= htmlspecialchars($p['Student_ID']) ?>"
                                data-student-name="<?= htmlspecialchars($p['First_name'] . ' ' . $p['Last_name']) ?>"
                                data-trx="<?= htmlspecialchars($p['Transaction_Id']) ?>"
                                data-status="<?= htmlspecialchars($p['Payment_Status']) ?>">
                                <td>
                                    <code style="font-size: 13px; font-weight: 700; color: var(--primary);">
                                        <?= htmlspecialchars($p['Transaction_Id']) ?>
                                    </code>
                                    <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">
                                        📅 <?= $p['Payment_Date'] ? date('d M Y', strtotime($p['Payment_Date'])) : 'Submitted recently' ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), #3b82f6); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; flex-shrink: 0;">
                                            <?= strtoupper(substr($p['First_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <strong><?= htmlspecialchars($p['First_name'] . ' ' . $p['Last_name']) ?></strong>
                                            <div style="font-size: 11px; color: var(--text-muted);">
                                                <code><?= htmlspecialchars($p['Student_ID']) ?></code> • <?= htmlspecialchars($p['Dept_Name'] ?? 'General') ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong style="color: var(--primary);"><?= htmlspecialchars($p['Semester']) ?> <?= htmlspecialchars($p['Year']) ?></strong>
                                </td>
                                <td>
                                    <span class="badge badge-info" style="font-size: 12px;">
                                        <?= $p['CourseCount'] ?> Course(s) • <?= number_format($credits, 1) ?> Cr
                                    </span>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                        Assessed: ৳ <?= number_format($estTuition, 2) ?>
                                    </div>
                                </td>
                                <td style="text-align: right;">
                                    <strong style="font-size: 14px; color: var(--primary);">
                                        ৳ <?= number_format($p['Amount'], 2) ?>
                                    </strong>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($p['Payment_Status'] === 'Paid'): ?>
                                        <span class="badge badge-success" style="padding: 6px 12px; font-size: 12px;">
                                            ✅ Verified & Paid
                                        </span>
                                    <?php elseif ($p['Payment_Status'] === 'Pending'): ?>
                                        <span class="badge badge-warning" style="padding: 6px 12px; font-size: 12px;">
                                            ⏳ Pending Approval
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-danger" style="padding: 6px 12px; font-size: 12px;">
                                            ❌ Failed / Rejected
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <div style="display: flex; justify-content: center; gap: 6px; flex-wrap: wrap;">
                                        <?php if ($p['Payment_Status'] === 'Pending'): ?>
                                            <!-- Quick Approve -->
                                            <a href="payments.php?quick_approve=<?= $p['Payment_Id'] ?>"
                                               class="btn btn-sm btn-success" style="font-weight: 600; padding: 4px 10px; font-size: 12px;"
                                               onclick="return confirm('Verify and approve payment of ৳ <?= number_format($p['Amount'], 2) ?> for <?= htmlspecialchars($p['Student_ID']) ?>?')">
                                                ✅ Approve
                                            </a>
                                            <!-- Quick Reject -->
                                            <a href="payments.php?quick_reject=<?= $p['Payment_Id'] ?>"
                                               class="btn btn-sm btn-danger" style="font-weight: 600; padding: 4px 10px; font-size: 12px;"
                                               onclick="return confirm('Reject this payment submission?')">
                                                ❌ Reject
                                            </a>
                                        <?php else: ?>
                                            <!-- Status change form -->
                                            <form action="payments.php" method="POST" style="display: flex; gap: 4px;">
                                                <input type="hidden" name="payment_id" value="<?= $p['Payment_Id'] ?>">
                                                <select name="payment_status" class="form-control" style="width: 95px; font-size: 11.5px; padding: 4px 6px;">
                                                    <option value="Paid" <?= $p['Payment_Status'] === 'Paid' ? 'selected' : '' ?>>Paid</option>
                                                    <option value="Pending" <?= $p['Payment_Status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                    <option value="Failed" <?= $p['Payment_Status'] === 'Failed' ? 'selected' : '' ?>>Failed</option>
                                                </select>
                                                <button type="submit" name="update_status" class="btn btn-sm btn-secondary" style="padding: 4px 8px; font-size: 11px;">
                                                    Save
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let currentStatusFilter = '';

function setPaymentStatusFilter(status, btnEl) {
    currentStatusFilter = status;

    // Update active button state
    document.querySelectorAll('.filter-tab-btn').forEach(btn => {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-secondary');
    });
    btnEl.classList.remove('btn-secondary');
    btnEl.classList.add('btn-primary');

    filterAdminPayments();
}

function filterAdminPayments() {
    const searchVal = document.getElementById('admin_pay_search').value.toLowerCase();
    const rows = document.querySelectorAll('#admin_payments_table tbody tr');

    rows.forEach(row => {
        const studentName = (row.getAttribute('data-student-name') || '').toLowerCase();
        const studentId   = (row.getAttribute('data-student-id') || '').toLowerCase();
        const trx         = (row.getAttribute('data-trx') || '').toLowerCase();
        const status      = row.getAttribute('data-status') || '';

        let matchesSearch = (!searchVal || studentName.includes(searchVal) || studentId.includes(searchVal) || trx.includes(searchVal));
        let matchesStatus = (!currentStatusFilter || status === currentStatusFilter);

        row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
