<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Staff Records & Login History";
$msg = '';
$err = '';

// 1. ADD NEW STAFF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_staff') {
    $staffId   = strtoupper(trim($_POST['staff_id']));
    $firstName = trim($_POST['first_name']);
    $lastName  = trim($_POST['last_name']);
    $dob       = $_POST['dob'];
    $staffType = $_POST['staff_type'];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO gym_staff (StaffID, FirstName, LastName, DateOfBirth, StaffType) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$staffId, $firstName, $lastName, $dob, $staffType]);

        if ($staffType === 'Trainer') {
            $cert = trim($_POST['certification'] ?? 'Certified Trainer');
            $pdo->prepare("INSERT INTO trainer (StaffID, Certification) VALUES (?, ?)")->execute([$staffId, $cert]);
        } elseif ($staffType === 'Maintenance') {
            $sched = trim($_POST['work_schedule'] ?? 'Mon-Sat 8AM-5PM');
            $task  = trim($_POST['assigned_task'] ?? 'General Equipment Maintenance');
            $pdo->prepare("INSERT INTO maintenance (StaffID, WorkSchedule, AssignedTask) VALUES (?, ?, ?)")->execute([$staffId, $sched, $task]);
        }

        $pdo->commit();
        $msg = "Staff member ($staffId) added successfully!";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $err = "Error adding staff: " . $e->getMessage();
    }
}

// 2. UPDATE STAFF PROFILE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_staff') {
    $staffId   = $_POST['staff_id'];
    $firstName = trim($_POST['first_name']);
    $lastName  = trim($_POST['last_name']);
    $dob       = $_POST['dob'];

    try {
        $stmt = $pdo->prepare("UPDATE gym_staff SET FirstName = ?, LastName = ?, DateOfBirth = ? WHERE StaffID = ?");
        $stmt->execute([$firstName, $lastName, $dob, $staffId]);

        if ($staffId === $_SESSION['staff_id']) {
            $_SESSION['staff_name'] = "$firstName $lastName";
        }
        $msg = "Profile updated for $staffId!";
    } catch (PDOException $e) {
        $err = "Update failed: " . $e->getMessage();
    }
}

