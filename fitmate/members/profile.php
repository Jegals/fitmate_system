<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Member Profile";
$id = $_GET['id'] ?? '';

$stmt = $pdo->prepare("
    SELECT m.MemberID, m.FirstName, m.LastName, m.DateOfBirth, m.Gender, 
           m.PhoneNumber AS MemberPhone, m.Email, m.Address,
           ec.ContactName, ec.Relationship, ec.PhoneNumber AS EmergencyPhone
    FROM gym_member m
    LEFT JOIN emergency_contact ec ON m.MemberID = ec.MemberID
    WHERE m.MemberID = ?
");
$stmt->execute([$id]);
$member = $stmt->fetch();

if (!$member) {
    $member = $pdo->query("
        SELECT m.MemberID, m.FirstName, m.LastName, m.DateOfBirth, m.Gender, 
               m.PhoneNumber AS MemberPhone, m.Email, m.Address,
               ec.ContactName, ec.Relationship, ec.PhoneNumber AS EmergencyPhone
        FROM gym_member m
        LEFT JOIN emergency_contact ec ON m.MemberID = ec.MemberID
        WHERE m.is_archived = 0
        LIMIT 1
    ")->fetch();
}

// TODO [Member 2]: Wire Tabs for Payments (M4), Programs (M3), and Progress (M5)
include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <?php if (!$member): ?>
        <p>No member record found.</p>
    <?php else: ?>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <div>
                <h2><?= htmlspecialchars($member['FirstName'] . ' ' . $member['LastName']) ?> <span class="badge badge-green">Member</span></h2>
                <p style="font-size:13px; color:#94a3b8; margin-top:4px;">
                    Member ID: <?= htmlspecialchars($member['MemberID']) ?> | Phone: <?= htmlspecialchars($member['MemberPhone'] ?? '-') ?> | Email: <?= htmlspecialchars($member['Email'] ?? '-') ?>
                </p>
            </div>
            <a href="/fitmate/members/records.php" class="btn btn-primary btn-sm">Back to Records</a>
        </div>

        <div style="display:flex; gap:8px; border-bottom:1px solid #1e293b; padding-bottom:12px; margin-bottom:16px; flex-wrap:wrap;">
            <button type="button" class="btn btn-primary btn-sm">Overview</button>
            <button type="button" class="btn btn-sm" style="background:#1e293b; color:#cbd5e1;">Payment History (M4)</button>
            <button type="button" class="btn btn-sm" style="background:#1e293b; color:#cbd5e1;">Enrolled Programs (M3)</button>
            <button type="button" class="btn btn-sm" style="background:#1e293b; color:#cbd5e1;">Fitness Progress (M5)</button>
        </div>

        <div class="grid-equal">
            <div>
                <h4 style="color:#60a5fa;">Personal Details</h4>
                <p style="font-size:13px; margin-top:8px;"><strong>Date of Birth:</strong> <?= htmlspecialchars($member['DateOfBirth'] ?? '-') ?></p>
                <p style="font-size:13px; margin-top:4px;"><strong>Gender:</strong> <?= htmlspecialchars($member['Gender'] ?? '-') ?></p>
                <p style="font-size:13px; margin-top:4px;"><strong>Address:</strong> <?= htmlspecialchars($member['Address'] ?? '-') ?></p>
            </div>
            <div>
                <h4 style="color:#60a5fa;">Emergency Contact</h4>
                <p style="font-size:13px; margin-top:8px;"><strong>Contact Person:</strong> <?= htmlspecialchars($member['ContactName'] ?? '-') ?></p>
                <p style="font-size:13px; margin-top:4px;"><strong>Relationship:</strong> <?= htmlspecialchars($member['Relationship'] ?? '-') ?></p>
                <p style="font-size:13px; margin-top:4px;"><strong>Phone Number:</strong> <?= htmlspecialchars($member['EmergencyPhone'] ?? '-') ?></p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>