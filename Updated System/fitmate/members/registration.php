<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Member Registration";
$message = '';
$error = '';

// Helper function to auto-generate prefixed IDs (e.g., MEM101, EMG401, SUB1001)
function getNextPrefixedId(PDO $pdo, string $table, string $column, string $prefix, int $startAt): string {
    $prefixLen = strlen($prefix) + 1;
    $stmt = $pdo->query("
        SELECT {$column}
        FROM {$table}
        WHERE {$column} REGEXP '^{$prefix}[0-9]+$'
        ORDER BY CAST(SUBSTRING({$column}, {$prefixLen}) AS UNSIGNED) DESC
        LIMIT 1
    ");
    $lastId = $stmt->fetchColumn();
    $nextNum = $lastId ? ((int)substr($lastId, strlen($prefix)) + 1) : $startAt;
    return $prefix . $nextNum;
}

$plans = $pdo->query("SELECT PlanID, PlanName, DurationDays, Price FROM membership_plan ORDER BY Price ASC")->fetchAll(PDO::FETCH_ASSOC);

// Only Admin and Manager can process registration (exclude Maintenance and Trainer)
$staffList = $pdo->query("
    SELECT StaffID, FirstName, LastName, StaffType
    FROM gym_staff
    WHERE StaffType IN ('Admin', 'Manager')
    ORDER BY FirstName ASC, LastName ASC
")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['FirstName'] ?? '');
    $lastName  = trim($_POST['LastName'] ?? '');
    $dob       = trim($_POST['DateOfBirth'] ?? '');
    $phone     = trim($_POST['PhoneNumber'] ?? '');

    $contactName  = trim($_POST['ContactName'] ?? '');
    $relationship = trim($_POST['Relationship'] ?? '');
    $contactPhone = trim($_POST['ContactPhone'] ?? '');

    $planId        = trim($_POST['PlanID'] ?? '');
    $staffId       = trim($_POST['StaffID'] ?? ($_SESSION['StaffID'] ?? ''));
    $amount        = (float)($_POST['Amount'] ?? 0);
    $paymentMethod = trim($_POST['PaymentMethod'] ?? 'Cash');
    $refNumber     = trim($_POST['ReferenceNumber'] ?? '');

    $stmtPlan = $pdo->prepare("SELECT PlanID, PlanName, DurationDays, Price FROM membership_plan WHERE PlanID = ?");
    $stmtPlan->execute([$planId]);
    $planData = $stmtPlan->fetch(PDO::FETCH_ASSOC);

    // Verify selected staff is Admin or Manager
    $stmtStaffCheck = $pdo->prepare("SELECT 1 FROM gym_staff WHERE StaffID = ? AND StaffType IN ('Admin', 'Manager')");
    $stmtStaffCheck->execute([$staffId]);
    $validStaff = (bool)$stmtStaffCheck->fetchColumn();

    if (
        !$firstName || !$lastName || !$dob || !$phone ||
        !$contactName || !$relationship || !$contactPhone ||
        !$planData || !$validStaff || $amount <= 0
    ) {
        $error = "Please fill in all required fields, select an Admin or Manager, and enter a valid payment amount.";
    } else {
        $days = (int)$planData['DurationDays'];
        if ($days <= 0) {
            $days = 30;
        }

        $startDate = date('Y-m-d');
        $endDate   = date('Y-m-d', strtotime("+{$days} days"));
        $subStatus = 'Pending'; // Will be updated to 'Active' by sp_ProcessMemberPayment

        if ($refNumber === '') {
            $refNumber = 'REF-' . date('YmdHis') . '-' . rand(100, 999);
        }

        $pdo->beginTransaction();

        try {
            $memberId       = getNextPrefixedId($pdo, 'gym_member', 'MemberID', 'MEM', 101);
            $contactId      = getNextPrefixedId($pdo, 'emergency_contact', 'ContactID', 'EMG', 401);
            $subscriptionId = getNextPrefixedId($pdo, 'subscription', 'SubscriptionID', 'SUB', 1001);

            // 1. gym_member
            $stmtMember = $pdo->prepare("
                INSERT INTO gym_member (MemberID, FirstName, LastName, DateOfBirth, PhoneNumber, is_archived)
                VALUES (?, ?, ?, ?, ?, 0)
            ");
            $stmtMember->execute([$memberId, $firstName, $lastName, $dob, $phone]);

            // 2. emergency_contact
            $stmtContact = $pdo->prepare("
                INSERT INTO emergency_contact (ContactID, MemberID, ContactName, Relationship, PhoneNumber)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmtContact->execute([$contactId, $memberId, $contactName, $relationship, $contactPhone]);

            // 3. subscription
            $stmtSub = $pdo->prepare("
                INSERT INTO subscription (SubscriptionID, MemberID, PlanID, StartDate, EndDate, Status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtSub->execute([$subscriptionId, $memberId, $planId, $startDate, $endDate, $subStatus]);

            // 4. Initial payment & subscription activation via Stored Procedure
            $stmt = $pdo->prepare("CALL sp_ProcessMemberPayment(:member_id, :staff_id, :sub_id, :amount, :method, :ref_no)");
            $stmt->execute([
                ':member_id' => $memberId,
                ':staff_id'  => $staffId,
                ':sub_id'    => $subscriptionId,
                ':amount'    => $amount,
                ':method'    => $paymentMethod,
                ':ref_no'    => $refNumber
            ]);
            $stmt->closeCursor();

            $pdo->commit();
            $message = "Registered {$firstName} {$lastName} under {$planData['PlanName']} plan.";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Transaction failed and was rolled back: " . $e->getMessage();
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<style>
    .main-wrapper, .main-content {
        height: 100vh !important;
        overflow: hidden !important;
        display: flex !important;
        flex-direction: column !important;
    }
    .content-area, .container {
        flex: 1 !important;
        height: calc(100vh - 60px) !important;
        max-height: calc(100vh - 60px) !important;
        display: flex !important;
        flex-direction: column !important;
        overflow-y: auto !important;
        padding: 16px 22px !important;
        gap: 14px !important;
    }
    .reg-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        align-items: start;
    }
    .reg-grid > .card {
        margin-bottom: 0 !important;
    }
    .section-subtitle {
        color: #60a5fa;
        font-size: 14px;
        font-weight: 700;
        margin: 14px 0 10px 0;
        padding-top: 12px;
        border-top: 1px solid #1e293b;
    }
</style>

<?php if ($message !== ''): ?>
    <div style="background:rgba(22, 163, 74, 0.2); border:1px solid #22c55e; color:#86efac; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        ✓ <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div style="background:rgba(220, 38, 38, 0.2); border:1px solid #ef4444; color:#fca5a5; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        ✕ <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form method="POST" action="registration.php">
    <div class="reg-grid">
        <!-- LEFT CARD: MEMBER & EMERGENCY CONTACT -->
        <div class="card">
            <h3 style="margin-bottom: 14px; color: #ffffff;">Member Personal Information</h3>

            <div class="form-row">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="FirstName" placeholder="e.g. Juan" required>
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="LastName" placeholder="e.g. Dela Cruz" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="DateOfBirth" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="PhoneNumber" placeholder="e.g. 09171234567" required>
                </div>
            </div>

            <div class="section-subtitle">Emergency Contact Details</div>

            <div class="form-group">
                <label>Contact Full Name</label>
                <input type="text" name="ContactName" placeholder="e.g. Maria Dela Cruz" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Relationship</label>
                    <input type="text" name="Relationship" placeholder="e.g. Mother / Spouse" required>
                </div>
                <div class="form-group">
                    <label>Emergency Contact Phone</label>
                    <input type="text" name="ContactPhone" placeholder="e.g. 09181234567" required>
                </div>
            </div>
        </div>

        <!-- RIGHT CARD: SUBSCRIPTION & INITIAL PAYMENT -->
        <div class="card">
            <h3 style="margin-bottom: 14px; color: #ffffff;">Membership Plan & Initial Payment</h3>

            <div class="form-group">
                <label>Select Membership Plan</label>
                <select name="PlanID" id="planSelect" onchange="autoFillPlanPrice()" required>
                    <option value="">-- Choose Membership Plan --</option>
                    <?php foreach ($plans as $p): ?>
                        <option value="<?= htmlspecialchars($p['PlanID']) ?>" data-price="<?= htmlspecialchars($p['Price']) ?>">
                            <?= htmlspecialchars($p['PlanName']) ?> (<?= (int)$p['DurationDays'] ?> Days — ₱<?= number_format((float)$p['Price'], 2) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Processed By (Admin / Manager)</label>
                <select name="StaffID" required>
                    <option value="">-- Select Admin or Manager --</option>
                    <?php foreach ($staffList as $st): ?>
                        <option value="<?= htmlspecialchars($st['StaffID']) ?>">
                            <?= htmlspecialchars($st['FirstName'] . ' ' . $st['LastName'] . ' (' . $st['StaffType'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Amount Paid (₱)</label>
                    <input type="number" step="0.01" min="1" name="Amount" id="amountInput" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="PaymentMethod" required>
                        <option value="Cash">Cash</option>
                        <option value="GCash">GCash</option>
                        <option value="Maya">Maya</option>
                        <option value="Card">Card</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Reference Number <small style="color:#94a3b8;">(Leave blank to auto-generate)</small></label>
                <input type="text" name="ReferenceNumber" placeholder="Optional for Cash / e-Wallet Ref #">
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; padding:11px; margin-top:6px;">
                Complete Member Registration
            </button>
        </div>
    </div>
</form>

<script>
function autoFillPlanPrice() {
    const select = document.getElementById('planSelect');
    const selectedOption = select.options[select.selectedIndex];
    const price = selectedOption ? selectedOption.getAttribute('data-price') : '';
    if (price) {
        document.getElementById('amountInput').value = price;
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>