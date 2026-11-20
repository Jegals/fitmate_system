<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Archived (Deleted Files)";
$msg = '';
$err = '';

// 1. RESTORE ARCHIVED MEMBER (is_archived = 0)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_id'])) {
    $restoreId = $_POST['restore_id'];
    try {
        $stmt = $pdo->prepare("UPDATE gym_member SET is_archived = 0 WHERE MemberID = ?");
        $stmt->execute([$restoreId]);
        $msg = "Member ($restoreId) has been restored to Active Gym Member Records!";
    } catch (PDOException $e) {
        $err = "Error restoring member: " . $e->getMessage();
    }
}

// 2. SEARCH & FETCH ARCHIVED MEMBERS
$search = trim($_GET['search'] ?? '');
$sql = "SELECT * FROM gym_member WHERE is_archived = 1";
if ($search !== '') {
    $sql .= " AND (MemberID LIKE ? OR FirstName LIKE ? OR LastName LIKE ? OR PhoneNumber LIKE ?)";
}
$sql .= " ORDER BY MemberID ASC";

$stmt = $pdo->prepare($sql);
if ($search !== '') {
    $like = "%$search%";
    $stmt->execute([$like, $like, $like, $like]);
} else {
    $stmt->execute();
}
$archivedMembers = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
    <div style="background:rgba(22, 163, 74, 0.2); border:1px solid #22c55e; color:#86efac; padding:10px 14px; border-radius:6px; margin-bottom:16px; font-size:13px;">
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>
<?php if ($err): ?>
    <div style="background:rgba(220, 38, 38, 0.2); border:1px solid #ef4444; color:#fca5a5; padding:10px 14px; border-radius:6px; margin-bottom:16px; font-size:13px;">
        <?= htmlspecialchars($err) ?>
    </div>
<?php endif; ?>

<div class="card">
    <h3 style="margin-bottom: 12px;">Archived Member Records (<?= count($archivedMembers) ?> Records)</h3>
    <form method="GET" class="search-bar">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search archived members by ID, Name, or Phone Number...">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search): ?>
            <a href="archived.php" class="btn" style="background:#1e293b; color:#f1f5f9;">Reset</a>
        <?php endif; ?>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>Member ID</th>
                <th>Full Name</th>
                <th>Date of Birth</th>
                <th>Phone Number</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($archivedMembers)): ?>
                <tr><td colspan="5">No archived records found.</td></tr>
            <?php else: foreach ($archivedMembers as $m): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($m['MemberID']) ?></strong></td>
                    <td><?= htmlspecialchars($m['FirstName'] . ' ' . $m['LastName']) ?></td>
                    <td><?= htmlspecialchars($m['DateOfBirth'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($m['PhoneNumber'] ?? '-') ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Restore this member back to active records?');">
                            <input type="hidden" name="restore_id" value="<?= htmlspecialchars($m['MemberID']) ?>">
                            <button type="submit" class="btn btn-success btn-sm">Restore</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>