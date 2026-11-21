<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Staff Profile";
$staffId = $_GET['id'] ?? '';

// 1. Kunin ang base info + subtype details ng staff
$stmt = $pdo->prepare("
    SELECT gs.*,
           t.Certification,
           sa.Username AS SystemUsername, sa.Role AS SystemRole
    FROM gym_staff gs
    LEFT JOIN trainer t ON gs.StaffID = t.StaffID
    LEFT JOIN system_access sa ON gs.StaffID = sa.StaffID
    WHERE gs.StaffID = ?
");
$stmt->execute([$staffId]);
$staff = $stmt->fetch();

// Fallback kung walang ?id= sa URL
if (!$staff) {
    $staff = $pdo->query("
        SELECT gs.*, t.Certification, sa.Username AS SystemUsername, sa.Role AS SystemRole
        FROM gym_staff gs
        LEFT JOIN trainer t ON gs.StaffID = t.StaffID
        LEFT JOIN system_access sa ON gs.StaffID = sa.StaffID
        ORDER BY gs.StaffID ASC LIMIT 1
    ")->fetch();
    $staffId = $staff['StaffID'] ?? '';
}

// 2. Kunin ang related records base sa StaffType
$handledPrograms = [];
$maintSchedules  = [];
$assignedEquips  = [];
$staffLogins     = [];

if ($staff) {
    if ($staff['StaffType'] === 'Trainer') {
        $pStmt = $pdo->prepare("SELECT * FROM program WHERE StaffID = ? ORDER BY ProgramID ASC");
        $pStmt->execute([$staffId]);
        $handledPrograms = $pStmt->fetchAll();
    } elseif ($staff['StaffType'] === 'Maintenance') {
        $mStmt = $pdo->prepare("SELECT * FROM maintenance WHERE StaffID = ?");
        $mStmt->execute([$staffId]);
        $maintSchedules = $mStmt->fetchAll();

        $eStmt = $pdo->prepare("SELECT * FROM equipment WHERE StaffID = ? ORDER BY EquipmentID ASC");
        $eStmt->execute([$staffId]);
        $assignedEquips = $eStmt->fetchAll();
    } elseif ($staff['StaffType'] === 'Admin') {
        $lStmt = $pdo->prepare("SELECT * FROM login_history WHERE StaffID = ? ORDER BY LoginID DESC LIMIT 10");
        $lStmt->execute([$staffId]);
        $staffLogins = $lStmt->fetchAll();
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <?php if (!$staff): ?>
        <p>No staff record found.</p>
    <?php else: 
        $badgeClass = 'badge';
        if ($staff['StaffType'] === 'Trainer') $badgeClass = 'badge badge-green';
        elseif ($staff['StaffType'] === 'Maintenance') $badgeClass = 'badge badge-amber';
    ?>
        <!-- HEADER BANNER -->
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #1e293b; padding-bottom:16px; margin-bottom:18px; flex-wrap:wrap; gap:12px;">
            <div>
                <h2 style="display:flex; align-items:center; gap:10px;">
                    <?= htmlspecialchars($staff['FirstName'] . ' ' . $staff['LastName']) ?>
                    <span class="<?= $badgeClass ?>"><?= htmlspecialchars($staff['StaffType']) ?></span>
                </h2>
                <p style="font-size:13px; color:#94a3b8; margin-top:4px;">
                    Staff ID: <strong><?= htmlspecialchars($staff['StaffID']) ?></strong> | Date of Birth: <?= htmlspecialchars($staff['DateOfBirth'] ?? '-') ?>
                </p>
            </div>
            <a href="/fitmate/auth/staff_records.php" class="btn btn-primary btn-sm">Back to Staff Records</a>
        </div>

        <!-- GENERAL & ROLE DETAILS -->
        <div class="grid-equal">
            <div style="background:#080d1a; border:1px solid #1e293b; border-radius:8px; padding:16px;">
                <h4 style="color:#60a5fa; margin-bottom:10px;">Personal Information</h4>
                <p style="font-size:13px; margin-bottom:6px;"><strong>Staff ID:</strong> <?= htmlspecialchars($staff['StaffID']) ?></p>
                <p style="font-size:13px; margin-bottom:6px;"><strong>Full Name:</strong> <?= htmlspecialchars($staff['FirstName'] . ' ' . $staff['LastName']) ?></p>
                <p style="font-size:13px; margin-bottom:6px;"><strong>Date of Birth:</strong> <?= htmlspecialchars($staff['DateOfBirth'] ?? '-') ?></p>
                <p style="font-size:13px;"><strong>Staff Role / Type:</strong> <?= htmlspecialchars($staff['StaffType']) ?></p>
            </div>

            <div style="background:#080d1a; border:1px solid #1e293b; border-radius:8px; padding:16px;">
                <h4 style="color:#60a5fa; margin-bottom:10px;">Role & Subtype Details</h4>
                <?php if ($staff['StaffType'] === 'Trainer'): ?>
                    <p style="font-size:13px; margin-bottom:6px;"><strong>Certification:</strong> <?= htmlspecialchars($staff['Certification'] ?: 'No certification recorded') ?></p>
                    <p style="font-size:13px;"><strong>Total Programs Handled:</strong> <?= count($handledPrograms) ?> Program(s)</p>
                <?php elseif ($staff['StaffType'] === 'Maintenance'): ?>
                    <p style="font-size:13px; margin-bottom:6px;"><strong>Assigned Schedules:</strong> <?= count($maintSchedules) ?> Schedule(s)</p>
                    <p style="font-size:13px;"><strong>Equipment Assigned:</strong> <?= count($assignedEquips) ?> Item(s)</p>
                <?php else: ?>
                    <p style="font-size:13px; margin-bottom:6px;"><strong>System Username:</strong> <?= htmlspecialchars($staff['SystemUsername'] ?: 'No system access account') ?></p>
                    <p style="font-size:13px;"><strong>Access Role:</strong> <?= htmlspecialchars($staff['SystemRole'] ?: 'N/A') ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- SUBTYPE RELATED TABLES -->
<?php if ($staff && $staff['StaffType'] === 'Trainer'): ?>
    <div class="card">
        <h3 style="margin-bottom: 12px;">Assigned Fitness Programs (<?= count($handledPrograms) ?>)</h3>
        <table class="table">
            <thead><tr><th>Program ID</th><th>Program Name</th><th>Schedule</th></tr></thead>
            <tbody>
                <?php if (empty($handledPrograms)): ?>
                    <tr><td colspan="3">No fitness programs assigned to this trainer yet.</td></tr>
                <?php else: foreach ($handledPrograms as $pr): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($pr['ProgramID']) ?></strong></td>
                        <td><?= htmlspecialchars($pr['ProgramName']) ?></td>
                        <td><?= htmlspecialchars($pr['Schedule'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
<?php elseif ($staff && $staff['StaffType'] === 'Maintenance'): ?>
    <div class="card">
        <h3 style="margin-bottom: 12px;">Maintenance Work Schedules & Tasks</h3>
        <table class="table">
            <thead><tr><th>Staff ID</th><th>Work Schedule</th><th>Assigned Task</th></tr></thead>
            <tbody>
                <?php if (empty($maintSchedules)): ?>
                    <tr><td colspan="3">No maintenance schedule found.</td></tr>
                <?php else: foreach ($maintSchedules as $ms): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($ms['StaffID']) ?></strong></td>
                        <td><?= htmlspecialchars($ms['WorkSchedule'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($ms['AssignedTask'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-bottom: 12px;">Assigned Gym Equipment (<?= count($assignedEquips) ?>)</h3>
        <table class="table">
            <thead><tr><th>Equip ID</th><th>Equipment Name</th><th>Status</th><th>Vendor</th></tr></thead>
            <tbody>
                <?php if (empty($assignedEquips)): ?>
                    <tr><td colspan="4">No equipment assigned to this maintenance staff.</td></tr>
                <?php else: foreach ($assignedEquips as $eq): 
                    $eqStatus = $eq['EquipmentStatus'] ?? $eq['Status'] ?? 'Operational';
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($eq['EquipmentID']) ?></strong></td>
                        <td><?= htmlspecialchars($eq['EquipmentName']) ?></td>
                        <td><span class="badge <?= $eqStatus === 'Operational' ? 'badge-green' : 'badge-amber' ?>"><?= htmlspecialchars($eqStatus) ?></span></td>
                        <td><?= htmlspecialchars($eq['Vendor'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
<?php elseif ($staff && $staff['StaffType'] === 'Admin'): ?>
    <div class="card">
        <h3 style="margin-bottom: 12px;">Recent System Login History</h3>
        <table class="table">
            <thead><tr><th>Login ID</th><th>Login Date</th><th>Login Time</th></tr></thead>
            <tbody>
                <?php if (empty($staffLogins)): ?>
                    <tr><td colspan="3">No login history recorded.</td></tr>
                <?php else: foreach ($staffLogins as $lg): ?>
                    <tr>
                        <td>#<?= htmlspecialchars($lg['LoginID']) ?></td>
                        <td><?= htmlspecialchars($lg['LoginDate']) ?></td>
                        <td><?= htmlspecialchars($lg['LoginTime']) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
