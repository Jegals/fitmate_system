<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Payment Logs";
$payments = $pdo->query("
    SELECT p.*, CONCAT(m.FirstName, ' ', m.LastName) AS FullName
    FROM payment p
    JOIN gym_member m ON p.MemberID = m.MemberID
    ORDER BY p.PaymentID DESC
")->fetchAll();

// TODO [Member 4]: Add Search by Ref#/Member & View Receipt Modal
include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <h3 style="margin-bottom: 12px;">Payment Transaction History (<?= count($payments) ?> Transactions)</h3>
    <form method="GET" class="search-bar">
        <input type="text" placeholder="Search by Reference Number or Member Name...">
        <button type="button" class="btn btn-primary">Search</button>
    </form>

    <table class="table">
        <thead><tr><th>Ref #</th><th>Member</th><th>Amount</th><th>Method</th><th>Date</th><th>Time</th><th>Receipt</th></tr></thead>
        <tbody>
            <?php if (empty($payments)): ?>
                <tr><td colspan="7">No payment records found.</td></tr>
            <?php else: foreach ($payments as $p): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($p['ReferenceNumber'] ?? $p['PaymentID']) ?></strong></td>
                    <td><?= htmlspecialchars($p['FullName']) ?></td>
                    <td>₱<?= number_format($p['Amount'], 2) ?></td>
                    <td><?= htmlspecialchars($p['PaymentMethod'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['PaymentDate'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['PaymentTime'] ?? '-') ?></td>
                    <td><button type="button" class="btn btn-primary btn-sm">View Receipt</button></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>