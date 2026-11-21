<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Member Profile";
$memberId = trim($_GET['id'] ?? '');

if ($memberId === '') {
    header("Location: /fitmate/members/records.php");
    exit;
}

// 1. Fetch Member Details
$stmt = $pdo->prepare("SELECT * FROM gym_member WHERE MemberID = ?");
$stmt->execute([$memberId]);
$member = $stmt->fetch();

if (!$member) {
    header("Location: /fitmate/members/records.php");
    exit;
}

// 2. Fetch Emergency Contact
$ecStmt = $pdo->prepare("SELECT * FROM emergency_contact WHERE MemberID = ? LIMIT 1");
$ecStmt->execute([$memberId]);
$emergency = $ecStmt->fetch();

// 3. Fetch Current / Latest Subscription
$subStmt = $pdo->prepare("
    SELECT s.*, mp.PlanName, mp.Price, mp.DurationDays
    FROM subscription s
    LEFT JOIN membership_plan mp ON s.PlanID = mp.PlanID
    WHERE s.MemberID = ?
    ORDER BY s.EndDate DESC
    LIMIT 1
");
$subStmt->execute([$memberId]);
$subscription = $subStmt->fetch();

// 4. Fetch Recent Attendance Logs (walang hardcoded ORDER BY column para iwas unknown column error)
$attStmt = $pdo->prepare("SELECT * FROM attendance WHERE MemberID = ?");
$attStmt->execute([$memberId]);
$attendanceLogs = array_slice(array_reverse($attStmt->fetchAll()), 0, 20);

// 5. Fetch Progress History (walang ProgressID sa ORDER BY)
$progStmt = $pdo->prepare("SELECT * FROM progress WHERE MemberID = ?");
$progStmt->execute([$memberId]);
$progressLogs = array_slice(array_reverse($progStmt->fetchAll()), 0, 20);

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

    .profile-header-card {
        flex-shrink: 0 !important;
        margin-bottom: 0 !important;
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
    }

    .info-grid-3 {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 14px;
        flex-shrink: 0;
    }
    .info-grid-3 > .card {
        margin-bottom: 0 !important;
    }
    .info-list {
        list-style: none;
        padding: 0;
        margin: 10px 0 0 0;
        display: flex;
        flex-direction: column;
        gap: 7px;
        font-size: 13px;
        color: #f1f5f9;
    }
    .info-list strong {
        color: #cbd5e1;
        display: inline-block;
        min-width: 130px;
    }

    .bottom-grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
        flex: 1;
        min-height: 0;
    }
    .bottom-fill-card {
        display: flex !important;
        flex-direction: column !important;
        min-height: 0 !important;
        margin-bottom: 0 !important;
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
        padding: 10px 12px;
        text-align: left;
        font-size: 12px;
        font-weight: 700;
        color: #60a5fa;
        background: #111c35;
    }
    .fixed-table td {
        padding: 9px 12px;
        font-size: 13px;
        color: #f1f5f9;
        border-bottom: 1px solid #1e293b;
        vertical-align: middle;
    }
    .fixed-table tbody tr:hover {
        background: rgba(37, 99, 235, 0.08);
    }
</style>

<!-- TOP PROFILE SUMMARY CARD -->
<div class="card profile-header-card">
    <div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <h2 style="margin: 0; font-size: 22px; color: #ffffff;">
                <?= htmlspecialchars($member['FirstName'] . ' ' . $member['LastName']) ?>
            </h2>
            <?php
                $today = date('Y-m-d');
                $subStatus = 'No Subscription';
                $subBadge = 'badge';
                if ($subscription) {
                    if ($subscription['EndDate'] < $today) {
                        $subStatus = 'Expired';
                        $subBadge = 'badge badge-red';
                    } else {
                        $subStatus = 'Active';
                        $subBadge = 'badge badge-green';
                    }
                }
            ?>
            <span class="<?= $subBadge ?>"><?= htmlspecialchars($subStatus) ?></span>
        </div>
        <p style="margin: 4px 0 0 0; font-size: 13px; color: #94a3b8;">
            Member ID: <strong style="color: #e2e8f0;"><?= htmlspecialchars($member['MemberID']) ?></strong>
            <?php if (!empty($member['DateOfBirth'])): ?>
                | Date of Birth: <?= htmlspecialchars($member['DateOfBirth']) ?>
            <?php endif; ?>
        </p>
    </div>

    <a href="/fitmate/members/records.php" class="btn btn-primary">Back to Member Records</a>
</div>

