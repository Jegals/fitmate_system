<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

date_default_timezone_set('Asia/Manila');
if (session_status() === PHP_SESSION_NONE) session_start();

$page_title = "Attendance Logs";

const SUB_TABLE  = 'subscription';
const PLAN_TABLE = 'membership_plan';

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function next_att_id(PDO $pdo) {
    $max = $pdo->query("SELECT MAX(CAST(SUBSTRING(AttendanceID, 4) AS UNSIGNED)) FROM attendance")->fetchColumn();
    return 'ATT' . ((int)$max + 1);
}

function member_status(PDO $pdo, $memberId) {
    $st = $pdo->prepare("
        SELECT s.EndDate, p.PlanName
        FROM " . SUB_TABLE . " s
        LEFT JOIN " . PLAN_TABLE . " p ON p.PlanID = s.PlanID
        WHERE s.MemberID = ?
        ORDER BY s.EndDate DESC LIMIT 1
    ");
    $st->execute([$memberId]);
    $sub = $st->fetch();

    if (!$sub) return ['status' => 'Expired', 'plan' => 'No subscription', 'end' => '-'];

    $today = new DateTime('today');
    $end   = new DateTime($sub['EndDate']);
    $days  = (int)$today->diff($end)->format('%r%a');

    if ($days < 0)       $status = 'Expired';
    elseif ($days <= 7)  $status = 'Expiring Soon';
    else                 $status = 'Active';

    return ['status' => $status, 'plan' => $sub['PlanName'] ?? '-', 'end' => $sub['EndDate']];
}

function is_inside(PDO $pdo, $memberId) {
    $st = $pdo->prepare("SELECT 1 FROM attendance WHERE MemberID = ? AND TimeOut IS NULL LIMIT 1");
    $st->execute([$memberId]);
    return (bool)$st->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'timein') {
        $mid = trim($_POST['member_id'] ?? '');
        $chk = $pdo->prepare("SELECT MemberID FROM gym_member WHERE MemberID = ? AND is_archived = 0");
        $chk->execute([$mid]);

        if (!$chk->fetch()) {
            $_SESSION['flash'] = ['err', 'Member not found.'];
        } else {
            $info = member_status($pdo, $mid);
            if ($info['status'] === 'Expired') {
                $_SESSION['flash'] = ['err', 'Entry blocked: subscription expired. Please renew first.'];
            } elseif (is_inside($pdo, $mid)) {
                $_SESSION['flash'] = ['err', 'This member is already inside the gym.'];
            } else {
                $ins = $pdo->prepare("INSERT INTO attendance (AttendanceID, MemberID, Date, TimeIn, TimeOut)
                                      VALUES (?, ?, ?, ?, NULL)");
                $ins->execute([next_att_id($pdo), $mid, date('Y-m-d'), date('H:i:s')]);
                $_SESSION['flash'] = ['ok', 'Time-In recorded for ' . $mid . '.'];
            }
        }
    }

    if ($action === 'timeout') {
        $upd = $pdo->prepare("UPDATE attendance SET TimeOut = ? WHERE AttendanceID = ? AND TimeOut IS NULL");
        $upd->execute([date('H:i:s'), $_POST['attendance_id'] ?? '']);
        $_SESSION['flash'] = $upd->rowCount() ? ['ok', 'Time-Out recorded.'] : ['err', 'Already timed out.'];
    }

    header('Location: logs.php');
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$q = trim($_GET['q'] ?? '');
$results = [];
if ($q !== '') {
    $st = $pdo->prepare("
        SELECT MemberID, FirstName, LastName FROM gym_member
        WHERE is_archived = 0
          AND (MemberID LIKE :q OR CONCAT(FirstName, ' ', LastName) LIKE :q)
        ORDER BY FirstName LIMIT 10
    ");
    $st->execute([':q' => "%$q%"]);
    foreach ($st->fetchAll() as $m) {
        $m['info']   = member_status($pdo, $m['MemberID']);
        $m['inside'] = is_inside($pdo, $m['MemberID']);
        $results[] = $m;
    }
}

$fDate   = $_GET['date']   ?? date('Y-m-d');
$fMember = trim($_GET['member'] ?? '');

$sql = "SELECT a.*, CONCAT(m.FirstName, ' ', m.LastName) AS FullName
        FROM attendance a
        JOIN gym_member m ON a.MemberID = m.MemberID
        WHERE 1=1";
$params = [];
if ($fDate !== '') {
    $sql .= " AND a.Date = :d";
    $params[':d'] = $fDate;
}
if ($fMember !== '') {
    $sql .= " AND (a.MemberID LIKE :m OR CONCAT(m.FirstName, ' ', m.LastName) LIKE :m)";
    $params[':m'] = "%$fMember%";
}
$sql .= " ORDER BY a.AttendanceID DESC LIMIT 100";
$st = $pdo->prepare($sql);
$st->execute($params);
$logs = $st->fetchAll();

function status_color($s) {
    return $s === 'Active' ? '#16a34a' : ($s === 'Expiring Soon' ? '#d97706' : '#dc2626');
}

include __DIR__ . '/../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="card" style="margin-bottom:16px; border-left:4px solid <?= $flash[0] === 'ok' ? '#16a34a' : '#dc2626' ?>;">
        <?= h($flash[1]) ?>
    </div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <h3 style="margin-bottom: 14px;">Member Check-In</h3>

        <form method="GET">
            <div class="form-group">
                <label>Search by Member ID or Name</label>
                <input type="text" name="q" value="<?= h($q) ?>" placeholder="e.g. MEM109 or Jihyo Park">
            </div>
            <button type="submit" class="btn btn-success" style="width:100%;">Search Member</button>
        </form>

        <?php if ($q !== ''): ?>
            <div style="margin-top:18px;">
                <?php if (empty($results)): ?>
                    <p>No member found.</p>
                <?php else: foreach ($results as $m):
                    $s = $m['info']['status'];
                    $blocked = ($s === 'Expired');
                ?>
                    <div style="border:1px solid #1e293b; border-radius:8px; padding:12px; margin-bottom:10px;">
                        <strong><?= h($m['FirstName'] . ' ' . $m['LastName']) ?></strong>
                        <span style="opacity:.7;">(<?= h($m['MemberID']) ?>)</span>
                        <div style="margin:6px 0;">
                            <span style="background:<?= status_color($s) ?>; color:#fff; padding:2px 10px; border-radius:12px; font-size:12px;">
                                <?= h($s) ?>
                            </span>
                            <?php if ($m['inside']): ?>
                                <span class="badge badge-green">Inside Gym</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:13px; opacity:.85;">
                            Plan: <?= h($m['info']['plan']) ?><br>
                            Expires: <?= h($m['info']['end']) ?>
                        </div>

                        <?php if ($blocked): ?>
                            <div style="margin-top:8px; color:#f87171; font-size:13px;">
                                Membership expired. Please renew before entering the gym.
                            </div>
                        <?php endif; ?>

                        <form method="POST" style="margin-top:10px;">
                            <input type="hidden" name="action" value="timein">
                            <input type="hidden" name="member_id" value="<?= h($m['MemberID']) ?>">
                            <?php $off = ($blocked || $m['inside']); ?>
                            <button type="submit" class="btn btn-success"
                                style="width:100%;<?= $off ? ' opacity:.5; cursor:not-allowed;' : '' ?>"
                                <?= $off ? 'disabled' : '' ?>>
                                <?= $m['inside'] ? 'Already Inside' : 'Time-In Member' ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3 style="margin-bottom: 12px;">
            Attendance Logs (<?= count($logs) ?> Records)
            <?= $fDate === date('Y-m-d') ? '<small style="opacity:.6;">- Today</small>' : '' ?>
        </h3>

        <form method="GET" style="display:flex; gap:10px; margin-bottom:14px; flex-wrap:wrap;">
            <input type="date" name="date" value="<?= h($fDate) ?>">
            <input type="text" name="member" value="<?= h($fMember) ?>" placeholder="Filter by member ID or name">
            <button type="submit" class="btn btn-success btn-sm">Filter</button>
            <a href="logs.php" class="btn btn-sm" style="text-decoration:none;">Today</a>
        </form>

        <table class="table">
            <thead><tr><th>ID</th><th>Member</th><th>Date</th><th>Time In</th><th>Time Out</th><th>Action</th></tr></thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="6">No attendance logs found.</td></tr>
                <?php else: foreach ($logs as $l): ?>
                    <tr>
                        <td><strong><?= h($l['AttendanceID']) ?></strong></td>
                        <td><?= h($l['FullName']) ?></td>
                        <td><?= h($l['Date'] ?? '-') ?></td>
                        <td><?= h($l['TimeIn'] ?? '-') ?></td>
                        <td><?= !empty($l['TimeOut']) ? h($l['TimeOut']) : '<span class="badge badge-green">Inside Gym</span>' ?></td>
                        <td>
                            <?php if (empty($l['TimeOut'])): ?>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="action" value="timeout">
                                    <input type="hidden" name="attendance_id" value="<?= h($l['AttendanceID']) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Time-Out</button>
                                </form>
                            <?php else: ?>
                                <span style="opacity:.5;">Done</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
