<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Attendance Logs";
$logs = $pdo->query("
    SELECT a.*, CONCAT(m.FirstName, ' ', m.LastName) AS FullName
    FROM attendance a
    JOIN gym_member m ON a.MemberID = m.MemberID
    ORDER BY a.AttendanceID DESC LIMIT 25
")->fetchAll();

// TODO [Member 3]: Add Time-In (INSERT into attendance) and Time-Out (UPDATE TimeOut)
include __DIR__ . '/../includes/header.php';
?>

<div class="grid-2">
    <div class="card">
        <h3 style="margin-bottom: 14px;">Member Check-In</h3>
        <form method="POST">
            <div class="form-group"><label>Member ID</label><input type="text" placeholder="e.g. MEM-001"></div>
            <button type="button" class="btn btn-success" style="width:100%;">Time-In Member</button>
        </form>
    </div>

    <div class="card">
        <h3 style="margin-bottom: 12px;">Attendance Logs (<?= count($logs) ?> Records)</h3>
        <table class="table">
            <thead><tr><th>ID</th><th>Member</th><th>Date</th><th>Time In</th><th>Time Out</th><th>Action</th></tr></thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="6">No attendance logs found.</td></tr>
                <?php else: foreach ($logs as $l): 
                    $attDate = $l['Date'] ?? $l['AttendanceDate'] ?? '-';
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($l['AttendanceID']) ?></strong></td>
                        <td><?= htmlspecialchars($l['FullName']) ?></td>
                        <td><?= htmlspecialchars($attDate) ?></td>
                        <td><?= htmlspecialchars($l['TimeIn'] ?? '-') ?></td>
                        <td><?= !empty($l['TimeOut']) ? htmlspecialchars($l['TimeOut']) : '<span class="badge badge-green">Inside Gym</span>' ?></td>
                        <td><button type="button" class="btn btn-danger btn-sm">Time-Out</button></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>