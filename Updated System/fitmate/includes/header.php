<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$page_title = $page_title ?? 'FITMATE System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FITMATE — <?= htmlspecialchars($page_title) ?></title>
    <link rel="stylesheet" href="/fitmate/assets/css/style.css">
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="topbar">
            <h1 class="page-title"><?= htmlspecialchars($page_title) ?></h1>
            <div class="user-info">
                <span class="role-pill"><?= htmlspecialchars($_SESSION['role'] ?? 'Staff') ?></span>
                <span class="staff-name"><?= htmlspecialchars($_SESSION['staff_name'] ?? 'User') ?></span>
                <a href="/fitmate/auth/logout.php" class="btn-logout">Logout</a>
            </div>
        </header>
        <main class="content-area">