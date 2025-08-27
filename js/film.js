document.addEventListener('DOMContentLoaded', () => {
  const search = document.getElementById('search');
  const filterAnno = document.getElementById('filterAnno');
  const filterGenere = document.getElementById('filterGenere');
  const cards = Array.from(document.querySelectorAll('.film-card'));

  function filter() {
    const q = search.value.trim().toLowerCase();
    const anno = filterAnno.value;
    const genere = filterGenere.value;
    cards.forEach(card => {
      const text = (card.dataset.search || '');
      const matchSearch = text.includes(q);
      const matchAnno = !anno || card.dataset.anno === anno;
      const genres = (card.dataset.generi || '').split(',');
      const matchGenere = !genere || genres.includes(genere);
      const visible = matchSearch && matchAnno && matchGenere;
      if (visible) {
        card.style.removeProperty('display');
      } else {
        card.style.setProperty('display', 'none', 'important');
      }
    });
  }

  search.addEventListener('input', filter);
  filterAnno.addEventListener('input', filter);
  filterGenere.addEventListener('input', filter);
  filter();
});
