document.addEventListener('DOMContentLoaded', () => {
  const searchBtn = document.getElementById('searchBtn');
  const queryInput = document.getElementById('query');
  const resultsDiv = document.getElementById('searchResults');
  const message = document.getElementById('filmImportMessage');
  const initialQuery = new URLSearchParams(window.location.search).get('q');
  if (initialQuery) queryInput.value = initialQuery;

  function showMessage(text, error = false) {
    message.textContent = text;
    message.className = 'alert ' + (error ? 'alert-danger' : 'alert-info');
  }

  async function searchMovies() {
    const q = queryInput.value.trim();
    if (!q) return;
    searchBtn.disabled = true;
    resultsDiv.replaceChildren();
    message.classList.add('d-none');
    try {
      const response = await fetch('ajax/film_search.php?q=' + encodeURIComponent(q));
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.error || 'Ricerca non disponibile');
      if (!data.results.length) showMessage('Nessun risultato trovato su TMDB.');
      data.results.forEach(movie => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-outline-secondary w-100 text-start mb-2';
        const year = (movie.release_date || '').slice(0, 4);
        const present = movie.existing_film_id !== null;
        btn.textContent = movie.title + (year ? ' (' + year + ')' : '') + (present ? ' · Già nella raccolta' : ' · Aggiungi');
        btn.addEventListener('click', () => {
          if (present) {
            window.location.href = 'film_dettaglio.php?id=' + movie.existing_film_id;
          } else {
            importMovie(movie.id, btn);
          }
        });
        resultsDiv.appendChild(btn);
      });
    } catch (error) {
      showMessage(error.message, true);
    } finally {
      searchBtn.disabled = false;
    }
  }

  async function importMovie(tmdbId, btn) {
    btn.disabled = true;
    try {
      const response = await fetch('ajax/film_import.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ tmdb_id: tmdbId })
      });
      const data = await response.json();
      if (!response.ok || !data.success || !data.id_film) throw new Error(data.error || 'Impossibile aggiungere il film');
      window.location.href = 'film_dettaglio.php?id=' + data.id_film;
    } catch (error) {
      showMessage(error.message, true);
      btn.disabled = false;
    }
  }

  searchBtn.addEventListener('click', searchMovies);
  queryInput.addEventListener('keydown', event => {
    if (event.key === 'Enter') searchMovies();
  });
  if (initialQuery) searchMovies();
});
