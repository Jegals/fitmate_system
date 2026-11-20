<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Program Applications";
$programs = $pdo->query("SELECT * FROM program ORDER BY ProgramName ASC")->fetchAll();
$apps = $pdo->query("
    SELECT ap.*, CONCAT(m.FirstName, ' ', m.LastName) AS FullName, p.ProgramName
    FROM application ap
    JOIN gym_member m ON ap.MemberID = m.MemberID
    JOIN program p ON ap.ProgramID = p.ProgramID
    ORDER BY ap.ApplicationID DESC
")->fetchAll();

// TODO [Member 3]: Add Apply (INSERT into application) and Approve/Cancel (UPDATE Status)
include __DIR__ . '/../includes/header.php';
?>

<div class="grid-2">
    <div class="card">
        <h3 style="margin-bottom: 14px;">Apply Member to Program</h3>
        <form method="POST">
            <div class="form-group"><label>Member ID</label><input type="text" placeholder="e.g. MEM-001"></div>
            <div class="form-group">
                <label>Select Program (Live from DB)</label>
                <select>
                    <?php foreach ($programs as $pr): ?>
                        <option value="<?= htmlspecialchars($pr['ProgramID']) ?>">
                            <?= htmlspecialchars($pr['ProgramName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" class="btn btn-primary" style="width:100%;">Submit Application</button>
        </form>
    </div>

    <div class="card">
        <h3 style="margin-bottom: 12px;">Program Application List (<?= count($apps) ?> Records)</h3>
        <table class="table">
            <thead><tr><th>App ID</th><th>Member</th><th>Program</th><th>Date Applied</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php if (empty($apps)): ?>
                    <tr><td colspan="6">No program applications found.</td></tr>
                <?php else: foreach ($apps as $a): 
                    $status = $a['Status'] ?? $a['ApplicationStatus'] ?? 'Pending';
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($a['ApplicationID']) ?></strong></td>
                        <td><?= htmlspecialchars($a['FullName']) ?></td>
                        <td><?= htmlspecialchars($a['ProgramName']) ?></td>
                        <td><?= htmlspecialchars($a['ApplicationDate'] ?? '-') ?></td>
                        <td><span class="badge <?= $status === 'Approved' ? 'badge-green' : 'badge-amber' ?>"><?= htmlspecialchars($status) ?></span></td>
                        <td>
                            <button type="button" class="btn btn-success btn-sm">Approve</button>
                            <button type="button" class="btn btn-danger btn-sm">Cancel</button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>