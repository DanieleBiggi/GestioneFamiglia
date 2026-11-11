document.addEventListener('DOMContentLoaded', () => {
  const search = document.getElementById('search');
  const showArchived = document.getElementById('showArchived');
  const cards = Array.from(document.querySelectorAll('.query-card'));

  function filter() {
    const q = search.value.trim().toLowerCase();
    cards.forEach(card => {
      const text = card.dataset.search || '';
      const isArchived = card.classList.contains('archiviato');
      const match = text.includes(q);
      const visible = match && (!isArchived || showArchived.checked || q !== '');
      if (visible) {
        card.style.removeProperty('display');
      } else {
        card.style.setProperty('display', 'none', 'important');
      }
    });
  }

  search.addEventListener('input', filter);
  showArchived.addEventListener('input', filter);
  filter();

  // I link Esegui aprono query_execute.php, che restituisce una pagina HTML.
  // Non intercettarli con fetch/JSON: il risultato viene visualizzato normalmente.
});
