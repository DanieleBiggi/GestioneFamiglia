document.addEventListener('DOMContentLoaded', () => {
    const buttons = Array.from(document.querySelectorAll('.months-scroll button'));
    if (!buttons.length) return;

    const monthsContainer = document.getElementById('monthsContainer');
    monthsContainer.scrollLeft = monthsContainer.scrollWidth;

    const yearSelect = document.getElementById('yearSelector');
    const mesi = buttons.map(btn => btn.dataset.mese);
    const movimenti = document.getElementById('movimenti');
    let minIdx = mesi.length - 1;
    let maxIdx = mesi.length - 1;
    // Evita il caricamento automatico dovuto allo scroll programmato
    let suppressScrollLoad = false;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const ym = entry.target.dataset.mese;
                const idx = mesi.indexOf(ym);
                if (idx !== -1) {
                    buttons.forEach((btn, i) => btn.classList.toggle('active', i === idx));
                    buttons[idx].scrollIntoView({ inline: 'center', behavior: 'smooth' });
                    if (yearSelect) {
                        yearSelect.value = ym.slice(0, 4);
                    }
                }
            }
        });
    }, { rootMargin: '-50% 0px -50% 0px', threshold: 0 });

    const observeSections = () => {
        document.querySelectorAll('.month-section').forEach(sec => {
            if (!sec.dataset.observed) {
                observer.observe(sec);
                sec.dataset.observed = '1';
            }
        });
    };

    const bindMovimenti = () => {
        document.querySelectorAll('.movement').forEach(el => {
            if (el.dataset.bound) return;
            el.dataset.bound = '1';
            el.addEventListener('click', () => {
                sessionStorage.setItem('tmScroll', window.scrollY);
                sessionStorage.setItem('tmMonth', el.dataset.mese);
                window.location.href = el.dataset.href;
            });
        });
    };

    const loadMovimenti = (idx, mode = 'replace', setActive = true) => {
        return fetch(`ajax/load_movimenti_mese.php?mese=${encodeURIComponent(mesi[idx])}`)
            .then(r => r.text())
            .then(html => {
                if (mode === 'append') {
                    movimenti.insertAdjacentHTML('beforeend', html);
                } else if (mode === 'prepend') {
                    movimenti.insertAdjacentHTML('afterbegin', html);
                } else {
                    movimenti.innerHTML = html;
                    // Imposta un flag per ignorare l'evento scroll generato dal repositioning
                    suppressScrollLoad = true;
                    window.scrollTo({ top: 0 });
                    // Rimuove il flag dopo il completamento dello scroll
                    setTimeout(() => { suppressScrollLoad = false; }, 100);
                }
                observeSections();
                bindMovimenti();
                if (setActive) {
                    buttons.forEach((btn, i) => btn.classList.toggle('active', i === idx));
                    if (yearSelect) {
                        yearSelect.value = mesi[idx].slice(0,4);
                    }
                }
            });
    };

    buttons.forEach((btn, idx) => {
        btn.addEventListener('click', () => {
            suppressScrollLoad = true;
            minIdx = maxIdx = idx;
            loadMovimenti(idx).then(() => {
                setTimeout(() => { suppressScrollLoad = false; }, 100);
            });
            btn.scrollIntoView({ inline: 'center', behavior: 'smooth' });
        });
    });

    if (yearSelect) {
        yearSelect.addEventListener('change', () => {
            const year = yearSelect.value;
            const idx = mesi.findIndex(m => m.startsWith(year));
            if (idx !== -1) {
                minIdx = maxIdx = idx;
                loadMovimenti(idx);
                buttons[idx].scrollIntoView({ inline: 'center', behavior: 'smooth' });
            }
        });
    }

    const savedMonth = sessionStorage.getItem('tmMonth');
    const savedScroll = sessionStorage.getItem('tmScroll');

    const restoreScroll = () => {
        if (savedScroll !== null) {
            window.scrollTo(0, parseFloat(savedScroll));
            if (savedMonth) {
                const idx = mesi.indexOf(savedMonth);
                if (idx !== -1) {
                    buttons.forEach((btn, i) => btn.classList.toggle('active', i === idx));
                    if (yearSelect) {
                        yearSelect.value = mesi[idx].slice(0,4);
                    }
                }
            }
            sessionStorage.removeItem('tmScroll');
            sessionStorage.removeItem('tmMonth');
        }
    };

    const loadInitial = () => {
        if (savedMonth) {
            const targetIdx = mesi.indexOf(savedMonth);
            if (targetIdx !== -1) {
                minIdx = maxIdx = mesi.length - 1;
                return loadMovimenti(maxIdx).then(function loadOlder() {
                    if (minIdx > targetIdx) {
                        minIdx--;
                        return loadMovimenti(minIdx, 'append', false).then(loadOlder);
                    } else {
                        restoreScroll();
                    }
                });
            }
        }
        return loadMovimenti(maxIdx).then(restoreScroll);
    };

    loadInitial();

    window.addEventListener('scroll', () => {
        if (suppressScrollLoad) return;
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
