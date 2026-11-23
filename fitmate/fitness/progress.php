<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Member Progress";


// ==========================================
// 1. GET MEMBERS FROM DATABASE
// ==========================================

$members = $pdo->query("
    SELECT MemberID, FirstName, LastName
    FROM gym_member
    ORDER BY FirstName ASC
")->fetchAll(PDO::FETCH_ASSOC);


// ==========================================
// 2. HANDLE ADD AND UPDATE PROGRESS
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? 'add';

    $memberID = trim($_POST['MemberID'] ?? '');
    $date = trim($_POST['Date'] ?? '');
    $height = $_POST['Height'] ?? '';
    $weight = $_POST['Weight'] ?? '';
    $recordID = trim($_POST['RecordID'] ?? '');

    // Validate required fields
    if (
        $memberID === '' ||
        $date === '' ||
        $height === '' ||
        $weight === ''
    ) {
        header("Location: progress.php?error=required");
        exit;
    }

    // Validate height and weight
    if (
        !is_numeric($height) ||
        !is_numeric($weight) ||
        (float)$height <= 0 ||
        (float)$weight <= 0 ||
        (float)$height > 999.99 ||
        (float)$weight > 999.99
    ) {
        header("Location: progress.php?error=measurement");
        exit;
    }

    // Validate date format
    $dateObject = DateTime::createFromFormat('!Y-m-d', $date);

    if (
        !$dateObject ||
        $dateObject->format('Y-m-d') !== $date
    ) {
        header("Location: progress.php?error=date");
        exit;
    }

    // Verify that the member exists
    $checkMember = $pdo->prepare("
        SELECT MemberID
        FROM gym_member
        WHERE MemberID = ?
    ");

    $checkMember->execute([$memberID]);

    if (!$checkMember->fetch()) {
        header("Location: progress.php?error=member");
        exit;
    }

    try {

        // ==================================
        // ADD NEW PROGRESS RECORD
        // ==================================

        if ($action === 'add') {

            // Find the latest RecordID
            $lastRecord = $pdo->query("
                SELECT RecordID
                FROM progress
                WHERE RecordID REGEXP '^REC[0-9]+$'
                ORDER BY CAST(SUBSTRING(RecordID, 4) AS UNSIGNED) DESC
                LIMIT 1
            ")->fetchColumn();

            // Generate the next RecordID
            $nextNumber = $lastRecord
                ? ((int) substr($lastRecord, 3) + 1)
                : 901;

            $newRecordID = 'REC' . $nextNumber;

            $stmt = $pdo->prepare("
                INSERT INTO progress
                    (RecordID, MemberID, `Date`, Height, Weight)
                VALUES
                    (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $newRecordID,
                $memberID,
                $date,
                $height,
                $weight
            ]);

            header("Location: progress.php?success=added");
            exit;
        }


        // ==================================
        // UPDATE EXISTING PROGRESS RECORD
        // ==================================

        elseif ($action === 'update') {

            if ($recordID === '') {
                header("Location: progress.php?error=invalid");
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE progress
                SET
                    MemberID = ?,
                    `Date` = ?,
                    Height = ?,
                    Weight = ?
                WHERE RecordID = ?
            ");

            $stmt->execute([
                $memberID,
                $date,
                $height,
                $weight,
                $recordID
            ]);

            header("Location: progress.php?success=updated");
            exit;
        }

    } catch (PDOException $e) {

        error_log("Progress Error: " . $e->getMessage());

        header("Location: progress.php?error=database");
        exit;
    }
}


// ==========================================
// 3. GET MEMBER / PROGRESS RECORDS (FOR LIVE SEARCH)
// ==========================================

$sql = "
    SELECT
        pr.RecordID,
        pr.MemberID,
        pr.`Date`,
        pr.Height,
        pr.Weight,
        CONCAT(m.FirstName, ' ', m.LastName) AS FullName

    FROM progress pr

    INNER JOIN gym_member m
        ON pr.MemberID = m.MemberID

    ORDER BY pr.`Date` DESC, pr.RecordID DESC
";

$progressList = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);


// ==========================================
// 4. GET RECORD INFORMATION FOR EDITING
// ==========================================

$editProgress = null;

if (isset($_GET['edit']) && $_GET['edit'] !== '') {

    $stmt = $pdo->prepare("
        SELECT *
        FROM progress
        WHERE RecordID = ?
    ");

    $stmt->execute([$_GET['edit']]);

    $editProgress = $stmt->fetch(PDO::FETCH_ASSOC);
}


// ==========================================
// 5. NOTIFICATIONS
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
     MAIN PROGRESS UI
======================================= -->

<div class="grid-2">


    <!-- ==================================
         ADD / EDIT PROGRESS FORM
    =================================== -->

    <div class="card">

        <h3 style="margin-bottom:14px;">
            <?= $editProgress
                ? 'Edit Member Progress'
                : 'Log Member Progress' ?>
        </h3>


        <!-- SUCCESS MESSAGES -->

        <?php if ($success === 'added'): ?>

            <p style="color:#22c55e;">
                Member progress recorded successfully!
            </p>

        <?php elseif ($success === 'updated'): ?>

            <p style="color:#22c55e;">
                Member progress updated successfully!
            </p>

        <?php endif; ?>


        <!-- ERROR MESSAGES -->

        <?php if ($error === 'required'): ?>

            <p style="color:#ef4444;">
                Please complete all required fields.
            </p>

        <?php elseif ($error === 'measurement'): ?>

            <p style="color:#ef4444;">
                Height and weight must be valid positive numbers.
            </p>

        <?php elseif ($error === 'date'): ?>

            <p style="color:#ef4444;">
                Please enter a valid date.
            </p>

        <?php elseif ($error === 'member'): ?>

            <p style="color:#ef4444;">
                Member ID does not exist. Please select a valid member.
            </p>

        <?php elseif ($error === 'invalid'): ?>

            <p style="color:#ef4444;">
                Invalid progress record.
            </p>

        <?php elseif ($error === 'database'): ?>

            <p style="color:#ef4444;">
                Database error. Please try again.
            </p>

        <?php endif; ?>


        <form method="POST" action="progress.php">

            <input
                type="hidden"
                name="action"
                value="<?= $editProgress ? 'update' : 'add' ?>"
            >


            <?php if ($editProgress): ?>

                <input
                    type="hidden"
                    name="RecordID"
                    value="<?= htmlspecialchars($editProgress['RecordID']) ?>"
                >

                <div class="form-group">

                    <label>Record ID</label>

                    <input
                        type="text"
                        value="<?= htmlspecialchars($editProgress['RecordID']) ?>"
                        readonly
                    >

                </div>

            <?php endif; ?>


            <!-- MEMBER ID -->

            <div class="form-group">

                <label>Member ID</label>

                <input
                    type="text"
                    name="MemberID"
                    list="memberList"
                    placeholder="e.g. MEM101"
                    value="<?= htmlspecialchars($editProgress['MemberID'] ?? '') ?>"
                    required
                >

                <datalist id="memberList">

                    <?php foreach ($members as $m): ?>

                        <option value="<?= htmlspecialchars($m['MemberID']) ?>">
                            <?= htmlspecialchars(
                                $m['FirstName'] . ' ' . $m['LastName']
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </datalist>

            </div>


            <!-- HEIGHT AND WEIGHT -->

            <div class="form-row">

                <div class="form-group">

                    <label>Height (cm)</label>

                    <input
                        type="number"
                        id="height"
                        name="Height"
                        step="0.01"
                        min="0.01"
                        max="999.99"
                        placeholder="170"
                        value="<?= htmlspecialchars($editProgress['Height'] ?? '') ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Weight (kg)</label>

                    <input
                        type="number"
                        id="weight"
                        name="Weight"
                        step="0.01"
                        min="0.01"
                        max="999.99"
                        placeholder="68.5"
                        value="<?= htmlspecialchars($editProgress['Weight'] ?? '') ?>"
                        required
                    >

                </div>

            </div>


            <!-- DATE -->

            <div class="form-group">

                <label>Date</label>

                <input
                    type="date"
                    name="Date"
                    value="<?= htmlspecialchars(
                        $editProgress['Date'] ?? date('Y-m-d')
                    ) ?>"
                    required
                >

            </div>


            <!-- AUTOMATIC BMI COMPUTATION -->

            <div class="form-group">

                <label>Calculated BMI</label>

                <input
                    type="text"
                    id="bmi"
                    placeholder="BMI will be calculated automatically"
                    readonly
                >

                <small style="color:#60a5fa;">
                    BMI = Weight (kg) / Height (m)²
                </small>

            </div>


            <!-- SAVE / UPDATE BUTTON -->

            <button
                type="submit"
                class="btn btn-primary"
                style="width:100%;"
            >

                <?= $editProgress
                    ? 'Update Progress'
                    : 'Save Progress' ?>

            </button>


            <?php if ($editProgress): ?>

                <a
                    href="progress.php"
                    class="btn btn-warning"
                    style="display:block; text-align:center; margin-top:10px;"
                >
                    Cancel Edit
                </a>

            <?php endif; ?>

        </form>

    </div>



    <!-- ==================================
         PROGRESS TRACKING HISTORY
    =================================== -->

    <div class="card">

        <h3 style="margin-bottom:12px;">
            Progress Tracking History
            (<span id="progressCount"><?= count($progressList) ?></span> Records)
        </h3>


        <!-- LIVE SEARCH MEMBER -->

        <div class="search-bar">

            <input
                type="text"
                id="liveProgressSearch"
                onkeyup="filterProgressTable()"
                oninput="filterProgressTable()"
                placeholder="Search Member ID or Name..."
            >

            <button
                type="button"
                class="btn btn-primary"
                onclick="filterProgressTable()"
            >
                Search
            </button>

        </div>



        <!-- PROGRESS TABLE -->

        <table class="table">

            <thead>

                <tr>
                    <th>Record ID</th>
                    <th>Member</th>
                    <th>Date</th>
                    <th>Height</th>
                    <th>Weight</th>
                    <th>BMI</th>
                    <th>Action</th>
                </tr>

            </thead>


            <tbody id="progressTableBody">

                <?php if (empty($progressList)): ?>

                    <tr>

                        <td colspan="7" style="text-align:center;">
                            No progress records found.
                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($progressList as $pg):

                        $heightMeters = (float)$pg['Height'] / 100;

                        $bmi = $heightMeters > 0
                            ? round(
                                (float)$pg['Weight'] /
                                ($heightMeters * $heightMeters),
                                1
                            )
                            : 0;

                    ?>

                        <tr class="progress-row" data-member-id="<?= htmlspecialchars(strtolower($pg['MemberID'])) ?>">

                            <td>
                                <strong>
                                    <?= htmlspecialchars($pg['RecordID']) ?>
                                </strong>
                            </td>


                            <td>
                                <?= htmlspecialchars($pg['FullName']) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars($pg['Date']) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars($pg['Height']) ?> cm
                            </td>


                            <td>
                                <?= htmlspecialchars($pg['Weight']) ?> kg
                            </td>


                            <td>

                                <span class="badge badge-green">
                                    <?= number_format($bmi, 1) ?>
                                </span>

                            </td>


                            <td>

                                <a
                                    href="progress.php?edit=<?= urlencode($pg['RecordID']) ?>"
                                    class="btn btn-warning btn-sm"
                                >
                                    Edit
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    <tr id="noProgressMatchRow" style="display: none;">
                        <td colspan="7" style="text-align:center; color:#94a3b8; padding:16px;">
                            No progress records found.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- ======================================
     JAVASCRIPT: AUTOMATIC BMI COMPUTATION & LIVE SEARCH
======================================= -->

<script>

function filterProgressTable() {
    const query = document.getElementById('liveProgressSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#progressTableBody .progress-row');
    const noMatchRow = document.getElementById('noProgressMatchRow');
    let visibleCount = 0;

    rows.forEach(row => {
        const recordId   = row.cells[0].textContent.toLowerCase();
        const memberName = row.cells[1].textContent.toLowerCase();
        const recordDate = row.cells[2].textContent.toLowerCase();
        const memberId   = (row.getAttribute('data-member-id') || '').toLowerCase();

        if (
            recordId.includes(query) ||
            memberId.includes(query) ||
            memberName.includes(query) ||
            recordDate.includes(query)
        ) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    document.getElementById('progressCount').textContent = visibleCount;
    if (noMatchRow) {
        noMatchRow.style.display = (rows.length > 0 && visibleCount === 0) ? '' : 'none';
    }
}

document.addEventListener('DOMContentLoaded', function () {

    const heightInput = document.getElementById('height');
    const weightInput = document.getElementById('weight');
    const bmiInput = document.getElementById('bmi');


    function calculateBMI() {

        const height = parseFloat(heightInput.value);
        const weight = parseFloat(weightInput.value);

        if (
            !heightInput.value ||
            !weightInput.value ||
            height <= 0 ||
            weight <= 0
        ) {

            bmiInput.value = '';
            return;

        }

        // Convert centimeters into meters
        const heightMeters = height / 100;

        // BMI formula
        const bmi = weight / (heightMeters * heightMeters);

        bmiInput.value = bmi.toFixed(1);

    }


    // Recalculate whenever height changes
    heightInput.addEventListener('input', calculateBMI);


    // Recalculate whenever weight changes
    weightInput.addEventListener('input', calculateBMI);


    // Calculate immediately when editing an existing record
    calculateBMI();

});

</script>


<?php include __DIR__ . '/../includes/footer.php'; ?>
