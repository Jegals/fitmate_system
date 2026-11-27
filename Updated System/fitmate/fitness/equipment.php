<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Gym Equipment & Maintenance";
$feedback = '';
$feedbackType = 'ok';

$activeTab = $_GET['tab'] ?? 'equipment';
if (!in_array($activeTab, ['equipment', 'active', 'logs'], true)) {
    $activeTab = 'equipment';
}

// Helper: Auto-generate next prefixed ID (e.g., EQ106, MLOG007)
function getNextPrefixedId(PDO $pdo, string $table, string $column, string $prefix, int $startAt, int $padLen = 0): string {
    $prefixLen = strlen($prefix) + 1;
    $stmt = $pdo->query("
        SELECT {$column}
        FROM {$table}
        WHERE {$column} REGEXP '^{$prefix}[0-9]+$'
        ORDER BY CAST(SUBSTRING({$column}, {$prefixLen}) AS UNSIGNED) DESC
        LIMIT 1
    ");
    $lastId = $stmt->fetchColumn();
    $nextNum = $lastId ? ((int)substr($lastId, strlen($prefix)) + 1) : $startAt;
    return $prefix . ($padLen > 0 ? str_pad((string)$nextNum, $padLen, '0', STR_PAD_LEFT) : $nextNum);
}

// Helper: Sync single equipment.EquipmentStatus based on open maintenance_log entries
function syncEquipmentStatus(PDO $pdo, string $equipmentId, ?string $staffId = null): void {
    $openStmt = $pdo->prepare("
        SELECT COUNT(*) FROM maintenance_log
        WHERE EquipmentID = ? AND RepairStatus IN ('Pending', 'In Progress')
    ");
    $openStmt->execute([$equipmentId]);
    $hasOpenLogs = ((int)$openStmt->fetchColumn()) > 0;

    $newStatus = $hasOpenLogs ? 'Under Maintenance' : 'Operational';

    if ($staffId !== null && $staffId !== '') {
        $upd = $pdo->prepare("UPDATE equipment SET EquipmentStatus = ?, StaffID = ? WHERE EquipmentID = ?");
        $upd->execute([$newStatus, $staffId, $equipmentId]);
    } else {
        $upd = $pdo->prepare("UPDATE equipment SET EquipmentStatus = ? WHERE EquipmentID = ?");
        $upd->execute([$newStatus, $equipmentId]);
    }
}

// Auto-sync all equipment statuses with maintenance_log
$pdo->exec("
    UPDATE equipment e
    SET e.EquipmentStatus = CASE
        WHEN EXISTS (
            SELECT 1 FROM maintenance_log ml
            WHERE ml.EquipmentID = e.EquipmentID
              AND ml.RepairStatus IN ('Pending', 'In Progress')
        ) THEN 'Under Maintenance'
        ELSE 'Operational'
    END
");

// ==========================================
// 1. EQUIPMENT INVENTORY CRUD HANDLERS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_equipment') {
    $activeTab = 'equipment';
    $eqName   = trim($_POST['EquipmentName'] ?? '');
    $staffId  = trim($_POST['StaffID'] ?? '');
    $purchase = trim($_POST['PurchaseDate'] ?? '');
    $vendor   = trim($_POST['Vendor'] ?? '');

    if ($eqName === '' || $staffId === '' || $purchase === '' || $vendor === '') {
        $feedback = "Please fill in all equipment fields.";
        $feedbackType = 'err';
    } else {
        try {
            $newEqId = getNextPrefixedId($pdo, 'equipment', 'EquipmentID', 'EQ', 101);
            $stmt = $pdo->prepare("
                INSERT INTO equipment (EquipmentID, StaffID, EquipmentName, EquipmentStatus, PurchaseDate, Vendor)
                VALUES (?, ?, ?, 'Operational', ?, ?)
            ");
            $stmt->execute([$newEqId, $staffId, $eqName, $purchase, $vendor]);
            $feedback = "Added new equipment ({$eqName}) as Operational.";
        } catch (PDOException $e) {
            $feedback = "Failed to add equipment: " . $e->getMessage();
            $feedbackType = 'err';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_equipment') {
    $activeTab = 'equipment';
    $eqId     = trim($_POST['EquipmentID'] ?? '');
    $eqName   = trim($_POST['EquipmentName'] ?? '');
    $staffId  = trim($_POST['StaffID'] ?? '');
    $purchase = trim($_POST['PurchaseDate'] ?? '');
    $vendor   = trim($_POST['Vendor'] ?? '');

    try {
        $stmt = $pdo->prepare("
            UPDATE equipment
            SET StaffID = ?, EquipmentName = ?, PurchaseDate = ?, Vendor = ?
            WHERE EquipmentID = ?
        ");
        $stmt->execute([$staffId, $eqName, $purchase, $vendor, $eqId]);
        $feedback = "Updated equipment details for {$eqName}.";
    } catch (PDOException $e) {
        $feedback = "Failed to update equipment: " . $e->getMessage();
        $feedbackType = 'err';
    }
}

// ==========================================
// 2. MAINTENANCE STAFF SCHEDULE UPDATE HANDLER
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_maintenance_schedule') {
    $activeTab    = 'active';
    $staffId      = trim($_POST['StaffID'] ?? '');
    $workSchedule = trim($_POST['WorkSchedule'] ?? '');
    $assignedTask = trim($_POST['AssignedTask'] ?? '');

    try {
        $stmt = $pdo->prepare("
            UPDATE maintenance
            SET WorkSchedule = ?, AssignedTask = ?
            WHERE StaffID = ?
        ");
        $stmt->execute([$workSchedule, $assignedTask, $staffId]);
        $feedback = "Updated schedule and assigned tasks.";
    } catch (PDOException $e) {
        $feedback = "Failed to update schedule: " . $e->getMessage();
        $feedbackType = 'err';
    }
}

// ==========================================
// 3. MAINTENANCE LOG CRUD HANDLERS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_maintenance_log') {
    $equipmentId  = trim($_POST['EquipmentID'] ?? '');
    $staffId      = trim($_POST['StaffID'] ?? '');
    $issueDesc    = trim($_POST['IssueDescription'] ?? '');
    $dateReported = trim($_POST['DateReported'] ?? date('Y-m-d'));
    $dateRepaired = trim($_POST['DateRepaired'] ?? '');
    $repairStatus = trim($_POST['RepairStatus'] ?? 'Pending');

    if ($repairStatus === 'Repaired' && $dateRepaired === '') {
        $dateRepaired = date('Y-m-d');
    } elseif ($repairStatus !== 'Repaired') {
        $dateRepaired = '';
    }

    if ($equipmentId === '' || $staffId === '' || $issueDesc === '' || $dateReported === '') {
        $activeTab = 'active';
        $feedback = "Please complete all required maintenance log fields.";
        $feedbackType = 'err';
    } else {
        $pdo->beginTransaction();
        try {
            $newLogId = getNextPrefixedId($pdo, 'maintenance_log', 'LogID', 'MLOG', 1, 3);
            $stmt = $pdo->prepare("
                INSERT INTO maintenance_log (LogID, EquipmentID, StaffID, IssueDescription, DateReported, DateRepaired, RepairStatus)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $newLogId,
                $equipmentId,
                $staffId,
                $issueDesc,
                $dateReported,
                $dateRepaired !== '' ? $dateRepaired : null,
                $repairStatus
            ]);

            syncEquipmentStatus($pdo, $equipmentId, $staffId);
            $pdo->commit();

            $activeTab = ($repairStatus === 'Repaired') ? 'logs' : 'active';
            $feedback = ($repairStatus === 'Repaired')
                ? "Maintenance recorded as Repaired and added to Maintenance Logs list."
                : "Submitted maintenance request ({$repairStatus}).";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $activeTab = 'active';
            $feedback = "Failed to log maintenance: " . $e->getMessage();
            $feedbackType = 'err';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_maintenance_log') {
    $logId        = trim($_POST['LogID'] ?? '');
    $equipmentId  = trim($_POST['EquipmentID'] ?? '');
    $staffId      = trim($_POST['StaffID'] ?? '');
    $issueDesc    = trim($_POST['IssueDescription'] ?? '');
    $dateReported = trim($_POST['DateReported'] ?? '');
    $dateRepaired = trim($_POST['DateRepaired'] ?? '');
    $repairStatus = trim($_POST['RepairStatus'] ?? 'Pending');

    if ($repairStatus === 'Repaired' && $dateRepaired === '') {
        $dateRepaired = date('Y-m-d');
    } elseif ($repairStatus !== 'Repaired') {
        $dateRepaired = '';
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            UPDATE maintenance_log
            SET EquipmentID = ?, StaffID = ?, IssueDescription = ?, DateReported = ?, DateRepaired = ?, RepairStatus = ?
            WHERE LogID = ?
        ");
        $stmt->execute([
            $equipmentId,
            $staffId,
            $issueDesc,
            $dateReported,
            $dateRepaired !== '' ? $dateRepaired : null,
            $repairStatus,
            $logId
        ]);

        syncEquipmentStatus($pdo, $equipmentId, $staffId);
        $pdo->commit();

        $activeTab = ($repairStatus === 'Repaired') ? 'logs' : 'active';
        $feedback = ($repairStatus === 'Repaired')
            ? "Maintenance request completed and moved to Maintenance Logs list."
            : "Updated maintenance request ({$repairStatus}).";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $activeTab = 'active';
        $feedback = "Failed to update maintenance request: " . $e->getMessage();
        $feedbackType = 'err';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'quick_mark_repaired') {
    $logId       = trim($_POST['LogID'] ?? '');
    $equipmentId = trim($_POST['EquipmentID'] ?? '');
    $today       = date('Y-m-d');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            UPDATE maintenance_log
            SET RepairStatus = 'Repaired', DateRepaired = COALESCE(DateRepaired, ?)
            WHERE LogID = ?
        ");
        $stmt->execute([$today, $logId]);

        if ($equipmentId !== '') {
            syncEquipmentStatus($pdo, $equipmentId);
        }
        $pdo->commit();

        $activeTab = 'logs';
        $feedback = "Maintenance request marked as Done ({$today}) and moved to Maintenance Logs list.";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $activeTab = 'active';
        $feedback = "Quick update failed: " . $e->getMessage();
        $feedbackType = 'err';
    }
}

// ==========================================
// 4. FETCH DATA
// ==========================================
$maintenanceStaff = $pdo->query("
    SELECT m.StaffID, gs.FirstName, gs.LastName, m.WorkSchedule, m.AssignedTask
    FROM maintenance m
    INNER JOIN gym_staff gs ON m.StaffID = gs.StaffID
    WHERE gs.StaffType = 'Maintenance'
    ORDER BY gs.FirstName ASC, gs.LastName ASC
")->fetchAll(PDO::FETCH_ASSOC);

$equipmentList = $pdo->query("
    SELECT
        e.*,
        CONCAT(gs.FirstName, ' ', gs.LastName) AS StaffName,
        (
            SELECT MAX(ml.DateRepaired)
            FROM maintenance_log ml
            WHERE ml.EquipmentID = e.EquipmentID AND ml.RepairStatus = 'Repaired'
        ) AS LastRepairedDate
    FROM equipment e
    LEFT JOIN gym_staff gs ON e.StaffID = gs.StaffID
    ORDER BY CAST(SUBSTRING(e.EquipmentID, 3) AS UNSIGNED) ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Maintenance Requests (Pending or In Progress)
$activeMaintenanceLogs = $pdo->query("
    SELECT
        ml.*,
        e.EquipmentName,
        CONCAT(gs.FirstName, ' ', gs.LastName) AS StaffName
    FROM maintenance_log ml
    LEFT JOIN equipment e ON ml.EquipmentID = e.EquipmentID
    LEFT JOIN gym_staff gs ON ml.StaffID = gs.StaffID
    WHERE ml.RepairStatus IN ('Pending', 'In Progress')
    ORDER BY
        CASE ml.RepairStatus WHEN 'In Progress' THEN 1 ELSE 2 END,
        ml.DateReported DESC,
        ml.LogID DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Complete Maintenance Logs List (All historical records)
$allMaintenanceLogs = $pdo->query("
    SELECT
        ml.*,
        e.EquipmentName,
        CONCAT(gs.FirstName, ' ', gs.LastName) AS StaffName
    FROM maintenance_log ml
    LEFT JOIN equipment e ON ml.EquipmentID = e.EquipmentID
    LEFT JOIN gym_staff gs ON ml.StaffID = gs.StaffID
    ORDER BY
        COALESCE(ml.DateRepaired, ml.DateReported) DESC,
        ml.LogID DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Edit Mode Loaders
$editEquipment = null;
if ($activeTab === 'equipment' && !empty($_GET['edit_eq'])) {
    $stmtEq = $pdo->prepare("SELECT * FROM equipment WHERE EquipmentID = ?");
    $stmtEq->execute([trim($_GET['edit_eq'])]);
    $editEquipment = $stmtEq->fetch(PDO::FETCH_ASSOC);
}

$editLog = null;
if ($activeTab === 'active' && !empty($_GET['edit_log'])) {
    $stmtLg = $pdo->prepare("SELECT * FROM maintenance_log WHERE LogID = ?");
    $stmtLg->execute([trim($_GET['edit_log'])]);
    $editLog = $stmtLg->fetch(PDO::FETCH_ASSOC);
}

$editScheduleStaff = null;
if ($activeTab === 'active' && !empty($_GET['edit_sched'])) {
    $stmtSc = $pdo->prepare("
        SELECT m.StaffID, gs.FirstName, gs.LastName, m.WorkSchedule, m.AssignedTask
        FROM maintenance m
        INNER JOIN gym_staff gs ON m.StaffID = gs.StaffID
        WHERE m.StaffID = ?
    ");
    $stmtSc->execute([trim($_GET['edit_sched'])]);
    $editScheduleStaff = $stmtSc->fetch(PDO::FETCH_ASSOC);
}

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
        padding: 16px 24px !important;
        gap: 14px !important;
    }

    /* Top Tabs + Action Toggle Bar */
    .top-action-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
        gap: 12px;
    }
    .eq-tabs {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    .eq-tab-btn {
        padding: 9px 18px;
        border-radius: 6px;
        font-size: 13.5px;
        font-weight: 600;
        text-decoration: none;
        background: #0c1324;
        border: 1px solid #1e293b;
        color: #94a3b8;
        transition: all 0.2s ease;
    }
    .eq-tab-btn:hover {
        border-color: #2563eb;
        color: #ffffff;
    }
    .eq-tab-btn.active {
        background: #2563eb;
        border-color: #2563eb;
        color: #ffffff;
    }

    /* Collapsible Top Form Card */
    .drawer-card {
        background: #0c1324;
        border: 1px solid #1e293b;
        border-radius: 10px;
        padding: 16px 20px;
        flex-shrink: 0;
    }
    .inline-grid-5 {
        display: grid;
        grid-template-columns: 1.4fr 1.2fr 1fr 1.2fr auto;
        gap: 12px;
        align-items: end;
    }
    .inline-grid-6 {
        display: grid;
        grid-template-columns: 1.2fr 1fr 1.5fr 0.9fr 0.9fr 0.9fr auto;
        gap: 10px;
        align-items: end;
    }
    .inline-grid-4 {
        display: grid;
        grid-template-columns: 1fr 1.3fr 2fr auto;
        gap: 12px;
        align-items: end;
    }
    .drawer-card .form-group {
        margin-bottom: 0 !important;
    }

    /* Full-Width Login History Style Card & Table */
    .history-card {
        background: #0c1324;
        border: 1px solid #1e293b;
        border-radius: 10px;
        padding: 18px 20px;
        display: flex;
        flex-direction: column;
        flex: 1;
        min-height: 0;
        overflow: hidden;
        margin-bottom: 0 !important;
    }
    .schedule-strip-card {
        background: #0c1324;
        border: 1px solid #1e293b;
        border-radius: 10px;
        padding: 14px 20px;
        flex-shrink: 0;
        margin-bottom: 0 !important;
    }
    .card-header-flex {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
        flex-shrink: 0;
    }
    .card-header-flex h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
        color: #ffffff;
    }

    /* Search Bar */
    .search-bar {
        display: flex;
        gap: 10px;
        margin-bottom: 12px;
        align-items: center;
        flex-shrink: 0;
    }
    .search-bar input {
        flex: 1;
        background: #060913 !important;
        border: 1px solid #1e293b !important;
        color: #f1f5f9 !important;
        padding: 9px 14px !important;
        border-radius: 6px !important;
        font-size: 13.5px !important;
        outline: none !important;
    }
    .search-bar input:focus {
        border-color: #2563eb !important;
    }
    .search-bar button {
        padding: 9px 18px !important;
        border-radius: 6px !important;
        font-weight: 600 !important;
    }

    /* Login-History-Style Clean Spacious Fixed Table */
    .lh-table-wrap {
        border: 1px solid #1e293b;
        border-radius: 8px;
        background: #080d1a;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        flex: 1;
        min-height: 0;
    }
    .lh-table {
        width: 100%;
        border-collapse: collapse;
        display: flex;
        flex-direction: column;
        flex: 1;
        min-height: 0;
    }
    .lh-table thead {
        flex-shrink: 0;
        background: #101b33;
        border-bottom: 1px solid #1e293b;
    }
    .lh-table thead tr,
    .lh-table tbody tr {
        display: table;
        width: 100%;
        table-layout: fixed;
    }
    .lh-table tbody {
        display: block;
        overflow-y: auto;
        width: 100%;
        flex: 1;
        min-height: 0;
    }
    .lh-table th {
        padding: 13px 16px;
        text-align: left;
        font-size: 12.5px;
        font-weight: 700;
        color: #3b82f6;
        background: #101b33;
    }
    .lh-table td {
        padding: 13px 16px;
        font-size: 13.5px;
        color: #f1f5f9;
        border-bottom: 1px solid #162036;
        vertical-align: middle;
    }
    .lh-table tbody tr:hover {
        background: rgba(37, 99, 235, 0.08);
    }

    /* Pill Badges */
    .pill-blue {
        display: inline-block;
        padding: 4px 14px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        background: rgba(37, 99, 235, 0.22);
        border: 1px solid #3b82f6;
        color: #93c5fd;
    }
    .pill-green {
        display: inline-block;
        padding: 4px 14px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        background: rgba(22, 163, 74, 0.2);
        border: 1px solid #22c55e;
        color: #86efac;
    }
    .pill-amber {
        display: inline-block;
        padding: 4px 14px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        background: rgba(217, 119, 6, 0.22);
        border: 1px solid #f59e0b;
        color: #fcd34d;
    }
    .pill-red {
        display: inline-block;
        padding: 4px 14px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        background: rgba(220, 38, 38, 0.22);
        border: 1px solid #ef4444;
        color: #fca5a5;
    }

    .action-group {
        display: flex;
        gap: 6px;
        align-items: center;
    }
</style>

<!-- TOP BAR: 3 TABS + CONTEXTUAL ADD BUTTON -->
<div class="top-action-bar">
    <div class="eq-tabs">
        <a href="equipment.php?tab=equipment" class="eq-tab-btn <?= $activeTab === 'equipment' ? 'active' : '' ?>">
            Equipment Inventory
        </a>
        <a href="equipment.php?tab=active" class="eq-tab-btn <?= $activeTab === 'active' ? 'active' : '' ?>">
            Maintenance Requests
        </a>
        <a href="equipment.php?tab=logs" class="eq-tab-btn <?= $activeTab === 'logs' ? 'active' : '' ?>">
            Maintenance Logs
        </a>
    </div>

    <?php if ($activeTab === 'equipment'): ?>
        <button type="button" class="btn btn-primary" onclick="toggleFormDrawer('eqFormDrawer')">
            <?= $editEquipment ? 'Editing ' . htmlspecialchars($editEquipment['EquipmentName']) : '+ Add Gym Equipment' ?>
        </button>
    <?php elseif ($activeTab === 'active'): ?>
        <button type="button" class="btn btn-primary" onclick="toggleFormDrawer('logFormDrawer')">
            <?= $editLog ? 'Editing Maintenance Request' : '+ Report Maintenance' ?>
        </button>
    <?php endif; ?>
</div>

<?php if ($feedback !== ''): ?>
    <div style="background:<?= $feedbackType === 'ok' ? 'rgba(22, 163, 74, 0.2)' : 'rgba(220, 38, 38, 0.2)' ?>;
                border:1px solid <?= $feedbackType === 'ok' ? '#22c55e' : '#ef4444' ?>;
                color:<?= $feedbackType === 'ok' ? '#86efac' : '#fca5a5' ?>;
                padding:9px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        <?= $feedbackType === 'ok' ? '✓ ' : '✕ ' ?><?= htmlspecialchars($feedback) ?>
    </div>
<?php endif; ?>

<?php if ($activeTab === 'equipment'): ?>
    <!-- ========================================== -->
    <!-- TAB 1: EQUIPMENT INVENTORY                 -->
    <!-- ========================================== -->
    <div id="eqFormDrawer" class="drawer-card" style="display: <?= $editEquipment ? 'block' : 'none' ?>;">
        <form method="POST" action="equipment.php?tab=equipment" class="inline-grid-5">
            <input type="hidden" name="action" value="<?= $editEquipment ? 'update_equipment' : 'add_equipment' ?>">
            <?php if ($editEquipment): ?>
                <input type="hidden" name="EquipmentID" value="<?= htmlspecialchars($editEquipment['EquipmentID']) ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Equipment Name</label>
                <input type="text" name="EquipmentName" placeholder="e.g. Treadmill Pro 5000" value="<?= htmlspecialchars($editEquipment['EquipmentName'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>Assigned Maintenance Staff</label>
                <select name="StaffID" required>
                    <option value="">-- Select Maintenance Staff --</option>
                    <?php foreach ($maintenanceStaff as $ms): ?>
                        <option value="<?= htmlspecialchars($ms['StaffID']) ?>" <?= (($editEquipment['StaffID'] ?? '') === $ms['StaffID']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ms['FirstName'] . ' ' . $ms['LastName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Purchase Date</label>
                <input type="date" name="PurchaseDate" value="<?= htmlspecialchars($editEquipment['PurchaseDate'] ?? date('Y-m-d')) ?>" required>
            </div>

            <div class="form-group">
                <label>Vendor / Supplier</label>
                <input type="text" name="Vendor" placeholder="e.g. FitTech Supplies" value="<?= htmlspecialchars($editEquipment['Vendor'] ?? '') ?>" required>
            </div>

            <div class="action-group">
                <button type="submit" class="btn btn-primary"><?= $editEquipment ? 'Save Changes' : 'Save Equipment' ?></button>
                <?php if ($editEquipment): ?>
                    <a href="equipment.php?tab=equipment" class="btn btn-warning">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="history-card">
        <div class="card-header-flex">
            <h3>Gym Equipment Inventory</h3>
        </div>

        <div class="search-bar">
            <input type="text" id="liveEqSearch" onkeyup="filterEqTable()" oninput="filterEqTable()" placeholder="Search by Equipment Name, Assigned Staff, Vendor, Purchase Date, or Status...">
            <button type="button" class="btn btn-primary" onclick="filterEqTable()">Search</button>
        </div>

        <div class="lh-table-wrap">
            <table class="lh-table">
                <thead>
                    <tr>
                        <th style="width: 24%;">Equipment Name</th>
                        <th style="width: 17%;">Assigned Staff</th>
                        <th style="width: 17%;">Vendor</th>
                        <th style="width: 12%;">Purchase Date</th>
                        <th style="width: 14%;">Status</th>
                        <th style="width: 9%;">Last Repaired</th>
                        <th style="width: 7%;">Action</th>
                    </tr>
                </thead>
                <tbody id="eqTableBody">
                    <?php if (empty($equipmentList)): ?>
                        <tr><td colspan="7" style="text-align:center; color:#94a3b8;">No equipment records found.</td></tr>
                    <?php else: foreach ($equipmentList as $eq):
                        $st = $eq['EquipmentStatus'] ?? 'Operational';
                        $pillClass = ($st === 'Operational') ? 'pill-green' : 'pill-amber';
                    ?>
                        <tr class="eq-row">
                            <td style="width: 24%;"><strong><?= htmlspecialchars($eq['EquipmentName']) ?></strong></td>
                            <td style="width: 17%;"><?= htmlspecialchars($eq['StaffName'] ?? '—') ?></td>
                            <td style="width: 17%;"><?= htmlspecialchars($eq['Vendor'] ?? '—') ?></td>
                            <td style="width: 12%;"><?= htmlspecialchars($eq['PurchaseDate'] ?? '—') ?></td>
                            <td style="width: 14%;"><span class="<?= $pillClass ?>"><?= htmlspecialchars($st) ?></span></td>
                            <td style="width: 9%; color:#94a3b8;"><?= htmlspecialchars($eq['LastRepairedDate'] ?? '—') ?></td>
                            <td style="width: 7%;">
                                <a href="equipment.php?tab=equipment&edit_eq=<?= urlencode($eq['EquipmentID']) ?>" class="btn btn-warning btn-sm">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    <tr id="noEqMatchRow" style="display:none;">
                        <td colspan="7" style="text-align:center; color:#94a3b8;">No matching equipment found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($activeTab === 'active'): ?>
    <!-- ========================================== -->
    <!-- TAB 2: MAINTENANCE REQUESTS (ONGOING WORK) -->
    <!-- ========================================== -->
    <?php if ($editScheduleStaff): ?>
        <div class="drawer-card">
            <form method="POST" action="equipment.php?tab=active" class="inline-grid-4">
                <input type="hidden" name="action" value="update_maintenance_schedule">
                <input type="hidden" name="StaffID" value="<?= htmlspecialchars($editScheduleStaff['StaffID']) ?>">

                <div class="form-group">
                    <label>Maintenance Staff</label>
                    <input type="text" value="<?= htmlspecialchars($editScheduleStaff['FirstName'] . ' ' . $editScheduleStaff['LastName']) ?>" readonly style="opacity:0.75;">
                </div>

                <div class="form-group">
                    <label>Work Schedule</label>
                    <input type="text" name="WorkSchedule" value="<?= htmlspecialchars($editScheduleStaff['WorkSchedule'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label>Assigned Task</label>
                    <input type="text" name="AssignedTask" value="<?= htmlspecialchars($editScheduleStaff['AssignedTask'] ?? '') ?>" required>
                </div>

                <div class="action-group">
                    <button type="submit" class="btn btn-primary">Save Schedule</button>
                    <a href="equipment.php?tab=active" class="btn btn-warning">Cancel</a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- REPORT / EDIT MAINTENANCE DRAWER -->
    <div id="logFormDrawer" class="drawer-card" style="display: <?= $editLog ? 'block' : 'none' ?>;">
        <form method="POST" action="equipment.php?tab=active" class="inline-grid-6">
            <input type="hidden" name="action" value="<?= $editLog ? 'update_maintenance_log' : 'add_maintenance_log' ?>">
            <?php if ($editLog): ?>
                <input type="hidden" name="LogID" value="<?= htmlspecialchars($editLog['LogID']) ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Equipment</label>
                <select name="EquipmentID" required>
                    <option value="">-- Choose Equipment --</option>
                    <?php foreach ($equipmentList as $eq): ?>
                        <option value="<?= htmlspecialchars($eq['EquipmentID']) ?>" <?= (($editLog['EquipmentID'] ?? '') === $eq['EquipmentID']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($eq['EquipmentName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Maintenance Staff</label>
                <select name="StaffID" required>
                    <option value="">-- Select Staff --</option>
                    <?php foreach ($maintenanceStaff as $ms): ?>
                        <option value="<?= htmlspecialchars($ms['StaffID']) ?>" <?= (($editLog['StaffID'] ?? '') === $ms['StaffID']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ms['FirstName'] . ' ' . $ms['LastName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Issue / Maintenance Description</label>
                <input type="text" name="IssueDescription" placeholder="e.g. Belt alignment & motor check" value="<?= htmlspecialchars($editLog['IssueDescription'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>Date Reported</label>
                <input type="date" name="DateReported" value="<?= htmlspecialchars($editLog['DateReported'] ?? date('Y-m-d')) ?>" required>
            </div>

            <div class="form-group">
                <label>Repair Status</label>
                <select name="RepairStatus" id="repairStatusSelect" onchange="toggleDateRepaired()" required>
                    <?php
                        $rStatuses = ['Pending', 'In Progress', 'Repaired'];
                        $currRStatus = $editLog['RepairStatus'] ?? 'Pending';
                        foreach ($rStatuses as $rs):
                    ?>
                        <option value="<?= $rs ?>" <?= $currRStatus === $rs ? 'selected' : '' ?>><?= $rs ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Date Repaired</label>
                <input type="date" name="DateRepaired" id="dateRepairedInput" value="<?= htmlspecialchars($editLog['DateRepaired'] ?? '') ?>">
            </div>

            <div class="action-group">
                <button type="submit" class="btn btn-primary"><?= $editLog ? 'Update' : 'Save Log' ?></button>
                <?php if ($editLog): ?>
                    <a href="equipment.php?tab=active" class="btn btn-warning">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- MAINTENANCE STAFF SCHEDULE OVERVIEW (NO STAFF ID COLUMN) -->
    <div class="schedule-strip-card">
        <div class="card-header-flex" style="margin-bottom:8px;">
            <h3>Maintenance Staff Schedule & Assigned Tasks</h3>
        </div>
        <div class="lh-table-wrap" style="max-height: 145px;">
            <table class="lh-table">
                <thead>
                    <tr>
                        <th style="width: 22%; padding: 9px 14px;">Name</th>
                        <th style="width: 30%; padding: 9px 14px;">Work Schedule</th>
                        <th style="width: 38%; padding: 9px 14px;">Assigned Task</th>
                        <th style="width: 10%; padding: 9px 14px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($maintenanceStaff as $ms): ?>
                        <tr>
                            <td style="width: 22%; padding: 9px 14px;"><strong><?= htmlspecialchars($ms['FirstName'] . ' ' . $ms['LastName']) ?></strong></td>
                            <td style="width: 30%; padding: 9px 14px;"><?= htmlspecialchars($ms['WorkSchedule'] ?? '—') ?></td>
                            <td style="width: 38%; padding: 9px 14px;"><?= htmlspecialchars($ms['AssignedTask'] ?? '—') ?></td>
                            <td style="width: 10%; padding: 9px 14px;">
                                <a href="equipment.php?tab=active&edit_sched=<?= urlencode($ms['StaffID']) ?>" class="btn btn-warning btn-sm">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- MAINTENANCE REQUESTS TABLE (NO LOG ID OR EQUIPMENT ID) -->
    <div class="history-card">
        <div class="card-header-flex">
            <h3>Maintenance Requests</h3>
        </div>

        <div class="search-bar">
            <input type="text" id="liveActiveSearch" onkeyup="filterActiveTable()" oninput="filterActiveTable()" placeholder="Search maintenance requests by Equipment, Staff, Issue, or Status...">
            <button type="button" class="btn btn-primary" onclick="filterActiveTable()">Search</button>
        </div>

        <div class="lh-table-wrap">
            <table class="lh-table">
                <thead>
                    <tr>
                        <th style="width: 22%;">Equipment</th>
                        <th style="width: 18%;">Assigned Staff</th>
                        <th style="width: 26%;">Issue Description</th>
                        <th style="width: 13%;">Status</th>
                        <th style="width: 11%;">Reported</th>
                        <th style="width: 10%;">Action</th>
                    </tr>
                </thead>
                <tbody id="activeTableBody">
                    <?php if (empty($activeMaintenanceLogs)): ?>
                        <tr><td colspan="6" style="text-align:center; color:#94a3b8;">No pending maintenance requests. All completed repairs are in Maintenance Logs.</td></tr>
                    <?php else: foreach ($activeMaintenanceLogs as $lg):
                        $rst = $lg['RepairStatus'] ?? 'Pending';
                        $rPill = ($rst === 'In Progress') ? 'pill-amber' : 'pill-red';
                    ?>
                        <tr class="active-row">
                            <td style="width: 22%;"><strong><?= htmlspecialchars($lg['EquipmentName'] ?? '—') ?></strong></td>
                            <td style="width: 18%;"><?= htmlspecialchars($lg['StaffName'] ?? '—') ?></td>
                            <td style="width: 26%;"><?= htmlspecialchars($lg['IssueDescription']) ?></td>
                            <td style="width: 13%;"><span class="<?= $rPill ?>"><?= htmlspecialchars($rst) ?></span></td>
                            <td style="width: 11%;"><?= htmlspecialchars($lg['DateReported']) ?></td>
                            <td style="width: 10%;">
                                <div class="action-group">
                                    <a href="equipment.php?tab=active&edit_log=<?= urlencode($lg['LogID']) ?>" class="btn btn-warning btn-sm">Edit</a>
                                    <form method="POST" action="equipment.php?tab=active" style="margin:0;">
                                        <input type="hidden" name="action" value="quick_mark_repaired">
                                        <input type="hidden" name="LogID" value="<?= htmlspecialchars($lg['LogID']) ?>">
                                        <input type="hidden" name="EquipmentID" value="<?= htmlspecialchars($lg['EquipmentID']) ?>">
                                        <button type="submit" class="btn btn-primary btn-sm" title="Mark Done & Move to Maintenance Logs">Done</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    <tr id="noActiveMatchRow" style="display:none;">
                        <td colspan="6" style="text-align:center; color:#94a3b8;">No matching maintenance requests found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>
    <!-- ========================================== -->
    <!-- TAB 3: MAINTENANCE LOGS (NO LOG ID OR EQ ID) -->
    <!-- ========================================== -->
    <div class="history-card">
        <div class="card-header-flex">
            <h3>Maintenance Logs</h3>
        </div>

        <div class="search-bar">
            <input type="text" id="liveLogSearch" onkeyup="filterLogTable()" oninput="filterLogTable()" placeholder="Search Maintenance Logs by Equipment, Repaired By, Issue, Status, or Date...">
            <button type="button" class="btn btn-primary" onclick="filterLogTable()">Search</button>
        </div>

        <div class="lh-table-wrap">
            <table class="lh-table">
                <thead>
                    <tr>
                        <th style="width: 23%;">Equipment</th>
                        <th style="width: 18%;">Repaired By</th>
                        <th style="width: 27%;">Issue Description</th>
                        <th style="width: 12%;">Status</th>
                        <th style="width: 10%;">Date Reported</th>
                        <th style="width: 10%;">Date Repaired</th>
                    </tr>
                </thead>
                <tbody id="logTableBody">
                    <?php if (empty($allMaintenanceLogs)): ?>
                        <tr><td colspan="6" style="text-align:center; color:#94a3b8;">No maintenance logs recorded yet.</td></tr>
                    <?php else: foreach ($allMaintenanceLogs as $lg):
                        $rst = $lg['RepairStatus'] ?? 'Pending';
                        $rPill = ($rst === 'Repaired') ? 'pill-green' : (($rst === 'In Progress') ? 'pill-amber' : 'pill-red');
                    ?>
                        <tr class="log-row">
                            <td style="width: 23%;"><strong><?= htmlspecialchars($lg['EquipmentName'] ?? '—') ?></strong></td>
                            <td style="width: 18%;"><?= htmlspecialchars($lg['StaffName'] ?? '—') ?></td>
                            <td style="width: 27%;"><?= htmlspecialchars($lg['IssueDescription']) ?></td>
                            <td style="width: 12%;"><span class="<?= $rPill ?>"><?= htmlspecialchars($rst) ?></span></td>
                            <td style="width: 10%;"><?= htmlspecialchars($lg['DateReported']) ?></td>
                            <td style="width: 10%; color:#94a3b8;"><?= htmlspecialchars($lg['DateRepaired'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    <tr id="noLogMatchRow" style="display:none;">
                        <td colspan="6" style="text-align:center; color:#94a3b8;">No matching maintenance logs found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<script>
function toggleFormDrawer(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'block' : 'none';
}

function filterEqTable() {
    const query = document.getElementById('liveEqSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#eqTableBody .eq-row');
    const noMatch = document.getElementById('noEqMatchRow');
    let visible = 0;

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(query)) {
            row.style.display = 'table';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });

    if (noMatch) noMatch.style.display = (rows.length > 0 && visible === 0) ? 'table' : 'none';
}

function filterActiveTable() {
    const query = document.getElementById('liveActiveSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#activeTableBody .active-row');
    const noMatch = document.getElementById('noActiveMatchRow');
    let visible = 0;

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(query)) {
            row.style.display = 'table';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });

    if (noMatch) noMatch.style.display = (rows.length > 0 && visible === 0) ? 'table' : 'none';
}

function filterLogTable() {
    const query = document.getElementById('liveLogSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#logTableBody .log-row');
    const noMatch = document.getElementById('noLogMatchRow');
    let visible = 0;

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(query)) {
            row.style.display = 'table';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });

    if (noMatch) noMatch.style.display = (rows.length > 0 && visible === 0) ? 'table' : 'none';
}

function toggleDateRepaired() {
    const status = document.getElementById('repairStatusSelect').value;
    const dateInput = document.getElementById('dateRepairedInput');
    if (status === 'Repaired' && !dateInput.value) {
        dateInput.value = new Date().toISOString().split('T')[0];
    } else if (status !== 'Repaired') {
        dateInput.value = '';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>