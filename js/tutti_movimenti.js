document.addEventListener('DOMContentLoaded', () => {
    const buttons = Array.from(document.querySelectorAll('.months-scroll button'));
    if (!buttons.length) return;

    const monthsContainer = document.getElementById('monthsContainer');
    monthsContainer.scrollLeft = monthsContainer.scrollWidth;

    const mesi = buttons.map(btn => btn.dataset.mese);
    const movimenti = document.getElementById('movimenti');
    let minIdx = mesi.length - 1;
    let maxIdx = mesi.length - 1;

    const loadMovimenti = (idx, mode = 'replace', setActive = true) => {
        fetch(`ajax/load_movimenti_mese.php?mese=${encodeURIComponent(mesi[idx])}`)
            .then(r => r.text())
            .then(html => {
                if (mode === 'append') {
                    movimenti.insertAdjacentHTML('beforeend', html);
                } else if (mode === 'prepend') {
                    movimenti.insertAdjacentHTML('afterbegin', html);
                } else {
                    movimenti.innerHTML = html;
                    window.scrollTo({ top: 0 });
                }
                if (setActive) {
                    buttons.forEach((btn, i) => btn.classList.toggle('active', i === idx));
                }
            });
    };

    buttons.forEach((btn, idx) => {
        btn.addEventListener('click', () => {
            minIdx = maxIdx = idx;
            loadMovimenti(idx);
            btn.scrollIntoView({ inline: 'center', behavior: 'smooth' });
        });
    });

    loadMovimenti(maxIdx);

    window.addEventListener('scroll', () => {
        if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 50) {
            if (minIdx > 0) {
                minIdx--;
                loadMovimenti(minIdx, 'append', false);
            }
        } else if (window.scrollY === 0) {
            if (maxIdx < mesi.length - 1) {
                maxIdx++;
                loadMovimenti(maxIdx, 'prepend', false);
            }
        }
    });
});
