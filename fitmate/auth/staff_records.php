<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Staff Records & Login History";
$msg = '';
$err = '';

// 1. ADD NEW STAFF (Trainer & Maintenance only in dropdown)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_staff') {
    $staffId   = strtoupper(trim($_POST['staff_id'] ?? ''));
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $dob       = $_POST['dob'] ?? '';
    $staffType = $_POST['staff_type'] ?? '';

    if (!in_array($staffType, ['Trainer', 'Maintenance'], true)) {
        $err = "Invalid Staff Type selected. Only Trainer or Maintenance is allowed.";
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO gym_staff (StaffID, FirstName, LastName, DateOfBirth, StaffType) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$staffId, $firstName, $lastName, $dob, $staffType]);

            if ($staffType === 'Trainer') {
                $cert = trim($_POST['certification'] ?? '');
                $pdo->prepare("INSERT INTO trainer (StaffID, Certification) VALUES (?, ?)")->execute([$staffId, $cert]);
            } elseif ($staffType === 'Maintenance') {
                $sched = trim($_POST['work_schedule'] ?? '');
                $task  = trim($_POST['assigned_task'] ?? '');
                $pdo->prepare("INSERT INTO maintenance (StaffID, WorkSchedule, AssignedTask) VALUES (?, ?, ?)")->execute([$staffId, $sched, $task]);
            }

            $pdo->commit();
            $msg = "New staff member ($staffId — $firstName $lastName) added successfully!";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $err = "Error adding staff: " . $e->getMessage();
        }
    }
}

// 2. EDIT STAFF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_staff') {
    $staffId   = $_POST['edit_staff_id'] ?? '';
    $firstName = trim($_POST['edit_first_name'] ?? '');
    $lastName  = trim($_POST['edit_last_name'] ?? '');
    $dob       = $_POST['edit_dob'] ?? '';
    $staffType = $_POST['edit_staff_type'] ?? '';

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE gym_staff SET FirstName = ?, LastName = ?, DateOfBirth = ? WHERE StaffID = ?");
        $stmt->execute([$firstName, $lastName, $dob, $staffId]);

        if ($staffType === 'Trainer') {
            $cert = trim($_POST['edit_certification'] ?? '');
            $pdo->prepare("UPDATE trainer SET Certification = ? WHERE StaffID = ?")->execute([$cert, $staffId]);
        } elseif ($staffType === 'Maintenance') {
            $sched = trim($_POST['edit_work_schedule'] ?? '');
            $task  = trim($_POST['edit_assigned_task'] ?? '');
            $pdo->prepare("UPDATE maintenance SET WorkSchedule = ?, AssignedTask = ? WHERE StaffID = ?")->execute([$sched, $task, $staffId]);
        }

        $pdo->commit();
        $msg = "Staff record ($staffId) updated successfully!";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $err = "Error updating staff: " . $e->getMessage();
    }
}

// 3. SEARCH & FETCH ALL STAFF DIRECTORY
$search = trim($_GET['search'] ?? '');
$sql = "
    SELECT gs.*,
           t.Certification,
           m.WorkSchedule, m.AssignedTask,
           sa.Username AS SystemUsername
    FROM gym_staff gs
    LEFT JOIN trainer t ON gs.StaffID = t.StaffID
    LEFT JOIN maintenance m ON gs.StaffID = m.StaffID
    LEFT JOIN system_access sa ON gs.StaffID = sa.StaffID
";
if ($search !== '') {
    $sql .= " WHERE gs.StaffID LIKE ? OR gs.FirstName LIKE ? OR gs.LastName LIKE ? OR gs.StaffType LIKE ?";
}
$sql .= " ORDER BY gs.StaffID ASC";

$stmt = $pdo->prepare($sql);
if ($search !== '') {
    $like = "%$search%";
    $stmt->execute([$like, $like, $like, $like]);
} else {
    $stmt->execute();
}
$staffList = $stmt->fetchAll();

