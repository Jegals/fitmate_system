<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Gym Member Records";

$members = $pdo->query("
    SELECT m.*, ec.ContactName, ec.Relationship, ec.PhoneNumber AS EmergencyPhone
    FROM gym_member m
    LEFT JOIN emergency_contact ec ON m.MemberID = ec.MemberID
    WHERE m.is_archived = 0
    ORDER BY m.MemberID ASC
")->fetchAll();

// TODO [Member 2]: Add Search filter, Update Member/Emergency Contact Modal, and Soft Delete (is_archived = 1)
include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <h3 style="margin-bottom: 12px;">Active Gym Members Directory (<?= count($members) ?> Records)</h3>
    <form method="GET" class="search-bar">
        <input type="text" name="search" placeholder="Search member by ID, Name, Phone Number, or Email...">
        <button type="button" class="btn btn-primary">Search</button>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>Member ID</th>
                <th>Full Name</th>
                <th>Phone Number</th>
                <th>Email</th>
                <th>Emergency Contact</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($members)): ?>
                <tr><td colspan="6">No active member records found.</td></tr>
            <?php else: ?>
                <?php foreach ($members as $m): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($m['MemberID']) ?></strong></td>
                        <td><?= htmlspecialchars($m['FirstName'] . ' ' . $m['LastName']) ?></td>
                        <td><?= htmlspecialchars($m['PhoneNumber'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($m['Email'] ?? '-') ?></td>
                        <td>
                            <?php if (!empty($m['ContactName'])): ?>
                                <?= htmlspecialchars($m['ContactName']) ?> (<?= htmlspecialchars($m['EmergencyPhone'] ?? '-') ?>)
                            <?php else: ?>
                                <span style="color:#64748b;">No emergency contact</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="/fitmate/members/profile.php?id=<?= urlencode($m['MemberID']) ?>" class="btn btn-primary btn-sm">View Profile</a>
                            <button type="button" class="btn btn-warning btn-sm">Edit</button>
                            <button type="button" class="btn btn-danger btn-sm">Archive</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>