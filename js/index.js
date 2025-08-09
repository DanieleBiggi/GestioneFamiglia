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
});
