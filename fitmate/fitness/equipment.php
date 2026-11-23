<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Equipment & Maintenance";

// =============================================
// HELPER FUNCTION
// =============================================

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

$successMessage = '';
$errorMessage = '';

if (isset($_GET['message'])) {
    $successMessage = $_GET['message'];
}

// =============================================
// 1. HANDLE FORM SUBMISSIONS
// =============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $formAction = $_POST['form_action'] ?? '';

        // =====================================
        // A. ADD / EDIT EQUIPMENT
        // =====================================

        if ($formAction === 'equipment') {

            $mode = $_POST['equipment_mode'] ?? 'add';

            $equipmentID = trim($_POST['equipment_id'] ?? '');
            $equipmentName = trim($_POST['equipment_name'] ?? '');
            $status = trim($_POST['status'] ?? '');
            $purchaseDate = trim($_POST['purchase_date'] ?? '');
            $vendor = trim($_POST['vendor'] ?? '');
            $staffID = trim($_POST['staff_id'] ?? '');

            $validStatuses = [
                'Operational',
                'Under Maintenance',
                'Out of Order'
            ];

            if (
                $equipmentName === '' ||
                $purchaseDate === '' ||
                $vendor === '' ||
                $staffID === '' ||
                !in_array($status, $validStatuses, true)
            ) {
                throw new Exception("Please complete all equipment fields.");
            }

            // Validate assigned maintenance staff
            $checkStaff = $pdo->prepare("
                SELECT COUNT(*)
                FROM gym_staff
                WHERE StaffID = ?
                AND StaffType = 'Maintenance'
            ");

            $checkStaff->execute([$staffID]);

            if (!$checkStaff->fetchColumn()) {
                throw new Exception("Invalid maintenance staff selected.");
            }

            if ($mode === 'edit') {

                // UPDATE EQUIPMENT

                if ($equipmentID === '') {
                    throw new Exception("Invalid Equipment ID.");
                }

                $updateEquipment = $pdo->prepare("
                    UPDATE equipment
                    SET
                        StaffID = ?,
                        EquipmentName = ?,
                        EquipmentStatus = ?,
                        PurchaseDate = ?,
                        Vendor = ?
                    WHERE EquipmentID = ?
                ");

                $updateEquipment->execute([
                    $staffID,
                    $equipmentName,
                    $status,
                    $purchaseDate,
                    $vendor,
                    $equipmentID
                ]);

                $successMessage = "Equipment successfully updated!";

            } else {

                // AUTO-GENERATE EQUIPMENT ID
                // Existing IDs: EQ101, EQ102, etc.

                $existingIDs = $pdo->query("
                    SELECT EquipmentID FROM equipment
                ")->fetchAll(PDO::FETCH_COLUMN);

                $maxNumber = 100;

                foreach ($existingIDs as $id) {

                    if (preg_match('/^EQ(\d+)$/i', $id, $matches)) {

                        $number = (int)$matches[1];

                        if ($number > $maxNumber) {
                            $maxNumber = $number;
                        }
                    }
                }

                $newEquipmentID = 'EQ' . ($maxNumber + 1);

                // INSERT EQUIPMENT

                $insertEquipment = $pdo->prepare("
                    INSERT INTO equipment
                    (
                        EquipmentID,
                        StaffID,
                        EquipmentName,
                        EquipmentStatus,
                        PurchaseDate,
                        Vendor
                    )
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $insertEquipment->execute([
                    $newEquipmentID,
                    $staffID,
                    $equipmentName,
                    $status,
                    $purchaseDate,
                    $vendor
                ]);

                $successMessage = "Equipment added successfully! ID: " . $newEquipmentID;
            }

        }

        // =====================================
        // B. ADD / EDIT MAINTENANCE SCHEDULE
        // =====================================

        elseif ($formAction === 'maintenance') {

            $mode = $_POST['maintenance_mode'] ?? 'add';

            $maintenanceStaffID = trim(
                $_POST['maintenance_staff_id'] ?? ''
            );

            $workSchedule = trim(
                $_POST['work_schedule'] ?? ''
            );

            $assignedTask = trim(
                $_POST['assigned_task'] ?? ''
            );

            if (
                $maintenanceStaffID === '' ||
                $workSchedule === '' ||
                $assignedTask === ''
            ) {
                throw new Exception("Please complete all maintenance fields.");
            }

            // Validate maintenance staff

            $checkStaff = $pdo->prepare("
                SELECT COUNT(*)
                FROM gym_staff
                WHERE StaffID = ?
                AND StaffType = 'Maintenance'
            ");

            $checkStaff->execute([$maintenanceStaffID]);

            if (!$checkStaff->fetchColumn()) {
                throw new Exception("Invalid maintenance staff selected.");
            }

            if ($mode === 'edit') {

                // UPDATE MAINTENANCE SCHEDULE

                $updateMaintenance = $pdo->prepare("
                    UPDATE maintenance
                    SET
                        WorkSchedule = ?,
                        AssignedTask = ?
                    WHERE StaffID = ?
                ");

                $updateMaintenance->execute([
                    $workSchedule,
                    $assignedTask,
                    $maintenanceStaffID
                ]);

                $successMessage = "Maintenance schedule successfully updated!";

            } else {

                // CHECK IF STAFF ALREADY HAS A SCHEDULE

                $checkExisting = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM maintenance
                    WHERE StaffID = ?
                ");

                $checkExisting->execute([$maintenanceStaffID]);

                if ($checkExisting->fetchColumn() > 0) {
                    throw new Exception(
                        "This maintenance staff already has a schedule. Please edit the existing record."
                    );
                }

                // INSERT MAINTENANCE SCHEDULE

                $insertMaintenance = $pdo->prepare("
                    INSERT INTO maintenance
                    (
                        StaffID,
                        WorkSchedule,
                        AssignedTask
                    )
                    VALUES (?, ?, ?)
                ");

                $insertMaintenance->execute([
                    $maintenanceStaffID,
                    $workSchedule,
                    $assignedTask
                ]);

                $successMessage = "Maintenance schedule added successfully!";
            }

        }

        else {
            throw new Exception("Invalid form submission.");
        }

        // PREVENT DUPLICATE SUBMISSION WHEN REFRESHING

        header(
            "Location: equipment.php?message=" .
            urlencode($successMessage)
        );

        exit;

    } catch (Exception $ex) {

        $errorMessage = $ex->getMessage();

    } catch (PDOException $ex) {

        $errorMessage = "Database error: " . $ex->getMessage();

    }

}

// =============================================
// 2. GET MAINTENANCE STAFF DROPDOWN
// =============================================

$maintStaffDropdown = $pdo->query("
    SELECT StaffID, FirstName, LastName
    FROM gym_staff
    WHERE StaffType = 'Maintenance'
    ORDER BY StaffID ASC
")->fetchAll(PDO::FETCH_ASSOC);


// Staff without an existing maintenance schedule

$availableMaintenanceStaff = $pdo->query("
    SELECT gs.StaffID, gs.FirstName, gs.LastName
    FROM gym_staff gs
    WHERE gs.StaffType = 'Maintenance'
    AND NOT EXISTS (
        SELECT 1
        FROM maintenance m
        WHERE m.StaffID = gs.StaffID
    )
    ORDER BY gs.StaffID ASC
")->fetchAll(PDO::FETCH_ASSOC);


// =============================================
// 3. GET EQUIPMENT LIST (FOR LIVE SEARCH)
// =============================================

$sql = "
    SELECT
        e.EquipmentID,
        e.StaffID,
        e.EquipmentName,
        e.EquipmentStatus,
        e.PurchaseDate,
        e.Vendor,
        CONCAT(gs.FirstName, ' ', gs.LastName) AS MaintName

    FROM equipment e

    LEFT JOIN gym_staff gs
        ON e.StaffID = gs.StaffID

    ORDER BY e.EquipmentID ASC
";

$equipments = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);


// =============================================
// 4. GET EQUIPMENT TO EDIT
// =============================================

$editEquipment = null;

if (isset($_GET['edit_equipment'])) {

    $editID = $_GET['edit_equipment'];

    $editQuery = $pdo->prepare("
        SELECT *
        FROM equipment
        WHERE EquipmentID = ?
    ");

    $editQuery->execute([$editID]);

    $editEquipment = $editQuery->fetch(PDO::FETCH_ASSOC);
}


// =============================================
// 5. GET MAINTENANCE STAFF SCHEDULES
// =============================================

$maintenanceList = $pdo->query("
    SELECT
        m.StaffID,
        m.WorkSchedule,
        m.AssignedTask,
        CONCAT(gs.FirstName, ' ', gs.LastName) AS StaffName

    FROM maintenance m

    LEFT JOIN gym_staff gs
        ON m.StaffID = gs.StaffID

    ORDER BY m.StaffID ASC
")->fetchAll(PDO::FETCH_ASSOC);


// =============================================
// 6. GET MAINTENANCE SCHEDULE TO EDIT
// =============================================

$editMaintenance = null;

if (isset($_GET['edit_maintenance'])) {

    $editStaffID = $_GET['edit_maintenance'];

    $editMaintenanceQuery = $pdo->prepare("
        SELECT *
        FROM maintenance
        WHERE StaffID = ?
    ");

    $editMaintenanceQuery->execute([$editStaffID]);

    $editMaintenance = $editMaintenanceQuery->fetch(PDO::FETCH_ASSOC);
}


// =============================================
// 7. LOAD HEADER
// =============================================

include __DIR__ . '/../includes/header.php';

?>

<style>
    /* Consistent Black & Blue Search Bar Styling */
    .search-bar {
        display: flex;
        gap: 10px;
        margin-bottom: 15px;
        align-items: center;
    }
    .search-bar input {
        flex: 1;
        background: #060913 !important;
        border: 1px solid #1e293b !important;
        color: #f1f5f9 !important;
        padding: 10px 14px !important;
        border-radius: 6px !important;
        font-size: 13.5px !important;
        outline: none !important;
        transition: border-color 0.2s ease;
    }
    .search-bar input::placeholder {
        color: #64748b !important;
    }
    .search-bar input:focus {
        border-color: #2563eb !important;
    }
    .search-bar button {
        padding: 10px 18px !important;
        border-radius: 6px !important;
        font-weight: 600 !important;
        flex-shrink: 0;
    }
</style>

<!-- =========================================
     SUCCESS / ERROR MESSAGES
========================================== -->

<?php if ($successMessage): ?>

    <div class="card" style="margin-bottom:15px; border-left:5px solid #22c55e;">
        <p style="color:#22c55e; margin:0;">
            ✓ <?= e($successMessage) ?>
        </p>
    </div>

<?php endif; ?>


<?php if ($errorMessage): ?>

    <div class="card" style="margin-bottom:15px; border-left:5px solid #ef4444;">
        <p style="color:#ef4444; margin:0;">
            ✕ <?= e($errorMessage) ?>
        </p>
    </div>

<?php endif; ?>


<!-- =========================================
     SECTION 1: EQUIPMENT MANAGEMENT
========================================== -->

<div class="grid-2">

    <!-- ADD / EDIT EQUIPMENT FORM -->

    <div class="card">

        <h3 style="margin-bottom:14px;">

            <?= $editEquipment
                ? 'Edit Gym Equipment'
                : 'Add Gym Equipment'
            ?>

        </h3>

        <form method="POST" action="equipment.php">

            <input type="hidden" name="form_action" value="equipment">

            <input
                type="hidden"
                name="equipment_mode"
                value="<?= $editEquipment ? 'edit' : 'add' ?>"
            >

            <?php if ($editEquipment): ?>

                <input
                    type="hidden"
                    name="equipment_id"
                    value="<?= e($editEquipment['EquipmentID']) ?>"
                >

                <div class="form-group">

                    <label>Equipment ID</label>

                    <input
                        type="text"
                        value="<?= e($editEquipment['EquipmentID']) ?>"
                        disabled
                    >

                </div>

            <?php endif; ?>


            <div class="form-group">

                <label>Equipment Name</label>

                <input
                    type="text"
                    name="equipment_name"
                    placeholder="e.g. Commercial Treadmill"
                    maxlength="100"
                    value="<?= e($editEquipment['EquipmentName'] ?? '') ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label>Status</label>

                <select name="status" required>

                    <option value="Operational"
                        <?= ($editEquipment['EquipmentStatus'] ?? '') === 'Operational' ? 'selected' : '' ?>>
                        Operational
                    </option>

                    <option value="Under Maintenance"
                        <?= ($editEquipment['EquipmentStatus'] ?? '') === 'Under Maintenance' ? 'selected' : '' ?>>
                        Under Maintenance
                    </option>

                    <option value="Out of Order"
                        <?= ($editEquipment['EquipmentStatus'] ?? '') === 'Out of Order' ? 'selected' : '' ?>>
                        Out of Order
                    </option>

                </select>

            </div>


            <div class="form-row">

                <div class="form-group">

                    <label>Purchase Date</label>

                    <input
                        type="date"
                        name="purchase_date"
                        value="<?= e($editEquipment['PurchaseDate'] ?? date('Y-m-d')) ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Vendor</label>

                    <input
                        type="text"
                        name="vendor"
                        placeholder="Fitness Depot PH"
                        maxlength="100"
                        value="<?= e($editEquipment['Vendor'] ?? '') ?>"
                        required
                    >

                </div>

            </div>


            <div class="form-group">

                <label>Assigned Maintenance Staff</label>

                <select name="staff_id" required>

                    <option value="">Select Maintenance Staff</option>

                    <?php foreach ($maintStaffDropdown as $ms): ?>

                        <option
                            value="<?= e($ms['StaffID']) ?>"
                            <?= ($editEquipment['StaffID'] ?? '') === $ms['StaffID'] ? 'selected' : '' ?>
                        >

                            <?= e(
                                $ms['StaffID'] . ' — ' .
                                $ms['FirstName'] . ' ' .
                                $ms['LastName']
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <button
                type="submit"
                class="btn btn-primary"
                style="width:100%;"
            >

                <?= $editEquipment
                    ? 'Update Equipment'
                    : 'Save Equipment'
                ?>

            </button>


            <?php if ($editEquipment): ?>

                <a
                    href="equipment.php"
                    class="btn btn-warning"
                    style="display:block; text-align:center; margin-top:10px;"
                >
                    Cancel Editing
                </a>

            <?php endif; ?>

        </form>

    </div>


    <!-- EQUIPMENT INVENTORY LIST -->

    <div class="card">

        <h3 style="margin-bottom:12px;">

            Equipment Inventory List
            (<span id="equipCount"><?= count($equipments) ?></span> Items)

        </h3>


        <!-- LIVE SEARCH EQUIPMENT -->

        <div class="search-bar">

            <input
                type="text"
                id="liveEquipSearch"
                onkeyup="filterEquipmentTable()"
                oninput="filterEquipmentTable()"
                placeholder="Search equipment, status, vendor..."
            >

            <button type="button" class="btn btn-primary" onclick="filterEquipmentTable()">
                Search
            </button>

        </div>


        <table class="table">

            <thead>

                <tr>
                    <th>Equip ID</th>
                    <th>Equipment Name</th>
                    <th>Status</th>
                    <th>Vendor</th>
                    <th>Assigned Staff</th>
                    <th>Action</th>
                </tr>

            </thead>


            <tbody id="equipTableBody">

                <?php if (empty($equipments)): ?>

                    <tr>
                        <td colspan="6" style="text-align:center; color:#94a3b8;">
                            No equipment records found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($equipments as $eq): ?>

                        <?php

                        $eqStatus = $eq['EquipmentStatus'];

                        $statusClass = 'badge-green';

                        if ($eqStatus === 'Under Maintenance') {
                            $statusClass = 'badge-amber';
                        } elseif ($eqStatus === 'Out of Order') {
                            $statusClass = 'badge-red';
                        }

                        ?>

                        <tr class="equip-row">

                            <td>
                                <strong><?= e($eq['EquipmentID']) ?></strong>
                            </td>

                            <td>
                                <?= e($eq['EquipmentName']) ?>
                            </td>

                            <td>

                                <span class="badge <?= $statusClass ?>">

                                    <?= e($eqStatus) ?>

                                </span>

                            </td>

                            <td>
                                <?= e($eq['Vendor']) ?>
                            </td>

                            <td>
                                <?= e($eq['MaintName'] ?? $eq['StaffID']) ?>
                            </td>

                            <td>

                                <a
                                    href="equipment.php?edit_equipment=<?= urlencode($eq['EquipmentID']) ?>"
                                    class="btn btn-warning btn-sm"
                                >
                                    Edit
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    <tr id="noEquipMatchRow" style="display: none;">
                        <td colspan="6" style="text-align: center; color: #94a3b8; padding: 16px;">
                            No equipment records found.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- =========================================
     SECTION 2: MAINTENANCE MANAGEMENT
========================================== -->

<div class="grid-2">


    <!-- ADD / EDIT MAINTENANCE FORM -->

    <div class="card">

        <h3 style="margin-bottom:14px;">

            <?= $editMaintenance
                ? 'Edit Maintenance Schedule'
                : 'Add Maintenance Schedule'
            ?>

        </h3>


        <form method="POST" action="equipment.php">

            <input type="hidden" name="form_action" value="maintenance">

            <input
                type="hidden"
                name="maintenance_mode"
                value="<?= $editMaintenance ? 'edit' : 'add' ?>"
            >


            <div class="form-group">

                <label>Maintenance Staff</label>

                <?php if ($editMaintenance): ?>

                    <input
                        type="text"
                        value="<?= e(
                            $editMaintenance['StaffID'] . ' — ' .
                            ($pdo->query("
                                SELECT CONCAT(FirstName, ' ', LastName)
                                FROM gym_staff
                                WHERE StaffID = " .
                                $pdo->quote($editMaintenance['StaffID'])
                            )->fetchColumn() ?: '')
                        ) ?>"
                        disabled
                    >

                    <input
                        type="hidden"
                        name="maintenance_staff_id"
                        value="<?= e($editMaintenance['StaffID']) ?>"
                    >

                <?php else: ?>

                    <select name="maintenance_staff_id" required>

                        <option value="">Select Maintenance Staff</option>

                        <?php foreach ($availableMaintenanceStaff as $ms): ?>

                            <option value="<?= e($ms['StaffID']) ?>">

                                <?= e(
                                    $ms['StaffID'] . ' — ' .
                                    $ms['FirstName'] . ' ' .
                                    $ms['LastName']
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <?php if (empty($availableMaintenanceStaff)): ?>

                        <small style="color:#f59e0b;">
                            All maintenance staff already have schedules.
                        </small>

                    <?php endif; ?>

                <?php endif; ?>

            </div>


            <div class="form-group">

                <label>Work Schedule</label>

                <input
                    type="text"
                    name="work_schedule"
                    placeholder="e.g. Monday, Tuesday, Wednesday"
                    maxlength="150"
                    value="<?= e($editMaintenance['WorkSchedule'] ?? '') ?>"
                    required
                >

            </div>


            <div class="form-group">

                <label>Assigned Task</label>

                <textarea
                    name="assigned_task"
                    placeholder="Enter assigned maintenance tasks..."
                    maxlength="255"
                    rows="4"
                    required
                ><?= e($editMaintenance['AssignedTask'] ?? '') ?></textarea>

            </div>


            <button
                type="submit"
                class="btn btn-primary"
                style="width:100%;"
                <?= (!$editMaintenance && empty($availableMaintenanceStaff)) ? 'disabled' : '' ?>
            >

                <?= $editMaintenance
                    ? 'Update Schedule'
                    : 'Save Schedule'
                ?>

            </button>


            <?php if ($editMaintenance): ?>

                <a
                    href="equipment.php"
                    class="btn btn-warning"
                    style="display:block; text-align:center; margin-top:10px;"
                >
                    Cancel Editing
                </a>

            <?php endif; ?>

        </form>

    </div>


    <!-- MAINTENANCE STAFF SCHEDULE TABLE -->

    <div class="card">

        <h3 style="margin-bottom:12px;">

            Maintenance Staff Schedule & Assigned Tasks
            (<?= count($maintenanceList) ?> Records)

        </h3>


        <table class="table">

            <thead>

                <tr>
                    <th>StaffID</th>
                    <th>Staff Name</th>
                    <th>WorkSchedule</th>
                    <th>AssignedTask</th>
                    <th>Action</th>
                </tr>

            </thead>


            <tbody>

                <?php if (empty($maintenanceList)): ?>

                    <tr>
                        <td colspan="5">
                            No maintenance schedules found in database.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($maintenanceList as $m): ?>

                        <tr>

                            <td>
                                <strong><?= e($m['StaffID']) ?></strong>
                            </td>

                            <td>
                                <?= e($m['StaffName'] ?? '-') ?>
                            </td>

                            <td>
                                <?= e($m['WorkSchedule']) ?>
                            </td>

                            <td>
                                <?= e($m['AssignedTask']) ?>
                            </td>

                            <td>

                                <a
                                    href="equipment.php?edit_maintenance=<?= urlencode($m['StaffID']) ?>"
                                    class="btn btn-warning btn-sm"
                                >
                                    Edit
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- =========================================
     JAVASCRIPT: LIVE SEARCH EQUIPMENT
========================================== -->

<script>
function filterEquipmentTable() {
    const query = document.getElementById('liveEquipSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#equipTableBody .equip-row');
    const noMatchRow = document.getElementById('noEquipMatchRow');
    let visibleCount = 0;

    rows.forEach(row => {
        const equipId   = row.cells[0].textContent.toLowerCase();
        const equipName = row.cells[1].textContent.toLowerCase();
        const status    = row.cells[2].textContent.toLowerCase();
        const vendor    = row.cells[3].textContent.toLowerCase();
        const staff     = row.cells[4].textContent.toLowerCase();

        if (
            equipId.includes(query) ||
            equipName.includes(query) ||
            status.includes(query) ||
            vendor.includes(query) ||
            staff.includes(query)
        ) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    document.getElementById('equipCount').textContent = visibleCount;
    if (noMatchRow) {
        noMatchRow.style.display = (rows.length > 0 && visibleCount === 0) ? '' : 'none';
    }
}
</script>


<?php

// =============================================
// 8. LOAD FOOTER
// =============================================

include __DIR__ . '/../includes/footer.php';

?>
