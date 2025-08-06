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
});
