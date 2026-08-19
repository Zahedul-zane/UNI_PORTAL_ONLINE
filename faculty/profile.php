<?php
$pageTitle = "Faculty Profile";
require_once __DIR__ . '/../config/auth.php';
require_role('faculty');

$facultyId = $_SESSION['user_id'];
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roomNo = sanitize($_POST['room_no'] ?? '');
    $phone1 = sanitize($_POST['phone1'] ?? '');
    $phone2 = sanitize($_POST['phone2'] ?? '');
    $newPass = $_POST['new_password'] ?? '';

    // Update Room No
    $stmt = $pdo->prepare("UPDATE Faculty SET Room_No = ? WHERE Faculty_ID = ?");
    $stmt->execute([$roomNo, $facultyId]);

    // Update or Insert Phone Numbers
    $stmtPhoneCheck = $pdo->prepare("SELECT * FROM Faculty_PhoneNum WHERE Faculty_ID = ?");
    $stmtPhoneCheck->execute([$facultyId]);
    if ($stmtPhoneCheck->fetch()) {
        $stmtP = $pdo->prepare("UPDATE Faculty_PhoneNum SET Phone_Number1 = ?, Phone_Number2 = ? WHERE Faculty_ID = ?");
        $stmtP->execute([$phone1, $phone2, $facultyId]);
    } else {
        $stmtP = $pdo->prepare("INSERT INTO Faculty_PhoneNum (Faculty_ID, Phone_Number1, Phone_Number2) VALUES (?, ?, ?)");
        $stmtP->execute([$facultyId, $phone1, $phone2]);
    }

    // Update Password if provided
    if (!empty($newPass)) {
        $hashed = password_hash($newPass, PASSWORD_BCRYPT);
        $stmtPass = $pdo->prepare("UPDATE Faculty SET Password = ? WHERE Faculty_ID = ?");
        $stmtPass->execute([$hashed, $facultyId]);
    }

    $msg = 'Faculty profile updated successfully!';
    $msgType = 'success';
}

$profile = get_full_user_profile($pdo);
if (!is_array($profile)) $profile = [];

include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <h1>Faculty Profile & Office Hours</h1>
    <p>Manage your faculty record, room location, and contact parameters.</p>
</div>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>">
        <span><?= htmlspecialchars($msg) ?></span>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 25px;">

    <!-- Faculty Card -->
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">👨‍🏫 Faculty Card</div>
        </div>
        <div class="panel-body">
            <div style="text-align: center; margin-bottom: 20px;">
                <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, var(--primary) 0%, var(--gold) 100%); color: #fff; font-size: 32px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px;">
                    <?= strtoupper(substr($profile['First_name'] ?? 'F', 0, 1)) ?>
                </div>
                <h3 style="font-size: 18px; color: var(--primary);"><?= htmlspecialchars(($profile['First_name'] ?? 'Faculty') . ' ' . ($profile['Last_name'] ?? '')) ?></h3>
                <span class="badge badge-gold" style="margin-top: 4px;"><?= htmlspecialchars($profile['Designation'] ?? 'Faculty Member') ?></span>
                <?php if (!empty($profile['Is_Head'])): ?>
                    <span class="badge badge-success" style="display: block; width: fit-content; margin: 6px auto;">Head of Department</span>
                <?php endif; ?>
            </div>

            <div style="border-top: 1px solid var(--border-color); padding-top: 15px; font-size: 13px;">
                <div style="margin-bottom: 10px;">
                    <strong style="color: var(--text-secondary);">Faculty ID:</strong><br>
                    <code><?= htmlspecialchars($profile['Faculty_ID'] ?? $facultyId) ?></code>
                </div>
                <div style="margin-bottom: 10px;">
                    <strong style="color: var(--text-secondary);">Department:</strong><br>
                    <span><?= htmlspecialchars($profile['Dept_Name'] ?? 'N/A') ?></span>
                </div>
                <div style="margin-bottom: 10px;">
                    <strong style="color: var(--text-secondary);">Official Email:</strong><br>
                    <span><?= htmlspecialchars($profile['E_mail'] ?? 'N/A') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Form -->
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">✏️ Update Faculty Details</div>
        </div>
        <div class="panel-body">
            <form action="profile.php" method="POST">
                
                <div class="form-group">
                    <label for="room_no">Office Room Number</label>
                    <input type="text" name="room_no" id="room_no" class="form-control" value="<?= htmlspecialchars($profile['Room_No'] ?? '') ?>" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label for="phone1">Primary Phone Number (*)</label>
                        <input type="text" name="phone1" id="phone1" class="form-control" value="<?= htmlspecialchars($profile['Phone_Number1'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="phone2">Secondary Phone Number (Optional)</label>
                        <input type="text" name="phone2" id="phone2" class="form-control" value="<?= htmlspecialchars($profile['Phone_Number2'] ?? '') ?>">
                    </div>
                </div>

                <hr style="margin: 20px 0; border: none; border-top: 1px solid var(--border-color);">

                <div class="form-group">
                    <label for="new_password">Change Account Password (Leave blank to keep current)</label>
                    <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Enter new password">
                </div>

                <button type="submit" class="btn btn-gold" style="padding: 12px 24px;">
                    💾 Update Profile
                </button>
            </form>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
