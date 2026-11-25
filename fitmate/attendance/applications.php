<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Manila');

$page_title = "Program Applications";
$msg = '';
$err = '';

// Show flash message after redirect (prevents form re-submit on refresh)
if (!empty($_SESSION['app_flash'])) {
    [$type, $text] = $_SESSION['app_flash'];
    if ($type === 'ok') $msg = $text; else $err = $text;
    unset($_SESSION['app_flash']);
}

// 1. SUBMIT NEW PROGRAM APPLICATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply_program') {
    $memberId  = strtoupper(trim($_POST['member_id'] ?? ''));
    $programId = $_POST['program_id'] ?? '';

    if ($memberId === '' || $programId === '') {
        $err = "Please enter a Member ID and select a Program.";
    } else {
        try {
            $chk = $pdo->prepare("SELECT MemberID FROM gym_member WHERE MemberID = ? AND is_archived = 0");
            $chk->execute([$memberId]);
            $pchk = $pdo->prepare("SELECT 1 FROM program WHERE ProgramID = ?");
            $pchk->execute([$programId]);

            $dup = $pdo->prepare("SELECT 1 FROM application WHERE MemberID = ? AND ProgramID = ? AND Status IN ('Pending','Approved') LIMIT 1");
            $dup->execute([$memberId, $programId]);

            if (!$chk->fetch()) {
                $err = "Member ID ($memberId) not found or is currently archived.";
            } elseif (!$pchk->fetchColumn()) {
                $err = "Selected program does not exist.";
            } elseif ($dup->fetchColumn()) {
                $err = "$memberId already has a Pending or Approved application for this program.";
            } else {
                // Generate next ApplicationID (e.g., APP316) using the highest NUMBER, not string order
                $lastApp = $pdo->query("SELECT ApplicationID FROM application ORDER BY CAST(SUBSTRING(ApplicationID, 4) AS UNSIGNED) DESC LIMIT 1")->fetchColumn();
                if ($lastApp && preg_match('/(\d+)$/', $lastApp, $matches)) {
                    $nextNum = (int)$matches[1] + 1;
                    $newAppId = 'APP' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
                } else {
                    $newAppId = 'APP301';
                }

                $stmt = $pdo->prepare("
                    INSERT INTO application (ApplicationID, MemberID, ProgramID, ApplicationDate, Status)
                    VALUES (?, ?, ?, CURDATE(), 'Pending')
                ");
                $stmt->execute([$newAppId, $memberId, $programId]);
                $_SESSION['app_flash'] = ['ok', "Program application ($newAppId) submitted for $memberId!"];
                header('Location: applications.php');
                exit;
            }
        } catch (PDOException $e) {
            $err = "Error submitting application: " . $e->getMessage();
        }
    }
}

// 2. UPDATE APPLICATION STATUS (Approve, Cancel, Rejected)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $appId     = $_POST['application_id'] ?? '';
    $newStatus = $_POST['new_status'] ?? '';

    if (in_array($newStatus, ['Approved', 'Cancelled', 'Rejected'], true)) {
        try {
            $stmt = $pdo->prepare("UPDATE application SET Status = ? WHERE ApplicationID = ? AND Status = 'Pending'");
            $stmt->execute([$newStatus, $appId]);
            $_SESSION['app_flash'] = $stmt->rowCount()
                ? ['ok', "Application ($appId) status updated to $newStatus!"]
                : ['err', "Application ($appId) is no longer Pending, so it can't be changed."];
            header('Location: applications.php');
            exit;
        } catch (PDOException $e) {
            $err = "Error updating status: " . $e->getMessage();
        }
    }
}

// 3. FETCH PROGRAMS FOR DROPDOWN
$programs = $pdo->query("SELECT ProgramID, ProgramName FROM program ORDER BY ProgramName ASC")->fetchAll();