// 4. FETCH ADMIN & MANAGER LOGIN HISTORY
$loginLogs = $pdo->query("
    SELECT lh.LoginID, lh.StaffID, lh.LoginDate, lh.LoginTime,
           CONCAT(gs.FirstName, ' ', gs.LastName) AS FullName,
           sa.Role
    FROM login_history lh
    JOIN gym_staff gs ON lh.StaffID = gs.StaffID
    LEFT JOIN system_access sa ON lh.StaffID = sa.StaffID
    ORDER BY lh.LoginID DESC LIMIT 30
")->fetchAll();

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
        padding: 14px 20px !important;
        gap: 14px !important;
    }

    /* Saktong taas para hindi masikip ang form at pantay ang Gym Staff Directory table */
    .grid-2 {
        flex-shrink: 0 !important;
        height: 405px !important;
        align-items: stretch !important;
    }
    .grid-2 > .card {
        height: 100% !important;
        margin-bottom: 0 !important;
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
    }
    .grid-2 > .card form.add-staff-form {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .add-staff-form .form-group {
        margin-bottom: 13px !important;
    }
    .add-staff-form label {
        margin-bottom: 6px !important;
    }

    /* FIXED TABLE HEADER + FULL-SPACE SCROLLABLE TBODY */
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
        border-bottom: 1px solid #283548;
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
        padding: 10px 12px;
        text-align: left;
        font-size: 12px;
        font-weight: 700;
        color: #60a5fa;
        background: #111c35;
    }
    .fixed-table td {
        padding: 10px 12px;
        font-size: 13px;
        color: #f1f5f9;
        border-bottom: 1px solid #1e293b;
        vertical-align: middle;
    }
    .fixed-table tbody tr:hover {
        background: rgba(37, 99, 235, 0.08);
    }

    /* Bottom Card: Admin & Manager Login History */
    .bottom-fill-card {
        flex: 1 !important;
        display: flex !important;
        flex-direction: column !important;
        min-height: 0 !important;
        margin-bottom: 0 !important;
        overflow: hidden !important;
    }

    /* Dark Modal Styling */
    .modal-overlay {
        display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.78);
        align-items: center; justify-content: center; z-index: 1000; padding: 16px;
    }
    .modal-box {
        background: #0c1324; border: 1px solid #2563eb; border-radius: 10px;
        width: 100%; max-width: 460px; padding: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.8);
    }
</style>

<?php if ($msg): ?>
    <div style="background:rgba(22, 163, 74, 0.2); border:1px solid #22c55e; color:#86efac; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>
<?php if ($err): ?>
    <div style="background:rgba(220, 38, 38, 0.2); border:1px solid #ef4444; color:#fca5a5; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        <?= htmlspecialchars($err) ?>
    </div>
<?php endif; ?>

<div class="grid-2">
    <!-- ADD NEW STAFF FORM (DROPDOWN: TRAINER & MAINTENANCE ONLY) -->
    <div class="card">
        <h3 style="margin-bottom: 14px;">Add New Staff</h3>
        <form method="POST" class="add-staff-form">
            <div>
                <input type="hidden" name="action" value="add_staff">
                <div class="form-group">
                    <label>Staff ID (e.g. TRN-008, MNT-005)</label>
                    <input type="text" name="staff_id" required placeholder="TRN-008">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" required placeholder="First name">
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" required placeholder="Last name">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="dob" required>
                    </div>
                    <div class="form-group">
                        <label>Staff Type</label>
                        <select name="staff_type" id="staffTypeSelect" onchange="toggleSubtypeFields()" required>
                            <option value="Trainer">Trainer</option>
                            <option value="Maintenance">Maintenance</option>
                        </select>
                    </div>
                </div>

                <!-- Trainer Subtype Field -->
                <div id="trainerFields" class="form-group">
                    <label>Trainer Certification</label>
                    <input type="text" name="certification" placeholder="e.g. Certified Fitness Coach">
                </div>

                <!-- Maintenance Subtype Fields (Side-by-side) -->
                <div id="maintFields" class="form-row" style="display:none;">
                    <div class="form-group">
                        <label>Work Schedule</label>
                        <input type="text" name="work_schedule" placeholder="e.g. Mon, Wed, Fri">
                    </div>
                    <div class="form-group">
                        <label>Assigned Task</label>
                        <input type="text" name="assigned_task" placeholder="e.g. Treadmill check">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top: 6px; padding: 10px;">Add Staff</button>
        </form>
    </div>

    <!-- GYM STAFF DIRECTORY TABLE (FULL SPACE + FIXED HEADER) -->
    <div class="card">
        <h3 style="margin-bottom: 12px;">Gym Staff Directory (<?= count($staffList) ?> Staff)</h3>
        <form method="GET" class="search-bar">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by Staff ID, Name, or Role...">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($search): ?>
                <a href="staff_records.php" class="btn" style="background:#1e293b; color:#f1f5f9;">Reset</a>
            <?php endif; ?>
        </form>

        <div class="fixed-table-wrap">
            <table class="fixed-table">
                <thead>
                    <tr>
                        <th style="width: 18%;">Staff ID</th>
                        <th style="width: 34%;">Name</th>
                        <th style="width: 22%;">Type</th>
                        <th style="width: 26%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staffList)): ?>
                        <tr><td colspan="4">No staff records found.</td></tr>
                    <?php else: foreach ($staffList as $s):
                        $badgeClass = 'badge';
                        if ($s['StaffType'] === 'Trainer') {
                            $badgeClass = 'badge badge-green';
                        } elseif ($s['StaffType'] === 'Maintenance') {
                            $badgeClass = 'badge badge-amber';
                        }
                    ?>
                        <tr>
                            <td style="width: 18%;"><strong><?= htmlspecialchars($s['StaffID']) ?></strong></td>
                            <td style="width: 34%;"><?= htmlspecialchars($s['FirstName'] . ' ' . $s['LastName']) ?></td>
                            <td style="width: 22%;"><span class="<?= $badgeClass ?>"><?= htmlspecialchars($s['StaffType']) ?></span></td>
                            <td style="width: 26%; white-space: nowrap;">
                                <a href="/fitmate/auth/staff_profile.php?id=<?= urlencode($s['StaffID']) ?>" class="btn btn-primary btn-sm">View Profile</a>
                                <button type="button" class="btn btn-warning btn-sm"
                                    onclick='openEditModal(<?= json_encode($s) ?>)'>Edit</button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ADMIN & MANAGER LOGIN HISTORY (100% FIXED HEADER + FILL BOTTOM SPACE) -->
