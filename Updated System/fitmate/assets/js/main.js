// Shared UI helpers
document.addEventListener('DOMContentLoaded', () => {
    console.log('FITMATE System Loaded');
});

/* Reusable Instant Live Search */
function attachLiveSearch(inputId, tbodyId, colSpan) {
    const input = document.getElementById(inputId);
    const tbody = document.getElementById(tbodyId);
    if (!input || !tbody) return;

    input.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        const rows = tbody.querySelectorAll('tr');
        let visibleCount = 0;

        rows.forEach(row => {
            if (row.classList.contains('no-match-row')) return;
            const match = row.textContent.toLowerCase().includes(query);
            row.style.display = match ? '' : 'none';
            if (match) visibleCount++;
        });

        let noMatchRow = tbody.querySelector('.no-match-row');
        if (visibleCount === 0) {
            if (!noMatchRow) {
                noMatchRow = document.createElement('tr');
                noMatchRow.className = 'no-match-row';
                noMatchRow.innerHTML = `<td colspan="${colSpan}">No matching records found.</td>`;
                tbody.appendChild(noMatchRow);
            }
            noMatchRow.style.display = '';
        } else if (noMatchRow) {
            noMatchRow.style.display = 'none';
        }
    });
}

/* Auto-attach live search for Billing pages */
document.addEventListener('DOMContentLoaded', function() {
    attachLiveSearch('subSearchInput', 'subTableBody', 7);
    attachLiveSearch('planSearchInput', 'planTableBody', 5);
    attachLiveSearch('paySearchInput', 'payTableBody', 7);

    // Close any open modal when clicking its dark backdrop
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
        backdrop.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('open');
            }
        });
    });
});