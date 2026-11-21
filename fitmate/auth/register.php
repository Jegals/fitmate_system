<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staffId   = strtoupper(trim($_POST['staff_id'] ?? ''));
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $dob       = $_POST['dob'] ?? '2000-01-01';
    $username  = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';
    $role      = $_POST['role'] ?? 'Admin';

    if ($staffId === '' || $username === '' || $password === '') {
        $err = "Please fill in Staff ID, Username, and Password.";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. I-check kung existing na ang StaffID sa gym_staff
            $chkStaff = $pdo->prepare("SELECT StaffID FROM gym_staff WHERE StaffID = ?");
            $chkStaff->execute([$staffId]);

            if (!$chkStaff->fetch()) {
                // Kung wala pa sa gym_staff (e.g., AD-002), i-insert muna sa gym_staff
                if ($firstName === '' || $lastName === '') {
                    throw new Exception("Staff ID ($staffId) is new. Please enter First Name and Last Name.");
                }
                $insStaff = $pdo->prepare("
                    INSERT INTO gym_staff (StaffID, FirstName, LastName, DateOfBirth, StaffType)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insStaff->execute([$staffId, $firstName, $lastName, $dob, $role]);
            }

            // 2. I-check kung may system_access account na ang StaffID o Username
            $chkAcc = $pdo->prepare("SELECT StaffID FROM system_access WHERE StaffID = ? OR Username = ?");
            $chkAcc->execute([$staffId, $username]);
            if ($chkAcc->fetch()) {
                throw new Exception("Staff ID ($staffId) or Username ($username) already has an account.");
            }

            // 3. I-save sa system_access table
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insAcc = $pdo->prepare("
                INSERT INTO system_access (StaffID, Username, Password, Role)
                VALUES (?, ?, ?, ?)
            ");
            $insAcc->execute([$staffId, $username, $hashedPassword, $role]);

            $pdo->commit();
            $msg = "System account created for $staffId ($role)! You can now log in.";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $err = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Staff Access — FITMATE</title>
    <link rel="stylesheet" href="/fitmate/assets/css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: #060913;
            padding: 16px;
        }
        .register-card {
            width: 100%;
            max-width: 430px;
            background: #0c1324;
            border: 1px solid #2563eb;
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.75);
        }
        .register-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .register-header h1 {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 1px;
            color: #ffffff;
        }
        .register-header p {
            font-size: 13px;
            color: #94a3b8;
            margin-top: 4px;
        }
        .alert-err {
            background: rgba(220, 38, 38, 0.2);
            border: 1px solid #ef4444;
            color: #fca5a5;
            padding: 10px 12px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 14px;
            text-align: center;
        }
        .alert-ok {
            background: rgba(22, 163, 74, 0.2);
            border: 1px solid #22c55e;
            color: #86efac;
            padding: 10px 12px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 14px;
            text-align: center;
        }
        .login-link {
            text-align: center;
            margin-top: 16px;
            font-size: 13px;
            color: #94a3b8;
        }
        .login-link a {
            color: #60a5fa;
            font-weight: 600;
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-card">
        <div class="register-header">
            <h1>FITMATE</h1>
            <p>Register Staff System Access</p>
        </div>

        <?php if ($err): ?>
            <div class="alert-err"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>
        <?php if ($msg): ?>
            <div class="alert-ok"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-row">
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

            <div class="form-row">
                <div class="form-group">
                    <label>First Name (if new ID)</label>
                    <input type="text" name="first_name" placeholder="First name">
                </div>
                <div class="form-group">
                    <label>Last Name (if new ID)</label>
                    <input type="text" name="last_name" placeholder="Last name">
                </div>
            </div>

            <div class="form-group">
                <label>Date of Birth (if new ID)</label>
                <input type="date" name="dob" value="2000-01-01">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required placeholder="Create username">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="Create password">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top: 8px; padding: 10px;">
                Create Account
            </button>
        </form>

        <div class="login-link">
            Already have an account? <a href="/fitmate/auth/login.php">Back to Login</a>
        </div>
    </div>
</body>
</html>