<div class="card bottom-fill-card">
    <h3 style="margin-bottom: 10px;">Admin & Manager Login History</h3>
    <div class="fixed-table-wrap">
        <table class="fixed-table">
            <thead>
                <tr>
                    <th style="width: 12%;">Login ID</th>
                    <th style="width: 15%;">Staff ID</th>
                    <th style="width: 27%;">Name</th>
                    <th style="width: 14%;">Role</th>
                    <th style="width: 16%;">Login Date</th>
                    <th style="width: 16%;">Login Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($loginLogs)): ?>
                    <tr><td colspan="6">No login history recorded yet.</td></tr>
                <?php else: foreach ($loginLogs as $log): ?>
                    <tr>
                        <td style="width: 12%;">#<?= htmlspecialchars($log['LoginID']) ?></td>
                        <td style="width: 15%;"><strong><?= htmlspecialchars($log['StaffID']) ?></strong></td>
                        <td style="width: 27%;"><?= htmlspecialchars($log['FullName']) ?></td>
                        <td style="width: 14%;"><span class="badge"><?= htmlspecialchars($log['Role'] ?? 'Admin') ?></span></td>
                        <td style="width: 16%;"><?= htmlspecialchars($log['LoginDate']) ?></td>
                        <td style="width: 16%;"><?= htmlspecialchars($log['LoginTime']) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- EDIT STAFF MODAL -->
<div id="editModal" class="modal-overlay">
    <div class="modal-box">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3>Edit Staff Record</h3>
            <button type="button" onclick="closeEditModal()" style="background:none; border:none; color:#94a3b8; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_staff">
            <input type="hidden" name="edit_staff_id" id="edit_staff_id">
            <input type="hidden" name="edit_staff_type" id="edit_staff_type">

            <div class="form-group">
                <label>Staff ID (Read-Only)</label>
                <input type="text" id="display_staff_id" disabled style="opacity:0.7;">
            </div>
            <div class="form-row">
                <div class="form-group"><label>First Name</label><input type="text" name="edit_first_name" id="edit_first_name" required></div>
                <div class="form-group"><label>Last Name</label><input type="text" name="edit_last_name" id="edit_last_name" required></div>
            </div>
            <div class="form-group"><label>Date of Birth</label><input type="date" name="edit_dob" id="edit_dob" required></div>

            <div id="editTrainerDiv" class="form-group" style="display:none;">
                <label>Trainer Certification</label>
                <input type="text" name="edit_certification" id="edit_certification">
            </div>
            <div id="editMaintDiv" style="display:none;">
                <div class="form-group"><label>Work Schedule</label><input type="text" name="edit_work_schedule" id="edit_work_schedule"></div>
                <div class="form-group"><label>Assigned Task</label><input type="text" name="edit_assigned_task" id="edit_assigned_task"></div>
            </div>

            <div style="display:flex; gap:10px; margin-top:14px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">Save Changes</button>
                <button type="button" class="btn" onclick="closeEditModal()" style="background:#1e293b; color:#f1f5f9;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleSubtypeFields() {
    const type = document.getElementById('staffTypeSelect').value;
    document.getElementById('trainerFields').style.display = (type === 'Trainer') ? 'block' : 'none';
    document.getElementById('maintFields').style.display   = (type === 'Maintenance') ? 'grid' : 'none';
}

function openEditModal(staff) {
    document.getElementById('edit_staff_id').value    = staff.StaffID;
    document.getElementById('display_staff_id').value = staff.StaffID + ' (' + staff.StaffType + ')';
    document.getElementById('edit_staff_type').value  = staff.StaffType;
    document.getElementById('edit_first_name').value  = staff.FirstName;
    document.getElementById('edit_last_name').value   = staff.LastName;
    document.getElementById('edit_dob').value         = staff.DateOfBirth;

    document.getElementById('editTrainerDiv').style.display = (staff.StaffType === 'Trainer') ? 'block' : 'none';
    document.getElementById('editMaintDiv').style.display   = (staff.StaffType === 'Maintenance') ? 'block' : 'none';

    document.getElementById('edit_certification').value = staff.Certification || '';
    document.getElementById('edit_work_schedule').value = staff.WorkSchedule || '';
    document.getElementById('edit_assigned_task').value = staff.AssignedTask || '';

    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
