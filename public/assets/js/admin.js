const toggleEditBtn = document.getElementById('toggleEditBtn');
const editMemberCard = document.getElementById('editMemberCard');
if (toggleEditBtn && editMemberCard) {
    toggleEditBtn.addEventListener('click', () => {
        editMemberCard.classList.toggle('d-none');
        if (!editMemberCard.classList.contains('d-none')) {
            editMemberCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
}

const decisionForm = document.getElementById('decisionForm');
const reasonInput = document.getElementById('decision_reason');
const reasonErr = document.getElementById('decision_reason_error');

if (decisionForm && reasonInput) {
    let clickedAction = '';
    const approveBtn = document.getElementById('approveBtn');
    const rejectBtn = document.getElementById('rejectBtn');

    approveBtn?.addEventListener('click', () => {
        clickedAction = 'approve';
    });
    rejectBtn?.addEventListener('click', () => {
        clickedAction = 'reject';
    });

    reasonInput.addEventListener('input', () => {
        reasonInput.classList.remove('is-invalid');
        if (reasonErr) reasonErr.textContent = '';
    });

    decisionForm.addEventListener('submit', (ev) => {
        if (clickedAction === 'reject' && reasonInput.value.trim() === '') {
            ev.preventDefault();
            reasonInput.classList.add('is-invalid');
            if (reasonErr) reasonErr.textContent = 'Enter a rejection reason before rejecting this application.';
            reasonInput.focus();
            return;
        }
        if (approveBtn) approveBtn.disabled = true;
        if (rejectBtn) rejectBtn.disabled = true;
    });
}

