<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (isset($_SESSION['staff_id'])) {
    header("Location: /fitmate/attendance/dashboard.php");
    exit();
}

$error = '';
$success = '';

// Helper: Auto-generate next StaffID based on Role (AD-004, MGR-001, etc.)
function generateNextStaffId(PDO $pdo, string $role): string {
    $prefix = ($role === 'Manager') ? 'MGR-' : 'AD-';
    $prefixLen = strlen($prefix) + 1;

    $stmt = $pdo->prepare("
        SELECT StaffID
        FROM gym_staff
        WHERE StaffID REGEXP ?
        ORDER BY CAST(SUBSTRING(StaffID, {$prefixLen}) AS UNSIGNED) DESC
        LIMIT 1
    ");
    $stmt->execute(['^' . $prefix . '[0-9]+$']);
    $lastId = $stmt->fetchColumn();

    $nextNum = $lastId ? ((int)substr($lastId, strlen($prefix)) + 1) : 1;
    return $prefix . str_pad((string)$nextNum, 3, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role      = trim($_POST['role'] ?? 'Admin');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $dob       = trim($_POST['dob'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';

    if (!in_array($role, ['Admin', 'Manager'], true)) {
        $role = 'Admin';
    }

    if ($firstName === '' || $lastName === '' || $dob === '' || $username === '' || $password === '') {
        $error = "Please fill in all required fields.";
    } else {
        $pdo->beginTransaction();
        try {
            // 1. Check if username already exists in system_access
            $chkUser = $pdo->prepare("SELECT COUNT(*) FROM system_access WHERE Username = ?");
            $chkUser->execute([$username]);
            if ((int)$chkUser->fetchColumn() > 0) {
                throw new Exception("Username '{$username}' is already taken. Please choose another.");
            }

            // 2. Auto-assign next available StaffID based on Role
            $newStaffId = generateNextStaffId($pdo, $role);

            // 3. Insert into parent table: gym_staff
            $stmtStaff = $pdo->prepare("
                INSERT INTO gym_staff (StaffID, FirstName, LastName, DateOfBirth, StaffType)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmtStaff->execute([$newStaffId, $firstName, $lastName, $dob, $role]);

            // 4. Insert into child table: system_access
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmtAccess = $pdo->prepare("
                INSERT INTO system_access (StaffID, Username, Password, Role)
                VALUES (?, ?, ?, ?)
            ");
            $stmtAccess->execute([$newStaffId, $username, $hashedPassword, $role]);

            $pdo->commit();
            $success = "Account created! Auto-assigned Staff ID: {$newStaffId} ({$role}). You can now log in.";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register — FITMATE</title>
    <link rel="stylesheet" href="/fitmate/assets/css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: radial-gradient(circle at top, #0f1e3d 0%, #050811 70%);
            padding: 20px;
        }
        .auth-box {
            background: #0c1324;
            border: 1px solid #2563eb;
            padding: 34px;
            border-radius: 10px;
            width: 100%;
            max-width: 460px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.7), 0 0 20px rgba(37, 99, 235, 0.2);
        }
        .auth-box h2 {
            text-align: center;
            margin-bottom: 4px;
            color: #ffffff;
            letter-spacing: 2px;
            font-size: 24px;
        }
        .auth-box p.sub {
            text-align: center;
            font-size: 13px;
            color: #94a3b8;
            margin-bottom: 22px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .alert-error {
            background: rgba(220, 38, 38, 0.2);
            border: 1px solid #ef4444;
            color: #fca5a5;
            padding: 10px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 14px;
        }
        .alert-success {
            background: rgba(22, 163, 74, 0.2);
            border: 1px solid #22c55e;
            color: #86efac;
            padding: 10px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 14px;
        }
        .auth-footer {
            text-align: center;
            margin-top: 18px;
            font-size: 13px;
            color: #cbd5e1;
        }
        .auth-footer a {
            color: #60a5fa;
            font-weight: 700;
            text-decoration: none;
        }
        .auth-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="auth-box">
    <h2>FITMATE</h2>
    <p class="sub">Register Staff System Access</p>

    <?php if (!empty($error)): ?>
        <div class="alert-error">✕ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert-success">✓ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Role</label>
            <select name="role" required>
                <option value="Admin" <?= (($_POST['role'] ?? 'Admin') === 'Admin') ? 'selected' : '' ?>>Admin</option>
                <option value="Manager" <?= (($_POST['role'] ?? '') === 'Manager') ? 'selected' : '' ?>>Manager</option>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>First Name</label>
                <input type="text" name="first_name" required placeholder="First name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="last_name" required placeholder="Last name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Date of Birth</label>
            <input type="date" name="dob" required value="<?= htmlspecialchars($_POST['dob'] ?? '2000-01-01') ?>">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required placeholder="Create username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Create password">
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; margin-top:8px; padding:10px;">Create Account</button>
    </form>

    <div class="auth-footer">
        Already have an account? <a href="/fitmate/auth/login.php">Back to Login</a>
    </div>
</div>
</body>
</html>