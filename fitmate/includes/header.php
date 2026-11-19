<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$staffName = $_SESSION['staff_name'] ?? 'Admin User';
$staffRole = $_SESSION['role'] ?? 'Manager';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FITMATE — Gym Member Management System</title>
    <link rel="stylesheet" href="/fitmate/assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="main-area">
        <header class="topbar">
            <div class="topbar-title">
                <h2><?= htmlspecialchars($page_title ?? 'FITMATE Management System') ?></h2>
            </div>
            <div class="topbar-user">
                <span class="user-badge"><?= htmlspecialchars($staffRole) ?></span>
                <span class="user-name"><?= htmlspecialchars($staffName) ?></span>
                <a href="/fitmate/auth/logout.php" class="btn-logout">Logout</a>
            </div>
        </header>

        <main class="content-container">