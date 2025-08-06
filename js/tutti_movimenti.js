document.addEventListener('DOMContentLoaded', () => {
    const buttons = document.querySelectorAll('.months-scroll button');
    if (!buttons.length) return;

    const loadMovimenti = (mese, clickedBtn) => {
        buttons.forEach(btn => btn.classList.remove('active'));
        clickedBtn.classList.add('active');

        fetch(`ajax/load_movimenti_mese.php?mese=${encodeURIComponent(mese)}`)
            .then(resp => resp.text())
            .then(html => {
                document.getElementById('movimenti').innerHTML = html;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
    };

    buttons.forEach(btn => {
        btn.addEventListener('click', () => loadMovimenti(btn.dataset.mese, btn));
    });

    // Carica il primo mese all'apertura
    loadMovimenti(buttons[0].dataset.mese, buttons[0]);
});