// 4. FETCH PROGRAM APPLICATION LIST (gamit ang tamang column na ApplicationDate)
$applications = $pdo->query("
    SELECT a.ApplicationID, a.MemberID, a.ApplicationDate, a.Status,
           CONCAT(m.FirstName, ' ', m.LastName) AS MemberName,
           p.ProgramName
    FROM application a
    JOIN gym_member m ON a.MemberID = m.MemberID
    JOIN program p ON a.ProgramID = p.ProgramID
    ORDER BY CAST(SUBSTRING(a.ApplicationID, 4) AS UNSIGNED) DESC
")->fetchAll();

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
        gap: 14px !important;
    }

    .apps-grid {
        flex: 1 !important;
        display: grid !important;
        grid-template-columns: 340px 1fr !important;
        gap: 18px !important;
        min-height: 0 !important;
        align-items: stretch !important;
    }
    .apps-grid > .card {
        margin-bottom: 0 !important;
        display: flex !important;
        flex-direction: column !important;
        min-height: 0 !important;
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
        padding: 10px 10px;
        text-align: left;
        font-size: 12px;
        font-weight: 700;
        color: #60a5fa;
        background: #111c35;
    }
    .fixed-table td {
        padding: 9px 10px;
        font-size: 13px;
        color: #f1f5f9;
        border-bottom: 1px solid #1e293b;
        vertical-align: middle;
    }
    .fixed-table tbody tr:hover {
        background: rgba(37, 99, 235, 0.08);
    }

    /* Status Badges (Approved = Green, Pending = Amber, Cancelled = Gray, Rejected = RED) */
    .badge-red {
        background: rgba(220, 38, 38, 0.22) !important;
        color: #fca5a5 !important;
        border: 1px solid #ef4444 !important;
    }
    .badge-gray {
        background: rgba(100, 116, 139, 0.22) !important;
        color: #cbd5e1 !important;
        border: 1px solid #64748b !important;
    }

    /* Action Buttons sa loob ng table */
    .action-btns {
        display: flex;
        gap: 4px;
        flex-wrap: nowrap;
    }
    .btn-xs {
        padding: 4px 7px;
        font-size: 11px;
        font-weight: 600;
        border-radius: 5px;
        border: none;
        cursor: pointer;
        color: #ffffff;
    }
    .btn-approve  { background: #16a34a; }
    .btn-approve:hover  { background: #15803d; }
    .btn-cancel   { background: #475569; }
    .btn-cancel:hover   { background: #334155; }
    .btn-rejected { background: #dc2626; }
    .btn-rejected:hover { background: #b91c1c; }
</style>

<?php if ($msg): ?>
    <div style="background:rgba(22, 163, 74, 0.2); border:1px solid #22c55e; color:#86efac; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>
<?php if ($err): ?>
    <div style="background:rgba(220, 38, 38, 0.2); border:1px solid #ef4444; color:#fca5a5; padding:10px 14px; border-radius:6px; font-size:13px; flex-shrink:0;">
        <?= htmlspecialchars($err) ?>
    </div>
<?php endif; ?>

<div class="apps-grid">
    <!-- LEFT CARD: APPLY MEMBER TO PROGRAM -->
    <div class="card" style="height: fit-content;">
        <h3 style="margin-bottom: 14px;">Apply Member to Program</h3>
        <form method="POST">
            <input type="hidden" name="action" value="apply_program">
            <div class="form-group">
                <label>Member ID</label>
                <input type="text" name="member_id" required placeholder="e.g. MEM101">
            </div>
            <div class="form-group">
                <label>Select Program (Live from DB)</label>
                <select name="program_id" required>
                    <option value="" disabled selected>-- Select a program --</option>
                    <?php foreach ($programs as $prog): ?>
                        <option value="<?= htmlspecialchars($prog['ProgramID']) ?>">
                            <?= htmlspecialchars($prog['ProgramName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; margin-top: 6px; padding: 10px;">
                Submit Application
            </button>
        </form>
    </div>

    <!-- RIGHT CARD: PROGRAM APPLICATION LIST -->
    <div class="card">
        <h3 style="margin-bottom: 12px; flex-shrink: 0;">Program Application List (<?= count($applications) ?> Records)</h3>

        <div class="fixed-table-wrap">
            <table class="fixed-table">
                <thead>
                    <tr>
                        <th style="width: 10%;">App ID</th>
                        <th style="width: 18%;">Member</th>
                        <th style="width: 26%;">Program</th>
                        <th style="width: 12%;">Date Applied</th>
                        <th style="width: 11%;">Status</th>
                        <th style="width: 23%;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($applications)): ?>
                        <tr><td colspan="6">No program applications found.</td></tr>
                    <?php else: foreach ($applications as $app):
                        $status = $app['Status'] ?? 'Pending';
                        $badgeClass = 'badge badge-amber'; // Pending
                        if ($status === 'Approved') {
                            $badgeClass = 'badge badge-green';
                        } elseif ($status === 'Rejected') {
                            $badgeClass = 'badge badge-red';
                        } elseif ($status === 'Cancelled') {
                            $badgeClass = 'badge badge-gray';
                        }
                    ?>
                        <tr>
                            <td style="width: 10%;"><strong><?= htmlspecialchars($app['ApplicationID']) ?></strong></td>
                            <td style="width: 18%;"><?= htmlspecialchars($app['MemberName']) ?></td>
                            <td style="width: 26%;"><?= htmlspecialchars($app['ProgramName']) ?></td>
                            <td style="width: 12%;"><?= htmlspecialchars($app['ApplicationDate']) ?></td>
                            <td style="width: 11%;"><span class="<?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span></td>
                            <td style="width: 23%;">
                                <?php if ($status !== 'Pending'): ?>
                                    <span style="opacity:.5;">Done</span>
                                <?php else: ?>
                                <div class="action-btns">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="application_id" value="<?= htmlspecialchars($app['ApplicationID']) ?>">
                                        <input type="hidden" name="new_status" value="Approved">
                                        <button type="submit" class="btn-xs btn-approve">Approve</button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="application_id" value="<?= htmlspecialchars($app['ApplicationID']) ?>">
                                        <input type="hidden" name="new_status" value="Cancelled">
                                        <button type="submit" class="btn-xs btn-cancel">Cancel</button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="application_id" value="<?= htmlspecialchars($app['ApplicationID']) ?>">
                                        <input type="hidden" name="new_status" value="Rejected">
                                        <button type="submit" class="btn-xs btn-rejected">Reject</button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
