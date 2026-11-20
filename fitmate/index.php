<?php
session_start();
if (isset($_SESSION['staff_id'])) {
    header("Location: /fitmate/attendance/dashboard.php");
} else {
    header("Location: /fitmate/auth/login.php");
}
exit();
?>