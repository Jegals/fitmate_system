<?php
// Kinokonekta ang database ($pdo) at sini-secure ang page gamit ang auth_check (dapat naka-login muna)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

date_default_timezone_set('Asia/Manila');
$pdo->exec("SET time_zone = '+08:00'");

// Pamagat ng page sa top header at variables para sa success/error alert messages
$page_title = "Staff Records & Login History";
$msg = '';
$err = '';

// =========================================================================
// 1. ADD NEW STAFF (Auto-assigns TRN-XXX or MNT-XXX based on Staff Type)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_staff') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $dob       = $_POST['dob'] ?? '';
    $staffType = $_POST['staff_type'] ?? '';
    $cert      = trim($_POST['certification'] ?? '');
    $sched     = trim($_POST['work_schedule'] ?? '');
    $task      = trim($_POST['assigned_task'] ?? '');

    if ($firstName === '' || $lastName === '' || $dob === '') {
        $err = "Please fill in all required fields.";
    } elseif (!in_array($staffType, ['Trainer', 'Maintenance'], true)) {
        $err = "Invalid Staff Type selected. Only Trainer or Maintenance is allowed.";
    } elseif ($staffType === 'Maintenance' && ($sched === '' || $task === '')) {
        $err = "Work Schedule and Assigned Task are required for Maintenance staff.";
    } elseif ($staffType === 'Trainer' && $cert === '') {
        $err = "Trainer Certification is required for Trainer staff.";
    } else {
        try {
            $pdo->beginTransaction();

            // Auto-generate next StaffID based on selected role
            $prefix = ($staffType === 'Trainer') ? 'TRN-' : 'MNT-';
            $stmtLast = $pdo->prepare("
                SELECT StaffID
                FROM gym_staff
                WHERE StaffID REGEXP ?
                ORDER BY CAST(SUBSTRING(StaffID, 5) AS UNSIGNED) DESC
                LIMIT 1
            ");
            $stmtLast->execute(['^' . $prefix . '[0-9]+$']);
            $lastStaffId = $stmtLast->fetchColumn();

            $nextNum = $lastStaffId ? ((int)substr($lastStaffId, 4) + 1) : 1;
            $staffId = $prefix . str_pad((string)$nextNum, 3, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("INSERT INTO gym_staff (StaffID, FirstName, LastName, DateOfBirth, StaffType) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$staffId, $firstName, $lastName, $dob, $staffType]);

            if ($staffType === 'Trainer') {
                $pdo->prepare("INSERT INTO trainer (StaffID, Certification) VALUES (?, ?)")->execute([$staffId, $cert]);
            } elseif ($staffType === 'Maintenance') {
                $pdo->prepare("INSERT INTO maintenance (StaffID, WorkSchedule, AssignedTask) VALUES (?, ?, ?)")->execute([$staffId, $sched, $task]);
            }

            $pdo->commit();
            $msg = "New {$staffType} ({$firstName} {$lastName}) added successfully!";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $err = "Error adding staff: " . $e->getMessage();
        }
    }
}

// =========================================================================
// 2. EDIT STAFF RECORD (Mula sa Edit Modal)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_staff') {
    $staffId   = $_POST['edit_staff_id'] ?? '';
    $firstName = trim($_POST['edit_first_name'] ?? '');
    $lastName  = trim($_POST['edit_last_name'] ?? '');
    $dob       = $_POST['edit_dob'] ?? '';
    $staffType = $_POST['edit_staff_type'] ?? '';
    $cert      = trim($_POST['edit_certification'] ?? '');
    $sched     = trim($_POST['edit_work_schedule'] ?? '');
    $task      = trim($_POST['edit_assigned_task'] ?? '');

    if ($firstName === '' || $lastName === '' || $dob === '') {
        $err = "Please fill in all required fields.";
    } elseif ($staffType === 'Maintenance' && ($sched === '' || $task === '')) {
        $err = "Work Schedule and Assigned Task are required for Maintenance staff.";
    } elseif ($staffType === 'Trainer' && $cert === '') {
        $err = "Trainer Certification is required for Trainer staff.";
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE gym_staff SET FirstName = ?, LastName = ?, DateOfBirth = ? WHERE StaffID = ?");
            $stmt->execute([$firstName, $lastName, $dob, $staffId]);

            if ($staffType === 'Trainer') {
                $pdo->prepare("UPDATE trainer SET Certification = ? WHERE StaffID = ?")->execute([$cert, $staffId]);
            } elseif ($staffType === 'Maintenance') {
                $pdo->prepare("UPDATE maintenance SET WorkSchedule = ?, AssignedTask = ? WHERE StaffID = ?")->execute([$sched, $task, $staffId]);
            }

            $pdo->commit();
            $msg = "Staff record ({$firstName} {$lastName}) updated successfully!";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $err = "Error updating staff: " . $e->getMessage();
        }
    }
}

