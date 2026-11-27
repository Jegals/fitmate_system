<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Fitness Programs";

// ==========================================
// 1. GET TRAINERS FROM DATABASE
// ==========================================
$trainers = $pdo->query("
    SELECT StaffID, FirstName, LastName
    FROM gym_staff
    WHERE StaffType = 'Trainer'
    ORDER BY FirstName ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// 2. HANDLE ADD AND UPDATE PROGRAM
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action      = $_POST['action'] ?? 'add';
    $programName = trim($_POST['ProgramName'] ?? '');
    $schedule    = trim($_POST['Schedule'] ?? '');
    $staffID     = trim($_POST['StaffID'] ?? '');
    $programID   = trim($_POST['ProgramID'] ?? '');

    if ($programName === '' || $schedule === '' || $staffID === '') {
        header("Location: programs.php?error=required");
        exit;
    }

    if (strlen($programName) > 100 || strlen($schedule) > 100) {
        header("Location: programs.php?error=length");
        exit;
    }

    $checkTrainer = $pdo->prepare("
        SELECT StaffID
        FROM gym_staff
        WHERE StaffID = ? AND StaffType = 'Trainer'
    ");
    $checkTrainer->execute([$staffID]);

    if (!$checkTrainer->fetch()) {
        header("Location: programs.php?error=trainer");
        exit;
    }

    try {
        // ADD NEW PROGRAM
        if ($action === 'add') {
            $lastProgram = $pdo->query("
                SELECT ProgramID
                FROM program
                WHERE ProgramID REGEXP '^PRG[0-9]+$'
                ORDER BY CAST(SUBSTRING(ProgramID, 4) AS UNSIGNED) DESC
                LIMIT 1
            ")->fetchColumn();

            $nextNumber = $lastProgram
                ? ((int) substr($lastProgram, 3) + 1)
                : 201;

            $newProgramID = 'PRG' . $nextNumber;

            $stmt = $pdo->prepare("
                INSERT INTO program (ProgramID, StaffID, ProgramName, Schedule)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$newProgramID, $staffID, $programName, $schedule]);

            header("Location: programs.php?success=added");
            exit;
        }
        // UPDATE EXISTING PROGRAM
        elseif ($action === 'update') {
            if ($programID === '') {
                header("Location: programs.php?error=invalid");
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE program
                SET ProgramName = ?, Schedule = ?, StaffID = ?
                WHERE ProgramID = ?
            ");
            $stmt->execute([$programName, $schedule, $staffID, $programID]);

            header("Location: programs.php?success=updated");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Program Error: " . $e->getMessage());
        header("Location: programs.php?error=database");
        exit;
    }
}

// ==========================================
// 3. GET PROGRAMS (FOR LIVE SEARCH)
// ==========================================
$sql = "
    SELECT
        p.ProgramID,
        p.ProgramName,
        p.Schedule,
        p.StaffID,
        CONCAT(gs.FirstName, ' ', gs.LastName) AS TrainerName
    FROM program p
    LEFT JOIN gym_staff gs ON p.StaffID = gs.StaffID
    ORDER BY CAST(SUBSTRING(p.ProgramID, 4) AS UNSIGNED) ASC
";
$programs = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// 4. GET PROGRAM INFORMATION FOR EDITING
// ==========================================
$editProgram = null;
if (isset($_GET['edit']) && $_GET['edit'] !== '') {
    $stmt = $pdo->prepare("SELECT * FROM program WHERE ProgramID = ?");
    $stmt->execute([$_GET['edit']]);
    $editProgram = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ==========================================
// 5. DISPLAY NOTIFICATIONS
// ==========================================
$success = $_GET['success'] ?? '';
$error   = $_GET['error'] ?? '';

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
        padding: 16px 22px !important;
        gap: 14px !important;
    }

    .programs-grid {
        flex: 1 !important;
        display: grid !important;
        grid-template-columns: 340px 1fr !important;
        gap: 18px !important;
        min-height: 0 !important;
        align-items: stretch !important;
    }
    .programs-grid > .card {
        margin-bottom: 0 !important;
        display: flex !important;
        flex-direction: column !important;
        min-height: 0 !important;
        overflow: hidden !important;
    }

    /* Consistent Black & Blue Search Bar Styling */
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

    /* FIXED TABLE HEADER + INTERNAL SCROLLABLE TBODY */
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
</style>

<?php if ($success === 'added'): ?>
    <div style="background:rgba(22, 163, 74, 0.2); border:1px solid #22c55e; color:#86efac; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        Fitness program added successfully!
    </div>
<?php elseif ($success === 'updated'): ?>
    <div style="background:rgba(22, 163, 74, 0.2); border:1px solid #22c55e; color:#86efac; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        Fitness program updated successfully!
    </div>
<?php endif; ?>

<?php if ($error === 'required'): ?>
    <div style="background:rgba(220, 38, 38, 0.2); border:1px solid #ef4444; color:#fca5a5; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        Please complete all required fields.
    </div>
<?php elseif ($error === 'length'): ?>
    <div style="background:rgba(220, 38, 38, 0.2); border:1px solid #ef4444; color:#fca5a5; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        Program Name and Schedule must not exceed 100 characters.
    </div>
<?php elseif ($error === 'trainer'): ?>
    <div style="background:rgba(220, 38, 38, 0.2); border:1px solid #ef4444; color:#fca5a5; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        Please select a valid trainer.
    </div>
<?php elseif ($error === 'invalid'): ?>
    <div style="background:rgba(220, 38, 38, 0.2); border:1px solid #ef4444; color:#fca5a5; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        Invalid program selected.
    </div>
<?php elseif ($error === 'database'): ?>
    <div style="background:rgba(220, 38, 38, 0.2); border:1px solid #ef4444; color:#fca5a5; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        Database error. Please check your input and try again.
    </div>
<?php endif; ?>

<div class="programs-grid">
    <!-- LEFT CARD: ADD / EDIT PROGRAM FORM -->
    <div class="card" style="height: fit-content;">
        <h3 style="margin-bottom: 14px;">
            <?= $editProgram ? 'Edit Fitness Program' : 'Add Fitness Program' ?>
        </h3>

        <form method="POST" action="programs.php">
            <input type="hidden" name="action" value="<?= $editProgram ? 'update' : 'add' ?>">

            <?php if ($editProgram): ?>
                <input type="hidden" name="ProgramID" value="<?= htmlspecialchars($editProgram['ProgramID']) ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Program Name</label>
                <input
                    type="text"
                    name="ProgramName"
                    placeholder="e.g. Strength & Conditioning"
                    maxlength="100"
                    value="<?= htmlspecialchars($editProgram['ProgramName'] ?? '') ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>Schedule</label>
                <input
                    type="text"
                    name="Schedule"
                    placeholder="e.g. Mon/Wed/Fri 5:00 PM"
                    maxlength="100"
                    value="<?= htmlspecialchars($editProgram['Schedule'] ?? '') ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>Assigned Trainer (Live from DB)</label>
                <select name="StaffID" required>
                    <option value="">-- Select Trainer --</option>
                    <?php foreach ($trainers as $t): ?>
                        <option
                            value="<?= htmlspecialchars($t['StaffID']) ?>"
                            <?= (($editProgram['StaffID'] ?? '') === $t['StaffID']) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($t['FirstName'] . ' ' . $t['LastName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top: 6px; padding: 10px;">
                <?= $editProgram ? 'Update Program' : 'Save Program' ?>
            </button>

            <?php if ($editProgram): ?>
                <a
                    href="programs.php"
                    class="btn btn-warning"
                    style="display:block; text-align:center; margin-top:10px;"
                >
                    Cancel Edit
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- RIGHT CARD: GYM PROGRAMS LIST (NO PROGRAM ID COLUMN) -->
    <div class="card">
        <h3 style="margin-bottom: 12px; flex-shrink: 0;">Gym Programs List</h3>

        <div class="search-bar">
            <input
                type="text"
                id="liveProgramSearch"
                onkeyup="filterProgramsTable()"
                oninput="filterProgramsTable()"
                placeholder="Search Program Name, Schedule, or Trainer..."
            >
            <button type="button" class="btn btn-primary" onclick="filterProgramsTable()">
                Search
            </button>
        </div>

        <div class="fixed-table-wrap">
            <table class="fixed-table">
                <thead>
                    <tr>
                        <th style="width: 34%;">Program Name</th>
                        <th style="width: 26%;">Schedule</th>
                        <th style="width: 25%;">Trainer</th>
                        <th style="width: 15%;">Action</th>
                    </tr>
                </thead>
                <tbody id="programTableBody">
                    <?php if (empty($programs)): ?>
                        <tr>
                            <td colspan="4" style="text-align:center; color:#94a3b8; padding:18px;">
                                No fitness programs found.
                            </td>
                        </tr>
                    <?php else: foreach ($programs as $pr): ?>
                        <tr class="program-row">
                            <td style="width: 34%;"><strong><?= htmlspecialchars($pr['ProgramName']) ?></strong></td>
                            <td style="width: 26%;"><?= htmlspecialchars($pr['Schedule']) ?></td>
                            <td style="width: 25%;"><?= htmlspecialchars($pr['TrainerName'] ?? '-') ?></td>
                            <td style="width: 15%;">
                                <a
                                    href="programs.php?edit=<?= urlencode($pr['ProgramID']) ?>"
                                    class="btn btn-warning btn-sm"
                                >
                                    Edit
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    <tr id="noProgramMatchRow" style="display: none;">
                        <td colspan="4" style="text-align:center; color:#94a3b8; padding:18px;">
                            No fitness programs found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function filterProgramsTable() {
    const query = document.getElementById('liveProgramSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#programTableBody .program-row');
    const noMatchRow = document.getElementById('noProgramMatchRow');
    let visibleCount = 0;

    rows.forEach(row => {
        const programName = row.cells[0].textContent.toLowerCase();
        const schedule    = row.cells[1].textContent.toLowerCase();
        const trainer     = row.cells[2].textContent.toLowerCase();

        if (
            programName.includes(query) ||
            schedule.includes(query) ||
            trainer.includes(query)
        ) {
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
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>