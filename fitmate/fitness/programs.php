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

    $action = $_POST['action'] ?? 'add';

    $programName = trim($_POST['ProgramName'] ?? '');
    $schedule = trim($_POST['Schedule'] ?? '');
    $staffID = trim($_POST['StaffID'] ?? '');
    $programID = trim($_POST['ProgramID'] ?? '');

    // Validate required fields
    if ($programName === '' || $schedule === '' || $staffID === '') {
        header("Location: programs.php?error=required");
        exit;
    }

    if (strlen($programName) > 100 || strlen($schedule) > 100) {
        header("Location: programs.php?error=length");
        exit;
    }

    // Confirm selected StaffID belongs to a trainer
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

            // Get the highest existing PRG number
            $lastProgram = $pdo->query("
                SELECT ProgramID
                FROM program
                WHERE ProgramID REGEXP '^PRG[0-9]+$'
                ORDER BY CAST(SUBSTRING(ProgramID, 4) AS UNSIGNED) DESC
                LIMIT 1
            ")->fetchColumn();

            // Generate next ProgramID
            $nextNumber = $lastProgram
                ? ((int) substr($lastProgram, 3) + 1)
                : 201;

            $newProgramID = 'PRG' . $nextNumber;

            $stmt = $pdo->prepare("
                INSERT INTO program
                    (ProgramID, StaffID, ProgramName, Schedule)
                VALUES
                    (?, ?, ?, ?)
            ");

            $stmt->execute([
                $newProgramID,
                $staffID,
                $programName,
                $schedule
            ]);

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
                SET
                    ProgramName = ?,
                    Schedule = ?,
                    StaffID = ?
                WHERE ProgramID = ?
            ");

            $stmt->execute([
                $programName,
                $schedule,
                $staffID,
                $programID
            ]);

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

    LEFT JOIN gym_staff gs
        ON p.StaffID = gs.StaffID

    ORDER BY p.ProgramID ASC
";

$programs = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);


// ==========================================
// 4. GET PROGRAM INFORMATION FOR EDITING
// ==========================================

$editProgram = null;

if (isset($_GET['edit']) && $_GET['edit'] !== '') {

    $stmt = $pdo->prepare("
        SELECT *
        FROM program
        WHERE ProgramID = ?
    ");

    $stmt->execute([$_GET['edit']]);

    $editProgram = $stmt->fetch(PDO::FETCH_ASSOC);

}


// ==========================================
// 5. DISPLAY NOTIFICATIONS
// ==========================================

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

include __DIR__ . '/../includes/header.php';
?>

<style>
    /* Consistent Black & Blue Search Bar Styling */
    .search-bar {
        display: flex;
        gap: 10px;
        margin-bottom: 18px;
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

<!-- ======================================
     MAIN PROGRAMS UI
======================================= -->

<div class="grid-2">

    <!-- ADD / EDIT PROGRAM FORM -->

    <div class="card">

        <h3 style="margin-bottom: 14px;">
            <?= $editProgram ? 'Edit Fitness Program' : 'Add Fitness Program' ?>
        </h3>

        <?php if ($success === 'added'): ?>
            <p style="color:#22c55e;">
                Fitness program added successfully!
            </p>

        <?php elseif ($success === 'updated'): ?>
            <p style="color:#22c55e;">
                Fitness program updated successfully!
            </p>
        <?php endif; ?>


        <?php if ($error === 'required'): ?>
            <p style="color:#ef4444;">
                Please complete all required fields.
            </p>

        <?php elseif ($error === 'length'): ?>
            <p style="color:#ef4444;">
                Program Name and Schedule must not exceed 100 characters.
            </p>

        <?php elseif ($error === 'trainer'): ?>
            <p style="color:#ef4444;">
                Please select a valid trainer.
            </p>

        <?php elseif ($error === 'invalid'): ?>
            <p style="color:#ef4444;">
                Invalid program selected.
            </p>

        <?php elseif ($error === 'database'): ?>
            <p style="color:#ef4444;">
                Database error. Please check your input and try again.
            </p>
        <?php endif; ?>


        <form method="POST" action="programs.php">

            <input
                type="hidden"
                name="action"
                value="<?= $editProgram ? 'update' : 'add' ?>"
            >

            <?php if ($editProgram): ?>
                <input
                    type="hidden"
                    name="ProgramID"
                    value="<?= htmlspecialchars($editProgram['ProgramID']) ?>"
                >

                <div class="form-group">
                    <label>Program ID</label>
                    <input
                        type="text"
                        value="<?= htmlspecialchars($editProgram['ProgramID']) ?>"
                        readonly
                    >
                </div>
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
                            <?= (
                                ($editProgram['StaffID'] ?? '') === $t['StaffID']
                            ) ? 'selected' : '' ?>
                        >

                            <?= htmlspecialchars(
                                $t['StaffID'] . ' — ' .
                                $t['FirstName'] . ' ' .
                                $t['LastName']
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


    <!-- PROGRAMS TABLE -->

    <div class="card">

        <h3 style="margin-bottom: 12px;">
            Gym Programs List (<span id="programCount"><?= count($programs) ?></span> Programs)
        </h3>


        <!-- LIVE SEARCH BAR -->

        <div class="search-bar">

            <input
                type="text"
                id="liveProgramSearch"
                onkeyup="filterProgramsTable()"
                oninput="filterProgramsTable()"
                placeholder="Search Program ID, Program Name, Schedule, or Trainer..."
            >

            <button type="button" class="btn btn-primary" onclick="filterProgramsTable()">
                Search
            </button>

        </div>


        <!-- PROGRAMS LIST -->

        <table class="table">

            <thead>

                <tr>
                    <th>Program ID</th>
                    <th>Program Name</th>
                    <th>Schedule</th>
                    <th>Trainer</th>
                    <th>Action</th>
                </tr>

            </thead>


            <tbody id="programTableBody">

                <?php if (empty($programs)): ?>

                    <tr>
                        <td colspan="5" style="text-align:center; color:#94a3b8;">
                            No fitness programs found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($programs as $pr): ?>

                        <tr class="program-row">

                            <td>
                                <strong>
                                    <?= htmlspecialchars($pr['ProgramID']) ?>
                                </strong>
                            </td>


                            <td>
                                <?= htmlspecialchars($pr['ProgramName']) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars($pr['Schedule']) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $pr['TrainerName'] ?? $pr['StaffID'] ?? '-'
                                ) ?>
                            </td>


                            <td>

                                <a
                                    href="programs.php?edit=<?= urlencode($pr['ProgramID']) ?>"
                                    class="btn btn-warning btn-sm"
                                >
                                    Edit
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    <tr id="noProgramMatchRow" style="display: none;">
                        <td colspan="5" style="text-align:center; color:#94a3b8; padding:16px;">
                            No fitness programs found.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- ======================================
     JAVASCRIPT: LIVE SEARCH PROGRAMS
======================================= -->

<script>
function filterProgramsTable() {
    const query = document.getElementById('liveProgramSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#programTableBody .program-row');
    const noMatchRow = document.getElementById('noProgramMatchRow');
    let visibleCount = 0;

    rows.forEach(row => {
        const programId   = row.cells[0].textContent.toLowerCase();
        const programName = row.cells[1].textContent.toLowerCase();
        const schedule    = row.cells[2].textContent.toLowerCase();
        const trainer     = row.cells[3].textContent.toLowerCase();

        if (
            programId.includes(query) ||
            programName.includes(query) ||
            schedule.includes(query) ||
            trainer.includes(query)
        ) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    document.getElementById('programCount').textContent = visibleCount;
    if (noMatchRow) {
        noMatchRow.style.display = (rows.length > 0 && visibleCount === 0) ? '' : 'none';
    }
}
</script>


<?php include __DIR__ . '/../includes/footer.php'; ?>
