<?php
require_once __DIR__ . '/../config/db.php';

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staffId  = strtoupper(trim($_POST['staff_id'] ?? ''));
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? '';

    try {
        $check = $pdo->prepare("SELECT StaffID FROM gym_staff WHERE StaffID = ?");
        $check->execute([$staffId]);
        if (!$check->fetch()) {
            $err = "Staff ID ($staffId) does not exist in Gym Staff records.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO system_access (StaffID, Username, Password, Role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$staffId, $username, $hash, $role]);
            $msg = "System access registered! You can now log in.";
        }
    } catch (PDOException $e) {
        $err = "Registration failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register Staff Access — FITMATE</title>
    <link rel="stylesheet" href="/fitmate/assets/css/style.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: radial-gradient(circle at top, #0f1e3d 0%, #050811 70%); }
        .auth-box { background: #0c1324; border: 1px solid #2563eb; padding: 34px; border-radius: 10px; width: 100%; max-width: 400px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.7), 0 0 20px rgba(37, 99, 235, 0.2); }
        .auth-box h2 { text-align: center; margin-bottom: 4px; color: #ffffff; letter-spacing: 1px; font-size: 22px; }
        .auth-box p.sub { text-align: center; font-size: 13px; color: #94a3b8; margin-bottom: 20px; }
        .alert-ok { background: rgba(22, 163, 74, 0.2); border: 1px solid #22c55e; color: #86efac; padding: 10px; border-radius: 6px; font-size: 13px; margin-bottom: 14px; }
        .alert-error { background: rgba(220, 38, 38, 0.2); border: 1px solid #ef4444; color: #fca5a5; padding: 10px; border-radius: 6px; font-size: 13px; margin-bottom: 14px; }
        .auth-footer { text-align: center; margin-top: 18px; font-size: 13px; color: #cbd5e1; }
        .auth-footer a { color: #60a5fa; font-weight: 700; text-decoration: none; }
        .auth-footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="auth-box">
    <h2>FITMATE</h2>
    <p class="sub">Register Staff System Access</p>

    <?php if (!empty($msg)): ?><div class="alert-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if (!empty($err)): ?><div class="alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Existing Staff ID (e.g. AD-001)</label>
            <input type="text" name="staff_id" required placeholder="AD-001">
        </div>
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" required placeholder="Create username">
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required placeholder="Create password">
        </div>
        <div class="form-group">
            <label>Role</label>
            <select name="role" required>
                <option value="Admin">Admin</option>
                <option value="Manager">Manager</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%; margin-top:8px; padding:10px;">Create Account</button>
    </form>

    <div class="auth-footer">
        Already have an account? <a href="/fitmate/auth/login.php">Back to Login</a>
    </div>
</div>
</body>
</html>