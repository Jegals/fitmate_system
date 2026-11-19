<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (isset($_SESSION['staff_id'])) {
    header("Location: /fitmate/dashboard.php");
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
            SELECT sa.StaffID, sa.Username, sa.Password, sa.Role,
                   gs.FirstName, gs.LastName
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

            // Record Login Date & Time sa LOGIN_HISTORY (Walang LogoutTime)
            $logStmt = $pdo->prepare("
                INSERT INTO login_history (StaffID, LoginDate, LoginTime)
                VALUES (?, CURDATE(), CURTIME())
            ");
            $logStmt->execute([$user['StaffID']]);

            header("Location: /fitmate/dashboard.php");
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
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #1e293b; }
        .auth-box { background: #fff; padding: 32px; border-radius: 8px; width: 100%; max-width: 400px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .auth-box h2 { text-align: center; margin-bottom: 6px; color: #0f172a; }
        .auth-box p.sub { text-align: center; font-size: 13px; color: #64748b; margin-bottom: 20px; }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; padding: 9px 10px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 14px; }
        .alert-error { background: #fee2e2; color: #b91c1c; padding: 10px; border-radius: 4px; font-size: 13px; margin-bottom: 14px; }
        .btn-full { width: 100%; padding: 10px; font-size: 14px; margin-top: 6px; }
        .auth-footer { text-align: center; margin-top: 16px; font-size: 13px; }
        .auth-footer a { color: #1e293b; font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>
<div class="auth-box">
    <h2>FITMATE</h2>
    <p class="sub">Gym Member Management System</p>

    <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" required placeholder="Enter username">
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required placeholder="Enter password">
        </div>
        <div class="form-group">
            <label>Role</label>
            <select name="role" required>
                <option value="Admin">Admin</option>
                <option value="Manager">Manager</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-full">Login</button>
    </form>

    <div class="auth-footer">
        No system account yet? <a href="/fitmate/auth/register.php">Register Staff Access</a>
    </div>
</div>
</body>
</html>