<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Membership Plans";
$success_msg = "";
$error_msg = "";

// Handle Add New Plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_plan') {
    $plan_name     = trim($_POST['plan_name'] ?? '');
    $duration_days = (int)($_POST['duration_days'] ?? 0);
    $price         = (float)($_POST['price'] ?? 0);

    if ($plan_name === '' || $duration_days <= 0 || $price <= 0) {
        $error_msg = "Please provide a valid plan name, duration (days), and price.";
    } else {
        try {
            // Generate next PlanID (e.g., PLN05)
            $lastPlan = $pdo->query("SELECT PlanID FROM membership_plan ORDER BY CAST(SUBSTRING(PlanID, 4) AS UNSIGNED) DESC LIMIT 1")->fetchColumn();
            if ($lastPlan && preg_match('/^([A-Za-z]+)(\d+)$/', $lastPlan, $m)) {
                $nextNum = str_pad((int)$m[2] + 1, strlen($m[2]), '0', STR_PAD_LEFT);
                $newPlanId = $m[1] . $nextNum;
            } else {
                $newPlanId = 'PLN01';
            }

            $stmt = $pdo->prepare("INSERT INTO membership_plan (PlanID, PlanName, DurationDays, Price) VALUES (?, ?, ?, ?)");
            $stmt->execute([$newPlanId, $plan_name, $duration_days, $price]);
            $success_msg = "Membership plan '{$plan_name}' added successfully.";
        } catch (Exception $e) {
            $error_msg = "Could not add plan: " . $e->getMessage();
        }
    }
}

// Handle Update/Edit Plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_plan') {
    $plan_id       = trim($_POST['plan_id'] ?? '');
    $plan_name     = trim($_POST['plan_name'] ?? '');
    $duration_days = (int)($_POST['duration_days'] ?? 0);
    $price         = (float)($_POST['price'] ?? 0);

    if ($plan_id === '' || $plan_name === '' || $duration_days <= 0 || $price <= 0) {
        $error_msg = "All fields are required when updating a plan.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE membership_plan SET PlanName = ?, DurationDays = ?, Price = ? WHERE PlanID = ?");
            $stmt->execute([$plan_name, $duration_days, $price, $plan_id]);
            $success_msg = "Membership plan '{$plan_name}' updated successfully.";
        } catch (Exception $e) {
            $error_msg = "Could not update plan: " . $e->getMessage();
        }
    }
}

