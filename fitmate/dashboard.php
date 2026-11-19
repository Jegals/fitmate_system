<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_check.php';

requireManagerRole();

$page_title = "Dashboard & Management Summary";

// 1. KPI: Currently Inside the Gym
$insideCount = $pdo->query("
    SELECT COUNT(*) FROM attendance 
    WHERE Date = CURDATE() AND TimeOut IS NULL
")->fetchColumn();

// 2. KPI: Today's Total Check-ins
$todayCheckins = $pdo->query("
    SELECT COUNT(*) FROM attendance 
    WHERE Date = CURDATE()
")->fetchColumn();

// 3. KPI: Today's Revenue & Monthly Revenue
$todayRevenue = $pdo->query("
    SELECT COALESCE(SUM(Amount), 0) FROM payment 
    WHERE PaymentDate = CURDATE()
")->fetchColumn();

$monthRevenue = $pdo->query("
    SELECT COALESCE(SUM(Amount), 0) FROM payment 
    WHERE MONTH(PaymentDate) = MONTH(CURDATE()) AND YEAR(PaymentDate) = YEAR(CURDATE())
")->fetchColumn();

// 4. KPI: Active, Expiring Soon (<= 7 days), and Expired Subscriptions
$activeSubs = $pdo->query("
    SELECT COUNT(*) FROM subscription 
    WHERE EndDate >= CURDATE() AND Status = 'Active'
")->fetchColumn();

$expiringSoon = $pdo->query("
    SELECT COUNT(*) FROM subscription 
    WHERE EndDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
")->fetchColumn();

$expiredSubs = $pdo->query("
    SELECT COUNT(*) FROM subscription 
    WHERE EndDate < CURDATE() OR Status = 'Expired'
")->fetchColumn();

// 5. Table: Expiring Soon Members (Next 7 Days)
$expiringList = $pdo->query("
    SELECT m.MemberID, CONCAT(m.FirstName, ' ', m.LastName) AS FullName, m.Phone,
           mp.PlanName, s.EndDate
    FROM subscription s
    JOIN gym_member m ON s.MemberID = m.MemberID
    JOIN membership_plan mp ON s.PlanID = mp.PlanID
    WHERE s.EndDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY s.EndDate ASC
    LIMIT 10
")->fetchAll();

// 6. Table: Recent Payments
$recentPayments = $pdo->query("
    SELECT p.ReferenceNumber, CONCAT(m.FirstName, ' ', m.LastName) AS FullName,
           p.Amount, p.PaymentMethod, p.PaymentDate
    FROM payment p
    JOIN gym_member m ON p.MemberID = m.MemberID
    ORDER BY p.PaymentDate DESC, p.PaymentTime DESC
    LIMIT 8
")->fetchAll();

// 7. Date-Range Report Generator
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-d');

$rangeAttendance = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE Date BETWEEN ? AND ?");
$rangeAttendance->execute([$startDate, $endDate]);
$totalRangeAttendance = $rangeAttendance->fetchColumn();

$rangeRevenue = $pdo->prepare("SELECT COALESCE(SUM(Amount), 0) FROM payment WHERE PaymentDate BETWEEN ? AND ?");
$rangeRevenue->execute([$startDate, $endDate]);
$totalRangeRevenue = $rangeRevenue->fetchColumn();

include __DIR__ . '/includes/header.php';
?>

<style>
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 22px; }
    .kpi-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 18px; border-left: 4px solid #1e293b; }
    .kpi-card.green { border-left-color: #16a34a; }
    .kpi-card.amber { border-left-color: #d97706; }
    .kpi-card.red   { border-left-color: #dc2626; }
    .kpi-label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; }
    .kpi-value { font-size: 26px; font-weight: 700; color: #0f172a; margin-top: 6px; }
    .kpi-sub   { font-size: 12px; color: #64748b; margin-top: 4px; }
    .dash-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .filter-form { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 16px; }
    .filter-form label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; }
    .filter-form input { padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 4px; }
    @media (max-width: 900px) { .dash-2col { grid-template-columns: 1fr; } }
</style>

<div class="kpi-grid">
    <div class="kpi-card green">
        <div class="kpi-label">Currently Inside Gym</div>
        <div class="kpi-value"><?= $insideCount ?></div>
        <div class="kpi-sub">Today's Total Check-ins: <?= $todayCheckins ?></div>
    </div>

    <div class="kpi-card green">
        <div class="kpi-label">Today's Revenue</div>
        <div class="kpi-value">₱<?= number_format($todayRevenue, 2) ?></div>
        <div class="kpi-sub">This Month: ₱<?= number_format($monthRevenue, 2) ?></div>
    </div>

    <div class="kpi-card">
        <div class="kpi-label">Active Members</div>
        <div class="kpi-value"><?= $activeSubs ?></div>
        <div class="kpi-sub">Valid active subscriptions</div>
    </div>

    <div class="kpi-card amber">
        <div class="kpi-label">Expiring Soon (≤7 Days)</div>
        <div class="kpi-value"><?= $expiringSoon ?></div>
        <div class="kpi-sub">Needs renewal reminder</div>
    </div>

    <div class="kpi-card red">
        <div class="kpi-label">Expired Subscriptions</div>
        <div class="kpi-value"><?= $expiredSubs ?></div>
        <div class="kpi-sub">Entry blocked until renewed</div>
    </div>
</div>

<div class="card">
    <h3 style="margin-bottom: 12px;">Date-Range Summary Report (Attendance & Revenue)</h3>
    <form method="GET" class="filter-form">
        <div>
            <label>Start Date</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" required>
        </div>
        <div>
            <label>End Date</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Generate Report</button>
    </form>

    <div class="kpi-grid" style="margin-bottom: 0;">
        <div class="kpi-card" style="background:#f8fafc;">
            <div class="kpi-label">Total Attendance (Selected Range)</div>
            <div class="kpi-value"><?= $totalRangeAttendance ?> <span style="font-size:14px; font-weight:500;">check-ins</span></div>
        </div>
        <div class="kpi-card green" style="background:#f8fafc;">
            <div class="kpi-label">Total Revenue (Selected Range)</div>
            <div class="kpi-value">₱<?= number_format($totalRangeRevenue, 2) ?></div>
        </div>
    </div>
</div>

<div class="dash-2col">
    <div class="card">
        <h3 style="margin-bottom: 10px;">Subscriptions Expiring Soon</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Plan</th>
                    <th>Expiry Date</th>
                    <th>Phone</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expiringList)): ?>
                    <tr><td colspan="4">No subscriptions expiring within 7 days.</td></tr>
                <?php else: ?>
                    <?php foreach ($expiringList as $exp): ?>
                        <tr>
                            <td><?= htmlspecialchars($exp['FullName']) ?></td>
                            <td><?= htmlspecialchars($exp['PlanName']) ?></td>
                            <td style="color:#d97706; font-weight:600;"><?= htmlspecialchars($exp['EndDate']) ?></td>
                            <td><?= htmlspecialchars($exp['Phone']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-bottom: 10px;">Recent Payments</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Member</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentPayments)): ?>
                    <tr><td colspan="5">No payment records yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentPayments as $pay): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($pay['ReferenceNumber']) ?></strong></td>
                            <td><?= htmlspecialchars($pay['FullName']) ?></td>
                            <td>₱<?= number_format($pay['Amount'], 2) ?></td>
                            <td><?= htmlspecialchars($pay['PaymentMethod']) ?></td>
                            <td><?= htmlspecialchars($pay['PaymentDate']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>