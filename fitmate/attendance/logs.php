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
    $st = $pdo->prepare("SELECT 1 FROM attendance WHERE MemberID = ? AND (TimeOut IS NULL OR TimeOut = '00:00:00') LIMIT 1");
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
                                      VALUES (?, ?, ?, ?, '00:00:00')");
                $ins->execute([next_att_id($pdo), $mid, date('Y-m-d'), date('H:i:s')]);
                $_SESSION['flash'] = ['ok', 'Time-In recorded for ' . $mid . '.'];
            }
        }
    }

    if ($action === 'timeout') {
        $upd = $pdo->prepare("UPDATE attendance SET TimeOut = ? WHERE AttendanceID = ? AND (TimeOut IS NULL OR TimeOut = '00:00:00')");
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

// Default empty so all attendance records show immediately
$fDate   = $_GET['date']   ?? '';
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

    .attendance-grid {
        display: grid;
        grid-template-columns: 380px 1fr;
        gap: 16px;
        flex: 1;
        min-height: 0;
        align-items: stretch;
    }
    .attendance-grid > .card {
        height: 100% !important;
        margin-bottom: 0 !important;
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
    }

    .checkin-results-scroll {
        flex: 1;
        overflow-y: auto;
        margin-top: 14px;
        padding-right: 4px;
    }
    .checkin-card {
        background: #060913;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 14px;
        margin-bottom: 10px;
    }

    /* Consistent Black & Blue Filter / Search Bar */
    .filter-bar {
        display: flex;
        gap: 10px;
        margin-bottom: 14px;
        align-items: center;
        flex-shrink: 0;
    }
    .filter-bar input[type="date"],
    .filter-bar input[type="text"] {
        background: #060913 !important;
        border: 1px solid #1e293b !important;
        color: #f1f5f9 !important;
        padding: 9px 12px !important;
        border-radius: 6px !important;
        font-size: 13.5px !important;
        outline: none !important;
        transition: border-color 0.2s ease;
    }
    .filter-bar input[type="text"] {
        flex: 1;
    }
    .filter-bar input:focus {
        border-color: #2563eb !important;
    }

    /* FIXED TABLE HEADER + SCROLLABLE TBODY */
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
        border-bottom: 2px solid #2563eb;
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
        padding: 11px 12px;
        text-align: left;
        font-size: 12px;
        font-weight: 700;
        color: #60a5fa;
        background: #111c35;
    }
    .fixed-table td {
        padding: 10px 12px;
        font-size: 13px;
        color: #f1f5f9;
        border-bottom: 1px solid #1e293b;
        vertical-align: middle;
    }
    .fixed-table tbody tr:hover {
        background: rgba(37, 99, 235, 0.08);
    }
</style>

<?php if ($flash): ?>
    <div style="background:<?= $flash[0] === 'ok' ? 'rgba(22, 163, 74, 0.2)' : 'rgba(220, 38, 38, 0.2)' ?>;
                border:1px solid <?= $flash[0] === 'ok' ? '#22c55e' : '#ef4444' ?>;
                color:<?= $flash[0] === 'ok' ? '#86efac' : '#fca5a5' ?>;
                padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        <?= h($flash[1]) ?>
    </div>
<?php endif; ?>

