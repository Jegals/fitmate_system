<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Dashboard & Management Summary";

// Safe live counts mula sa fitmate_db
$insideCount   = $pdo->query("SELECT COUNT(*) FROM attendance WHERE TimeOut IS NULL")->fetchColumn();
$totalCheckins = $pdo->query("SELECT COUNT(*) FROM attendance")->fetchColumn();
$totalRevenue  = $pdo->query("SELECT COALESCE(SUM(Amount), 0) FROM payment")->fetchColumn();
$activeSubs    = $pdo->query("SELECT COUNT(*) FROM subscription WHERE EndDate >= CURDATE()")->fetchColumn();
$expiringSoon  = $pdo->query("SELECT COUNT(*) FROM subscription WHERE EndDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();

$expiringList = $pdo->query("
    SELECT CONCAT(m.FirstName, ' ', m.LastName) AS FullName, mp.PlanName, s.EndDate
    FROM subscription s
    JOIN gym_member m ON s.MemberID = m.MemberID
    JOIN membership_plan mp ON s.PlanID = mp.PlanID
    WHERE s.EndDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY s.EndDate ASC LIMIT 5
")->fetchAll();

$recentPayments = $pdo->query("
    SELECT p.*, CONCAT(m.FirstName, ' ', m.LastName) AS FullName
    FROM payment p
    JOIN gym_member m ON p.MemberID = m.MemberID
    ORDER BY p.PaymentID DESC LIMIT 5
")->fetchAll();

// TODO [Member 3]: Add Date-Range Filter backend logic
include __DIR__ . '/../includes/header.php';
?>

<style>
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 16px; margin-bottom: 20px; }
    .kpi-card { background: #0c1324; border: 1px solid #1e293b; border-radius: 8px; padding: 18px; border-left: 4px solid #2563eb; }
    .kpi-card.green { border-left-color: #16a34a; }
    .kpi-card.amber { border-left-color: #d97706; }
    .kpi-label { font-size: 11px; font-weight: 700; color: #60a5fa; text-transform: uppercase; }
    .kpi-val { font-size: 26px; font-weight: 700; margin-top: 6px; color: #ffffff; }
</style>

<div class="kpi-grid">
    <div class="kpi-card green"><div class="kpi-label">Currently Inside Gym</div><div class="kpi-val"><?= $insideCount ?></div></div>
    <div class="kpi-card"><div class="kpi-label">Total Check-ins</div><div class="kpi-val"><?= $totalCheckins ?></div></div>
    <div class="kpi-card green"><div class="kpi-label">Total Revenue</div><div class="kpi-val">₱<?= number_format($totalRevenue, 2) ?></div></div>
    <div class="kpi-card"><div class="kpi-label">Active Subscriptions</div><div class="kpi-val"><?= $activeSubs ?></div></div>
    <div class="kpi-card amber"><div class="kpi-label">Expiring Soon (≤7 Days)</div><div class="kpi-val"><?= $expiringSoon ?></div></div>
</div>

<div class="card">
    <h3 style="margin-bottom: 12px;">Date-Range Revenue & Attendance Summary</h3>
    <form method="GET" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
        <div class="form-group" style="margin:0;"><label>Start Date</label><input type="date" name="start_date"></div>
        <div class="form-group" style="margin:0;"><label>End Date</label><input type="date" name="end_date"></div>
        <button type="submit" class="btn btn-primary">Filter Report</button>
    </form>
</div>

<div class="grid-equal">
    <div class="card">
        <h3 style="margin-bottom: 10px;">Subscriptions Expiring Soon</h3>
        <table class="table">
            <thead><tr><th>Member</th><th>Plan</th><th>End Date</th></tr></thead>
            <tbody>
                <?php if (empty($expiringList)): ?>
                    <tr><td colspan="3">No subscriptions expiring within 7 days.</td></tr>
                <?php else: foreach ($expiringList as $e): ?>
                    <tr><td><?= htmlspecialchars($e['FullName']) ?></td><td><?= htmlspecialchars($e['PlanName']) ?></td><td><?= htmlspecialchars($e['EndDate']) ?></td></tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card">
        <h3 style="margin-bottom: 10px;">Recent Payments</h3>
        <table class="table">
            <thead><tr><th>Ref #</th><th>Member</th><th>Amount</th><th>Date</th></tr></thead>
            <tbody>
                <?php if (empty($recentPayments)): ?>
                    <tr><td colspan="4">No payment records yet.</td></tr>
                <?php else: foreach ($recentPayments as $p): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($p['ReferenceNumber'] ?? $p['PaymentID']) ?></strong></td>
                        <td><?= htmlspecialchars($p['FullName']) ?></td>
                        <td>₱<?= number_format($p['Amount'], 2) ?></td>
                        <td><?= htmlspecialchars($p['PaymentDate'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
