<?php
$pageTitle = "Student Profile";
require_once __DIR__ . '/../config/auth.php';
require_role('student');

$studentId = $_SESSION['user_id'];
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address = sanitize($_POST['address'] ?? '');
    $phone1 = sanitize($_POST['phone1'] ?? '');
    $phone2 = sanitize($_POST['phone2'] ?? '');
    $newPass = $_POST['new_password'] ?? '';

    // Update Address
    $stmt = $pdo->prepare("UPDATE Student SET Address = ? WHERE Student_ID = ?");
    $stmt->execute([$address, $studentId]);

    // Update or Insert Phone Numbers
    $stmtPhoneCheck = $pdo->prepare("SELECT * FROM Student_PhoneNum WHERE Student_ID = ?");
    $stmtPhoneCheck->execute([$studentId]);
    if ($stmtPhoneCheck->fetch()) {
        $stmtP = $pdo->prepare("UPDATE Student_PhoneNum SET Phone_Number1 = ?, Phone_Number2 = ? WHERE Student_ID = ?");
        $stmtP->execute([$phone1, $phone2, $studentId]);
    } else {
        $stmtP = $pdo->prepare("INSERT INTO Student_PhoneNum (Student_ID, Phone_Number1, Phone_Number2) VALUES (?, ?, ?)");
        $stmtP->execute([$studentId, $phone1, $phone2]);
    }

    // Update Password if provided
    if (!empty($newPass)) {
        $hashed = password_hash($newPass, PASSWORD_BCRYPT);
        $stmtPass = $pdo->prepare("UPDATE Student SET Password = ? WHERE Student_ID = ?");
        $stmtPass->execute([$hashed, $studentId]);
    }

    $msg = 'Profile details updated successfully!';
    $msgType = 'success';
}

$profile = get_full_user_profile($pdo);
if (!is_array($profile)) $profile = [];

include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <h1>Student Profile & Settings</h1>
    <p>Manage your official record, contact details, and account security.</p>
</div>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>">
        <span><?= htmlspecialchars($msg) ?></span>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 25px;">

    <!-- Left Card: Academic Identity -->
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">👤 Academic Identification</div>
        </div>
        <div class="panel-body">
            <div style="text-align: center; margin-bottom: 20px;">
                <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, var(--primary) 0%, var(--gold) 100%); color: #fff; font-size: 32px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px; border: 3px solid var(--gold-light);">
                    <?= strtoupper(substr($profile['First_name'] ?? 'S', 0, 1)) ?>
                </div>
                <h3 style="font-size: 18px; color: var(--primary);"><?= htmlspecialchars(($profile['First_name'] ?? 'Student') . ' ' . ($profile['Last_name'] ?? '')) ?></h3>
                <span class="badge badge-info" style="margin-top: 4px;"><?= htmlspecialchars($profile['Student_ID'] ?? $studentId) ?></span>
            </div>

            <div style="border-top: 1px solid var(--border-color); padding-top: 15px; font-size: 13px;">
                <div style="margin-bottom: 10px;">
                    <strong style="color: var(--text-secondary);">Department:</strong><br>
                    <span><?= htmlspecialchars($profile['Dept_Name'] ?? 'N/A') ?></span>
                </div>
                <div style="margin-bottom: 10px;">
                    <strong style="color: var(--text-secondary);">Institutional Email:</strong><br>
                    <span><?= htmlspecialchars($profile['E_mail'] ?? 'N/A') ?></span>
                </div>
                <div style="margin-bottom: 10px;">
                    <strong style="color: var(--text-secondary);">Date of Birth:</strong><br>
                    <span><?= htmlspecialchars($profile['DOB'] ?? 'N/A') ?></span>
                </div>
                <div>
                    <strong style="color: var(--text-secondary);">Faculty Advisor:</strong><br>
                    <span><?= htmlspecialchars($profile['Advisor_Name'] ?? 'Unassigned') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Card: Edit Information Form -->
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">✏️ Update Profile Information</div>
        </div>
        <div class="panel-body">
            <form action="profile.php" method="POST">
                
                <div class="form-group">
                    <label for="address">Residential Address</label>
                    <textarea name="address" id="address" class="form-control" rows="3" required><?= htmlspecialchars($profile['Address'] ?? '') ?></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label for="phone1">Primary Contact Number (*)</label>
                        <input type="text" name="phone1" id="phone1" class="form-control" value="<?= htmlspecialchars($profile['Phone_Number1'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="phone2">Secondary Contact Number (Optional)</label>
                        <input type="text" name="phone2" id="phone2" class="form-control" value="<?= htmlspecialchars($profile['Phone_Number2'] ?? '') ?>">
                    </div>
                </div>

                <hr style="margin: 20px 0; border: none; border-top: 1px solid var(--border-color);">

                <h4 style="font-size: 15px; margin-bottom: 15px; color: var(--primary);">Security Settings</h4>

                <div class="form-group">
                    <label for="new_password">Change Password (Leave blank to keep current)</label>
                    <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Enter new password">
                </div>

                <button type="submit" class="btn btn-gold" style="padding: 12px 24px;">
                    💾 Save Profile Changes
                </button>
            </form>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
