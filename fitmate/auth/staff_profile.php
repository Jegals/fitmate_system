<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Staff Profile";
$staffId = trim($_GET['id'] ?? '');

if ($staffId === '') {
    header("Location: /fitmate/auth/staff_records.php");
    exit;
}

// 1. Fetch main staff record + subtype details + system access
$stmt = $pdo->prepare("
    SELECT gs.*,
           t.Certification,
           m.WorkSchedule, m.AssignedTask,
           sa.Username AS SystemUsername,
           sa.Role AS AccessRole
    FROM gym_staff gs
    LEFT JOIN trainer t ON gs.StaffID = t.StaffID
    LEFT JOIN maintenance m ON gs.StaffID = m.StaffID
    LEFT JOIN system_access sa ON gs.StaffID = sa.StaffID
    WHERE gs.StaffID = ?
");
$stmt->execute([$staffId]);
$staff = $stmt->fetch();

if (!$staff) {
    header("Location: /fitmate/auth/staff_records.php");
    exit;
}

// 2. Fetch Recent System Login History for this staff member (without displaying LoginID)
$logStmt = $pdo->prepare("
    SELECT LoginDate, LoginTime
    FROM login_history
    WHERE StaffID = ?
    ORDER BY LoginID DESC
    LIMIT 30
");
$logStmt->execute([$staffId]);
$loginLogs = $logStmt->fetchAll();

// 3. If Trainer, fetch assigned programs/sessions if any
$trainerPrograms = [];
if ($staff['StaffType'] === 'Trainer') {
    $progStmt = $pdo->prepare("
        SELECT *
        FROM program
        WHERE StaffID = ?
        ORDER BY ProgramID ASC
    ");
    $progStmt->execute([$staffId]);
    $trainerPrograms = $progStmt->fetchAll();
}

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

    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
        flex-shrink: 0;
    }
    .info-grid > .card {
        margin-bottom: 0 !important;
    }
    .info-list {
        list-style: none;
        padding: 0;
        margin: 10px 0 0 0;
        display: flex;
        flex-direction: column;
        gap: 8px;
        font-size: 13.5px;
        color: #f1f5f9;
    }
    .info-list strong {
        color: #cbd5e1;
        display: inline-block;
        min-width: 145px;
    }

    /* Bottom Card: Full remaining height + internal scrollable table */
    .bottom-fill-card {
        flex: 1 !important;
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
        padding: 11px 14px;
        text-align: left;
        font-size: 12px;
        font-weight: 700;
        color: #60a5fa;
        background: #111c35;
    }
    .fixed-table td {
        padding: 10px 14px;
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
                <?= htmlspecialchars($staff['FirstName'] . ' ' . $staff['LastName']) ?>
            </h2>
            <?php
                $badgeClass = 'badge';
                if ($staff['StaffType'] === 'Trainer') $badgeClass = 'badge badge-green';
                elseif ($staff['StaffType'] === 'Maintenance') $badgeClass = 'badge badge-amber';
            ?>
            <span class="<?= $badgeClass ?>"><?= htmlspecialchars($staff['StaffType']) ?></span>
        </div>
        <p style="margin: 4px 0 0 0; font-size: 13px; color: #94a3b8;">
            Staff ID: <strong style="color: #e2e8f0;"><?= htmlspecialchars($staff['StaffID']) ?></strong> |
            Date of Birth: <?= htmlspecialchars($staff['DateOfBirth']) ?>
        </p>
    </div>

    <a href="/fitmate/auth/staff_records.php" class="btn btn-primary">Back to Staff Records</a>
</div>

<!-- PERSONAL INFORMATION & ROLE / SUBTYPE DETAILS -->
<div class="info-grid">
    <div class="card">
        <h3 style="color: #60a5fa; margin-bottom: 8px;">Personal Information</h3>
        <ul class="info-list">
            <li><strong>Staff ID:</strong> <?= htmlspecialchars($staff['StaffID']) ?></li>
            <li><strong>Full Name:</strong> <?= htmlspecialchars($staff['FirstName'] . ' ' . $staff['LastName']) ?></li>
            <li><strong>Date of Birth:</strong> <?= htmlspecialchars($staff['DateOfBirth']) ?></li>
            <li><strong>Staff Role / Type:</strong> <?= htmlspecialchars($staff['StaffType']) ?></li>
        </ul>
    </div>

    <div class="card">
        <h3 style="color: #60a5fa; margin-bottom: 8px;">Role & Subtype Details</h3>
        <ul class="info-list">
            <?php if ($staff['StaffType'] === 'Trainer'): ?>
                <li><strong>Certification:</strong> <?= htmlspecialchars($staff['Certification'] ?: 'Not specified') ?></li>
                <li><strong>Assigned Programs:</strong> <?= count($trainerPrograms) ?> Program(s)</li>
            <?php elseif ($staff['StaffType'] === 'Maintenance'): ?>
                <li><strong>Work Schedule:</strong> <?= htmlspecialchars($staff['WorkSchedule'] ?: 'Not specified') ?></li>
                <li><strong>Assigned Task:</strong> <?= htmlspecialchars($staff['AssignedTask'] ?: 'Not specified') ?></li>
            <?php else: ?>
                <li><strong>System Username:</strong> <?= htmlspecialchars($staff['SystemUsername'] ?: 'N/A') ?></li>
                <li><strong>Access Role:</strong> <?= htmlspecialchars($staff['AccessRole'] ?: $staff['StaffType']) ?></li>
            <?php endif; ?>
        </ul>
    </div>
</div>

<!-- BOTTOM CARD: RECENT SYSTEM LOGIN HISTORY (NO LOGIN ID COLUMN) -->
<div class="card bottom-fill-card">
    <h3 style="margin-bottom: 12px; flex-shrink: 0;">Recent System Login History</h3>
    <div class="fixed-table-wrap">
        <table class="fixed-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Login Date</th>
                    <th style="width: 50%;">Login Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($loginLogs)): ?>
                    <tr><td colspan="2" style="text-align: center; color: #94a3b8; padding: 18px;">No login history recorded for this staff member.</td></tr>
                <?php else: foreach ($loginLogs as $log): ?>
                    <tr>
                        <td style="width: 50%;"><?= htmlspecialchars($log['LoginDate']) ?></td>
                        <td style="width: 50%;"><?= htmlspecialchars($log['LoginTime']) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
