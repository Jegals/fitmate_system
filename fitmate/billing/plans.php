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
            // Generate next PlanID (e.g., PLN04)
            $lastPlan = $pdo->query("SELECT PlanID FROM membership_plan ORDER BY PlanID DESC LIMIT 1")->fetchColumn();
            if ($lastPlan && preg_match('/^([A-Za-z]+)(\d+)$/', $lastPlan, $m)) {
                $nextNum = str_pad((int)$m[2] + 1, strlen($m[2]), '0', STR_PAD_LEFT);
                $newPlanId = $m[1] . $nextNum;
            } else {
                $newPlanId = 'PLN' . rand(10, 99);
            }

            $stmt = $pdo->prepare("INSERT INTO membership_plan (PlanID, PlanName, DurationDays, Price) VALUES (?, ?, ?, ?)");
            $stmt->execute([$newPlanId, $plan_name, $duration_days, $price]);
            $success_msg = "Membership plan '{$plan_name}' ({$newPlanId}) added successfully.";
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
            $success_msg = "Plan {$plan_id} updated successfully.";
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

<?php if ($success_msg): ?>
    <div class="alert-success"><?= htmlspecialchars($success_msg) ?></div>
<?php endif; ?>
<?php if ($error_msg): ?>
    <div class="alert-error"><?= htmlspecialchars($error_msg) ?></div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
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
            <button type="submit" class="btn btn-primary" style="width:100%;">Save Plan</button>
        </form>
    </div>

    <div class="card">
        <h3 style="margin-bottom: 12px;">Available Membership Plans (<?= count($plans) ?> Plans)</h3>
        <form method="GET" class="search-bar">
            <input type="text" id="planSearchInput" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search plan by name or ID...">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($search !== ''): ?>
                <a href="plans.php" class="btn btn-warning">Reset</a>
            <?php endif; ?>
        </form>

        <table class="table">
            <thead>
                <tr>
                    <th>Plan ID</th>
                    <th>Plan Name</th>
                    <th>Duration</th>
                    <th>Price</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="planTableBody">
                <?php if (empty($plans)): ?>
                    <tr><td colspan="5">No membership plans found.</td></tr>
                <?php else: foreach ($plans as $p): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($p['PlanID']) ?></strong></td>
                        <td><?= htmlspecialchars($p['PlanName']) ?></td>
                        <td><?= (int)$p['DurationDays'] ?> Days</td>
                        <td>₱<?= number_format($p['Price'] ?? 0, 2) ?></td>
                        <td>
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
            </tbody>
        </table>
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
                <label>Plan ID</label>
                <input type="text" id="edit_plan_id_display" readonly style="opacity:0.8;">
            </div>
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
    document.getElementById('edit_plan_id_display').value = id;
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
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
