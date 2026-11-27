<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Member Progress";

// ==========================================
// 1. GET ACTIVE MEMBERS FROM DATABASE (FOR NAME AUTOCOMPLETE)
// ==========================================
$members = $pdo->query("
    SELECT MemberID, CONCAT(FirstName, ' ', LastName) AS FullName
    FROM gym_member
    WHERE is_archived = 0
    ORDER BY FirstName ASC, LastName ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// 2. HANDLE ADD AND UPDATE PROGRESS (BY MEMBER NAME)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action          = $_POST['action'] ?? 'add';
    $memberNameInput = trim(preg_replace('/\s+/', ' ', $_POST['MemberName'] ?? ''));
    $date            = trim($_POST['Date'] ?? '');
    $height          = $_POST['Height'] ?? '';
    $weight          = $_POST['Weight'] ?? '';
    $recordID        = trim($_POST['RecordID'] ?? '');

    if ($memberNameInput === '' || $date === '' || $height === '' || $weight === '') {
        header("Location: progress.php?error=required");
        exit;
    }

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

    $dateObject = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
        header("Location: progress.php?error=date");
        exit;
    }

    // Lookup active member by exact Full Name (case-insensitive)
    $checkMember = $pdo->prepare("
        SELECT MemberID
        FROM gym_member
        WHERE is_archived = 0
          AND LOWER(TRIM(CONCAT(FirstName, ' ', LastName))) = LOWER(?)
    ");
    $checkMember->execute([$memberNameInput]);
    $matchedMembers = $checkMember->fetchAll(PDO::FETCH_ASSOC);

    if (count($matchedMembers) === 0) {
        header("Location: progress.php?error=member");
        exit;
    } elseif (count($matchedMembers) > 1) {
        header("Location: progress.php?error=duplicate_name");
        exit;
    }

    $memberID = $matchedMembers[0]['MemberID'];

    try {
        // ADD NEW PROGRESS RECORD
        if ($action === 'add') {
            $lastRecord = $pdo->query("
                SELECT RecordID
                FROM progress
                WHERE RecordID REGEXP '^REC[0-9]+$'
                ORDER BY CAST(SUBSTRING(RecordID, 4) AS UNSIGNED) DESC
                LIMIT 1
            ")->fetchColumn();

            $nextNumber = $lastRecord
                ? ((int) substr($lastRecord, 3) + 1)
                : 901;

            $newRecordID = 'REC' . $nextNumber;

            $stmt = $pdo->prepare("
                INSERT INTO progress (RecordID, MemberID, `Date`, Height, Weight)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$newRecordID, $memberID, $date, $height, $weight]);

            header("Location: progress.php?success=added");
            exit;
        }
        // UPDATE EXISTING PROGRESS RECORD
        elseif ($action === 'update') {
            if ($recordID === '') {
                header("Location: progress.php?error=invalid");
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE progress
                SET MemberID = ?, `Date` = ?, Height = ?, Weight = ?
                WHERE RecordID = ?
            ");
            $stmt->execute([$memberID, $date, $height, $weight, $recordID]);

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
    INNER JOIN gym_member m ON pr.MemberID = m.MemberID
    ORDER BY pr.`Date` DESC, CAST(SUBSTRING(pr.RecordID, 4) AS UNSIGNED) DESC
";
$progressList = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// 4. GET RECORD INFORMATION FOR EDITING (WITH FULL NAME)
// ==========================================
$editProgress = null;
if (isset($_GET['edit']) && $_GET['edit'] !== '') {
    $stmt = $pdo->prepare("
        SELECT pr.*, CONCAT(m.FirstName, ' ', m.LastName) AS FullName
        FROM progress pr
        INNER JOIN gym_member m ON pr.MemberID = m.MemberID
        WHERE pr.RecordID = ?
    ");
    $stmt->execute([$_GET['edit']]);
    $editProgress = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ==========================================
// 5. NOTIFICATIONS
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

    .progress-grid {
        flex: 1 !important;
        display: grid !important;
        grid-template-columns: 340px 1fr !important;
        gap: 18px !important;
        min-height: 0 !important;
        align-items: stretch !important;
    }
    .progress-grid > .card {
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
        Member progress recorded successfully!
    </div>
<?php elseif ($success === 'updated'): ?>
    <div style="background:rgba(22, 163, 74, 0.2); border:1px solid #22c55e; color:#86efac; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        Member progress updated successfully!
    </div>
<?php endif; ?>

<?php if ($error === 'required'): ?>
    <div style="background:rgba(220, 38, 38, 0.2); border:1px solid #ef4444; color:#fca5a5; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        Please complete all required fields.
    </div>
<?php elseif ($error === 'measurement'): ?>
    <div style="background:rgba(220, 38, 38, 0.2); border:1px solid #ef4444; color:#fca5a5; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        Height and weight must be valid positive numbers.
    </div>
<?php elseif ($error === 'date'): ?>
    <div style="background:rgba(220, 38, 38, 0.2); border:1px solid #ef4444; color:#fca5a5; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        Please enter a valid date.
    </div>
<?php elseif ($error === 'member'): ?>
    <div style="background:rgba(220, 38, 38, 0.2); border:1px solid #ef4444; color:#fca5a5; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        Member name was not found or is currently archived. Please check the spelling.
    </div>
<?php elseif ($error === 'duplicate_name'): ?>
    <div style="background:rgba(220, 38, 38, 0.2); border:1px solid #ef4444; color:#fca5a5; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        Multiple active members share that name. Please verify the member record.
    </div>
<?php elseif ($error === 'invalid'): ?>
    <div style="background:rgba(220, 38, 38, 0.2); border:1px solid #ef4444; color:#fca5a5; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        Invalid progress record.
    </div>
<?php elseif ($error === 'database'): ?>
    <div style="background:rgba(220, 38, 38, 0.2); border:1px solid #ef4444; color:#fca5a5; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        Database error. Please try again.
    </div>
<?php endif; ?>

<div class="progress-grid">
    <!-- LEFT CARD: ADD / EDIT PROGRESS FORM (USING MEMBER NAME INPUT) -->
    <div class="card" style="height: fit-content;">
        <h3 style="margin-bottom: 14px;">
            <?= $editProgress ? 'Edit Member Progress' : 'Log Member Progress' ?>
        </h3>

        <form method="POST" action="progress.php">
            <input type="hidden" name="action" value="<?= $editProgress ? 'update' : 'add' ?>">

            <?php if ($editProgress): ?>
                <input type="hidden" name="RecordID" value="<?= htmlspecialchars($editProgress['RecordID']) ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Member Name</label>
                <input
                    type="text"
                    name="MemberName"
                    list="memberNameList"
                    placeholder="e.g. Jihyo Park"
                    value="<?= htmlspecialchars($editProgress['FullName'] ?? '') ?>"
                    autocomplete="off"
                    required
                >
                <datalist id="memberNameList">
                    <?php foreach ($members as $m): ?>
                        <option value="<?= htmlspecialchars($m['FullName']) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>

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

            <div class="form-group">
                <label>Date</label>
                <input
                    type="date"
                    name="Date"
                    value="<?= htmlspecialchars($editProgress['Date'] ?? date('Y-m-d')) ?>"
                    required
                >
            </div>

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

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top: 6px; padding: 10px;">
                <?= $editProgress ? 'Update Progress' : 'Save Progress' ?>
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

    <!-- RIGHT CARD: PROGRESS TRACKING HISTORY -->
    <div class="card">
        <h3 style="margin-bottom: 12px; flex-shrink: 0;">Progress Tracking History</h3>

        <div class="search-bar">
            <input
                type="text"
                id="liveProgressSearch"
                onkeyup="filterProgressTable()"
                oninput="filterProgressTable()"
                placeholder="Search Member Name or Date..."
            >
            <button type="button" class="btn btn-primary" onclick="filterProgressTable()">
                Search
            </button>
        </div>

        <div class="fixed-table-wrap">
            <table class="fixed-table">
                <thead>
                    <tr>
                        <th style="width: 26%;">Member</th>
                        <th style="width: 16%;">Date</th>
                        <th style="width: 15%;">Height</th>
                        <th style="width: 15%;">Weight</th>
                        <th style="width: 13%;">BMI</th>
                        <th style="width: 15%;">Action</th>
                    </tr>
                </thead>
                <tbody id="progressTableBody">
                    <?php if (empty($progressList)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; color:#94a3b8; padding:18px;">
                                No progress records found.
                            </td>
                        </tr>
                    <?php else: foreach ($progressList as $pg):
                        $heightMeters = (float)$pg['Height'] / 100;
                        $bmi = $heightMeters > 0
                            ? round((float)$pg['Weight'] / ($heightMeters * $heightMeters), 1)
                            : 0;
                    ?>
                        <tr class="progress-row">
                            <td style="width: 26%;"><strong><?= htmlspecialchars($pg['FullName']) ?></strong></td>
                            <td style="width: 16%;"><?= htmlspecialchars($pg['Date']) ?></td>
                            <td style="width: 15%;"><?= htmlspecialchars($pg['Height']) ?> cm</td>
                            <td style="width: 15%;"><?= htmlspecialchars($pg['Weight']) ?> kg</td>
                            <td style="width: 13%;">
                                <span class="badge badge-green">
                                    <?= number_format($bmi, 1) ?>
                                </span>
                            </td>
                            <td style="width: 15%;">
                                <a
                                    href="progress.php?edit=<?= urlencode($pg['RecordID']) ?>"
                                    class="btn btn-warning btn-sm"
                                >
                                    Edit
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    <tr id="noProgressMatchRow" style="display: none;">
                        <td colspan="6" style="text-align:center; color:#94a3b8; padding:18px;">
                            No progress records found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function filterProgressTable() {
    const query = document.getElementById('liveProgressSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#progressTableBody .progress-row');
    const noMatchRow = document.getElementById('noProgressMatchRow');
    let visibleCount = 0;

    rows.forEach(row => {
        const memberName = row.cells[0].textContent.toLowerCase();
        const recordDate = row.cells[1].textContent.toLowerCase();
        const height     = row.cells[2].textContent.toLowerCase();
        const weight     = row.cells[3].textContent.toLowerCase();

        if (
            memberName.includes(query) ||
            recordDate.includes(query) ||
            height.includes(query) ||
            weight.includes(query)
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

document.addEventListener('DOMContentLoaded', function () {
    const heightInput = document.getElementById('height');
    const weightInput = document.getElementById('weight');
    const bmiInput    = document.getElementById('bmi');

    function calculateBMI() {
        const height = parseFloat(heightInput.value);
        const weight = parseFloat(weightInput.value);

        if (!heightInput.value || !weightInput.value || height <= 0 || weight <= 0) {
            bmiInput.value = '';
            return;
        }

        const heightMeters = height / 100;
        const bmi = weight / (heightMeters * heightMeters);
        bmiInput.value = bmi.toFixed(1);
    }

    heightInput.addEventListener('input', calculateBMI);
    weightInput.addEventListener('input', calculateBMI);
    calculateBMI();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>