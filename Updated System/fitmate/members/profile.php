<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Member Profile";
$memberId = trim($_GET['id'] ?? '');
$tab = $_GET['tab'] ?? 'overview';
$allowedTabs = ['overview', 'payments', 'programs', 'progress'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'overview';
}

if ($memberId === '') {
    header("Location: /fitmate/members/records.php");
    exit;
}

// 1. Fetch Member + Emergency Contact using PDO
$stmt = $pdo->prepare("
    SELECT m.*, ec.ContactName, ec.Relationship, ec.PhoneNumber AS ContactPhone
    FROM gym_member m
    LEFT JOIN emergency_contact ec ON m.MemberID = ec.MemberID
    WHERE m.MemberID = ?
");
$stmt->execute([$memberId]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    header("Location: /fitmate/members/records.php");
    exit;
}

// 2. Fetch Latest Subscription (Overview)
$subStmt = $pdo->prepare("
    SELECT s.*, mp.PlanName, mp.Price, mp.DurationDays
    FROM subscription s
    LEFT JOIN membership_plan mp ON s.PlanID = mp.PlanID
    WHERE s.MemberID = ?
    ORDER BY s.EndDate DESC, s.SubscriptionID DESC
    LIMIT 1
");
$subStmt->execute([$memberId]);
$subscription = $subStmt->fetch(PDO::FETCH_ASSOC);

// 3. Fetch Attendance Logs (Overview)
$attStmt = $pdo->prepare("
    SELECT * FROM attendance
    WHERE MemberID = ?
    ORDER BY AttendanceID DESC
    LIMIT 30
");
$attStmt->execute([$memberId]);
$attendanceLogs = $attStmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Fetch Payment History (Payments Tab)
$payStmt = $pdo->prepare("
    SELECT p.*, mp.PlanName, CONCAT(gs.FirstName, ' ', gs.LastName) AS StaffName
    FROM payment p
    LEFT JOIN subscription s ON p.SubscriptionID = s.SubscriptionID
    LEFT JOIN membership_plan mp ON s.PlanID = mp.PlanID
    LEFT JOIN gym_staff gs ON p.StaffID = gs.StaffID
    WHERE p.MemberID = ?
    ORDER BY p.PaymentDate DESC, p.PaymentID DESC
");
$payStmt->execute([$memberId]);
$payments = $payStmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Fetch Program Applications (Programs Tab)
$appStmt = $pdo->prepare("
    SELECT a.*, p.ProgramName, p.Schedule, CONCAT(gs.FirstName, ' ', gs.LastName) AS TrainerName
    FROM application a
    LEFT JOIN program p ON a.ProgramID = p.ProgramID
    LEFT JOIN gym_staff gs ON p.StaffID = gs.StaffID
    WHERE a.MemberID = ?
    ORDER BY CAST(SUBSTRING(a.ApplicationID, 4) AS UNSIGNED) DESC
");
$appStmt->execute([$memberId]);
$applications = $appStmt->fetchAll(PDO::FETCH_ASSOC);

// 6. Fetch Fitness Progress Records (Progress Tab)
$progStmt = $pdo->prepare("
    SELECT * FROM progress
    WHERE MemberID = ?
    ORDER BY `Date` DESC, RecordID DESC
");
$progStmt->execute([$memberId]);
$progressLogs = $progStmt->fetchAll(PDO::FETCH_ASSOC);

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

    /* Tab Navigation Bar */
    .profile-tabs {
        display: flex;
        gap: 8px;
        flex-shrink: 0;
    }
    .profile-tab-btn {
        padding: 9px 18px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        background: #0c1324;
        border: 1px solid #1e293b;
        color: #94a3b8;
        transition: all 0.2s ease;
    }
    .profile-tab-btn:hover {
        border-color: #2563eb;
        color: #ffffff;
    }
    .profile-tab-btn.active {
        background: #2563eb;
        border-color: #2563eb;
        color: #ffffff;
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
        min-width: 125px;
    }

    .bottom-fill-card {
        flex: 1 !important;
        display: flex !important;
        flex-direction: column !important;
        min-height: 0 !important;
        margin-bottom: 0 !important;
        overflow: hidden !important;
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
                        $subStatus = $subscription['Status'] ?: 'Active';
                        $subBadge = 'badge badge-green';
                    }
                }
            ?>
            <span class="<?= $subBadge ?>"><?= htmlspecialchars($subStatus) ?></span>
            <?php if (!empty($member['is_archived'])): ?>
                <span class="badge badge-amber">Archived</span>
            <?php endif; ?>
        </div>
        <p style="margin: 4px 0 0 0; font-size: 13px; color: #94a3b8;">
            Member ID: <strong style="color: #e2e8f0;"><?= htmlspecialchars($member['MemberID']) ?></strong> |
            Phone: <?= htmlspecialchars($member['PhoneNumber'] ?? 'N/A') ?> |
            DOB: <?= htmlspecialchars($member['DateOfBirth'] ?? 'N/A') ?>
        </p>
    </div>

    <div style="display: flex; gap: 8px;">
        <a href="/fitmate/members/records.php?edit=<?= urlencode($member['MemberID']) ?>" class="btn btn-warning">Edit Member</a>
        <a href="/fitmate/members/records.php" class="btn btn-primary">Back to Records</a>
    </div>
</div>

<!-- TAB NAVIGATION -->
<div class="profile-tabs">
    <a href="profile.php?id=<?= urlencode($memberId) ?>&tab=overview" class="profile-tab-btn <?= $tab === 'overview' ? 'active' : '' ?>">Overview</a>
    <a href="profile.php?id=<?= urlencode($memberId) ?>&tab=payments" class="profile-tab-btn <?= $tab === 'payments' ? 'active' : '' ?>">Payments (<?= count($payments) ?>)</a>
    <a href="profile.php?id=<?= urlencode($memberId) ?>&tab=programs" class="profile-tab-btn <?= $tab === 'programs' ? 'active' : '' ?>">Programs (<?= count($applications) ?>)</a>
    <a href="profile.php?id=<?= urlencode($memberId) ?>&tab=progress" class="profile-tab-btn <?= $tab === 'progress' ? 'active' : '' ?>">Progress (<?= count($progressLogs) ?>)</a>
</div>

<?php if ($tab === 'overview'): ?>
    <!-- OVERVIEW TAB -->
    <div class="info-grid-3">
        <div class="card">
            <h3 style="color: #60a5fa; margin-bottom: 6px;">Personal Details</h3>
            <ul class="info-list">
                <li><strong>Member ID:</strong> <?= htmlspecialchars($member['MemberID']) ?></li>
                <li><strong>Full Name:</strong> <?= htmlspecialchars($member['FirstName'] . ' ' . $member['LastName']) ?></li>
                <li><strong>Date of Birth:</strong> <?= htmlspecialchars($member['DateOfBirth'] ?? 'N/A') ?></li>
                <li><strong>Phone Number:</strong> <?= htmlspecialchars($member['PhoneNumber'] ?? 'N/A') ?></li>
            </ul>
        </div>

        <div class="card">
            <h3 style="color: #60a5fa; margin-bottom: 6px;">Emergency Contact</h3>
            <ul class="info-list">
                <?php if (!empty($member['ContactName'])): ?>
                    <li><strong>Contact Name:</strong> <?= htmlspecialchars($member['ContactName']) ?></li>
                    <li><strong>Relationship:</strong> <?= htmlspecialchars($member['Relationship'] ?? 'N/A') ?></li>
                    <li><strong>Contact Phone:</strong> <?= htmlspecialchars($member['ContactPhone'] ?? 'N/A') ?></li>
                <?php else: ?>
                    <li style="color: #94a3b8;">No emergency contact recorded.</li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="card">
            <h3 style="color: #60a5fa; margin-bottom: 6px;">Latest Subscription</h3>
            <ul class="info-list">
                <?php if ($subscription): ?>
                    <li><strong>Plan Name:</strong> <?= htmlspecialchars($subscription['PlanName'] ?? 'N/A') ?></li>
                    <li><strong>Start Date:</strong> <?= htmlspecialchars($subscription['StartDate']) ?></li>
                    <li><strong>End Date:</strong> <?= htmlspecialchars($subscription['EndDate']) ?></li>
                    <li><strong>Status:</strong> <span class="<?= $subBadge ?>"><?= htmlspecialchars($subStatus) ?></span></li>
                <?php else: ?>
                    <li style="color: #94a3b8;">No subscription found.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="card bottom-fill-card">
        <h3 style="margin-bottom: 10px; flex-shrink: 0;">Recent Gym Attendance Logs</h3>
        <div class="fixed-table-wrap">
            <table class="fixed-table">
                <thead>
                    <tr>
                        <th style="width: 22%;">Attendance ID</th>
                        <th style="width: 26%;">Date</th>
                        <th style="width: 26%;">Time In</th>
                        <th style="width: 26%;">Time Out</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attendanceLogs)): ?>
                        <tr><td colspan="4" style="text-align: center; color: #94a3b8; padding: 18px;">No attendance logs recorded.</td></tr>
                    <?php else: foreach ($attendanceLogs as $att):
                        $isInside = empty($att['TimeOut']) || $att['TimeOut'] === '00:00:00';
                    ?>
                        <tr>
                            <td style="width: 22%;"><strong><?= htmlspecialchars($att['AttendanceID']) ?></strong></td>
                            <td style="width: 26%;"><?= htmlspecialchars($att['Date'] ?? '-') ?></td>
                            <td style="width: 26%;"><?= htmlspecialchars($att['TimeIn'] ?? '-') ?></td>
                            <td style="width: 26%;"><?= !$isInside ? htmlspecialchars($att['TimeOut']) : '<span class="badge badge-green">Inside Gym</span>' ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($tab === 'payments'): ?>
    <!-- PAYMENTS TAB -->
    <div class="card bottom-fill-card">
        <h3 style="margin-bottom: 10px; flex-shrink: 0;">Payment History (<?= count($payments) ?> Transactions)</h3>
        <div class="fixed-table-wrap">
            <table class="fixed-table">
                <thead>
                    <tr>
                        <th style="width: 12%;">Payment ID</th>
                        <th style="width: 20%;">Plan</th>
                        <th style="width: 14%;">Amount</th>
                        <th style="width: 13%;">Method</th>
                        <th style="width: 17%;">Date & Time</th>
                        <th style="width: 24%;">Reference No.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="6" style="text-align: center; color: #94a3b8; padding: 18px;">No payments recorded for this member.</td></tr>
                    <?php else: foreach ($payments as $pay): ?>
                        <tr>
                            <td style="width: 12%;"><strong>#<?= htmlspecialchars($pay['PaymentID']) ?></strong></td>
                            <td style="width: 20%;"><?= htmlspecialchars($pay['PlanName'] ?? $pay['SubscriptionID'] ?? '-') ?></td>
                            <td style="width: 14%;">₱<?= number_format((float)$pay['Amount'], 2) ?></td>
                            <td style="width: 13%;"><span class="badge badge-green"><?= htmlspecialchars($pay['PaymentMethod']) ?></span></td>
                            <td style="width: 17%;"><?= htmlspecialchars($pay['PaymentDate'] . ' ' . ($pay['PaymentTime'] ?? '')) ?></td>
                            <td style="width: 24%; color: #94a3b8;"><?= htmlspecialchars($pay['ReferenceNumber'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($tab === 'programs'): ?>
    <!-- PROGRAMS TAB -->
    <div class="card bottom-fill-card">
        <h3 style="margin-bottom: 10px; flex-shrink: 0;">Enrolled & Applied Fitness Programs (<?= count($applications) ?>)</h3>
        <div class="fixed-table-wrap">
            <table class="fixed-table">
                <thead>
                    <tr>
                        <th style="width: 14%;">App ID</th>
                        <th style="width: 26%;">Program Name</th>
                        <th style="width: 22%;">Schedule</th>
                        <th style="width: 16%;">Date Applied</th>
                        <th style="width: 22%;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($applications)): ?>
                        <tr><td colspan="5" style="text-align: center; color: #94a3b8; padding: 18px;">No program applications found.</td></tr>
                    <?php else: foreach ($applications as $app):
                        $st = $app['Status'] ?? 'Pending';
                        $bClass = $st === 'Approved' ? 'badge badge-green' : ($st === 'Pending' ? 'badge badge-amber' : 'badge badge-red');
                    ?>
                        <tr>
                            <td style="width: 14%;"><strong><?= htmlspecialchars($app['ApplicationID']) ?></strong></td>
                            <td style="width: 26%;"><?= htmlspecialchars($app['ProgramName'] ?? '-') ?></td>
                            <td style="width: 22%;"><?= htmlspecialchars($app['Schedule'] ?? '-') ?></td>
                            <td style="width: 16%;"><?= htmlspecialchars($app['ApplicationDate'] ?? '-') ?></td>
                            <td style="width: 22%;"><span class="<?= $bClass ?>"><?= htmlspecialchars($st) ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($tab === 'progress'): ?>
    <!-- PROGRESS TAB -->
    <div class="card bottom-fill-card">
        <h3 style="margin-bottom: 10px; flex-shrink: 0;">Fitness Progress Tracking (<?= count($progressLogs) ?> Records)</h3>
        <div class="fixed-table-wrap">
            <table class="fixed-table">
                <thead>
                    <tr>
                        <th style="width: 18%;">Record ID</th>
                        <th style="width: 22%;">Date</th>
                        <th style="width: 20%;">Height</th>
                        <th style="width: 20%;">Weight</th>
                        <th style="width: 20%;">BMI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($progressLogs)): ?>
                        <tr><td colspan="5" style="text-align: center; color: #94a3b8; padding: 18px;">No progress records logged yet.</td></tr>
                    <?php else: foreach ($progressLogs as $pg):
                        $hMeters = (float)$pg['Height'] / 100;
                        $bmi = $hMeters > 0 ? round((float)$pg['Weight'] / ($hMeters * $hMeters), 1) : 0;
                    ?>
                        <tr>
                            <td style="width: 18%;"><strong><?= htmlspecialchars($pg['RecordID']) ?></strong></td>
                            <td style="width: 22%;"><?= htmlspecialchars($pg['Date']) ?></td>
                            <td style="width: 20%;"><?= htmlspecialchars($pg['Height']) ?> cm</td>
                            <td style="width: 20%;"><?= htmlspecialchars($pg['Weight']) ?> kg</td>
                            <td style="width: 20%;"><span class="badge badge-green"><?= number_format($bmi, 1) ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>