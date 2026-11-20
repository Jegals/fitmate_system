<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Membership Plans";
$plans = $pdo->query("SELECT * FROM membership_plan ORDER BY Price ASC")->fetchAll();

// TODO [Member 4]: Add Search, Insert Plan, and Edit Plan
include __DIR__ . '/../includes/header.php';
?>

<div class="grid-2">
    <div class="card">
        <h3 style="margin-bottom: 14px;">Add Membership Plan</h3>
        <form method="POST">
            <div class="form-group"><label>Plan Name</label><input type="text" placeholder="e.g. Monthly Basic"></div>
            <div class="form-group"><label>Duration (Days)</label><input type="number" placeholder="30"></div>
            <div class="form-group"><label>Price (₱)</label><input type="number" step="0.01" placeholder="1500.00"></div>
            <button type="button" class="btn btn-primary" style="width:100%;">Save Plan</button>
        </form>
    </div>

    <div class="card">
        <h3 style="margin-bottom: 12px;">Available Membership Plans (<?= count($plans) ?> Plans)</h3>
        <table class="table">
            <thead><tr><th>Plan ID</th><th>Plan Name</th><th>Duration</th><th>Price</th><th>Action</th></tr></thead>
            <tbody>
                <?php if (empty($plans)): ?>
                    <tr><td colspan="5">No membership plans found.</td></tr>
                <?php else: foreach ($plans as $p): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($p['PlanID']) ?></strong></td>
                        <td><?= htmlspecialchars($p['PlanName']) ?></td>
                        <td><?= htmlspecialchars($p['DurationDays']) ?> Days</td>
                        <td>₱<?= number_format($p['Price'] ?? 0, 2) ?></td>
                        <td><button type="button" class="btn btn-warning btn-sm">Edit</button></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>