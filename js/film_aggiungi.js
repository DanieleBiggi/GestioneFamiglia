document.addEventListener('DOMContentLoaded', () => {
  const searchBtn = document.getElementById('searchBtn');
  const queryInput = document.getElementById('query');
  const resultsDiv = document.getElementById('searchResults');

  searchBtn.addEventListener('click', () => {
    const q = queryInput.value.trim();
    if (!q || !TMDB_API_KEY) return;
    fetch(`https://api.themoviedb.org/3/search/movie?api_key=${TMDB_API_KEY}&language=it-IT&query=${encodeURIComponent(q)}`)
      .then(r => r.json())
      .then(data => {
        resultsDiv.innerHTML = '';
        (data.results || []).forEach(movie => {
          const btn = document.createElement('button');
          btn.className = 'btn btn-outline-light w-100 text-start mb-2';
          const year = (movie.release_date || '').slice(0,4);
          btn.textContent = `${movie.title} (${year})`;
          btn.addEventListener('click', () => importMovie(movie));
          resultsDiv.appendChild(btn);
        });
      });
  });

  function importMovie(movie) {
    const data_visto = prompt('Data visto (YYYY-MM-DD)', '');
    const voto = prompt('Voto (1-10, es 8.5)', '');
    const commento = prompt('Commento', '');
    fetch('ajax/film_import.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        tmdb_id: movie.id,
        data_visto,
        voto,
        commento
      })
    })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        alert('Film importato');
      } else {
        alert(res.error || 'Errore');
      }
    });
  }
});