// =========================================================================
// 3. FETCH ALL STAFF DIRECTORY (Kinukuha lahat para sa instant JS live search)
// =========================================================================
$staffList = $pdo->query("
    SELECT gs.*,
           t.Certification,
           m.WorkSchedule, m.AssignedTask,
           sa.Username AS SystemUsername
    FROM gym_staff gs
    LEFT JOIN trainer t ON gs.StaffID = t.StaffID
    LEFT JOIN maintenance m ON gs.StaffID = m.StaffID
    LEFT JOIN system_access sa ON gs.StaffID = sa.StaffID
    ORDER BY gs.FirstName ASC, gs.LastName ASC
")->fetchAll();

// =========================================================================
// 4. FETCH ADMIN & MANAGER LOGIN HISTORY (Latest 30 login records)
// =========================================================================
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

    .grid-2 {
        flex-shrink: 0 !important;
        height: 360px !important;
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

    /* INTERNAL TABLE SCROLLING */
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

    .bottom-fill-card {
        flex: 1 !important;
        display: flex !important;
        flex-direction: column !important;
        min-height: 0 !important;
        margin-bottom: 0 !important;
        overflow: hidden !important;
    }

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
    <!-- LEFT CARD: ADD NEW STAFF FORM (AUTO-GENERATED STAFF ID) -->
    <div class="card">
        <h3 style="margin-bottom: 14px;">Add New Staff</h3>
        <form method="POST" class="add-staff-form">
            <div>
                <input type="hidden" name="action" value="add_staff">
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

                <div id="trainerFields" class="form-group">
                    <label>Trainer Certification</label>
                    <input type="text" name="certification" id="certificationInput" placeholder="e.g. Certified Fitness Coach" required>
                </div>

                <div id="maintFields" class="form-row" style="display:none;">
                    <div class="form-group">
                        <label>Work Schedule</label>
                        <input type="text" name="work_schedule" id="workScheduleInput" placeholder="e.g. Mon, Wed, Fri">
                    </div>
                    <div class="form-group">
                        <label>Assigned Task</label>
                        <input type="text" name="assigned_task" id="assignedTaskInput" placeholder="e.g. Treadmill check">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top: 6px; padding: 10px;">Add Staff</button>
        </form>
    </div>

    <!-- RIGHT CARD: GYM STAFF DIRECTORY (NO STAFF ID COLUMN) -->
    <div class="card">
        <h3 style="margin-bottom: 12px;">Gym Staff Directory</h3>
        <div class="search-bar" style="margin-bottom: 12px;">
            <input type="text" id="liveStaffSearch" onkeyup="filterStaffTable()" oninput="filterStaffTable()"
                   placeholder="Search by Name or Role...">
            <button type="button" class="btn btn-primary" onclick="filterStaffTable()">Search</button>
        </div>

        <div class="fixed-table-wrap">
            <table class="fixed-table">
                <thead>
                    <tr>
                        <th style="width: 44%;">Name</th>
                        <th style="width: 26%;">Type</th>
                        <th style="width: 30%;">Actions</th>
                    </tr>
                </thead>
                <tbody id="staffTableBody">
                    <?php if (empty($staffList)): ?>
                        <tr><td colspan="3" style="text-align: center; color: #94a3b8; padding: 18px;">No staff record found.</td></tr>
                    <?php else: foreach ($staffList as $s):
                        $badgeClass = 'badge';
                        if ($s['StaffType'] === 'Trainer') {
                            $badgeClass = 'badge badge-green';
                        } elseif ($s['StaffType'] === 'Maintenance') {
                            $badgeClass = 'badge badge-amber';
                        }
                    ?>
                        <tr class="staff-row" data-staff-id="<?= htmlspecialchars(strtolower($s['StaffID'])) ?>">
                            <td style="width: 44%;"><strong><?= htmlspecialchars($s['FirstName'] . ' ' . $s['LastName']) ?></strong></td>
                            <td style="width: 26%;"><span class="<?= $badgeClass ?>"><?= htmlspecialchars($s['StaffType']) ?></span></td>
                            <td style="width: 30%; white-space: nowrap;">
                                <a href="/fitmate/auth/staff_profile.php?id=<?= urlencode($s['StaffID']) ?>" class="btn btn-primary btn-sm">View Profile</a>
                                <button type="button" class="btn btn-warning btn-sm"
                                    onclick='openEditModal(<?= json_encode($s) ?>)'>Edit</button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    <tr id="noStaffMatchRow" style="display: none;">
                        <td colspan="3" style="text-align: center; color: #94a3b8; padding: 18px;">No staff record found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- BOTTOM CARD: ADMIN & MANAGER LOGIN HISTORY (NO STAFF ID COLUMN) -->
<div class="card bottom-fill-card">
    <h3 style="margin-bottom: 10px;">Admin & Manager Login History</h3>
    <div class="fixed-table-wrap">
        <table class="fixed-table">
            <thead>
                <tr>
                    <th style="width: 38%;">Name</th>
                    <th style="width: 22%;">Role</th>
                    <th style="width: 20%;">Login Date</th>
                    <th style="width: 20%;">Login Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($loginLogs)): ?>
                    <tr><td colspan="4" style="text-align: center; color: #94a3b8; padding: 18px;">No login history recorded yet.</td></tr>
                <?php else: foreach ($loginLogs as $log):
                    $formattedLoginTime = !empty($log['LoginTime']) ? date('H:i:s', strtotime($log['LoginTime'])) : '-';
                ?>
                    <tr>
                        <td style="width: 38%;"><strong><?= htmlspecialchars($log['FullName']) ?></strong></td>
                        <td style="width: 22%;"><span class="badge"><?= htmlspecialchars($log['Role'] ?? 'Admin') ?></span></td>
                        <td style="width: 20%;"><?= htmlspecialchars($log['LoginDate']) ?></td>
                        <td style="width: 20%;"><?= htmlspecialchars($formattedLoginTime) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- POPUP MODAL: EDIT STAFF RECORD -->
<div id="editModal" class="modal-overlay">
    <div class="modal-box">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3>Edit Staff Record <small id="display_staff_role" style="color:#60a5fa; font-weight:500;"></small></h3>
            <button type="button" onclick="closeEditModal()" style="background:none; border:none; color:#94a3b8; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_staff">
            <input type="hidden" name="edit_staff_id" id="edit_staff_id">
            <input type="hidden" name="edit_staff_type" id="edit_staff_type">

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
function filterStaffTable() {
    const query = document.getElementById('liveStaffSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#staffTableBody .staff-row');
    const noMatchRow = document.getElementById('noStaffMatchRow');
    let visibleCount = 0;

    rows.forEach(row => {
        const staffName = row.cells[0].textContent.toLowerCase();
        const staffType = row.cells[1].textContent.toLowerCase();
        const staffId   = (row.getAttribute('data-staff-id') || '').toLowerCase();

        if (staffId.includes(query) || staffName.includes(query) || staffType.includes(query)) {
            row.style.display = 'table';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    if (noMatchRow) {
        noMatchRow.style.display = (rows.length > 0 && visibleCount === 0) ? 'table' : 'none';
    }
}

function toggleSubtypeFields() {
    const type = document.getElementById('staffTypeSelect').value;
    const isTrainer = (type === 'Trainer');
    const isMaint   = (type === 'Maintenance');

    document.getElementById('trainerFields').style.display = isTrainer ? 'block' : 'none';
    document.getElementById('maintFields').style.display   = isMaint ? 'grid' : 'none';

    document.getElementById('certificationInput').required = isTrainer;
    document.getElementById('workScheduleInput').required  = isMaint;
    document.getElementById('assignedTaskInput').required  = isMaint;
}

document.addEventListener('DOMContentLoaded', toggleSubtypeFields);

function openEditModal(staff) {
    const isTrainer = (staff.StaffType === 'Trainer');
    const isMaint   = (staff.StaffType === 'Maintenance');

    document.getElementById('edit_staff_id').value            = staff.StaffID;
    document.getElementById('display_staff_role').textContent = '(' + staff.StaffType + ')';
    document.getElementById('edit_staff_type').value          = staff.StaffType;
    document.getElementById('edit_first_name').value          = staff.FirstName;
    document.getElementById('edit_last_name').value           = staff.LastName;
    document.getElementById('edit_dob').value                 = staff.DateOfBirth;

    document.getElementById('editTrainerDiv').style.display = isTrainer ? 'block' : 'none';
    document.getElementById('editMaintDiv').style.display   = isMaint ? 'block' : 'none';

    const editCert  = document.getElementById('edit_certification');
    const editSched = document.getElementById('edit_work_schedule');
    const editTask  = document.getElementById('edit_assigned_task');

    editCert.value  = staff.Certification || '';
    editSched.value = staff.WorkSchedule || '';
    editTask.value  = staff.AssignedTask || '';

    editCert.required  = isTrainer;
    editSched.required = isMaint;
    editTask.required  = isMaint;

    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>