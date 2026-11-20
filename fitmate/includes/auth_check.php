<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['staff_id'])) {
    header("Location: /fitmate/auth/login.php");
    exit();
}

function requireManagerRole() {
    if (!in_array($_SESSION['role'] ?? '', ['Admin', 'Manager'])) {
        die("Access Denied: Admin or Manager role required.");
    }
}
?>