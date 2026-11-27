<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Manila');

$page_title = "Program Applications";
$msg = '';
$err = '';

// Show flash message after redirect (prevents form re-submit on refresh)
if (!empty($_SESSION['app_flash'])) {
    [$type, $text] = $_SESSION['app_flash'];
    if ($type === 'ok') $msg = $text; else $err = $text;
    unset($_SESSION['app_flash']);
}

// 1. SUBMIT NEW PROGRAM APPLICATION (By Typing Member Name)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply_program') {
    $memberNameInput = trim(preg_replace('/\s+/', ' ', $_POST['member_name'] ?? ''));
    $programId       = trim($_POST['program_id'] ?? '');

    if ($memberNameInput === '' || $programId === '') {
        $err = "Please enter the Member Name and select a Program.";
    } else {
        try {
            // Find active member by exact Full Name (case-insensitive)
            $chk = $pdo->prepare("
                SELECT MemberID, CONCAT(FirstName, ' ', LastName) AS FullName
                FROM gym_member
                WHERE is_archived = 0
                  AND LOWER(TRIM(CONCAT(FirstName, ' ', LastName))) = LOWER(?)
            ");
            $chk->execute([$memberNameInput]);
            $matchedMembers = $chk->fetchAll();

            $pchk = $pdo->prepare("SELECT ProgramName FROM program WHERE ProgramID = ?");
            $pchk->execute([$programId]);
            $programName = $pchk->fetchColumn();

            if (count($matchedMembers) === 0) {
                $err = "Member \"{$memberNameInput}\" was not found or is currently archived. Please check the spelling.";
            } elseif (count($matchedMembers) > 1) {
                $err = "Multiple active members have the name \"{$memberNameInput}\". Please verify the member record.";
            } elseif (!$programName) {
                $err = "Selected program does not exist.";
            } else {
                $memberId = $matchedMembers[0]['MemberID'];
                $fullName = $matchedMembers[0]['FullName'];

                $dup = $pdo->prepare("
                    SELECT 1 FROM application
                    WHERE MemberID = ? AND ProgramID = ? AND Status IN ('Pending','Approved')
                    LIMIT 1
                ");
                $dup->execute([$memberId, $programId]);

                if ($dup->fetchColumn()) {
                    $err = "{$fullName} already has a Pending or Approved application for {$programName}.";
                } else {
                    // Generate next ApplicationID (e.g., APP320)
                    $lastApp = $pdo->query("SELECT ApplicationID FROM application ORDER BY CAST(SUBSTRING(ApplicationID, 4) AS UNSIGNED) DESC LIMIT 1")->fetchColumn();
                    if ($lastApp && preg_match('/(\d+)$/', $lastApp, $matches)) {
                        $nextNum = (int)$matches[1] + 1;
                        $newAppId = 'APP' . str_pad((string)$nextNum, 3, '0', STR_PAD_LEFT);
                    } else {
                        $newAppId = 'APP301';
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO application (ApplicationID, MemberID, ProgramID, ApplicationDate, Status)
                        VALUES (?, ?, ?, CURDATE(), 'Pending')
                    ");
                    $stmt->execute([$newAppId, $memberId, $programId]);
                    $_SESSION['app_flash'] = ['ok', "Program application submitted for {$fullName} ({$programName})!"];
                    header('Location: applications.php');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $err = "Error submitting application: " . $e->getMessage();
        }
    }
}

// 2. UPDATE APPLICATION STATUS (Approve, Cancel, Rejected)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $appId     = $_POST['application_id'] ?? '';
    $newStatus = $_POST['new_status'] ?? '';

    if (in_array($newStatus, ['Approved', 'Cancelled', 'Rejected'], true)) {
        try {
            $stmt = $pdo->prepare("UPDATE application SET Status = ? WHERE ApplicationID = ? AND Status = 'Pending'");
            $stmt->execute([$newStatus, $appId]);
            $_SESSION['app_flash'] = $stmt->rowCount()
                ? ['ok', "Application status updated to {$newStatus}!"]
                : ['err', "Application is no longer Pending, so it can't be changed."];
            header('Location: applications.php');
            exit;
        } catch (PDOException $e) {
            $err = "Error updating status: " . $e->getMessage();
        }
    }
}

// 3. FETCH ACTIVE MEMBERS (for input autocomplete suggestions) & PROGRAMS
$activeMembers = $pdo->query("
    SELECT CONCAT(FirstName, ' ', LastName) AS FullName
    FROM gym_member
    WHERE is_archived = 0
    ORDER BY FirstName ASC, LastName ASC
")->fetchAll();

$programs = $pdo->query("SELECT ProgramID, ProgramName FROM program ORDER BY ProgramName ASC")->fetchAll();

// 4. FETCH PROGRAM APPLICATION LIST
$applications = $pdo->query("
    SELECT a.ApplicationID, a.MemberID, a.ApplicationDate, a.Status,
           CONCAT(m.FirstName, ' ', m.LastName) AS MemberName,
           p.ProgramName
    FROM application a
    JOIN gym_member m ON a.MemberID = m.MemberID
    JOIN program p ON a.ProgramID = p.ProgramID
    ORDER BY CAST(SUBSTRING(a.ApplicationID, 4) AS UNSIGNED) DESC
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
        padding: 16px 22px !important;
        gap: 14px !important;
    }

    .apps-grid {
        flex: 1 !important;
        display: grid !important;
        grid-template-columns: 340px 1fr !important;
        gap: 18px !important;
        min-height: 0 !important;
        align-items: stretch !important;
    }
    .apps-grid > .card {
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

    /* FIXED TABLE HEADER + SCROLLABLE TBODY */
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
        padding: 9px 12px;
        font-size: 13px;
        color: #f1f5f9;
        border-bottom: 1px solid #1e293b;
        vertical-align: middle;
    }
    .fixed-table tbody tr:hover {
        background: rgba(37, 99, 235, 0.08);
    }

    /* Status Badges (Approved = Green, Pending = Amber, Cancelled = Gray, Rejected = RED) */
    .badge-red {
        background: rgba(220, 38, 38, 0.22) !important;
        color: #fca5a5 !important;
        border: 1px solid #ef4444 !important;
    }
    .badge-gray {
        background: rgba(100, 116, 139, 0.22) !important;
        color: #cbd5e1 !important;
        border: 1px solid #64748b !important;
    }

    /* Action Buttons sa loob ng table */
    .action-btns {
        display: flex;
        gap: 4px;
        flex-wrap: nowrap;
    }
    .btn-xs {
        padding: 4px 7px;
        font-size: 11px;
        font-weight: 600;
        border-radius: 5px;
        border: none;
        cursor: pointer;
        color: #ffffff;
    }
    .btn-approve  { background: #16a34a; }
    .btn-approve:hover  { background: #15803d; }
    .btn-cancel   { background: #475569; }
    .btn-cancel:hover   { background: #334155; }
    .btn-rejected { background: #dc2626; }
    .btn-rejected:hover { background: #b91c1c; }
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

<div class="apps-grid">
    <!-- LEFT CARD: APPLY MEMBER TO PROGRAM (TEXT INPUT WITH AUTOCOMPLETE) -->
    <div class="card" style="height: fit-content;">
        <h3 style="margin-bottom: 14px;">Apply Member to Program</h3>
        <form method="POST">
            <input type="hidden" name="action" value="apply_program">
            <div class="form-group">
                <label>Member Name</label>
                <input
                    type="text"
                    name="member_name"
                    list="memberNamesList"
                    value="<?= htmlspecialchars($_POST['member_name'] ?? '') ?>"
                    required
                    placeholder="e.g. Wonho Lee"
                    autocomplete="off"
                >
                <datalist id="memberNamesList">
                    <?php foreach ($activeMembers as $mem): ?>
                        <option value="<?= htmlspecialchars($mem['FullName']) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="form-group">
                <label>Select Program (Live from DB)</label>
                <select name="program_id" required>
                    <option value="" disabled <?= empty($_POST['program_id']) ? 'selected' : '' ?>>-- Select a program --</option>
                    <?php foreach ($programs as $prog): ?>
                        <option value="<?= htmlspecialchars($prog['ProgramID']) ?>" <?= (($_POST['program_id'] ?? '') === $prog['ProgramID']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($prog['ProgramName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; margin-top: 6px; padding: 10px;">
                Submit Application
            </button>
        </form>
    </div>

    <!-- RIGHT CARD: PROGRAM APPLICATION LIST (NO APP ID COLUMN) -->
    <div class="card">
        <h3 style="margin-bottom: 12px; flex-shrink: 0;">Program Application List</h3>

        <div class="search-bar">
            <input
                type="text"
                id="liveAppSearch"
                onkeyup="filterApplicationsTable()"
                oninput="filterApplicationsTable()"
                placeholder="Search by Member Name, Program, Date Applied, or Status..."
            >
            <button type="button" class="btn btn-primary" onclick="filterApplicationsTable()">Search</button>
        </div>

        <div class="fixed-table-wrap">
            <table class="fixed-table">
                <thead>
                    <tr>
                        <th style="width: 22%;">Member</th>
                        <th style="width: 30%;">Program</th>
                        <th style="width: 14%;">Date Applied</th>
                        <th style="width: 12%;">Status</th>
                        <th style="width: 22%;">Action</th>
                    </tr>
                </thead>
                <tbody id="appTableBody">
                    <?php if (empty($applications)): ?>
                        <tr><td colspan="5" style="text-align:center; color:#94a3b8; padding:18px;">No program applications found.</td></tr>
                    <?php else: foreach ($applications as $app):
                        $status = $app['Status'] ?? 'Pending';
                        $badgeClass = 'badge badge-amber'; // Pending
                        if ($status === 'Approved') {
                            $badgeClass = 'badge badge-green';
                        } elseif ($status === 'Rejected') {
                            $badgeClass = 'badge badge-red';
                        } elseif ($status === 'Cancelled') {
                            $badgeClass = 'badge badge-gray';
                        }
                    ?>
                        <tr class="app-row" data-member-id="<?= htmlspecialchars(strtolower($app['MemberID'])) ?>">
                            <td style="width: 22%;"><?= htmlspecialchars($app['MemberName']) ?></td>
                            <td style="width: 30%;"><?= htmlspecialchars($app['ProgramName']) ?></td>
                            <td style="width: 14%;"><?= htmlspecialchars($app['ApplicationDate']) ?></td>
                            <td style="width: 12%;"><span class="<?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span></td>
                            <td style="width: 22%;">
                                <?php if ($status !== 'Pending'): ?>
                                    <span style="opacity:.5;">Done</span>
                                <?php else: ?>
                                <div class="action-btns">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="application_id" value="<?= htmlspecialchars($app['ApplicationID']) ?>">
                                        <input type="hidden" name="new_status" value="Approved">
                                        <button type="submit" class="btn-xs btn-approve">Approve</button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="application_id" value="<?= htmlspecialchars($app['ApplicationID']) ?>">
                                        <input type="hidden" name="new_status" value="Cancelled">
                                        <button type="submit" class="btn-xs btn-cancel">Cancel</button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="application_id" value="<?= htmlspecialchars($app['ApplicationID']) ?>">
                                        <input type="hidden" name="new_status" value="Rejected">
                                        <button type="submit" class="btn-xs btn-rejected">Reject</button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    <tr id="noAppMatchRow" style="display: none;">
                        <td colspan="5" style="text-align:center; color:#94a3b8; padding:18px;">No program applications found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function filterApplicationsTable() {
    const query = document.getElementById('liveAppSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#appTableBody .app-row');
    const noMatchRow = document.getElementById('noAppMatchRow');
    let visibleCount = 0;

    rows.forEach(row => {
        const memberName  = row.cells[0].textContent.toLowerCase();
        const programName = row.cells[1].textContent.toLowerCase();
        const dateApplied = row.cells[2].textContent.toLowerCase();
        const status      = row.cells[3].textContent.toLowerCase();
        const memberId    = (row.getAttribute('data-member-id') || '').toLowerCase();

        if (
            memberId.includes(query) ||
            memberName.includes(query) ||
            programName.includes(query) ||
            dateApplied.includes(query) ||
            status.includes(query)
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