<div class="attendance-grid">
    <!-- LEFT CARD: MEMBER CHECK-IN -->
    <div class="card">
        <h3 style="margin-bottom: 14px; flex-shrink: 0;">Member Check-In</h3>

        <form method="GET" style="flex-shrink: 0;">
            <?php if ($fDate !== ''): ?>
                <input type="hidden" name="date" value="<?= h($fDate) ?>">
            <?php endif; ?>
            <div class="form-group">
                <label>Search by Member ID or Name</label>
                <input type="text" name="q" value="<?= h($q) ?>" placeholder="e.g. MEM109 or Jihyo Park">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; padding:10px;">Search Member</button>
        </form>

        <?php if ($q !== ''): ?>
            <div class="checkin-results-scroll">
                <?php if (empty($results)): ?>
                    <p style="color:#94a3b8; font-size:13.5px; text-align:center; margin-top:10px;">No member found.</p>
                <?php else: foreach ($results as $m):
                    $s = $m['info']['status'];
                    $blocked = ($s === 'Expired');
                    $statusBadge = ($s === 'Active') ? 'badge-green' : (($s === 'Expiring Soon') ? 'badge-amber' : 'badge-red');
                ?>
                    <div class="checkin-card">
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                            <div>
                                <strong style="color:#ffffff; font-size:14.5px;"><?= h($m['FirstName'] . ' ' . $m['LastName']) ?></strong>
                                <span style="color:#94a3b8; font-size:12.5px;">(<?= h($m['MemberID']) ?>)</span>
                            </div>
                            <span class="badge <?= $statusBadge ?>"><?= h($s) ?></span>
                        </div>

                        <div style="margin-top:8px; font-size:13px; color:#cbd5e1; line-height:1.5;">
                            <div>Plan: <strong style="color:#f1f5f9;"><?= h($m['info']['plan']) ?></strong></div>
                            <div>Expires: <strong style="color:#f1f5f9;"><?= h($m['info']['end']) ?></strong></div>
                            <?php if ($m['inside']): ?>
                                <div style="margin-top:4px;"><span class="badge badge-green">Currently Inside Gym</span></div>
                            <?php endif; ?>
                        </div>

                        <?php if ($blocked): ?>
                            <div style="margin-top:8px; color:#fca5a5; font-size:12.5px;">
                                Membership expired. Please renew before entering the gym.
                            </div>
                        <?php endif; ?>

                        <form method="POST" style="margin-top:12px;">
                            <input type="hidden" name="action" value="timein">
                            <input type="hidden" name="member_id" value="<?= h($m['MemberID']) ?>">
                            <?php $off = ($blocked || $m['inside']); ?>
                            <button type="submit" class="btn btn-primary"
                                style="width:100%;<?= $off ? ' opacity:0.45; cursor:not-allowed;' : '' ?>"
                                <?= $off ? 'disabled' : '' ?>>
                                <?= $m['inside'] ? 'Already Inside' : 'Time-In Member' ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT CARD: ATTENDANCE LOGS TABLE -->
    <div class="card">
        <h3 style="margin-bottom: 12px; flex-shrink: 0;">
            Attendance Logs (<span id="attCount"><?= count($logs) ?></span> Records)
            <?php if ($fDate !== ''): ?>
                <small style="color:#60a5fa; font-weight:500;">— <?= h($fDate) ?></small>
            <?php endif; ?>
        </h3>

        <form method="GET" class="filter-bar">
            <input type="date" name="date" value="<?= h($fDate) ?>" onchange="this.form.submit()">
            <input type="text" id="liveAttSearch" name="member" value="<?= h($fMember) ?>"
                   onkeyup="filterAttendanceTable()" oninput="filterAttendanceTable()"
                   placeholder="Filter by Attendance ID, Member ID, or Name...">
            <button type="submit" class="btn btn-primary" style="padding:9px 18px;">Search</button>
        </form>

        <div class="fixed-table-wrap">
            <table class="fixed-table">
                <thead>
                    <tr>
                        <th style="width: 13%;">ID</th>
                        <th style="width: 27%;">Member</th>
                        <th style="width: 16%;">Date</th>
                        <th style="width: 14%;">Time In</th>
                        <th style="width: 16%;">Time Out</th>
                        <th style="width: 14%;">Action</th>
                    </tr>
                </thead>
                <tbody id="attTableBody">
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="6" style="text-align:center; color:#94a3b8; padding:18px;">No attendance logs found.</td></tr>
                    <?php else: foreach ($logs as $l):
                        $isInsideRow = empty($l['TimeOut']) || $l['TimeOut'] === '00:00:00';
                    ?>
                        <tr class="att-row" data-member-id="<?= h(strtolower($l['MemberID'])) ?>">
                            <td style="width: 13%;"><strong><?= h($l['AttendanceID']) ?></strong></td>
                            <td style="width: 27%;"><?= h($l['FullName']) ?></td>
                            <td style="width: 16%;"><?= h($l['Date'] ?? '-') ?></td>
                            <td style="width: 14%;"><?= h($l['TimeIn'] ?? '-') ?></td>
                            <td style="width: 16%;"><?= !$isInsideRow ? h($l['TimeOut']) : '<span class="badge badge-green">Inside Gym</span>' ?></td>
                            <td style="width: 14%;">
                                <?php if ($isInsideRow): ?>
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="action" value="timeout">
                                        <input type="hidden" name="attendance_id" value="<?= h($l['AttendanceID']) ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Time-Out</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:#64748b;">Done</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    <tr id="noAttMatchRow" style="display: none;">
                        <td colspan="6" style="text-align:center; color:#94a3b8; padding:18px;">No attendance logs found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function filterAttendanceTable() {
    const query = document.getElementById('liveAttSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#attTableBody .att-row');
    const noMatchRow = document.getElementById('noAttMatchRow');
    let visibleCount = 0;

    rows.forEach(row => {
        const attId      = row.cells[0].textContent.toLowerCase();
        const memberName = row.cells[1].textContent.toLowerCase();
        const attDate    = row.cells[2].textContent.toLowerCase();
        const memberId   = (row.getAttribute('data-member-id') || '').toLowerCase();

        if (
            attId.includes(query) ||
            memberId.includes(query) ||
            memberName.includes(query) ||
            attDate.includes(query)
        ) {
            row.style.display = 'table';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    document.getElementById('attCount').textContent = visibleCount;
    if (noMatchRow) {
        noMatchRow.style.display = (rows.length > 0 && visibleCount === 0) ? 'table' : 'none';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
