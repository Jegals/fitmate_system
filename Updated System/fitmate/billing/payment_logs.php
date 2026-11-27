<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

date_default_timezone_set('Asia/Manila');
$pdo->exec("SET time_zone = '+08:00'");

$page_title = "Payment Logs";

$search = trim($_GET['search'] ?? '');
$sql = "
    SELECT p.*, CONCAT(m.FirstName, ' ', m.LastName) AS FullName
    FROM payment p
    JOIN gym_member m ON p.MemberID = m.MemberID
";
$params = [];

if ($search !== '') {
    $sql .= " WHERE p.ReferenceNumber LIKE ? OR p.PaymentID LIKE ? OR CONCAT(m.FirstName, ' ', m.LastName) LIKE ?";
    $like = "%{$search}%";
    $params = [$like, $like, $like];
}
$sql .= " ORDER BY p.PaymentDate DESC, p.PaymentTime DESC, p.PaymentID DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

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

    .payment-card {
        height: 100% !important;
        margin-bottom: 0 !important;
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
    }

    /* Consistent Black & Blue Search Bar Styling */
    .search-bar {
        display: flex;
        gap: 10px;
        margin-bottom: 12px;
        align-items: center;
        flex-shrink: 0;
    }
    .search-bar input {
        flex: 1;
        background: #060913 !important;
        border: 1px solid #1e293b !important;
        color: #f1f5f9 !important;
        padding: 10px 14px !important;
        border-radius: 6px !important;
        font-size: 13.5px !important;
        outline: none !important;
        transition: border-color 0.2s ease;
    }
    .search-bar input::placeholder {
        color: #64748b !important;
    }
    .search-bar input:focus {
        border-color: #2563eb !important;
    }
    .search-bar button {
        padding: 10px 18px !important;
        border-radius: 6px !important;
        font-weight: 600 !important;
        flex-shrink: 0;
    }

    /* FIXED TABLE HEADER + INTERNAL SCROLLABLE TBODY */
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
        padding: 10px 12px;
        text-align: left;
        font-size: 12px;
        font-weight: 700;
        color: #60a5fa;
        background: #111c35;
    }
    .fixed-table td {
        padding: 10px 12px;
        font-size: 13px;
        color: #f1f5f9;
        border-bottom: 1px solid #1e293b;
        vertical-align: middle;
    }
    .fixed-table tbody tr:hover {
        background: rgba(37, 99, 235, 0.08);
    }
</style>

<div class="card payment-card">
    <h3 style="margin-bottom: 12px; flex-shrink: 0;">Payment Transaction History</h3>

    <form method="GET" class="search-bar" onsubmit="event.preventDefault(); filterPaymentTable();">
        <input
            type="text"
            id="livePaySearch"
            name="search"
            value="<?= htmlspecialchars($search) ?>"
            onkeyup="filterPaymentTable()"
            oninput="filterPaymentTable()"
            placeholder="Search by Reference Number, Member Name, Method, or Date..."
        >
        <button type="button" class="btn btn-primary" onclick="filterPaymentTable()">Search</button>
    </form>

    <div class="fixed-table-wrap">
        <table class="fixed-table">
            <thead>
                <tr>
                    <th style="width: 23%;">Ref #</th>
                    <th style="width: 21%;">Member</th>
                    <th style="width: 12%;">Amount</th>
                    <th style="width: 12%;">Method</th>
                    <th style="width: 11%;">Date</th>
                    <th style="width: 10%;">Time</th>
                    <th style="width: 11%;">Receipt</th>
                </tr>
            </thead>
            <tbody id="payTableBody">
                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; color:#94a3b8; padding:18px;">
                            No payment records found.
                        </td>
                    </tr>
                <?php else: foreach ($payments as $p):
                    $ref = $p['ReferenceNumber'] ?? $p['PaymentID'];
                    $formattedTime = !empty($p['PaymentTime'])
                        ? date('H:i:s', strtotime($p['PaymentTime']))
                        : '-';
                ?>
                    <tr class="pay-row">
                        <td style="width: 23%;"><strong><?= htmlspecialchars($ref) ?></strong></td>
                        <td style="width: 21%;"><?= htmlspecialchars($p['FullName']) ?></td>
                        <td style="width: 12%;">₱<?= number_format((float)$p['Amount'], 2) ?></td>
                        <td style="width: 12%;"><?= htmlspecialchars($p['PaymentMethod'] ?? '-') ?></td>
                        <td style="width: 11%;"><?= htmlspecialchars($p['PaymentDate'] ?? '-') ?></td>
                        <td style="width: 10%;"><?= htmlspecialchars($formattedTime) ?></td>
                        <td style="width: 11%;">
                            <button
                                type="button"
                                class="btn btn-primary btn-sm"
                                onclick="openReceiptModal(
                                    '<?= htmlspecialchars($ref, ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($p['PaymentID'], ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($p['FullName'], ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($p['MemberID'], ENT_QUOTES) ?>',
                                    '<?= number_format((float)$p['Amount'], 2) ?>',
                                    '<?= htmlspecialchars($p['PaymentMethod'] ?? 'Cash', ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($p['PaymentDate'] ?? '-', ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($formattedTime, ENT_QUOTES) ?>'
                                )"
                            >
                                View Receipt
                            </button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                <tr id="noPayMatchRow" style="display: none;">
                    <td colspan="7" style="text-align:center; color:#94a3b8; padding:18px;">
                        No payment records found.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Official Receipt Pop-Up Modal -->
