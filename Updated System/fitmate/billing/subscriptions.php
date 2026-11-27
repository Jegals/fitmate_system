<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Member Subscriptions";
$success_msg = "";
$error_msg = "";

// Fetch available plans for the pop-up dropdown
$plans = $pdo->query("SELECT * FROM membership_plan ORDER BY Price ASC")->fetchAll();

// Handle Renew Subscription + Payment submission using Stored Procedure
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'renew_subscription') {
    $sub_id         = trim($_POST['subscription_id'] ?? '');
    $member_id      = trim($_POST['member_id'] ?? '');
    $plan_id        = trim($_POST['plan_id'] ?? '');
    $start_date     = trim($_POST['start_date'] ?? date('Y-m-d'));
    $payment_method = trim($_POST['payment_method'] ?? 'Cash');
    $ref_number     = trim($_POST['reference_number'] ?? '');
    $staff_id       = $_SESSION['StaffID'] ?? ($_SESSION['staff_id'] ?? 'AD-001');

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
            $ref_number = 'REF-' . date('YmdHis') . '-' . rand(100, 999);
        }

        $pdo->beginTransaction();

        // 1. Update subscription dates and selected plan (Status will be set to 'Active' by Stored Procedure)
        $updSub = $pdo->prepare("
            UPDATE subscription
            SET PlanID = ?, StartDate = ?, EndDate = ?
            WHERE SubscriptionID = ?
        ");
        $updSub->execute([$plan_id, $start_date, $end_date, $sub_id]);

        // 2. Call Stored Procedure to insert payment & set subscription Status = 'Active'
        $stmt = $pdo->prepare("CALL sp_ProcessMemberPayment(:member_id, :staff_id, :sub_id, :amount, :method, :ref_no)");
        $stmt->execute([
            ':member_id' => $member_id,
            ':staff_id'  => $staff_id,
            ':sub_id'    => $sub_id,
            ':amount'    => $amount,
            ':method'    => $payment_method,
            ':ref_no'    => $ref_number
        ]);
        $stmt->closeCursor();

        $pdo->commit();
        $success_msg = "Subscription renewed ({$selectedPlan['PlanName']}) and ₱" . number_format($amount, 2) . " payment recorded (Ref: {$ref_number}).";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_msg = "Error processing renewal: " . $e->getMessage();
    }
}

// Fetch subscriptions using SQL View: vw_member_subscriptions
$sql = "
    SELECT v.*, s.SubscriptionID, s.PlanID
    FROM vw_member_subscriptions v
    JOIN subscription s
      ON s.MemberID = v.MemberID
     AND s.StartDate = v.StartDate
     AND s.EndDate = v.EndDate
    ORDER BY v.EndDate DESC
";
$subs = $pdo->query($sql)->fetchAll();

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
        overflow: hidden !important;
        padding: 16px 22px !important;
        gap: 14px !important;
    }
    .sub-card {
        flex: 1 !important;
        display: flex !important;
        flex-direction: column !important;
        min-height: 0 !important;
        margin-bottom: 0 !important;
        overflow: hidden !important;
    }
    .fixed-table-wrap {
        border: 1px solid #1e293b;
        border-radius: 8px;
        background: #0c1324;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        flex: 1;
        min-height: 0;
    }
    .fixed-table {
        width: 100%;
        border-collapse: collapse;
        display: flex;
        flex-direction: column;
        flex: 1;
        min-height: 0;
    }
    .fixed-table thead {
        flex-shrink: 0;
        background: #111c35;
        border-bottom: 2px solid #2563eb;
    }
    .fixed-table thead tr,
    .fixed-table tbody tr {
        display: table;
        width: 100%;
        table-layout: fixed;
    }
    .fixed-table tbody {
        display: block;
        overflow-y: auto;
        width: 100%;
        flex: 1;
        min-height: 0;
    }
    .fixed-table th {
        padding: 11px 12px;
        text-align: left;
        font-size: 13px;
        font-weight: 700;
        color: #93c5fd;
        background: #111c35;
    }
    .fixed-table td {
        padding: 11px 12px;
        font-size: 13px;
        color: #f1f5f9;
        border-bottom: 1px solid #1e293b;
        vertical-align: middle;
    }
    .fixed-table tbody tr:hover td {
        background: #131d36;
    }
</style>

