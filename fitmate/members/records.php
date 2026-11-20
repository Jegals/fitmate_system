<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = "Gym Member Records";

$members = $pdo->query("
    SELECT m.*, ec.ContactName, ec.Relationship, ec.PhoneNumber AS EmergencyPhone
    FROM gym_member m
    LEFT JOIN emergency_contact ec ON m.MemberID = ec.MemberID
    WHERE m.is_archived = 0
    ORDER BY m.MemberID ASC
")->fetchAll();

// TODO [Member 2]: Add Search filter, Update Member/Emergency Contact Modal, and Soft Delete (is_archived = 1)
include __DIR__ . '/../includes/header.php';
?>

<style>
    .main-wrapper, .main-content {
        height: 100vh !important;
        overflow: hidden !important;
        display: flex !important;
        flex-direction: column !important;
    }
    .content-area, .container {
        flex: 1 !important;
        height: calc(100vh - 60px) !important;
        max-height: calc(100vh - 60px) !important;
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
        padding: 16px 22px !important;
    }
    .full-height-card {
        flex: 1 !important;
        display: flex !important;
        flex-direction: column !important;
        min-height: 0 !important;
        margin-bottom: 0 !important;
        overflow: hidden !important;
    }

    /* FIXED TABLE HEADER + SCROLLABLE TBODY */
    .fixed-table-wrap {
        border: 1px solid #1e293b;
        border-radius: 8px;
        background: #0c1324;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        flex: 1;
        min-height: 0;
    }
    .fixed-table {
        width: 100%;
        border-collapse: collapse;
        display: flex;
        flex-direction: column;
        flex: 1;
        min-height: 0;
    }
    .fixed-table thead {
        flex-shrink: 0;
        background: #111c35;
        border-bottom: 1px solid #283548;
    }
    .fixed-table thead tr,
    .fixed-table tbody tr {
        display: table;
        width: 100%;
        table-layout: fixed;
    }
    .fixed-table tbody {
        display: block;
        overflow-y: auto;
        width: 100%;
        flex: 1;
        min-height: 0;
    }
    .fixed-table th {
        padding: 11px 14px;
        text-align: left;
        font-size: 12px;
        font-weight: 700;
        color: #60a5fa;
        background: #111c35;
    }
    .fixed-table td {
        padding: 10px 14px;
        font-size: 13px;
        color: #f1f5f9;
        border-bottom: 1px solid #1e293b;
        vertical-align: middle;
    }
    .fixed-table tbody tr:hover {
        background: rgba(37, 99, 235, 0.08);
    }
</style>

<div class="card full-height-card">
    <h3 style="margin-bottom: 12px; flex-shrink: 0;">Active Gym Members Directory (<?= count($members) ?> Records)</h3>
    <form method="GET" class="search-bar" style="flex-shrink: 0;">
        <input type="text" name="search" placeholder="Search member by ID, Name, or Phone Number...">
        <button type="button" class="btn btn-primary">Search</button>
    </form>

    <div class="fixed-table-wrap">
        <table class="fixed-table">
            <thead>
                <tr>
                    <th style="width: 13%;">Member ID</th>
                    <th style="width: 22%;">Full Name</th>
                    <th style="width: 17%;">Phone Number</th>
                    <th style="width: 26%;">Emergency Contact</th>
                    <th style="width: 22%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($members)): ?>
                    <tr><td colspan="5">No active member records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($members as $m): ?>
                        <tr>
                            <td style="width: 13%;"><strong><?= htmlspecialchars($m['MemberID']) ?></strong></td>
                            <td style="width: 22%;"><?= htmlspecialchars($m['FirstName'] . ' ' . $m['LastName']) ?></td>
                            <td style="width: 17%;"><?= htmlspecialchars($m['PhoneNumber'] ?? '-') ?></td>
                            <td style="width: 26%;">
                                <?php if (!empty($m['ContactName'])): ?>
                                    <?= htmlspecialchars($m['ContactName']) ?> (<?= htmlspecialchars($m['EmergencyPhone'] ?? '-') ?>)
                                <?php else: ?>
                                    <span style="color:#64748b;">No emergency contact</span>
                                <?php endif; ?>
                            </td>
                            <td style="width: 22%; white-space: nowrap;">
                                <a href="/fitmate/members/profile.php?id=<?= urlencode($m['MemberID']) ?>" class="btn btn-primary btn-sm">View Profile</a>
                                <button type="button" class="btn btn-warning btn-sm">Edit</button>
                                <button type="button" class="btn btn-danger btn-sm">Archive</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