<!-- 3-COLUMN INFO CARDS (PERSONAL, EMERGENCY CONTACT, SUBSCRIPTION) -->
<div class="info-grid-3">
    <div class="card">
        <h3 style="color: #60a5fa; margin-bottom: 6px;">Personal Information</h3>
        <ul class="info-list">
            <li><strong>Member ID:</strong> <?= htmlspecialchars($member['MemberID']) ?></li>
            <li><strong>Full Name:</strong> <?= htmlspecialchars($member['FirstName'] . ' ' . $member['LastName']) ?></li>
            <li><strong>Contact No.:</strong> <?= htmlspecialchars($member['ContactNumber'] ?? $member['PhoneNumber'] ?? 'N/A') ?></li>
            <li><strong>Date of Birth:</strong> <?= htmlspecialchars($member['DateOfBirth'] ?? 'N/A') ?></li>
            <?php if (!empty($member['JoinDate'])): ?>
                <li><strong>Join Date:</strong> <?= htmlspecialchars($member['JoinDate']) ?></li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="card">
        <h3 style="color: #60a5fa; margin-bottom: 6px;">Emergency Contact</h3>
        <ul class="info-list">
            <?php if ($emergency): ?>
                <li><strong>Contact Name:</strong> <?= htmlspecialchars($emergency['ContactName'] ?? $emergency['Name'] ?? 'N/A') ?></li>
                <li><strong>Relationship:</strong> <?= htmlspecialchars($emergency['Relationship'] ?? 'N/A') ?></li>
                <li><strong>Contact No.:</strong> <?= htmlspecialchars($emergency['ContactNumber'] ?? $emergency['PhoneNumber'] ?? 'N/A') ?></li>
            <?php else: ?>
                <li style="color: #94a3b8;">No emergency contact recorded.</li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="card">
        <h3 style="color: #60a5fa; margin-bottom: 6px;">Current Subscription</h3>
        <ul class="info-list">
            <?php if ($subscription): ?>
                <li><strong>Plan Name:</strong> <?= htmlspecialchars($subscription['PlanName'] ?? 'N/A') ?></li>
                <li><strong>Start Date:</strong> <?= htmlspecialchars($subscription['StartDate']) ?></li>
                <li><strong>End Date:</strong> <?= htmlspecialchars($subscription['EndDate']) ?></li>
                <li><strong>Status:</strong> <span class="<?= $subBadge ?>"><?= htmlspecialchars($subStatus) ?></span></li>
            <?php else: ?>
                <li style="color: #94a3b8;">No active or past subscription found.</li>
            <?php endif; ?>
        </ul>
    </div>
</div>

<!-- BOTTOM 2-COLUMN TABLES: ATTENDANCE & PROGRESS HISTORY -->
<div class="bottom-grid-2">
    <div class="card bottom-fill-card">
        <h3 style="margin-bottom: 10px; flex-shrink: 0;">Recent Attendance Logs</h3>
        <div class="fixed-table-wrap">
            <table class="fixed-table">
                <thead>
                    <tr>
                        <th style="width: 34%;">Date</th>
                        <th style="width: 33%;">Time In</th>
                        <th style="width: 33%;">Time Out</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attendanceLogs)): ?>
                        <tr><td colspan="3" style="text-align: center; color: #94a3b8;">No attendance logs recorded.</td></tr>
                    <?php else: foreach ($attendanceLogs as $att): ?>
                        <tr>
                            <td style="width: 34%;"><?= htmlspecialchars($att['Date'] ?? $att['AttendanceDate'] ?? '-') ?></td>
                            <td style="width: 33%;"><?= htmlspecialchars($att['TimeIn'] ?? '-') ?></td>
                            <td style="width: 33%;"><?= htmlspecialchars($att['TimeOut'] ?? 'Active') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card bottom-fill-card">
        <h3 style="margin-bottom: 10px; flex-shrink: 0;">Fitness Progress History</h3>
        <div class="fixed-table-wrap">
            <table class="fixed-table">
                <thead>
                    <tr>
                        <th style="width: 30%;">Date</th>
                        <th style="width: 25%;">Weight</th>
                        <th style="width: 45%;">Notes / Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($progressLogs)): ?>
                        <tr><td colspan="3" style="text-align: center; color: #94a3b8;">No progress records logged yet.</td></tr>
                    <?php else: foreach ($progressLogs as $prog): ?>
                        <tr>
                            <td style="width: 30%;"><?= htmlspecialchars($prog['RecordDate'] ?? $prog['DateRecorded'] ?? $prog['Date'] ?? '-') ?></td>
                            <td style="width: 25%;"><?= htmlspecialchars(($prog['Weight'] ?? '-') . (isset($prog['Weight']) ? ' kg' : '')) ?></td>
                            <td style="width: 45%;"><?= htmlspecialchars($prog['Notes'] ?? $prog['Remarks'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
