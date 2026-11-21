<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Member Subscriptions";
$success_msg = "";
$error_msg = "";

// Fetch available plans for the pop-up dropdown
$plans = $pdo->query("SELECT * FROM membership_plan ORDER BY Price ASC")->fetchAll();

// Handle Renew Subscription + Payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'renew_subscription') {
    $sub_id         = trim($_POST['subscription_id'] ?? '');
    $member_id      = trim($_POST['member_id'] ?? '');
    $plan_id        = trim($_POST['plan_id'] ?? '');
    $start_date     = trim($_POST['start_date'] ?? date('Y-m-d'));
    $payment_method = trim($_POST['payment_method'] ?? 'Cash');
    $ref_number     = trim($_POST['reference_number'] ?? '');

    try {
        // Look up selected plan details
        $planStmt = $pdo->prepare("SELECT * FROM membership_plan WHERE PlanID = ?");
        $planStmt->execute([$plan_id]);
        $selectedPlan = $planStmt->fetch();

        if (!$selectedPlan) {
            throw new Exception("Selected membership plan was not found.");
        }

        $duration_days = (int)$selectedPlan['DurationDays'];
        $amount        = (float)$selectedPlan['Price'];
        $end_date      = date('Y-m-d', strtotime($start_date . " + {$duration_days} days"));

        if ($ref_number === '') {
            $ref_number = 'REF-' . date('Ymd') . '-' . rand(1000, 9999);
        }

        $pdo->beginTransaction();

        // 1. Update the subscription record with the new plan, dates, and Active status
        $updSub = $pdo->prepare("
            UPDATE subscription
            SET PlanID = ?, StartDate = ?, EndDate = ?, Status = 'Active'
            WHERE SubscriptionID = ?
        ");
        $updSub->execute([$plan_id, $start_date, $end_date, $sub_id]);

        // 2. Generate next PaymentID (supports string IDs like PAY001 or numeric auto-increment)
        $lastPay = $pdo->query("SELECT PaymentID FROM payment ORDER BY PaymentID DESC LIMIT 1")->fetchColumn();
        if ($lastPay && preg_match('/^([A-Za-z]+)(\d+)$/', $lastPay, $m)) {
            $nextNum = str_pad((int)$m[2] + 1, strlen($m[2]), '0', STR_PAD_LEFT);
            $newPayId = $m[1] . $nextNum;
        } else {
            $newPayId = 'PAY' . rand(1000, 9999);
        }

        // Check if payment table has SubscriptionID or StaffID columns dynamically
        $payCols = $pdo->query("SHOW COLUMNS FROM payment")->fetchAll(PDO::FETCH_COLUMN);

        $insertCols = ['PaymentID', 'MemberID', 'Amount', 'PaymentMethod', 'ReferenceNumber', 'PaymentDate', 'PaymentTime'];
        $insertVals = [$newPayId, $member_id, $amount, $payment_method, $ref_number, date('Y-m-d'), date('H:i:s')];

        if (in_array('SubscriptionID', $payCols, true)) {
            $insertCols[] = 'SubscriptionID';
            $insertVals[] = $sub_id;
        }
        if (in_array('StaffID', $payCols, true) && isset($_SESSION['staff_id'])) {
            $insertCols[] = 'StaffID';
            $insertVals[] = $_SESSION['staff_id'];
        }

        $placeholders = implode(', ', array_fill(0, count($insertCols), '?'));
        $colList      = implode(', ', $insertCols);

        $insPay = $pdo->prepare("INSERT INTO payment ({$colList}) VALUES ({$placeholders})");
        $insPay->execute($insertVals);

        $pdo->commit();
        $success_msg = "Subscription {$sub_id} renewed ({$selectedPlan['PlanName']}) and ₱" . number_format($amount, 2) . " payment recorded (Ref: {$ref_number}).";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_msg = "Error processing renewal: " . $e->getMessage();
    }
}

// Handle Search Filter
$search = trim($_GET['search'] ?? '');
$sql = "
    SELECT s.*, CONCAT(m.FirstName, ' ', m.LastName) AS FullName, mp.PlanName, mp.Price
    FROM subscription s
    JOIN gym_member m ON s.MemberID = m.MemberID
    JOIN membership_plan mp ON s.PlanID = mp.PlanID
";
$params = [];

if ($search !== '') {
    $sql .= " WHERE s.SubscriptionID LIKE ? OR s.MemberID LIKE ? OR CONCAT(m.FirstName, ' ', m.LastName) LIKE ? OR mp.PlanName LIKE ?";
    $like = "%{$search}%";
    $params = [$like, $like, $like, $like];
}
$sql .= " ORDER BY s.EndDate DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$subs = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<?php if ($success_msg): ?>
    <div class="alert-success"><?= htmlspecialchars($success_msg) ?></div>
<?php endif; ?>
<?php if ($error_msg): ?>
    <div class="alert-error"><?= htmlspecialchars($error_msg) ?></div>
<?php endif; ?>

