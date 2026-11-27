<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Archived Members";
$successMessage = '';
$errorMessage = '';

// Handle Restore Member & Reactivate Valid Subscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_id'])) {
    $restoreId = trim($_POST['restore_id'] ?? '');

    if ($restoreId !== '') {
        $pdo->beginTransaction();
        try {
            // 1. Restore member back to active records
            $stmt = $pdo->prepare("UPDATE gym_member SET is_archived = 0 WHERE MemberID = ?");
            $stmt->execute([$restoreId]);

            // 2. Reactivate subscription if EndDate is still valid (not expired)
            $restoreSub = $pdo->prepare("
                UPDATE subscription
                SET Status = 'Active'
                WHERE MemberID = ?
                  AND EndDate >= CURDATE()
                  AND Status = 'Inactive'
            ");
            $restoreSub->execute([$restoreId]);

            $pdo->commit();
            $successMessage = "Member {$restoreId} has been restored to active records!";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errorMessage = "Failed to restore member: " . $e->getMessage();
        }
    }
}

// Fetch all archived members with their latest subscription info
$archivedMembers = $pdo->query("
    SELECT
        m.MemberID,
        m.FirstName,
        m.LastName,
        m.DateOfBirth,
        m.PhoneNumber,
        s.Status AS SubStatus,
        s.EndDate,
        mp.PlanName
    FROM gym_member m
    LEFT JOIN subscription s ON s.SubscriptionID = (
        SELECT s2.SubscriptionID
        FROM subscription s2
        WHERE s2.MemberID = m.MemberID
        ORDER BY s2.StartDate DESC, s2.SubscriptionID DESC
        LIMIT 1
    )
    LEFT JOIN membership_plan mp ON s.PlanID = mp.PlanID
    WHERE m.is_archived = 1
    ORDER BY m.MemberID ASC
")->fetchAll(PDO::FETCH_ASSOC);

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

    .archive-card {
        flex: 1 !important;
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
        padding: 11px 12px;
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

<?php if ($successMessage !== ''): ?>
    <div style="background:rgba(22, 163, 74, 0.2); border:1px solid #22c55e; color:#86efac; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        ✓ <?= htmlspecialchars($successMessage) ?>
    </div>
<?php endif; ?>

<?php if ($errorMessage !== ''): ?>
    <div style="background:rgba(220, 38, 38, 0.2); border:1px solid #ef4444; color:#fca5a5; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        ✕ <?= htmlspecialchars($errorMessage) ?>
    </div>
<?php endif; ?>

<div class="card archive-card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-shrink:0;">
        <h3 style="margin:0;">Archived Gym Members (<span id="archivedCount"><?= count($archivedMembers) ?></span> Records)</h3>
        <a href="/fitmate/members/records.php" class="btn btn-primary btn-sm">Back to Active Records</a>
    </div>

    <div class="search-bar">
        <input
            type="text"
            id="liveArchiveSearch"
            onkeyup="filterArchiveTable()"
            oninput="filterArchiveTable()"
            placeholder="Search by Member ID, Full Name, Phone, or Plan..."
        >
        <button type="button" class="btn btn-primary" onclick="filterArchiveTable()">Search</button>
    </div>

    <div class="fixed-table-wrap">
        <table class="fixed-table">
            <thead>
                <tr>
                    <th style="width: 14%;">Member ID</th>
                    <th style="width: 24%;">Full Name</th>
                    <th style="width: 16%;">Phone</th>
                    <th style="width: 16%;">Date of Birth</th>
                    <th style="width: 16%;">Last Plan</th>
                    <th style="width: 14%;">Action</th>
                </tr>
            </thead>
            <tbody id="archiveTableBody">
                <?php if (empty($archivedMembers)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; color:#94a3b8; padding:18px;">
                            No archived members found.
                        </td>
                    </tr>
                <?php else: foreach ($archivedMembers as $m): ?>
                    <tr class="archive-row">
                        <td style="width: 14%;"><strong><?= htmlspecialchars($m['MemberID']) ?></strong></td>
                        <td style="width: 24%;"><?= htmlspecialchars($m['FirstName'] . ' ' . $m['LastName']) ?></td>
                        <td style="width: 16%;"><?= htmlspecialchars($m['PhoneNumber'] ?? '-') ?></td>
                        <td style="width: 16%;"><?= htmlspecialchars($m['DateOfBirth'] ?? '-') ?></td>
                        <td style="width: 16%;"><?= htmlspecialchars($m['PlanName'] ?? 'None') ?></td>
                        <td style="width: 14%;">
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Restore this member back to active records?');">
                                <input type="hidden" name="restore_id" value="<?= htmlspecialchars($m['MemberID']) ?>">
                                <button type="submit" class="btn btn-primary btn-sm">Restore</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                <tr id="noArchiveMatchRow" style="display: none;">
                    <td colspan="6" style="text-align:center; color:#94a3b8; padding:18px;">
                        No matching archived members found.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
function filterArchiveTable() {
    const query = document.getElementById('liveArchiveSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#archiveTableBody .archive-row');
    const noMatchRow = document.getElementById('noArchiveMatchRow');
    let visibleCount = 0;

    rows.forEach(row => {
        const memberId = row.cells[0].textContent.toLowerCase();
        const fullName = row.cells[1].textContent.toLowerCase();
        const phone    = row.cells[2].textContent.toLowerCase();
        const dob      = row.cells[3].textContent.toLowerCase();
        const plan     = row.cells[4].textContent.toLowerCase();

        if (
            memberId.includes(query) ||
            fullName.includes(query) ||
            phone.includes(query) ||
            dob.includes(query) ||
            plan.includes(query)
        ) {
            row.style.display = 'table';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    document.getElementById('archivedCount').textContent = visibleCount;
    if (noMatchRow) {
        noMatchRow.style.display = (rows.length > 0 && visibleCount === 0) ? 'table' : 'none';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>