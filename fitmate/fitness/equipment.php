<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Equipment & Maintenance";

// 1. Dropdown options para sa Maintenance Staff
$maintStaffDropdown = $pdo->query("
    SELECT StaffID, FirstName, LastName 
    FROM gym_staff 
    WHERE StaffType = 'Maintenance'
    ORDER BY StaffID ASC
")->fetchAll();

// 2. Listahan ng Equipment mula sa equipment table
$equipments = $pdo->query("
    SELECT e.*, CONCAT(gs.FirstName, ' ', gs.LastName) AS MaintName
    FROM equipment e
    LEFT JOIN gym_staff gs ON e.StaffID = gs.StaffID
    ORDER BY e.EquipmentID ASC
")->fetchAll();

// 3. Listahan ng Maintenance Staff Schedule & Assigned Tasks mula sa maintenance table
$maintenanceList = $pdo->query("
    SELECT m.*, CONCAT(gs.FirstName, ' ', gs.LastName) AS StaffName
    FROM maintenance m
    LEFT JOIN gym_staff gs ON m.StaffID = gs.StaffID
    ORDER BY m.StaffID ASC
")->fetchAll();

// TODO [Member 5]: Add Search, Add Equipment (INSERT), Edit Equipment (UPDATE), and Add/Edit Maintenance Schedule
include __DIR__ . '/../includes/header.php';
?>

<!-- SECTION 1: ADD EQUIPMENT FORM & EQUIPMENT INVENTORY LIST -->
<div class="grid-2">
    <div class="card">
        <h3 style="margin-bottom: 14px;">Add Gym Equipment</h3>
        <form method="POST">
            <div class="form-group">
                <label>Equipment Name</label>
                <input type="text" name="equipment_name" placeholder="e.g. Commercial Treadmill">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option>Operational</option>
                    <option>Under Maintenance</option>
                    <option>Out of Order</option>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Purchase Date</label>
                    <input type="date" name="purchase_date">
                </div>
                <div class="form-group">
                    <label>Vendor</label>
                    <input type="text" name="vendor" placeholder="Fitness Depot PH">
                </div>
            </div>
            <div class="form-group">
                <label>Assigned Maintenance Staff</label>
                <select name="staff_id">
                    <?php foreach ($maintStaffDropdown as $ms): ?>
                        <option value="<?= htmlspecialchars($ms['StaffID']) ?>">
                            <?= htmlspecialchars($ms['StaffID'] . ' — ' . $ms['FirstName'] . ' ' . $ms['LastName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" class="btn btn-primary" style="width:100%;">Save Equipment</button>
        </form>
    </div>

    <div class="card">
        <h3 style="margin-bottom: 12px;">Equipment Inventory List (<?= count($equipments) ?> Items)</h3>
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
            <tbody>
                <?php if (empty($equipments)): ?>
                    <tr><td colspan="6">No equipment records found.</td></tr>
                <?php else: foreach ($equipments as $eq): 
                    $eqStatus = $eq['EquipmentStatus'] ?? $eq['Status'] ?? 'Operational';
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($eq['EquipmentID']) ?></strong></td>
                        <td><?= htmlspecialchars($eq['EquipmentName']) ?></td>
                        <td>
                            <span class="badge <?= $eqStatus === 'Operational' ? 'badge-green' : ($eqStatus === 'Under Maintenance' ? 'badge-amber' : 'badge-red') ?>">
                                <?= htmlspecialchars($eqStatus) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($eq['Vendor'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($eq['MaintName'] ?? $eq['StaffID'] ?? '-') ?></td>
                        <td><button type="button" class="btn btn-warning btn-sm">Edit</button></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- SECTION 2: MAINTENANCE STAFF SCHEDULE & ASSIGNED TASKS LIST -->
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 12px;">
        <h3>Maintenance Staff Schedule & Assigned Tasks (<?= count($maintenanceList) ?> Records)</h3>
    </div>

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
                <tr><td colspan="5">No maintenance schedules found in database.</td></tr>
            <?php else: foreach ($maintenanceList as $m): 
                $workSched = $m['WorkSchedule'] ?? $m['Schedule'] ?? '-';
                $task = $m['AssignedTask'] ?? $m['TaskPerformed'] ?? '-';
            ?>
                <tr>
                    <td><strong><?= htmlspecialchars($m['StaffID']) ?></strong></td>
                    <td><?= htmlspecialchars($m['StaffName'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($workSched) ?></td>
                    <td><?= htmlspecialchars($task) ?></td>
                    <td><button type="button" class="btn btn-warning btn-sm">Edit</button></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>