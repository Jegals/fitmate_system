<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Member Subscriptions";
$subs = $pdo->query("
    SELECT s.*, CONCAT(m.FirstName, ' ', m.LastName) AS FullName, mp.PlanName
    FROM subscription s
    JOIN gym_member m ON s.MemberID = m.MemberID
    JOIN membership_plan mp ON s.PlanID = mp.PlanID
    ORDER BY s.EndDate DESC
")->fetchAll();

// TODO [Member 4]: Add Search & Renew Subscription + Payment transaction
include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <h3 style="margin-bottom: 12px;">Subscription Status & Renewals (<?= count($subs) ?> Records)</h3>
    <form method="GET" class="search-bar">
        <input type="text" placeholder="Search subscription by Member Name or ID...">
        <button type="button" class="btn btn-primary">Search</button>
    </form>

    <table class="table">
        <thead><tr><th>Sub ID</th><th>Member</th><th>Plan</th><th>Start Date</th><th>End Date</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
            <?php if (empty($subs)): ?>
                <tr><td colspan="7">No subscription records found.</td></tr>
            <?php else: foreach ($subs as $s): 
                $status = $s['Status'] ?? ($s['EndDate'] >= date('Y-m-d') ? 'Active' : 'Expired');
            ?>
                <tr>
                    <td><strong><?= htmlspecialchars($s['SubscriptionID']) ?></strong></td>
                    <td><?= htmlspecialchars($s['FullName']) ?></td>
                    <td><?= htmlspecialchars($s['PlanName']) ?></td>
                    <td><?= htmlspecialchars($s['StartDate']) ?></td>
                    <td><?= htmlspecialchars($s['EndDate']) ?></td>
                    <td><span class="badge <?= $status === 'Active' ? 'badge-green' : 'badge-red' ?>"><?= htmlspecialchars($status) ?></span></td>
                    <td><button type="button" class="btn btn-primary btn-sm">Renew Plan</button></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>