<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

date_default_timezone_set('Asia/Manila');
if (session_status() === PHP_SESSION_NONE) session_start();

$page_title = "Dashboard & Management Summary";

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/**
 * Manager check. ADJUST the session key / role names to match your login code.
 * Tries $_SESSION['role'] then $_SESSION['user']['role'].
 */
if (!function_exists('is_manager')) {
    function is_manager() {
        $role = $_SESSION['role'] ?? ($_SESSION['user']['role'] ?? '');
        $role = strtolower(trim((string)$role));
        return in_array($role, ['manager', 'gym manager', 'owner', 'gym manager/owner', 'admin'], true);
    }
}
$isManager = is_manager();

$today = date('Y-m-d');
$soon  = date('Y-m-d', strtotime('+7 days'));

// Latest subscription per member (same rule used in logs.php)
$latestSub = "SELECT MemberID, MAX(EndDate) AS EndDate FROM subscription GROUP BY MemberID";

// 1. Currently inside the gym: checked in TODAY with TimeOut IS NULL
$st = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE Date = :d AND TimeOut IS NULL");
$st->execute([':d' => $today]);
$insideCount = (int)$st->fetchColumn();

// 2. Today's check-ins
$st = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE Date = :d");
$st->execute([':d' => $today]);
$todayCheckins = (int)$st->fetchColumn();

// Total revenue
$totalRevenue = $pdo->query("SELECT COALESCE(SUM(Amount), 0) FROM payment")->fetchColumn();

// Active / Expiring / Expired members (per member's latest subscription)
$st = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN l.EndDate > :s1 THEN 1 ELSE 0 END), 0) AS active_cnt,
        COALESCE(SUM(CASE WHEN l.EndDate BETWEEN :t1 AND :s2 THEN 1 ELSE 0 END), 0) AS expiring_cnt,
        COALESCE(SUM(CASE WHEN l.EndDate IS NULL OR l.EndDate < :t2 THEN 1 ELSE 0 END), 0) AS expired_cnt
    FROM gym_member m
    LEFT JOIN ($latestSub) l ON l.MemberID = m.MemberID
    WHERE m.is_archived = 0
");
$st->execute([':s1' => $soon, ':t1' => $today, ':s2' => $soon, ':t2' => $today]);
$stat = $st->fetch();
$activeMembers = (int)$stat['active_cnt'];
$expiringSoon  = (int)$stat['expiring_cnt'];
$expiredCount  = (int)$stat['expired_cnt'];

