<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['staff_id'])) {
    header("Location: /fitmate/auth/login.php");
    exit();
}

function requireManagerRole() {
    $role = $_SESSION['role'] ?? '';
    if ($role !== 'Manager' && $role !== 'Admin') {
        echo "<div style='padding:30px; font-family:sans-serif;'>
                <h2 style='color:#dc2626;'>Access Denied</h2>
                <p>Only Managers or Admins are authorized to view this section.</p>
                <a href='/fitmate/attendance/logs.php'>Go to Attendance Logs</a>
              </div>";
        exit();
    }
}
?>