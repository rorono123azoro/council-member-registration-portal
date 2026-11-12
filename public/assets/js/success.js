const printBtn = document.getElementById('printAckBtn');
if (printBtn) {
    printBtn.addEventListener('click', () => {
        window.print();
    });
}