<?php if ($success_msg): ?>
    <div class="alert-success" style="margin-bottom:0; flex-shrink:0;"><?= htmlspecialchars($success_msg) ?></div>
<?php endif; ?>
<?php if ($error_msg): ?>
    <div class="alert-error" style="margin-bottom:0; flex-shrink:0;"><?= htmlspecialchars($error_msg) ?></div>
<?php endif; ?>

<div class="card sub-card">
    <h3 style="margin-bottom: 12px; flex-shrink: 0;">Subscription Status & Renewals</h3>
    <div class="search-bar" style="flex-shrink: 0;">
        <input
            type="text"
            id="liveSubSearch"
            onkeyup="filterSubTable()"
            oninput="filterSubTable()"
            placeholder="Search subscription by Member Name, Plan, or Status..."
        >
        <button type="button" class="btn btn-primary" onclick="filterSubTable()">Search</button>
    </div>

    <div class="fixed-table-wrap">
        <table class="fixed-table">
            <thead>
                <tr>
                    <th style="width: 24%;">Member</th>
                    <th style="width: 20%;">Plan</th>
                    <th style="width: 14%;">Start Date</th>
                    <th style="width: 14%;">End Date</th>
                    <th style="width: 14%;">Status</th>
                    <th style="width: 14%;">Action</th>
                </tr>
            </thead>
            <tbody id="subscriptionListBody">
                <?php if (empty($subs)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; color:#94a3b8; padding:18px;">
                            No matching subscription records found.
                        </td>
                    </tr>
                <?php else: foreach ($subs as $s):
                    $today = date('Y-m-d');
                    $soon  = date('Y-m-d', strtotime('+7 days'));
                    $memberName = $s['MemberName'] ?? $s['FullName'] ?? trim(($s['FirstName'] ?? '') . ' ' . ($s['LastName'] ?? ''));

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
                    <tr class="sub-row">
                        <td style="width: 24%;"><strong><?= htmlspecialchars($memberName) ?></strong></td>
                        <td style="width: 20%;"><?= htmlspecialchars($s['PlanName']) ?></td>
                        <td style="width: 14%;"><?= htmlspecialchars($s['StartDate']) ?></td>
                        <td style="width: 14%;"><?= htmlspecialchars($s['EndDate']) ?></td>
                        <td style="width: 14%;"><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span></td>
                        <td style="width: 14%;">
                            <?php if ($status === 'Expired'): ?>
                                <button
                                    type="button"
                                    class="btn btn-primary btn-sm"
                                    onclick="openRenewModal(
                                        '<?= htmlspecialchars($s['SubscriptionID'], ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($s['MemberID'], ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($memberName, ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($s['PlanID'], ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($s['EndDate'], ENT_QUOTES) ?>'
                                    )"
                                >
                                    Renew Plan
                                </button>
                            <?php else: ?>
                                <span style="color:#64748b;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                <tr id="noSubMatchRow" style="display: none;">
                    <td colspan="6" style="text-align:center; color:#94a3b8; padding:18px;">
                        No matching subscription records found.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
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
function filterSubTable() {
    const query = document.getElementById('liveSubSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#subscriptionListBody .sub-row');
    const noMatchRow = document.getElementById('noSubMatchRow');
    let visibleCount = 0;

    rows.forEach(row => {
        const memberName = row.cells[0].textContent.toLowerCase();
        const planName   = row.cells[1].textContent.toLowerCase();
        const startDate  = row.cells[2].textContent.toLowerCase();
        const endDate    = row.cells[3].textContent.toLowerCase();
        const status     = row.cells[4].textContent.toLowerCase();

        if (
            memberName.includes(query) ||
            planName.includes(query) ||
            startDate.includes(query) ||
            endDate.includes(query) ||
            status.includes(query)
        ) {
            row.style.display = 'table';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    if (noMatchRow) {
        noMatchRow.style.display = (rows.length > 0 && visibleCount === 0) ? 'table' : 'none';
    }
}

function openRenewModal(subId, memberId, memberName, currentPlanId, currentEndDate) {
    document.getElementById('modal_sub_id').value = subId;
    document.getElementById('modal_member_id').value = memberId;
    document.getElementById('modal_member_display').value = memberName;

    const planSelect = document.getElementById('modal_plan_id');
    planSelect.value = currentPlanId;

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

document.getElementById('renewModal').addEventListener('click', function(e) {
    if (e.target === this) closeRenewModal();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>