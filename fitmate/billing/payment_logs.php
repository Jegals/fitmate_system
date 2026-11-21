<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Payment Logs";

$search = trim($_GET['search'] ?? '');
$sql = "
    SELECT p.*, CONCAT(m.FirstName, ' ', m.LastName) AS FullName
    FROM payment p
    JOIN gym_member m ON p.MemberID = m.MemberID
";
$params = [];

if ($search !== '') {
    $sql .= " WHERE p.ReferenceNumber LIKE ? OR p.PaymentID LIKE ? OR CONCAT(m.FirstName, ' ', m.LastName) LIKE ?";
    $like = "%{$search}%";
    $params = [$like, $like, $like];
}
$sql .= " ORDER BY p.PaymentDate DESC, p.PaymentTime DESC, p.PaymentID DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <h3 style="margin-bottom: 12px;">Payment Transaction History (<?= count($payments) ?> Transactions)</h3>
    <form method="GET" class="search-bar">
        <input type="text" id="paySearchInput" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by Reference Number or Member Name...">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search !== ''): ?>
            <a href="payment_logs.php" class="btn btn-warning">Reset</a>
        <?php endif; ?>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>Ref #</th>
                <th>Member</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Date</th>
                <th>Time</th>
                <th>Receipt</th>
            </tr>
        </thead>
        <tbody id="payTableBody">
            <?php if (empty($payments)): ?>
                <tr><td colspan="7">No payment records found.</td></tr>
            <?php else: foreach ($payments as $p):
                $ref = $p['ReferenceNumber'] ?? $p['PaymentID'];
            ?>
                <tr>
                    <td><strong><?= htmlspecialchars($ref) ?></strong></td>
                    <td><?= htmlspecialchars($p['FullName']) ?></td>
                    <td>₱<?= number_format($p['Amount'], 2) ?></td>
                    <td><?= htmlspecialchars($p['PaymentMethod'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['PaymentDate'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['PaymentTime'] ?? '-') ?></td>
                    <td>
                        <button
                            type="button"
                            class="btn btn-primary btn-sm"
                            onclick="openReceiptModal(
                                '<?= htmlspecialchars($ref, ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($p['PaymentID'], ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($p['FullName'], ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($p['MemberID'], ENT_QUOTES) ?>',
                                '<?= number_format((float)$p['Amount'], 2) ?>',
                                '<?= htmlspecialchars($p['PaymentMethod'] ?? 'Cash', ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($p['PaymentDate'] ?? '-', ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($p['PaymentTime'] ?? '-', ENT_QUOTES) ?>'
                            )"
                        >
                            View Receipt
                        </button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- Official Receipt Pop-Up Modal -->
<div id="receiptModal" class="modal-backdrop">
    <div class="receipt-box">
        <div class="receipt-header">
            <h2 style="margin:0; letter-spacing:1px; color:#38bdf8;">FITMATE GYM</h2>
            <small style="color:#94a3b8;">Official Payment Receipt</small>
        </div>

        <div class="receipt-row"><span style="color:#94a3b8;">Reference #:</span><strong id="rec_ref"></strong></div>
        <div class="receipt-row"><span style="color:#94a3b8;">Payment ID:</span><span id="rec_pay_id"></span></div>
        <div class="receipt-row"><span style="color:#94a3b8;">Member Name:</span><strong id="rec_member"></strong></div>
        <div class="receipt-row"><span style="color:#94a3b8;">Member ID:</span><span id="rec_member_id"></span></div>
        <div class="receipt-row"><span style="color:#94a3b8;">Payment Method:</span><span id="rec_method"></span></div>
        <div class="receipt-row"><span style="color:#94a3b8;">Date & Time:</span><span id="rec_datetime"></span></div>

        <div class="receipt-total">
            <span>Amount Paid</span>
            <span id="rec_amount"></span>
        </div>

        <div style="display:flex; gap:10px; margin-top:20px;">
            <button type="button" class="btn btn-warning" style="flex:1;" onclick="closeReceiptModal()">Close</button>
            <button type="button" class="btn btn-primary" style="flex:1;" onclick="window.print()">Print Receipt</button>
        </div>
    </div>
</div>

<script>
function openReceiptModal(ref, payId, member, memberId, amount, method, date, time) {
    document.getElementById('rec_ref').textContent = ref;
    document.getElementById('rec_pay_id').textContent = payId;
    document.getElementById('rec_member').textContent = member;
    document.getElementById('rec_member_id').textContent = memberId;
    document.getElementById('rec_method').textContent = method;
    document.getElementById('rec_datetime').textContent = date + ' ' + time;
    document.getElementById('rec_amount').textContent = '₱' + amount;
    document.getElementById('receiptModal').classList.add('open');
}
function closeReceiptModal() {
    document.getElementById('receiptModal').classList.remove('open');
}
document.getElementById('receiptModal').addEventListener('click', function(e) {
    if (e.target === this) closeReceiptModal();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
