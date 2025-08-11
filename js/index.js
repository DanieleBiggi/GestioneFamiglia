document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('search');
    const results = document.getElementById('searchResults');
    const recent = document.getElementById('recentMovimenti');

    input.addEventListener('input', () => {
        const q = input.value.trim();
        if (q === '') {
            results.innerHTML = '';
            recent.style.display = 'block';
            return;
        }
        fetch(`ajax/search_movimenti.php?q=${encodeURIComponent(q)}`)
            .then(r => r.text())
            .then(html => {
                results.innerHTML = html;
                recent.style.display = 'none';
                bindMovimenti();
            });
    });
    
    
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
    
    bindMovimenti();

    const saveBtn = document.getElementById('saveSalvadanai');
    if (saveBtn) {
        document.querySelectorAll('.salvadanaio-item').forEach(item => {
            const favIcon = item.querySelector('.toggle-preferito');
            const hideIcon = item.querySelector('.toggle-nascosto');

            favIcon.addEventListener('click', () => {
                document.querySelectorAll('.salvadanaio-item').forEach(it => {
                    it.dataset.preferito = '0';
                    const ic = it.querySelector('.toggle-preferito');
                    ic.classList.remove('bi-star-fill', 'text-warning');
                    ic.classList.add('bi-star');
                });
                item.dataset.preferito = '1';
                favIcon.classList.remove('bi-star');
                favIcon.classList.add('bi-star-fill', 'text-warning');
            });

            hideIcon.addEventListener('click', () => {
                if (item.dataset.nascosto === '1') {
                    item.dataset.nascosto = '0';
                    hideIcon.classList.remove('bi-eye-slash');
                    hideIcon.classList.add('bi-eye');
                } else {
                    item.dataset.nascosto = '1';
                    hideIcon.classList.remove('bi-eye');
                    hideIcon.classList.add('bi-eye-slash');
                    if (item.dataset.preferito === '1') {
                        item.dataset.preferito = '0';
                        const ic = item.querySelector('.toggle-preferito');
                        ic.classList.remove('bi-star-fill', 'text-warning');
                        ic.classList.add('bi-star');
                    }
                }
            });
        });

        saveBtn.addEventListener('click', () => {
            const items = document.querySelectorAll('.salvadanaio-item');
            const hidden = [];
            let preferito = null;
            items.forEach(it => {
                if (it.dataset.nascosto === '1') hidden.push(it.dataset.id);
                if (it.dataset.preferito === '1') preferito = it.dataset.id;
            });
            fetch('ajax/salvadanai_prefs.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ hidden: hidden, preferito: preferito })
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        });
    }
});