<div class="card">
    <h3 style="margin-bottom: 12px;">Subscription Status & Renewals (<?= count($subs) ?> Records)</h3>
    <form method="GET" class="search-bar">
        <input type="text" id="subSearchInput" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search subscription by Member Name, Sub ID, or Plan...">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search !== ''): ?>
            <a href="subscriptions.php" class="btn btn-warning">Reset</a>
        <?php endif; ?>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>Sub ID</th>
                <th>Member</th>
                <th>Plan</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody id="subTableBody">
            <?php if (empty($subs)): ?>
                <tr><td colspan="7">No subscription records found.</td></tr>
            <?php else: foreach ($subs as $s):
                $today = date('Y-m-d');
                $soon  = date('Y-m-d', strtotime('+7 days'));

                if ($s['EndDate'] < $today) {
                    $status = 'Expired';
                    $badgeClass = 'badge-red';
                } elseif ($s['EndDate'] <= $soon) {
                    $status = 'Expiring Soon';
                    $badgeClass = 'badge-yellow';
                } else {
                    $status = 'Active';
                    $badgeClass = 'badge-green';
                }
            ?>
                <tr>
                    <td><strong><?= htmlspecialchars($s['SubscriptionID']) ?></strong></td>
                    <td><?= htmlspecialchars($s['FullName']) ?></td>
                    <td><?= htmlspecialchars($s['PlanName']) ?></td>
                    <td><?= htmlspecialchars($s['StartDate']) ?></td>
                    <td><?= htmlspecialchars($s['EndDate']) ?></td>
                    <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span></td>
                    <td>
                        <button
                            type="button"
                            class="btn btn-primary btn-sm"
                            onclick="openRenewModal(
                                '<?= htmlspecialchars($s['SubscriptionID'], ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($s['MemberID'], ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($s['FullName'], ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($s['PlanID'], ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($s['EndDate'], ENT_QUOTES) ?>'
                            )"
                        >
                            Renew Plan
                        </button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- Renew & Pay Pop-Up Modal -->
<div id="renewModal" class="modal-backdrop">
    <div class="modal-box">
        <div class="modal-header">
            <h3 style="margin:0;">Renew Subscription & Pay</h3>
            <button type="button" class="modal-close" onclick="closeRenewModal()">&times;</button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="renew_subscription">
            <input type="hidden" name="subscription_id" id="modal_sub_id">
            <input type="hidden" name="member_id" id="modal_member_id">

            <div class="form-group">
                <label>Member</label>
                <input type="text" id="modal_member_display" readonly style="opacity:0.85;">
            </div>

            <div class="form-group">
                <label>Select Membership Plan</label>
                <select name="plan_id" id="modal_plan_id" required onchange="recalculateRenewal()">
                    <?php foreach ($plans as $pl): ?>
                        <option
                            value="<?= htmlspecialchars($pl['PlanID']) ?>"
                            data-days="<?= (int)$pl['DurationDays'] ?>"
                            data-price="<?= (float)$pl['Price'] ?>"
                        >
                            <?= htmlspecialchars($pl['PlanName']) ?> — <?= (int)$pl['DurationDays'] ?> Days (₱<?= number_format((float)$pl['Price'], 2) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>New Start Date</label>
                <input type="date" name="start_date" id="modal_start_date" required onchange="recalculateRenewal()">
            </div>

            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method" id="modal_payment_method" required>
                    <option value="Cash">Cash</option>
                    <option value="GCash">GCash</option>
                    <option value="Maya">Maya</option>
                    <option value="Card">Debit / Credit Card</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                </select>
            </div>

            <div class="form-group">
                <label>Reference Number <small style="color:#94a3b8;">(auto-generated if left blank)</small></label>
                <input type="text" name="reference_number" id="modal_ref_number" placeholder="e.g. GCASH-984312">
            </div>

            <div class="summary-box">
                <div class="summary-row">
                    <span>Duration:</span>
                    <span id="summary_days">30 Days</span>
                </div>
                <div class="summary-row">
                    <span>New End Date:</span>
                    <span id="summary_end_date">-</span>
                </div>
                <div class="summary-row">
                    <span>Total Amount Due:</span>
                    <span id="summary_amount">₱0.00</span>
                </div>
            </div>

            <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-warning" style="flex:1;" onclick="closeRenewModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:2;">Confirm Renewal & Pay</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRenewModal(subId, memberId, memberName, currentPlanId, currentEndDate) {
    document.getElementById('modal_sub_id').value = subId;
    document.getElementById('modal_member_id').value = memberId;
    document.getElementById('modal_member_display').value = memberName + ' (' + subId + ')';

    const planSelect = document.getElementById('modal_plan_id');
    planSelect.value = currentPlanId;

    // If subscription is still active, start renewal from current end date; if expired, start today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('modal_start_date').value = (currentEndDate && currentEndDate > today) ? currentEndDate : today;
    document.getElementById('modal_ref_number').value = '';

    recalculateRenewal();
    document.getElementById('renewModal').classList.add('open');
}

function closeRenewModal() {
    document.getElementById('renewModal').classList.remove('open');
}

function recalculateRenewal() {
    const planSelect = document.getElementById('modal_plan_id');
    const selected = planSelect.options[planSelect.selectedIndex];
    if (!selected) return;

    const days = parseInt(selected.getAttribute('data-days') || '0', 10);
    const price = parseFloat(selected.getAttribute('data-price') || '0');
    const startVal = document.getElementById('modal_start_date').value;

    document.getElementById('summary_days').textContent = days + ' Days';
    document.getElementById('summary_amount').textContent = '₱' + price.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    if (startVal) {
        const d = new Date(startVal + 'T00:00:00');
        d.setDate(d.getDate() + days);
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        document.getElementById('summary_end_date').textContent = `${yyyy}-${mm}-${dd}`;
    }
}

// Close modal when clicking outside the modal box
document.getElementById('renewModal').addEventListener('click', function(e) {
    if (e.target === this) closeRenewModal();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
