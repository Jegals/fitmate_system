<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (isset($_SESSION['staff_id'])) {
    header("Location: /fitmate/attendance/dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? '';

    if (empty($username) || empty($password) || empty($role)) {
        $error = "Please fill in all fields.";
    } else {
        $stmt = $pdo->prepare("
            SELECT sa.StaffID, sa.Username, sa.Password, sa.Role, gs.FirstName, gs.LastName
            FROM system_access sa
            JOIN gym_staff gs ON sa.StaffID = gs.StaffID
            WHERE sa.Username = ? AND sa.Role = ?
        ");
        $stmt->execute([$username, $role]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['Password'])) {
            $_SESSION['staff_id']   = $user['StaffID'];
            $_SESSION['username']   = $user['Username'];
            $_SESSION['role']       = $user['Role'];
            $_SESSION['staff_name'] = $user['FirstName'] . ' ' . $user['LastName'];

            $pdo->prepare("INSERT INTO login_history (StaffID, LoginDate, LoginTime) VALUES (?, CURDATE(), CURTIME())")
                ->execute([$user['StaffID']]);

            header("Location: /fitmate/attendance/dashboard.php");
            exit();
        } else {
            $error = "Invalid username, password, or role.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login — FITMATE</title>
    <link rel="stylesheet" href="/fitmate/assets/css/style.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: radial-gradient(circle at top, #0f1e3d 0%, #050811 70%); }
        .auth-box { background: #0c1324; border: 1px solid #2563eb; padding: 34px; border-radius: 10px; width: 100%; max-width: 400px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.7), 0 0 20px rgba(37, 99, 235, 0.2); }
        .auth-box h2 { text-align: center; margin-bottom: 4px; color: #ffffff; letter-spacing: 2px; font-size: 24px; }
        .auth-box p.sub { text-align: center; font-size: 13px; color: #94a3b8; margin-bottom: 22px; }
        .alert-error { background: rgba(220, 38, 38, 0.2); border: 1px solid #ef4444; color: #fca5a5; padding: 10px; border-radius: 6px; font-size: 13px; margin-bottom: 14px; }
        .auth-footer { text-align: center; margin-top: 18px; font-size: 13px; color: #cbd5e1; }
        .auth-footer a { color: #60a5fa; font-weight: 700; text-decoration: none; }
        .auth-footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="auth-box">
    <h2>FITMATE</h2>
    <p class="sub">Gym Member Management System</p>
    <?php if (!empty($error)): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
        <div class="form-group"><label>Username</label><input type="text" name="username" required placeholder="Enter username"></div>
        <div class="form-group"><label>Password</label><input type="password" name="password" required placeholder="Enter password"></div>
        <div class="form-group">
            <label>Role</label>
            <select name="role" required>
                <option value="Admin">Admin</option>
                <option value="Manager">Manager</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%; margin-top:8px; padding:10px;">Login</button>
    </form>
    <div class="auth-footer">
        No system account yet? <a href="/fitmate/auth/register.php">Register Staff Access</a>
    </div>
</div>
</body>
</html>