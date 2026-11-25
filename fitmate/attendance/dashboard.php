<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Dashboard & Management Summary";
$msg = '';
$err = '';

$openCond = "(TimeOut IS NULL OR TimeOut = '00:00:00')";
$fmtTime  = fn($t) => ($t && $t !== '00:00:00') ? date('g:i A', strtotime($t)) : '—';
$fmtDur   = fn($m) => $m === null ? '—' : intdiv((int)$m, 60) . 'h ' . str_pad((string)((int)$m % 60), 2, '0', STR_PAD_LEFT) . 'm';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_in') {
    $memberId = strtoupper(trim($_POST['member_id'] ?? ''));

    if ($memberId === '') {
        $err = "Please enter a Member ID.";
    } else {
        try {
            $chk = $pdo->prepare("SELECT FirstName FROM gym_member WHERE MemberID = ? AND is_archived = 0");
            $chk->execute([$memberId]);
            $member = $chk->fetch();

            if (!$member) {
                $err = "Member ID ($memberId) not found or is currently archived.";
            } else {
                $sub = $pdo->prepare("SELECT 1 FROM subscription WHERE MemberID = ? AND Status = 'Active' AND EndDate >= CURDATE() LIMIT 1");
                $sub->execute([$memberId]);

                $open = $pdo->prepare("SELECT 1 FROM attendance WHERE MemberID = ? AND Date = CURDATE() AND $openCond LIMIT 1");
                $open->execute([$memberId]);

                if (!$sub->fetchColumn()) {
                    $err = $member['FirstName'] . " has no active subscription. Check-in denied.";
                } elseif ($open->fetchColumn()) {
                    $err = $member['FirstName'] . " is already checked in.";
                } else {
                    $lastAtt = $pdo->query("SELECT AttendanceID FROM attendance ORDER BY AttendanceID DESC LIMIT 1")->fetchColumn();
                    if ($lastAtt && preg_match('/(\d+)$/', $lastAtt, $matches)) {
                        $newAttId = 'ATT' . ((int)$matches[1] + 1);
                    } else {
                        $newAttId = 'ATT8001';
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO attendance (AttendanceID, MemberID, Date, TimeIn, TimeOut)
                        VALUES (?, ?, CURDATE(), CURTIME(), '00:00:00')
                    ");
                    $stmt->execute([$newAttId, $memberId]);
                    $msg = "Welcome, " . $member['FirstName'] . "! Checked in ($newAttId).";
                }
            }
        } catch (PDOException $e) {
            $err = "Error checking in: " . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_out') {
    $attId = $_POST['attendance_id'] ?? '';
    try {
        $stmt = $pdo->prepare("UPDATE attendance SET TimeOut = CURTIME() WHERE AttendanceID = ? AND $openCond");
        $stmt->execute([$attId]);
        if ($stmt->rowCount()) {
            $msg = "Attendance ($attId) checked out!";
        } else {
            $err = "That session is already closed.";
        }
    } catch (PDOException $e) {
        $err = "Error checking out: " . $e->getMessage();
    }
}

$validDate = fn($d) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
$startDate = $validDate($_GET['start_date'] ?? '') ? $_GET['start_date'] : '';
$endDate   = $validDate($_GET['end_date'] ?? '') ? $_GET['end_date'] : '';
if ($startDate && $endDate && $startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}
$hasRange = ($startDate !== '' || $endDate !== '');

$insideCount   = $pdo->query("SELECT COUNT(*) FROM attendance WHERE $openCond")->fetchColumn();
$todayCheckins = $pdo->query("SELECT COUNT(*) FROM attendance WHERE Date = CURDATE()")->fetchColumn();
$totalCheckins = $pdo->query("SELECT COUNT(*) FROM attendance")->fetchColumn();
$totalRevenue  = $pdo->query("SELECT COALESCE(SUM(Amount), 0) FROM payment")->fetchColumn();
$activeSubs    = $pdo->query("SELECT COUNT(*) FROM subscription WHERE Status = 'Active' AND EndDate >= CURDATE()")->fetchColumn();
$expiringSoon  = $pdo->query("SELECT COUNT(*) FROM subscription WHERE Status = 'Active' AND EndDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();

$rangeSummary = null;
if ($hasRange) {
    $aw = []; $pw = []; $aa = []; $pa = [];
    if ($startDate) { $aw[] = "Date >= ?"; $aa[] = $startDate; $pw[] = "PaymentDate >= ?"; $pa[] = $startDate; }
    if ($endDate)   { $aw[] = "Date <= ?"; $aa[] = $endDate;   $pw[] = "PaymentDate <= ?"; $pa[] = $endDate; }
    $aWhere = 'WHERE ' . implode(' AND ', $aw);
    $pWhere = 'WHERE ' . implode(' AND ', $pw);

    $s1 = $pdo->prepare("
        SELECT COUNT(*) AS visits, COUNT(DISTINCT MemberID) AS members,
               AVG(CASE WHEN TimeOut IS NULL OR TimeOut = '00:00:00' THEN NULL
                        ELSE TIMESTAMPDIFF(MINUTE, CONCAT(Date, ' ', TimeIn), CONCAT(Date, ' ', TimeOut)) END) AS avg_min
        FROM attendance $aWhere
    ");
    $s1->execute($aa);
    $rangeSummary = $s1->fetch();

    $s2 = $pdo->prepare("SELECT COALESCE(SUM(Amount), 0) AS revenue, COUNT(*) AS payments FROM payment $pWhere");
    $s2->execute($pa);
    $rangeSummary += $s2->fetch();
}

$expiringList = $pdo->query("
    SELECT CONCAT(m.FirstName, ' ', m.LastName) AS FullName, mp.PlanName, s.EndDate
    FROM subscription s
    JOIN gym_member m ON s.MemberID = m.MemberID
    JOIN membership_plan mp ON s.PlanID = mp.PlanID
    WHERE s.Status = 'Active' AND s.EndDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY s.EndDate ASC LIMIT 5
")->fetchAll();

$recentPayments = $pdo->query("
    SELECT p.*, CONCAT(m.FirstName, ' ', m.LastName) AS FullName
    FROM payment p
    JOIN gym_member m ON p.MemberID = m.MemberID
    ORDER BY p.PaymentID DESC LIMIT 5
")->fetchAll();

$insideList = $pdo->query("
    SELECT a.AttendanceID, a.MemberID, a.TimeIn,
           CONCAT(m.FirstName, ' ', m.LastName) AS FullName,
           TIMESTAMPDIFF(MINUTE, CONCAT(a.Date, ' ', a.TimeIn), NOW()) AS mins
    FROM attendance a
    JOIN gym_member m ON a.MemberID = m.MemberID
    WHERE a.Date = CURDATE() AND (a.TimeOut IS NULL OR a.TimeOut = '00:00:00')
    ORDER BY a.TimeIn ASC
")->fetchAll();

$recentAttendance = $pdo->query("
    SELECT a.AttendanceID, a.Date, a.TimeIn, a.TimeOut,
           CONCAT(m.FirstName, ' ', m.LastName) AS FullName,
           (a.TimeOut IS NULL OR a.TimeOut = '00:00:00') AS is_open,
           CASE WHEN a.TimeOut IS NULL OR a.TimeOut = '00:00:00' THEN NULL
                ELSE TIMESTAMPDIFF(MINUTE, CONCAT(a.Date, ' ', a.TimeIn), CONCAT(a.Date, ' ', a.TimeOut)) END AS mins
    FROM attendance a
    JOIN gym_member m ON a.MemberID = m.MemberID
    ORDER BY a.Date DESC, a.TimeIn DESC LIMIT 5
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<style>
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 16px; margin-bottom: 20px; }
    .kpi-card { background: #0c1324; border: 1px solid #1e293b; border-radius: 8px; padding: 18px; border-left: 4px solid #2563eb; }
    .kpi-card.green { border-left-color: #16a34a; }
    .kpi-card.amber { border-left-color: #d97706; }
    .kpi-label { font-size: 11px; font-weight: 700; color: #60a5fa; text-transform: uppercase; }
    .kpi-val { font-size: 26px; font-weight: 700; margin-top: 6px; color: #ffffff; }
    .btn-link { display: inline-block; text-decoration: none; text-align: center; }
    .btn-xs {
        padding: 4px 9px;
        font-size: 11px;
        font-weight: 600;
        border-radius: 5px;
        border: none;
        cursor: pointer;
        color: #ffffff;
        background: #dc2626;
    }
    .btn-xs:hover { background: #b91c1c; }
</style>

<?php if ($msg): ?>
    <div style="background:rgba(22, 163, 74, 0.2); border:1px solid #22c55e; color:#86efac; padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:16px;">
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>
<?php if ($err): ?>
    <div style="background:rgba(220, 38, 38, 0.2); border:1px solid #ef4444; color:#fca5a5; padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:16px;">
        <?= htmlspecialchars($err) ?>
    </div>
<?php endif; ?>

<div class="kpi-grid">
    <div class="kpi-card green"><div class="kpi-label">Currently Inside Gym</div><div class="kpi-val"><?= $insideCount ?></div></div>
    <div class="kpi-card"><div class="kpi-label">Today's Check-ins</div><div class="kpi-val"><?= $todayCheckins ?></div></div>
    <div class="kpi-card"><div class="kpi-label">Total Check-ins</div><div class="kpi-val"><?= $totalCheckins ?></div></div>
    <div class="kpi-card green"><div class="kpi-label">Total Revenue</div><div class="kpi-val">₱<?= number_format($totalRevenue, 2) ?></div></div>
    <div class="kpi-card"><div class="kpi-label">Active Subscriptions</div><div class="kpi-val"><?= $activeSubs ?></div></div>
    <div class="kpi-card amber"><div class="kpi-label">Expiring Soon (≤7 Days)</div><div class="kpi-val"><?= $expiringSoon ?></div></div>
</div>

<div class="card">
    <h3 style="margin-bottom: 12px;">Date-Range Revenue & Attendance Summary</h3>
    <form method="GET" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
        <div class="form-group" style="margin:0;"><label>Start Date</label><input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>"></div>
        <div class="form-group" style="margin:0;"><label>End Date</label><input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>"></div>
        <button type="submit" class="btn btn-primary">Filter Report</button>
        <?php if ($hasRange): ?>
            <a href="dashboard.php" class="btn btn-link">Reset</a>
        <?php endif; ?>
    </form>

    <?php if ($rangeSummary): ?>
        <div class="kpi-grid" style="margin: 16px 0 0;">
            <div class="kpi-card"><div class="kpi-label">Visits in Range</div><div class="kpi-val"><?= (int)$rangeSummary['visits'] ?></div></div>
            <div class="kpi-card"><div class="kpi-label">Unique Members</div><div class="kpi-val"><?= (int)$rangeSummary['members'] ?></div></div>
            <div class="kpi-card amber"><div class="kpi-label">Avg. Workout Time</div><div class="kpi-val"><?= $rangeSummary['avg_min'] !== null ? $fmtDur(round((float)$rangeSummary['avg_min'])) : '—' ?></div></div>
            <div class="kpi-card green"><div class="kpi-label">Revenue in Range</div><div class="kpi-val">₱<?= number_format((float)$rangeSummary['revenue'], 2) ?></div></div>
            <div class="kpi-card"><div class="kpi-label">Payments in Range</div><div class="kpi-val"><?= (int)$rangeSummary['payments'] ?></div></div>
        </div>
    <?php endif; ?>
</div>

<div class="grid-equal">
    <div class="card">
        <h3 style="margin-bottom: 10px;">Member Check-In</h3>
        <form method="POST" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; margin-bottom: 14px;">
            <input type="hidden" name="action" value="check_in">
            <div class="form-group" style="margin:0; flex:1; min-width:160px;">
                <label>Member ID</label>
                <input type="text" name="member_id" required placeholder="e.g. MEM101">
            </div>
            <button type="submit" class="btn btn-primary">Check In</button>
        </form>

        <h3 style="margin-bottom: 10px;">Currently Inside (<?= count($insideList) ?>)</h3>
        <table class="table">
            <thead><tr><th>Member</th><th>Time In</th><th>Duration</th><th>Action</th></tr></thead>
            <tbody>
                <?php if (empty($insideList)): ?>
                    <tr><td colspan="4">Nobody is inside right now.</td></tr>
                <?php else: foreach ($insideList as $i): ?>
                    <tr>
                        <td><?= htmlspecialchars($i['FullName']) ?></td>
                        <td><?= $fmtTime($i['TimeIn']) ?></td>
                        <td><?= $fmtDur(max(0, (int)$i['mins'])) ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="check_out">
                                <input type="hidden" name="attendance_id" value="<?= htmlspecialchars($i['AttendanceID']) ?>">
                                <button type="submit" class="btn-xs">Check Out</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-bottom: 10px;">Recent Attendance</h3>
        <table class="table">
            <thead><tr><th>Member</th><th>Date</th><th>In</th><th>Out</th><th>Status</th></tr></thead>
            <tbody>
                <?php if (empty($recentAttendance)): ?>
                    <tr><td colspan="5">No attendance records yet.</td></tr>
                <?php else: foreach ($recentAttendance as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['FullName']) ?></td>
                        <td><?= htmlspecialchars($r['Date']) ?></td>
                        <td><?= $fmtTime($r['TimeIn']) ?></td>
                        <td><?= $fmtTime($r['TimeOut']) ?></td>
                        <td>
                            <?php if ($r['is_open']): ?>
                                <span class="badge badge-amber">Inside</span>
                            <?php else: ?>
                                <span class="badge badge-green"><?= $fmtDur($r['mins']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
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
