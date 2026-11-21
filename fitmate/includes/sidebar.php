<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-logo" style="padding: 0; margin-bottom: 16px; width: 100%; height: 64px; border-radius: 8px; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #0c1324 !important; border: 1px solid #2563eb !important; box-shadow: none !important;">
        <img src="/fitmate/assets/images/logo4.svg" alt="FITMATE Logo" style="width: 100%; height: 100%; object-fit: cover; display: block; transform: scale(1.03);">
    </div>

    <nav class="nav-menu">
        <a href="/fitmate/attendance/dashboard.php" class="nav-btn <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">DASHBOARD</a>
        <a href="/fitmate/members/registration.php" class="nav-btn <?= $current_page == 'registration.php' ? 'active' : '' ?>">REGISTRATION</a>
        <a href="/fitmate/members/records.php" class="nav-btn <?= $current_page == 'records.php' ? 'active' : '' ?>">GYM MEMBER RECORDS</a>
        <a href="/fitmate/attendance/logs.php" class="nav-btn <?= $current_page == 'logs.php' ? 'active' : '' ?>">ATTENDANCE LOGS</a>
        <a href="/fitmate/billing/payment_logs.php" class="nav-btn <?= $current_page == 'payment_logs.php' ? 'active' : '' ?>">PAYMENT LOGS</a>

        <div class="nav-section-label">OTHERS</div>

        <a href="/fitmate/attendance/applications.php" class="nav-btn <?= $current_page == 'applications.php' ? 'active' : '' ?>">PROGRAM APPLICATION</a>
        <a href="/fitmate/billing/subscriptions.php" class="nav-btn <?= $current_page == 'subscriptions.php' ? 'active' : '' ?>">SUBSCRIPTION</a>
        <a href="/fitmate/billing/plans.php" class="nav-btn <?= $current_page == 'plans.php' ? 'active' : '' ?>">MEMBERSHIP PLANS</a>
        <a href="/fitmate/fitness/programs.php" class="nav-btn <?= $current_page == 'programs.php' ? 'active' : '' ?>">PROGRAMS</a>
        <a href="/fitmate/fitness/progress.php" class="nav-btn <?= $current_page == 'progress.php' ? 'active' : '' ?>">MEMBER PROGRESS</a>
        <a href="/fitmate/auth/staff_records.php" class="nav-btn <?= $current_page == 'staff_records.php' ? 'active' : '' ?>">STAFF RECORDS</a>
        <a href="/fitmate/fitness/equipment.php" class="nav-btn <?= $current_page == 'equipment.php' ? 'active' : '' ?>">EQUIPMENT</a>

        <div class="nav-spacer"></div>
        <a href="/fitmate/auth/archived.php" class="nav-btn <?= $current_page == 'archived.php' ? 'active' : '' ?>">ARCHIVED (Deleted Files)</a>
    </nav>
</aside>