// Search Plans
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare("SELECT * FROM membership_plan WHERE PlanName LIKE ? OR PlanID LIKE ? ORDER BY Price ASC");
    $like = "%{$search}%";
    $stmt->execute([$like, $like]);
    $plans = $stmt->fetchAll();
} else {
    $plans = $pdo->query("SELECT * FROM membership_plan ORDER BY Price ASC")->fetchAll();
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

    .plans-grid {
        flex: 1 !important;
        display: grid !important;
        grid-template-columns: 340px 1fr !important;
        gap: 18px !important;
        min-height: 0 !important;
        align-items: stretch !important;
    }
    .plans-grid > .card {
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

    /* FIXED TABLE HEADER + SCROLLABLE TBODY (Matches Program Applications style) */
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

<?php if ($success_msg): ?>
    <div style="background:rgba(22, 163, 74, 0.2); border:1px solid #22c55e; color:#86efac; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        <?= htmlspecialchars($success_msg) ?>
    </div>
<?php endif; ?>
<?php if ($error_msg): ?>
    <div style="background:rgba(220, 38, 38, 0.2); border:1px solid #ef4444; color:#fca5a5; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        <?= htmlspecialchars($error_msg) ?>
    </div>
<?php endif; ?>

<div class="plans-grid">
    <!-- LEFT CARD: ADD MEMBERSHIP PLAN -->
    <div class="card" style="height: fit-content;">
        <h3 style="margin-bottom: 14px;">Add Membership Plan</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_plan">
            <div class="form-group">
                <label>Plan Name</label>
                <input type="text" name="plan_name" placeholder="e.g. Monthly Basic" required>
            </div>
            <div class="form-group">
                <label>Duration (Days)</label>
                <input type="number" name="duration_days" min="1" placeholder="30" required>
            </div>
            <div class="form-group">
                <label>Price (₱)</label>
                <input type="number" name="price" step="0.01" min="1" placeholder="1500.00" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; margin-top: 6px; padding: 10px;">
                Save Plan
            </button>
        </form>
    </div>

    <!-- RIGHT CARD: AVAILABLE MEMBERSHIP PLANS -->
    <div class="card">
        <h3 style="margin-bottom: 12px; flex-shrink: 0;">Available Membership Plans</h3>

        <form method="GET" class="search-bar" onsubmit="event.preventDefault(); filterPlansTable();">
            <input
                type="text"
                id="livePlanSearch"
                name="search"
                value="<?= htmlspecialchars($search) ?>"
                onkeyup="filterPlansTable()"
                oninput="filterPlansTable()"
                placeholder="Search plan by name, duration, or price..."
            >
            <button type="button" class="btn btn-primary" onclick="filterPlansTable()">Search</button>
        </form>

        <div class="fixed-table-wrap">
            <table class="fixed-table">
                <thead>
                    <tr>
                        <th style="width: 36%;">Plan Name</th>
                        <th style="width: 22%;">Duration</th>
                        <th style="width: 24%;">Price</th>
                        <th style="width: 18%;">Action</th>
                    </tr>
                </thead>
                <tbody id="planTableBody">
                    <?php if (empty($plans)): ?>
                        <tr>
                            <td colspan="4" style="text-align:center; color:#94a3b8; padding:18px;">
                                No membership plans found.
                            </td>
                        </tr>
                    <?php else: foreach ($plans as $p): ?>
                        <tr class="plan-row">
                            <td style="width: 36%;"><strong><?= htmlspecialchars($p['PlanName']) ?></strong></td>
                            <td style="width: 22%;"><?= (int)$p['DurationDays'] ?> Days</td>
                            <td style="width: 24%;">₱<?= number_format($p['Price'] ?? 0, 2) ?></td>
                            <td style="width: 18%;">
                                <button
                                    type="button"
                                    class="btn btn-warning btn-sm"
                                    onclick="openEditPlanModal(
                                        '<?= htmlspecialchars($p['PlanID'], ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($p['PlanName'], ENT_QUOTES) ?>',
                                        '<?= (int)$p['DurationDays'] ?>',
                                        '<?= (float)$p['Price'] ?>'
                                    )"
                                >
                                    Edit
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    <tr id="noPlanMatchRow" style="display: none;">
                        <td colspan="4" style="text-align:center; color:#94a3b8; padding:18px;">
                            No membership plans found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Plan Pop-Up Modal -->
<div id="editPlanModal" class="modal-backdrop">
    <div class="modal-box" style="max-width:440px;">
        <div class="modal-header">
            <h3 style="margin:0;">Edit Membership Plan</h3>
            <button type="button" class="modal-close" onclick="closeEditPlanModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_plan">
            <input type="hidden" name="plan_id" id="edit_plan_id">

            <div class="form-group">
                <label>Plan Name</label>
                <input type="text" name="plan_name" id="edit_plan_name" required>
            </div>
            <div class="form-group">
                <label>Duration (Days)</label>
                <input type="number" name="duration_days" id="edit_duration_days" min="1" required>
            </div>
            <div class="form-group">
                <label>Price (₱)</label>
                <input type="number" name="price" id="edit_price" step="0.01" min="1" required>
            </div>

            <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-warning" style="flex:1;" onclick="closeEditPlanModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:2;">Update Plan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditPlanModal(id, name, days, price) {
    document.getElementById('edit_plan_id').value = id;
    document.getElementById('edit_plan_name').value = name;
    document.getElementById('edit_duration_days').value = days;
    document.getElementById('edit_price').value = price;
    document.getElementById('editPlanModal').classList.add('open');
}
function closeEditPlanModal() {
    document.getElementById('editPlanModal').classList.remove('open');
}
document.getElementById('editPlanModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditPlanModal();
});

function filterPlansTable() {
    const query = document.getElementById('livePlanSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#planTableBody .plan-row');
    const noMatchRow = document.getElementById('noPlanMatchRow');
    let visibleCount = 0;

    rows.forEach(row => {
        const planName = row.cells[0].textContent.toLowerCase();
        const duration = row.cells[1].textContent.toLowerCase();
        const price    = row.cells[2].textContent.toLowerCase();

        if (
            planName.includes(query) ||
            duration.includes(query) ||
            price.includes(query)
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