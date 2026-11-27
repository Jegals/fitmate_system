<?php
session_start();
session_unset();
session_destroy();
header("Location: /fitmate/auth/login.php");
exit();
?>