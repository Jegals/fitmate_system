<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Fitness Programs";
$trainers = $pdo->query("SELECT StaffID, FirstName, LastName FROM gym_staff WHERE StaffType = 'Trainer'")->fetchAll();
$programs = $pdo->query("
    SELECT p.*, CONCAT(gs.FirstName, ' ', gs.LastName) AS TrainerName
    FROM program p
    LEFT JOIN gym_staff gs ON p.StaffID = gs.StaffID
    ORDER BY p.ProgramID ASC
")->fetchAll();

// TODO [Member 5]: Add Search, Add Program, and Edit Program
include __DIR__ . '/../includes/header.php';
?>

<div class="grid-2">
    <div class="card">
        <h3 style="margin-bottom: 14px;">Add Fitness Program</h3>
        <form method="POST">
            <div class="form-group"><label>Program Name</label><input type="text" placeholder="e.g. Strength & Conditioning"></div>
            <div class="form-group"><label>Schedule</label><input type="text" placeholder="e.g. Mon/Wed/Fri 5:00 PM"></div>
            <div class="form-group">
                <label>Assigned Trainer (Live from DB)</label>
                <select>
                    <?php foreach ($trainers as $t): ?>
                        <option value="<?= htmlspecialchars($t['StaffID']) ?>">
                            <?= htmlspecialchars($t['StaffID'] . ' — ' . $t['FirstName'] . ' ' . $t['LastName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" class="btn btn-primary" style="width:100%;">Save Program</button>
        </form>
    </div>

    <div class="card">
        <h3 style="margin-bottom: 12px;">Gym Programs List (<?= count($programs) ?> Programs)</h3>
        <table class="table">
            <thead><tr><th>Program ID</th><th>Program Name</th><th>Schedule</th><th>Trainer</th><th>Action</th></tr></thead>
            <tbody>
                <?php if (empty($programs)): ?>
                    <tr><td colspan="5">No fitness programs found.</td></tr>
                <?php else: foreach ($programs as $pr): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($pr['ProgramID']) ?></strong></td>
                        <td><?= htmlspecialchars($pr['ProgramName']) ?></td>
                        <td><?= htmlspecialchars($pr['Schedule'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($pr['TrainerName'] ?? $pr['StaffID'] ?? '-') ?></td>
                        <td><button type="button" class="btn btn-warning btn-sm">Edit</button></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>