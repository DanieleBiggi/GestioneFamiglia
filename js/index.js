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
        saveBtn.addEventListener('click', () => {
            const form = document.getElementById('salvadanaiForm');
            const hidden = [...form.querySelectorAll('input[name="hidden[]"]:checked')].map(el => el.value);
            const prefInput = form.querySelector('input[name="preferito"]:checked');
            const preferito = prefInput ? prefInput.value : null;
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
