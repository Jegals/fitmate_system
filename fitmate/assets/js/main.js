// Shared helper para sa confirmation prompts at tabs
function confirmAction(message) {
    return confirm(message || "Are you sure you want to proceed?");
}

function openProfileTab(evt, tabId) {
    const contents = document.querySelectorAll('.profile-tab-content');
    contents.forEach(c => c.style.display = 'none');

    const buttons = document.querySelectorAll('.tab-btn');
    buttons.forEach(b => b.classList.remove('active'));

    const target = document.getElementById(tabId);
    if (target) target.style.display = 'block';
    if (evt && evt.currentTarget) evt.currentTarget.classList.add('active');
}