<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staffId   = strtoupper(trim($_POST['staff_id'] ?? ''));
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $dob       = $_POST['dob'] ?? '';
    $role      = $_POST['role'] ?? 'Manager';
    $username  = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';

    if (empty($staffId) || empty($firstName) || empty($lastName) || empty($dob) || empty($username) || empty($password)) {
        $error = "All fields are required.";
    } else {
        try {
            $pdo->beginTransaction();

            $stmtStaff = $pdo->prepare("
                INSERT INTO gym_staff (StaffID, FirstName, LastName, DateOfBirth, StaffType)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmtStaff->execute([$staffId, $firstName, $lastName, $dob, $role]);

            $hashedPass = password_hash($password, PASSWORD_DEFAULT);
            $stmtAccess = $pdo->prepare("
                INSERT INTO system_access (StaffID, Username, Password, Role)
                VALUES (?, ?, ?, ?)
            ");
            $stmtAccess->execute([$staffId, $username, $hashedPass, $role]);

            $pdo->commit();
            $success = "Account registered! You can now log in.";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Registration failed (Staff ID or Username may already exist).";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register System Access — FITMATE</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: radial-gradient(circle at top, #1e3a8a 0%, #090d16 70%);
            padding: 20px;
        }
        .auth-box {
            background: #ffffff;
            padding: 32px;
            border-radius: 10px;
            width: 100%;
            max-width: 460px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.45);
            border-top: 5px solid #2563eb;
        }
        .auth-box h2 {
            text-align: center;
            color: #0f172a;
            font-size: 22px;
            margin-bottom: 4px;
        }
        .auth-box p.sub {
            text-align: center;
            color: #64748b;
            font-size: 13px;
            margin-bottom: 22px;
        }
        .row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .form-group {
            margin-bottom: 14px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 6px;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            color: #0f172a;
            background: #f8fafc;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2563eb;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .alert-error {
            background: #fee2e2;
            color: #b91c1c;
            padding: 10px 12px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 14px;
        }
        .alert-success {
            background: #dcfce7;
            color: #15803d;
            padding: 10px 12px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 14px;
        }
        .btn-submit {
            width: 100%;
            padding: 11px;
            background: #2563eb;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 6px;
            transition: background 0.2s;
        }
        .btn-submit:hover {
            background: #1d4ed8;
        }
        .auth-footer {
            text-align: center;
            margin-top: 18px;
            font-size: 13px;
            color: #475569;
        }
        .auth-footer a {
            color: #2563eb;
            font-weight: 600;
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
    <p class="sub">Register Admin / Manager System Access</p>

    <?php if ($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="POST">
        <div class="row-2">
            <div class="form-group">
                <label>Staff ID (e.g. AD-002)</label>
                <input type="text" name="staff_id" required placeholder="AD-002">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role" required>
                    <option value="Admin">Admin</option>
                    <option value="Manager">Manager</option>
                </select>
            </div>
        </div>

        <div class="row-2">
            <div class="form-group">
                <label>First Name</label>
                <input type="text" name="first_name" required placeholder="Juan">
            </div>
            <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="last_name" required placeholder="Dela Cruz">
            </div>
        </div>

        <div class="form-group">
            <label>Date of Birth</label>
            <input type="date" name="dob" required>
        </div>

        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" required placeholder="Enter username">
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required placeholder="Create password">
        </div>

        <button type="submit" class="btn-submit">Register</button>
    </form>

    <div class="auth-footer">
        Already have an account? <a href="/fitmate/auth/login.php">Back to Login</a>
    </div>
</div>
</body>
</html>