$st = $pdo->prepare("
    SELECT CONCAT(m.FirstName, ' ', m.LastName) AS FullName, mp.PlanName, s.EndDate
    FROM subscription s
    JOIN ($latestSub) l ON l.MemberID = s.MemberID AND l.EndDate = s.EndDate
    JOIN gym_member m ON s.MemberID = m.MemberID
    LEFT JOIN membership_plan mp ON s.PlanID = mp.PlanID
    WHERE m.is_archived = 0 AND s.EndDate BETWEEN :t AND :s
    ORDER BY s.EndDate ASC LIMIT 5
");
$st->execute([':t' => $today, ':s' => $soon]);
$expiringList = $st->fetchAll();

$recentPayments = $pdo->query("
    SELECT p.*, CONCAT(m.FirstName, ' ', m.LastName) AS FullName
    FROM payment p
    JOIN gym_member m ON p.MemberID = m.MemberID
    ORDER BY p.PaymentID DESC LIMIT 5
")->fetchAll();

// 3. MANAGER-ONLY date-range reports
$start = trim($_GET['start_date'] ?? '');
$end   = trim($_GET['end_date'] ?? '');
$reportErr = '';
$attReport = $payReport = [];
$attSummary = ['rows' => 0, 'members' => 0];
$payTotal = 0;
$hasReport = false;

if ($isManager && ($start !== '' || $end !== '')) {
    $validDate = function ($d) {
        $dt = DateTime::createFromFormat('Y-m-d', $d);
        return $dt && $dt->format('Y-m-d') === $d;
    };
    if ($start === '' || $end === '') {
        $reportErr = 'Please select both a Start Date and an End Date.';
    } elseif (!$validDate($start) || !$validDate($end)) {
        $reportErr = 'Invalid date format.';
    } else {
        if ($start > $end) { [$start, $end] = [$end, $start]; }
        $hasReport = true;

        $st = $pdo->prepare("
            SELECT a.AttendanceID, a.Date, a.TimeIn, a.TimeOut, a.MemberID,
                   CONCAT(m.FirstName, ' ', m.LastName) AS FullName
            FROM attendance a
            JOIN gym_member m ON a.MemberID = m.MemberID
            WHERE a.Date BETWEEN :s AND :e
            ORDER BY a.Date DESC, a.AttendanceID DESC
        ");
        $st->execute([':s' => $start, ':e' => $end]);
        $attReport = $st->fetchAll();
        $attSummary['rows']    = count($attReport);
        $attSummary['members'] = count(array_unique(array_column($attReport, 'MemberID')));

        $st = $pdo->prepare("
            SELECT p.*, CONCAT(m.FirstName, ' ', m.LastName) AS FullName
            FROM payment p
            JOIN gym_member m ON p.MemberID = m.MemberID
            WHERE p.PaymentDate BETWEEN :s AND :e
            ORDER BY p.PaymentDate DESC, p.PaymentID DESC
        ");
        $st->execute([':s' => $start, ':e' => $end]);
        $payReport = $st->fetchAll();
        $payTotal  = array_sum(array_map(fn($r) => (float)$r['Amount'], $payReport));
    }
}

include __DIR__ . '/../includes/header.php';
?>

<style>
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 16px; margin-bottom: 20px; }
    .kpi-card { background: #0c1324; border: 1px solid #1e293b; border-radius: 8px; padding: 18px; border-left: 4px solid #2563eb; }
    .kpi-card.green { border-left-color: #16a34a; }
    .kpi-card.amber { border-left-color: #d97706; }
    .kpi-card.red   { border-left-color: #dc2626; }
    .kpi-label { font-size: 11px; font-weight: 700; color: #60a5fa; text-transform: uppercase; }
    .kpi-val { font-size: 26px; font-weight: 700; margin-top: 6px; color: #ffffff; }
    .report-scroll { max-height: 320px; overflow-y: auto; }
</style>

<div class="kpi-grid">
    <div class="kpi-card green"><div class="kpi-label">Currently Inside Gym</div><div class="kpi-val"><?= $insideCount ?></div></div>
    <div class="kpi-card"><div class="kpi-label">Today's Check-ins</div><div class="kpi-val"><?= $todayCheckins ?></div></div>
    <div class="kpi-card green"><div class="kpi-label">Active Members</div><div class="kpi-val"><?= $activeMembers ?></div></div>
    <div class="kpi-card red"><div class="kpi-label">Expired Members</div><div class="kpi-val"><?= $expiredCount ?></div></div>
    <div class="kpi-card amber"><div class="kpi-label">Expiring Soon (≤7 Days)</div><div class="kpi-val"><?= $expiringSoon ?></div></div>
    <div class="kpi-card green"><div class="kpi-label">Total Revenue</div><div class="kpi-val">₱<?= number_format((float)$totalRevenue, 2) ?></div></div>
</div>

<div class="card" style="margin-bottom:20px;">
    <h3 style="margin-bottom: 12px;">Date-Range Revenue & Attendance Summary</h3>

    <?php if (!$isManager): ?>
        <div style="border-left:4px solid #dc2626; padding:10px 14px; color:#fca5a5;">
            <strong>Access Denied.</strong> Only the Gym Manager can generate Attendance and Payment reports.
        </div>
    <?php else: ?>
        <form method="GET" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
            <div class="form-group" style="margin:0;"><label>Start Date</label><input type="date" name="start_date" value="<?= h($start) ?>" required></div>
            <div class="form-group" style="margin:0;"><label>End Date</label><input type="date" name="end_date" value="<?= h($end) ?>" required></div>
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="dashboard.php" class="btn btn-sm" style="text-decoration:none;">Reset</a>
        </form>

        <?php if ($reportErr): ?>
            <p style="color:#fca5a5; margin-top:12px;"><?= h($reportErr) ?></p>
        <?php endif; ?>

        <?php if ($hasReport): ?>
            <div class="kpi-grid" style="margin-top:16px;">
                <div class="kpi-card"><div class="kpi-label">Attendance Records</div><div class="kpi-val"><?= $attSummary['rows'] ?></div></div>
                <div class="kpi-card"><div class="kpi-label">Unique Members</div><div class="kpi-val"><?= $attSummary['members'] ?></div></div>
                <div class="kpi-card green"><div class="kpi-label">Revenue (<?= h($start) ?> to <?= h($end) ?>)</div><div class="kpi-val">₱<?= number_format($payTotal, 2) ?></div></div>
                <div class="kpi-card"><div class="kpi-label">Payments</div><div class="kpi-val"><?= count($payReport) ?></div></div>
            </div>

            <div class="grid-equal">
                <div>
                    <h4 style="margin:10px 0;">Attendance Report</h4>
                    <div class="report-scroll">
                        <table class="table">
                            <thead><tr><th>Date</th><th>Member</th><th>Time In</th><th>Time Out</th></tr></thead>
                            <tbody>
                            <?php if (empty($attReport)): ?>
                                <tr><td colspan="4">No attendance in this range.</td></tr>
                            <?php else: foreach ($attReport as $r): ?>
                                <tr>
                                    <td><?= h($r['Date']) ?></td>
                                    <td><?= h($r['FullName']) ?></td>
                                    <td><?= h($r['TimeIn'] ?? '-') ?></td>
                                    <td><?= !empty($r['TimeOut']) ? h($r['TimeOut']) : '-' ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div>
                    <h4 style="margin:10px 0;">Payment Report</h4>
                    <div class="report-scroll">
                        <table class="table">
                            <thead><tr><th>Ref #</th><th>Member</th><th>Amount</th><th>Date</th></tr></thead>
                            <tbody>
                            <?php if (empty($payReport)): ?>
                                <tr><td colspan="4">No payments in this range.</td></tr>
                            <?php else: foreach ($payReport as $r): ?>
                                <tr>
                                    <td><strong><?= h($r['ReferenceNumber'] ?? $r['PaymentID']) ?></strong></td>
                                    <td><?= h($r['FullName']) ?></td>
                                    <td>₱<?= number_format((float)$r['Amount'], 2) ?></td>
                                    <td><?= h($r['PaymentDate'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
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
                    <tr><td><?= h($e['FullName']) ?></td><td><?= h($e['PlanName'] ?? '-') ?></td><td><?= h($e['EndDate']) ?></td></tr>
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
                        <td><strong><?= h($p['ReferenceNumber'] ?? $p['PaymentID']) ?></strong></td>
                        <td><?= h($p['FullName']) ?></td>
                        <td>₱<?= number_format((float)$p['Amount'], 2) ?></td>
                        <td><?= h($p['PaymentDate'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