<div id="receiptModal" class="modal-backdrop">
    <div class="receipt-box">
        <div class="receipt-header">
            <h2 style="margin:0; letter-spacing:1px; color:#38bdf8;">FITMATE GYM</h2>
            <small style="color:#94a3b8;">Official Payment Receipt</small>
        </div>

        <div class="receipt-row"><span style="color:#94a3b8;">Reference #:</span><strong id="rec_ref"></strong></div>
        <div class="receipt-row"><span style="color:#94a3b8;">Payment ID:</span><span id="rec_pay_id"></span></div>
        <div class="receipt-row"><span style="color:#94a3b8;">Member Name:</span><strong id="rec_member"></strong></div>
        <div class="receipt-row"><span style="color:#94a3b8;">Member ID:</span><span id="rec_member_id"></span></div>
        <div class="receipt-row"><span style="color:#94a3b8;">Payment Method:</span><span id="rec_method"></span></div>
        <div class="receipt-row"><span style="color:#94a3b8;">Date & Time:</span><span id="rec_datetime"></span></div>

        <div class="receipt-total">
            <span>Amount Paid</span>
            <span id="rec_amount"></span>
        </div>

        <div style="display:flex; gap:10px; margin-top:20px;">
            <button type="button" class="btn btn-warning" style="flex:1;" onclick="closeReceiptModal()">Close</button>
            <button type="button" class="btn btn-primary" style="flex:1;" onclick="window.print()">Print Receipt</button>
        </div>
    </div>
</div>

<script>
function openReceiptModal(ref, payId, member, memberId, amount, method, date, time) {
    document.getElementById('rec_ref').textContent = ref;
    document.getElementById('rec_pay_id').textContent = payId;
    document.getElementById('rec_member').textContent = member;
    document.getElementById('rec_member_id').textContent = memberId;
    document.getElementById('rec_method').textContent = method;
    document.getElementById('rec_datetime').textContent = date + ' ' + time;
    document.getElementById('rec_amount').textContent = '₱' + amount;
    document.getElementById('receiptModal').classList.add('open');
}
function closeReceiptModal() {
    document.getElementById('receiptModal').classList.remove('open');
}
document.getElementById('receiptModal').addEventListener('click', function(e) {
    if (e.target === this) closeReceiptModal();
});

function filterPaymentTable() {
    const query = document.getElementById('livePaySearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#payTableBody .pay-row');
    const noMatchRow = document.getElementById('noPayMatchRow');
    let visibleCount = 0;

    rows.forEach(row => {
        const refNum     = row.cells[0].textContent.toLowerCase();
        const memberName = row.cells[1].textContent.toLowerCase();
        const amount     = row.cells[2].textContent.toLowerCase();
        const method     = row.cells[3].textContent.toLowerCase();
        const payDate    = row.cells[4].textContent.toLowerCase();
        const payTime    = row.cells[5].textContent.toLowerCase();

        if (
            refNum.includes(query) ||
            memberName.includes(query) ||
            amount.includes(query) ||
            method.includes(query) ||
            payDate.includes(query) ||
            payTime.includes(query)
        ) {
            row.style.display = 'table';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    if (noMatchRow) {
        noMatchRow.style.display = (rows.length > 0 && visibleCount === 0) ? 'table' : 'none';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>