// 3. SEARCH & FETCH STAFF LIST
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare("
        SELECT gs.*, t.Certification, m.WorkSchedule, m.AssignedTask, sa.Username, sa.Role
        FROM gym_staff gs
        LEFT JOIN trainer t ON gs.StaffID = t.StaffID
        LEFT JOIN maintenance m ON gs.StaffID = m.StaffID
        LEFT JOIN system_access sa ON gs.StaffID = sa.StaffID
        WHERE gs.StaffID LIKE ? OR gs.FirstName LIKE ? OR gs.LastName LIKE ? OR gs.StaffType LIKE ?
        ORDER BY gs.StaffID ASC
    ");
    $like = "%$search%";
    $stmt->execute([$like, $like, $like, $like]);
} else {
    $stmt = $pdo->query("
        SELECT gs.*, t.Certification, m.WorkSchedule, m.AssignedTask, sa.Username, sa.Role
        FROM gym_staff gs
        LEFT JOIN trainer t ON gs.StaffID = t.StaffID
        LEFT JOIN maintenance m ON gs.StaffID = m.StaffID
        LEFT JOIN system_access sa ON gs.StaffID = sa.StaffID
        ORDER BY gs.StaffID ASC
    ");
}
$staffList = $stmt->fetchAll();

// 4. FETCH ADMIN / MANAGER LOGIN HISTORY
$logsStmt = $pdo->query("
    SELECT lh.LoginID, lh.StaffID, CONCAT(gs.FirstName, ' ', gs.LastName) AS FullName,
           sa.Role, lh.LoginDate, lh.LoginTime
    FROM login_history lh
    JOIN system_access sa ON lh.StaffID = sa.StaffID
    JOIN gym_staff gs ON lh.StaffID = gs.StaffID
    ORDER BY lh.LoginDate DESC, lh.LoginTime DESC
    LIMIT 25
");
$loginLogs = $logsStmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<style>
    .grid-2 { display: grid; grid-template-columns: 1fr 2fr; gap: 20px; align-items: start; }
    .form-group { margin-bottom: 12px; }
    .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; }
    .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px; }
    .search-bar { display: flex; gap: 8px; margin-bottom: 14px; }
    .search-bar input { flex: 1; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 4px; }
    .badge { padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; background: #e2e8f0; }
    .alert-ok { background: #dcfce7; color: #15803d; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 14px; }
    .alert-err { background: #fee2e2; color: #b91c1c; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 14px; }
    @media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } }
</style>

<?php if ($msg): ?><div class="alert-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert-err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="grid-2">
    <!-- LEFT: ADD NEW STAFF FORM -->
    <div class="card">
        <h3 style="margin-bottom: 14px;">Add New Staff</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_staff">
            <div class="form-group">
                <label>Staff ID (e.g. TRN-002, MNT-002)</label>
                <input type="text" name="staff_id" required placeholder="TRN-002">
            </div>
            <div class="form-group">
                <label>First Name</label>
                <input type="text" name="first_name" required>
            </div>
            <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="last_name" required>
            </div>
            <div class="form-group">
                <label>Date of Birth</label>
                <input type="date" name="dob" required>
            </div>
            <div class="form-group">
                <label>Staff Type</label>
                <select name="staff_type" id="staffTypeSelect" onchange="toggleSubtypeFields()" required>
                    <option value="Trainer">Trainer</option>
                    <option value="Maintenance">Maintenance</option>
                    <option value="Manager">Manager</option>
                    <option value="Admin">Admin</option>
                </select>
            </div>

            <!-- Trainer Subtype Field -->
            <div class="form-group" id="trainerFields">
                <label>Trainer Certification</label>
                <input type="text" name="certification" placeholder="e.g. Certified Fitness Coach">
            </div>

            <!-- Maintenance Subtype Fields -->
            <div id="maintenanceFields" style="display:none;">
                <div class="form-group">
                    <label>Work Schedule</label>
                    <input type="text" name="work_schedule" placeholder="e.g. Mon-Fri 8AM-5PM">
                </div>
                <div class="form-group">
                    <label>Assigned Task</label>
                    <input type="text" name="assigned_task" placeholder="e.g. Cardio Equipment Check">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;">Add Staff</button>
        </form>
    </div>

    <!-- RIGHT: STAFF RECORDS LIST + EDIT -->
    <div class="card">
        <h3 style="margin-bottom: 12px;">Gym Staff Directory</h3>
        <form method="GET" class="search-bar">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by Staff ID, Name, or Role...">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($search): ?>
                <a href="staff_records.php" class="btn" style="background:#e2e8f0; color:#111;">Reset</a>
            <?php endif; ?>
        </form>

        <table class="table">
            <thead>
                <tr>
                    <th>Staff ID</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Details / Subtype</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($staffList)): ?>
                    <tr><td colspan="5">No staff records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($staffList as $s): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($s['StaffID']) ?></strong></td>
                            <td><?= htmlspecialchars($s['FirstName'] . ' ' . $s['LastName']) ?></td>
                            <td><span class="badge"><?= htmlspecialchars($s['StaffType']) ?></span></td>
                            <td style="font-size:12px; color:#475569;">
                                <?php
                                if ($s['StaffType'] === 'Trainer') echo "Cert: " . htmlspecialchars($s['Certification'] ?? '-');
                                elseif ($s['StaffType'] === 'Maintenance') echo htmlspecialchars($s['WorkSchedule'] ?? '') . " (" . htmlspecialchars($s['AssignedTask'] ?? '') . ")";
                                else echo "System User: " . htmlspecialchars($s['Username'] ?? 'No Login');
                                ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-primary" style="padding:6px 12px; font-size:12px;"
                                    onclick="openEditModal('<?= htmlspecialchars($s['StaffID'], ENT_QUOTES) ?>', '<?= htmlspecialchars($s['FirstName'], ENT_QUOTES) ?>', '<?= htmlspecialchars($s['LastName'], ENT_QUOTES) ?>', '<?= htmlspecialchars($s['DateOfBirth'] ?? '', ENT_QUOTES) ?>')">
                                    Edit
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- BOTTOM: ADMIN / MANAGER LOGIN HISTORY -->
<div class="card">
    <h3 style="margin-bottom: 10px;">Admin & Manager Login History</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Login ID</th>
                <th>Staff ID</th>
                <th>Name</th>
                <th>Role</th>
                <th>Login Date</th>
                <th>Login Time</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($loginLogs)): ?>
                <tr><td colspan="6">No login history recorded yet.</td></tr>
            <?php else: ?>
                <?php foreach ($loginLogs as $log): ?>
                    <tr>
                        <td>#<?= $log['LoginID'] ?></td>
                        <td><?= htmlspecialchars($log['StaffID']) ?></td>
                        <td><?= htmlspecialchars($log['FullName']) ?></td>
                        <td><span class="badge"><?= htmlspecialchars($log['Role']) ?></span></td>
                        <td><?= htmlspecialchars($log['LoginDate']) ?></td>
                        <td><?= htmlspecialchars($log['LoginTime']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- EDIT PROFILE MODAL -->
<div id="editModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000;">
    <div class="card" style="width:100%; max-width:400px; margin:0;">
        <h3 style="margin-bottom:14px;">Edit Staff Profile</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update_staff">
            <input type="hidden" name="staff_id" id="edit_staff_id">
            <div class="form-group">
                <label>First Name</label>
                <input type="text" name="first_name" id="edit_first_name" required>
            </div>
            <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="last_name" id="edit_last_name" required>
            </div>
            <div class="form-group">
                <label>Date of Birth</label>
                <input type="date" name="dob" id="edit_dob" required>
            </div>
            <div style="display:flex; gap:8px; justify-content:flex-end;">
                <button type="button" class="btn" style="background:#e2e8f0; color:#111;" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-success">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleSubtypeFields() {
    const type = document.getElementById('staffTypeSelect').value;
    document.getElementById('trainerFields').style.display = (type === 'Trainer') ? 'block' : 'none';
    document.getElementById('maintenanceFields').style.display = (type === 'Maintenance') ? 'block' : 'none';
}
function openEditModal(id, fname, lname, dob) {
    document.getElementById('edit_staff_id').value = id;
    document.getElementById('edit_first_name').value = fname;
    document.getElementById('edit_last_name').value = lname;
    document.getElementById('edit_dob').value = dob;
    document.getElementById('editModal').style.display = 'flex';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>