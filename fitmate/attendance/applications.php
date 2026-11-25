<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Attendance Dashboard";
$msg = '';
$err = '';

$fmtTime = fn($t) => ($t && $t !== '00:00:00') ? date('g:i A', strtotime($t)) : '—';
$fmtDur  = fn($m) => $m === null ? '—' : intdiv((int)$m, 60) . 'h ' . str_pad((string)((int)$m % 60), 2, '0', STR_PAD_LEFT) . 'm';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_in') {
    $memberId = strtoupper(trim($_POST['member_id'] ?? ''));

    if ($memberId === '') {
        $err = "Please enter a Member ID.";
    } else {
        try {
            $chk = $pdo->prepare("SELECT FirstName, LastName FROM gym_member WHERE MemberID = ? AND is_archived = 0");
            $chk->execute([$memberId]);
            $member = $chk->fetch();

            if (!$member) {
                $err = "Member ID ($memberId) not found or is currently archived.";
            } else {
                $sub = $pdo->prepare("SELECT 1 FROM subscription WHERE MemberID = ? AND Status = 'Active' AND EndDate >= CURDATE() LIMIT 1");
                $sub->execute([$memberId]);

                $open = $pdo->prepare("SELECT 1 FROM attendance WHERE MemberID = ? AND Date = CURDATE() AND (TimeOut IS NULL OR TimeOut = '00:00:00') LIMIT 1");
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
        $stmt = $pdo->prepare("UPDATE attendance SET TimeOut = CURTIME() WHERE AttendanceID = ? AND (TimeOut IS NULL OR TimeOut = '00:00:00')");
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

$stats = $pdo->query("
    SELECT COUNT(*) AS total,
           COALESCE(SUM(TimeOut IS NULL OR TimeOut = '00:00:00'), 0) AS inside
    FROM attendance WHERE Date = CURDATE()
")->fetch();

$expiring = (int)$pdo->query("
    SELECT COUNT(*) FROM subscription
    WHERE Status = 'Active' AND EndDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
")->fetchColumn();

$records = $pdo->query("
    SELECT a.AttendanceID, a.MemberID, a.TimeIn, a.TimeOut,
           CONCAT(m.FirstName, ' ', m.LastName) AS MemberName,
           (a.TimeOut IS NULL OR a.TimeOut = '00:00:00') AS is_open,
           TIMESTAMPDIFF(MINUTE, CONCAT(a.Date, ' ', a.TimeIn),
               CASE WHEN a.TimeOut IS NULL OR a.TimeOut = '00:00:00' THEN NOW()
                    ELSE CONCAT(a.Date, ' ', a.TimeOut) END) AS mins
    FROM attendance a
    JOIN gym_member m ON a.MemberID = m.MemberID
    WHERE a.Date = CURDATE()
    ORDER BY is_open DESC, a.TimeIn DESC
")->fetchAll();

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

    .stats-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 14px;
        flex-shrink: 0;
    }
    .stats-row .card {
        margin-bottom: 0 !important;
        padding: 14px 18px !important;
    }
    .stat-num { font-size: 26px; font-weight: 700; color: #f1f5f9; line-height: 1.1; }
    .stat-lbl { font-size: 12px; color: #94a3b8; margin-top: 4px; }

    .apps-grid {
        flex: 1 !important;
        display: grid !important;
        grid-template-columns: 340px 1fr !important;
        gap: 18px !important;
        min-height: 0 !important;
        align-items: stretch !important;
    }
    .apps-grid > .card {
        margin-bottom: 0 !important;
        display: flex !important;
        flex-direction: column !important;
        min-height: 0 !important;
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
        border-bottom: 1px solid #283548;
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
        padding: 10px 10px;
        text-align: left;
        font-size: 12px;
        font-weight: 700;
        color: #60a5fa;
        background: #111c35;
    }
    .fixed-table td {
        padding: 9px 10px;
        font-size: 13px;
        color: #f1f5f9;
        border-bottom: 1px solid #1e293b;
        vertical-align: middle;
    }
    .fixed-table tbody tr:hover {
        background: rgba(37, 99, 235, 0.08);
    }

    .badge-red {
        background: rgba(220, 38, 38, 0.22) !important;
        color: #fca5a5 !important;
        border: 1px solid #ef4444 !important;
    }
    .btn-xs {
        padding: 4px 9px;
        font-size: 11px;
        font-weight: 600;
        border-radius: 5px;
        border: none;
        cursor: pointer;
        color: #ffffff;
    }
    .btn-rejected { background: #dc2626; }
    .btn-rejected:hover { background: #b91c1c; }
</style>

<?php if ($msg): ?>
    <div style="background:rgba(22, 163, 74, 0.2); border:1px solid #22c55e; color:#86efac; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>
<?php if ($err): ?>
    <div style="background:rgba(220, 38, 38, 0.2); border:1px solid #ef4444; color:#fca5a5; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        <?= htmlspecialchars($err) ?>
    </div>
<?php endif; ?>

<div class="stats-row">
    <div class="card">
        <div class="stat-num"><?= (int)$stats['total'] ?></div>
        <div class="stat-lbl">Check-ins Today</div>
    </div>
    <div class="card">
        <div class="stat-num"><?= (int)$stats['inside'] ?></div>
        <div class="stat-lbl">Currently Inside</div>
    </div>
    <div class="card">
        <div class="stat-num"><?= $expiring ?></div>
        <div class="stat-lbl">Subscriptions Expiring in 7 Days</div>
    </div>
</div>

<div class="apps-grid">
    <div class="card" style="height: fit-content;">
        <h3 style="margin-bottom: 14px;">Member Check-In</h3>
        <form method="POST">
            <input type="hidden" name="action" value="check_in">
            <div class="form-group">
                <label>Member ID</label>
                <input type="text" name="member_id" required placeholder="e.g. MEM101" autofocus>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; margin-top: 6px; padding: 10px;">
                Check In
            </button>
        </form>
    </div>

    <div class="card">
        <h3 style="margin-bottom: 12px; flex-shrink: 0;">Today's Attendance (<?= count($records) ?> Records)</h3>

        <div class="fixed-table-wrap">
            <table class="fixed-table">
                <thead>
                    <tr>
                        <th style="width: 11%;">Att ID</th>
                        <th style="width: 25%;">Member</th>
                        <th style="width: 13%;">Time In</th>
                        <th style="width: 13%;">Time Out</th>
                        <th style="width: 13%;">Duration</th>
                        <th style="width: 25%;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr><td colspan="6">No attendance recorded today.</td></tr>
                    <?php else: foreach ($records as $r): ?>
                        <tr>
                            <td style="width: 11%;"><strong><?= htmlspecialchars($r['AttendanceID']) ?></strong></td>
                            <td style="width: 25%;"><?= htmlspecialchars($r['MemberName']) ?></td>
                            <td style="width: 13%;"><?= $fmtTime($r['TimeIn']) ?></td>
                            <td style="width: 13%;"><?= $fmtTime($r['TimeOut']) ?></td>
                            <td style="width: 13%;"><?= $fmtDur($r['mins']) ?></td>
                            <td style="width: 25%;">
                                <?php if ($r['is_open']): ?>
                                    <form method="POST" style="display:flex; align-items:center; gap:8px;">
                                        <span class="badge badge-amber">Inside</span>
                                        <input type="hidden" name="action" value="check_out">
                                        <input type="hidden" name="attendance_id" value="<?= htmlspecialchars($r['AttendanceID']) ?>">
                                        <button type="submit" class="btn-xs btn-rejected">Check Out</button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge badge-green">Completed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
