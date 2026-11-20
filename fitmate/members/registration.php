<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Member Registration";
$plans = $pdo->query("SELECT * FROM membership_plan ORDER BY Price ASC")->fetchAll();

// TODO [Member 2]: Add INSERT transaction for GYM_MEMBER (PhoneNumber), EMERGENCY_CONTACT (PhoneNumber), SUBSCRIPTION, and PAYMENT
include __DIR__ . '/../includes/header.php';
?>

<form method="POST">
    <div class="grid-equal">
        <div class="card">
            <h3 style="margin-bottom: 14px;">1. Member Personal Information</h3>
            <div class="form-row">
                <div class="form-group"><label>First Name</label><input type="text" name="first_name" placeholder="Juan"></div>
                <div class="form-group"><label>Last Name</label><input type="text" name="last_name" placeholder="Dela Cruz"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Date of Birth</label><input type="date" name="dob"></div>
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender"><option>Male</option><option>Female</option><option>Other</option></select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Phone Number</label><input type="text" name="phone_number" placeholder="09171234567"></div>
                <div class="form-group"><label>Email Address</label><input type="email" name="email" placeholder="juan@email.com"></div>
            </div>
            <div class="form-group"><label>Home Address</label><input type="text" name="address" placeholder="Legazpi City, Albay"></div>

            <h3 style="margin: 18px 0 12px;">2. Emergency Contact</h3>
            <div class="form-group"><label>Contact Name</label><input type="text" name="ec_name" placeholder="Maria Dela Cruz"></div>
            <div class="form-row">
                <div class="form-group"><label>Relationship</label><input type="text" name="ec_relation" placeholder="Mother / Spouse"></div>
                <div class="form-group"><label>Contact Phone Number</label><input type="text" name="ec_phone_number" placeholder="09181234567"></div>
            </div>
        </div>

        <div class="card">
            <h3 style="margin-bottom: 14px;">3. Membership Plan & Initial Payment</h3>
            <div class="form-group">
                <label>Select Membership Plan (Live from DB)</label>
                <select name="plan_id">
                    <option value="">-- Choose Plan --</option>
                    <?php foreach ($plans as $p): ?>
                        <option value="<?= htmlspecialchars($p['PlanID']) ?>">
                            <?= htmlspecialchars($p['PlanName']) ?> (<?= $p['DurationDays'] ?> Days) — ₱<?= number_format($p['Price'], 2) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Start Date</label><input type="date" name="start_date" value="<?= date('Y-m-d') ?>"></div>
            <div class="form-row">
                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="payment_method"><option>Cash</option><option>GCash</option><option>Maya</option><option>Credit Card</option><option>Debit Card</option></select>
                </div>
                <div class="form-group"><label>Amount Paid (₱)</label><input type="number" step="0.01" name="amount" placeholder="1500.00"></div>
            </div>
            <div class="form-group"><label>Reference Number</label><input type="text" name="ref_no" placeholder="OR-2026-001"></div>

            <button type="button" class="btn btn-primary" style="width:100%; margin-top: 12px;">Complete Registration & Payment</button>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>