<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Gym Member Records";
$feedback = '';
$feedbackType = 'ok';

// Soft Delete (move to Archives)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'soft_delete') {
    $memberId = trim($_POST['MemberID'] ?? '');
    $stmt = $pdo->prepare("UPDATE gym_member SET is_archived = 1 WHERE MemberID = ?");
    if ($stmt->execute([$memberId])) {
        $feedback = "Member {$memberId} moved to Archives.";
        $feedbackType = 'ok';
    }
}

// Update Member & Emergency Contact
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_member') {
    $memberId  = trim($_POST['MemberID'] ?? '');
    $firstName = trim($_POST['FirstName'] ?? '');
    $lastName  = trim($_POST['LastName'] ?? '');
    $dob       = trim($_POST['DateOfBirth'] ?? '');
    $phone     = trim($_POST['PhoneNumber'] ?? '');

    $contactId    = trim($_POST['ContactID'] ?? '');
    $contactName  = trim($_POST['ContactName'] ?? '');
    $relationship = trim($_POST['Relationship'] ?? '');
    $contactPhone = trim($_POST['ContactPhone'] ?? '');

    $pdo->beginTransaction();
    try {
        $stmt1 = $pdo->prepare("
            UPDATE gym_member
            SET FirstName = ?, LastName = ?, DateOfBirth = ?, PhoneNumber = ?
            WHERE MemberID = ?
        ");
        $stmt1->execute([$firstName, $lastName, $dob, $phone, $memberId]);

        if ($contactId !== '') {
            $stmt2 = $pdo->prepare("
                UPDATE emergency_contact
                SET ContactName = ?, Relationship = ?, PhoneNumber = ?
                WHERE ContactID = ? AND MemberID = ?
            ");
            $stmt2->execute([$contactName, $relationship, $contactPhone, $contactId, $memberId]);
        } else {
            // Auto-generate next ContactID (e.g., EMG401, EMG402...)
            $lastContact = $pdo->query("
                SELECT ContactID FROM emergency_contact
                WHERE ContactID REGEXP '^EMG[0-9]+$'
                ORDER BY CAST(SUBSTRING(ContactID, 4) AS UNSIGNED) DESC
                LIMIT 1
            ")->fetchColumn();
            $nextNum = $lastContact ? ((int)substr($lastContact, 3) + 1) : 401;
            $newContactId = 'EMG' . $nextNum;

            $stmt2 = $pdo->prepare("
                INSERT INTO emergency_contact (ContactID, MemberID, ContactName, Relationship, PhoneNumber)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt2->execute([$newContactId, $memberId, $contactName, $relationship, $contactPhone]);
        }

        $pdo->commit();
        $feedback = "Updated member {$memberId} and emergency contact.";
        $feedbackType = 'ok';
    } catch (Exception $e) {
        $pdo->rollBack();
        $feedback = "Update failed: " . $e->getMessage();
        $feedbackType = 'err';
    }
}

// Fetch active (non-archived) members with latest subscription & emergency contact
$baseQuery = "
    SELECT
        m.MemberID, m.FirstName, m.LastName, m.DateOfBirth, m.PhoneNumber,
        ec.ContactID, ec.ContactName, ec.Relationship, ec.PhoneNumber AS ContactPhone,
        s.Status AS SubStatus, s.EndDate,
        mp.PlanName
    FROM gym_member m
    LEFT JOIN emergency_contact ec ON m.MemberID = ec.MemberID
    LEFT JOIN subscription s ON s.SubscriptionID = (
        SELECT s2.SubscriptionID
        FROM subscription s2
        WHERE s2.MemberID = m.MemberID
        ORDER BY s2.StartDate DESC, s2.SubscriptionID DESC
        LIMIT 1
    )
    LEFT JOIN membership_plan mp ON s.PlanID = mp.PlanID
    WHERE m.is_archived = 0
    ORDER BY m.MemberID ASC
";

$stmt = $pdo->prepare($baseQuery);
$stmt->execute();
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Load member for editing if requested
$editMember = null;
if (isset($_GET['edit']) && $_GET['edit'] !== '') {
    $editId = trim($_GET['edit']);
    $stmtEdit = $pdo->prepare("
        SELECT m.*, ec.ContactID, ec.ContactName, ec.Relationship, ec.PhoneNumber AS ContactPhone
        FROM gym_member m
        LEFT JOIN emergency_contact ec ON m.MemberID = ec.MemberID
        WHERE m.MemberID = ? AND m.is_archived = 0
    ");
    $stmtEdit->execute([$editId]);
    $editMember = $stmtEdit->fetch(PDO::FETCH_ASSOC);
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
        padding: 16px 22px !important;
        gap: 14px !important;
    }

    .records-layout {
        display: grid;
        grid-template-columns: <?= $editMember ? '370px 1fr' : '1fr' ?>;
        gap: 16px;
        flex: 1;
        min-height: 0;
        align-items: stretch;
    }
    .records-layout > .card {
        height: 100% !important;
        margin-bottom: 0 !important;
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
    }
    .edit-form-scroll {
        flex: 1;
        overflow-y: auto;
        padding-right: 4px;
    }

    /* Consistent Black & Blue Search Bar Styling */
    .search-bar {
        display: flex;
        gap: 10px;
        margin-bottom: 14px;
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
        border-bottom: 2px solid #2563eb;
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
        padding: 11px 10px;
        text-align: left;
        font-size: 12px;
        font-weight: 700;
        color: #60a5fa;
        background: #111c35;
    }
    .fixed-table td {
        padding: 10px 10px;
        font-size: 13px;
        color: #f1f5f9;
        border-bottom: 1px solid #1e293b;
        vertical-align: middle;
    }
    .fixed-table tbody tr:hover {
        background: rgba(37, 99, 235, 0.08);
    }

    .action-group {
        display: flex;
        gap: 5px;
        flex-wrap: nowrap;
        align-items: center;
    }
</style>

<?php if ($feedback !== ''): ?>
    <div style="background:<?= $feedbackType === 'ok' ? 'rgba(22, 163, 74, 0.2)' : 'rgba(220, 38, 38, 0.2)' ?>;
                border:1px solid <?= $feedbackType === 'ok' ? '#22c55e' : '#ef4444' ?>;
                color:<?= $feedbackType === 'ok' ? '#86efac' : '#fca5a5' ?>;
                padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        <?= htmlspecialchars($feedback) ?>
    </div>
<?php endif; ?>

<div class="records-layout">
    <?php if ($editMember): ?>
        <!-- LEFT CARD: EDIT MEMBER & EMERGENCY CONTACT FORM -->
        <div class="card">
            <h3 style="margin-bottom: 12px; flex-shrink: 0; color: #60a5fa;">
                Edit Member (<?= htmlspecialchars($editMember['MemberID']) ?>)
            </h3>

            <div class="edit-form-scroll">
                <form method="POST" action="records.php">
                    <input type="hidden" name="action" value="update_member">
                    <input type="hidden" name="MemberID" value="<?= htmlspecialchars($editMember['MemberID']) ?>">
                    <input type="hidden" name="ContactID" value="<?= htmlspecialchars($editMember['ContactID'] ?? '') ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" name="FirstName" value="<?= htmlspecialchars($editMember['FirstName']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" name="LastName" value="<?= htmlspecialchars($editMember['LastName']) ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="DateOfBirth" value="<?= htmlspecialchars($editMember['DateOfBirth'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="PhoneNumber" value="<?= htmlspecialchars($editMember['PhoneNumber'] ?? '') ?>" required>
                        </div>
                    </div>

                    <hr style="border: 0; border-top: 1px solid #1e293b; margin: 12px 0;">
                    <h4 style="margin-bottom: 10px; font-size: 13.5px; color: #60a5fa;">Emergency Contact</h4>

                    <div class="form-group">
                        <label>Contact Name</label>
                        <input type="text" name="ContactName" value="<?= htmlspecialchars($editMember['ContactName'] ?? '') ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Relationship</label>
                            <input type="text" name="Relationship" value="<?= htmlspecialchars($editMember['Relationship'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Contact Phone</label>
                            <input type="text" name="ContactPhone" value="<?= htmlspecialchars($editMember['ContactPhone'] ?? '') ?>" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%; margin-top:6px;">
                        Save Changes
                    </button>
                    <a href="records.php" class="btn btn-warning" style="display:block; text-align:center; margin-top:8px;">
                        Cancel Edit
                    </a>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- RIGHT / MAIN CARD: GYM MEMBER RECORDS TABLE -->
    <div class="card">
        <h3 style="margin-bottom: 12px; flex-shrink: 0;">
            Gym Member Records (<span id="memberCount"><?= count($members) ?></span> Active Members)
        </h3>

        <div class="search-bar">
            <input
                type="text"
                id="liveMemberSearch"
                onkeyup="filterMemberTable()"
                oninput="filterMemberTable()"
                placeholder="Search by Member ID, Name, Phone, Plan, or Status..."
            >
            <button type="button" class="btn btn-primary" onclick="filterMemberTable()">Search</button>
        </div>

        <div class="fixed-table-wrap">
            <table class="fixed-table">
                <thead>
                    <tr>
                        <th style="width: 10%;">Member ID</th>
                        <th style="width: 18%;">Full Name</th>
                        <th style="width: 13%;">Phone</th>
                        <th style="width: 19%;">Emergency Contact</th>
                        <th style="width: 14%;">Current Plan</th>
                        <th style="width: 10%;">Status</th>
                        <th style="width: 16%;">Actions</th>
                    </tr>
                </thead>
                <tbody id="memberTableBody">
                    <?php if (empty($members)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; color:#94a3b8; padding:18px;">
                                No active member records found.
                            </td>
                        </tr>
                    <?php else: foreach ($members as $m):
                        $today = date('Y-m-d');
                        $statusText = 'No Plan';
                        $badgeClass = 'badge';
                        if (!empty($m['PlanName'])) {
                            if (!empty($m['EndDate']) && $m['EndDate'] < $today) {
                                $statusText = 'Expired';
                                $badgeClass = 'badge badge-red';
                            } else {
                                $statusText = $m['SubStatus'] ?: 'Active';
                                $badgeClass = ($statusText === 'Active') ? 'badge badge-green' : 'badge badge-amber';
                            }
                        }
                    ?>
                        <tr class="member-row">
                            <td style="width: 10%;"><strong><?= htmlspecialchars($m['MemberID']) ?></strong></td>
                            <td style="width: 18%;"><?= htmlspecialchars($m['FirstName'] . ' ' . $m['LastName']) ?></td>
                            <td style="width: 13%;"><?= htmlspecialchars($m['PhoneNumber'] ?? '-') ?></td>
                            <td style="width: 19%;">
                                <?php if (!empty($m['ContactName'])): ?>
                                    <div><?= htmlspecialchars($m['ContactName']) ?> <small style="color:#94a3b8;">(<?= htmlspecialchars($m['Relationship'] ?? '-') ?>)</small></div>
                                    <small style="color:#94a3b8;"><?= htmlspecialchars($m['ContactPhone'] ?? '') ?></small>
                                <?php else: ?>
                                    <span style="color:#64748b;">None</span>
                                <?php endif; ?>
                            </td>
                            <td style="width: 14%;"><?= htmlspecialchars($m['PlanName'] ?? '-') ?></td>
                            <td style="width: 10%;"><span class="<?= $badgeClass ?>"><?= htmlspecialchars($statusText) ?></span></td>
                            <td style="width: 16%;">
                                <div class="action-group">
                                    <a href="profile.php?id=<?= urlencode($m['MemberID']) ?>" class="btn btn-primary btn-sm">Profile</a>
                                    <a href="records.php?edit=<?= urlencode($m['MemberID']) ?>" class="btn btn-warning btn-sm">Edit</a>
                                    <form method="POST" style="margin:0;" onsubmit="return confirm('Move <?= htmlspecialchars($m['MemberID']) ?> to Archives?');">
                                        <input type="hidden" name="action" value="soft_delete">
                                        <input type="hidden" name="MemberID" value="<?= htmlspecialchars($m['MemberID']) ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Archive</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    <tr id="noMemberMatchRow" style="display: none;">
                        <td colspan="7" style="text-align:center; color:#94a3b8; padding:18px;">
                            No matching member records found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function filterMemberTable() {
    const query = document.getElementById('liveMemberSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#memberTableBody .member-row');
    const noMatchRow = document.getElementById('noMemberMatchRow');
    let visibleCount = 0;

    rows.forEach(row => {
        const memberId   = row.cells[0].textContent.toLowerCase();
        const fullName   = row.cells[1].textContent.toLowerCase();
        const phone      = row.cells[2].textContent.toLowerCase();
        const emergency  = row.cells[3].textContent.toLowerCase();
        const plan       = row.cells[4].textContent.toLowerCase();
        const status     = row.cells[5].textContent.toLowerCase();

        if (
            memberId.includes(query) ||
            fullName.includes(query) ||
            phone.includes(query) ||
            emergency.includes(query) ||
            plan.includes(query) ||
            status.includes(query)
        ) {
            row.style.display = 'table';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    document.getElementById('memberCount').textContent = visibleCount;
    if (noMatchRow) {
        noMatchRow.style.display = (rows.length > 0 && visibleCount === 0) ? 'table' : 'none';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
