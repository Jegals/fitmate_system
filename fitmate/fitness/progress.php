<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Member Progress";

// Exact columns base sa ERD: RecordID, MemberID, Date, Height, Weight
$progressList = $pdo->query("
    SELECT pr.RecordID, pr.MemberID, pr.Date, pr.Height, pr.Weight,
           CONCAT(m.FirstName, ' ', m.LastName) AS FullName
    FROM progress pr
    JOIN gym_member m ON pr.MemberID = m.MemberID
    ORDER BY pr.Date DESC, pr.RecordID DESC
")->fetchAll();

// TODO [Member 5]: Add Search member, Insert & Update Progress (RecordID, MemberID, Date, Height, Weight) with BMI auto-compute
include __DIR__ . '/../includes/header.php';
?>

<div class="grid-2">
    <div class="card">
        <h3 style="margin-bottom: 14px;">Log Member Progress</h3>
        <form method="POST">
            <div class="form-group"><label>Member ID</label><input type="text" name="member_id" placeholder="MEM-001"></div>
            <div class="form-row">
                <div class="form-group"><label>Height (cm)</label><input type="number" step="0.1" name="height" placeholder="170"></div>
                <div class="form-group"><label>Weight (kg)</label><input type="number" step="0.1" name="weight" placeholder="68.5"></div>
            </div>
            <div class="form-group"><label>Date</label><input type="date" name="date" value="<?= date('Y-m-d') ?>"></div>
            <button type="button" class="btn btn-primary" style="width:100%;">Save Progress</button>
        </form>
    </div>

    <div class="card">
        <h3 style="margin-bottom: 12px;">Progress Tracking History (<?= count($progressList) ?> Records)</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Record ID</th>
                    <th>Member</th>
                    <th>Date</th>
                    <th>Height</th>
                    <th>Weight</th>
                    <th>BMI</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($progressList)): ?>
                    <tr><td colspan="7">No progress records found.</td></tr>
                <?php else: foreach ($progressList as $pg): 
                    $hMeters = ($pg['Height'] > 0) ? ($pg['Height'] / 100) : 1;
                    $bmi = round($pg['Weight'] / ($hMeters * $hMeters), 1);
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($pg['RecordID']) ?></strong></td>
                        <td><?= htmlspecialchars($pg['FullName']) ?></td>
                        <td><?= htmlspecialchars($pg['Date']) ?></td>
                        <td><?= htmlspecialchars($pg['Height']) ?> cm</td>
                        <td><?= htmlspecialchars($pg['Weight']) ?> kg</td>
                        <td><span class="badge badge-green"><?= $bmi ?></span></td>
                        <td><button type="button" class="btn btn-warning btn-sm">Edit</button></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>