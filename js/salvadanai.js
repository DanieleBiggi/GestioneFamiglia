document.addEventListener('DOMContentLoaded', () => {
  const search = document.getElementById('search');
  const showExpired = document.getElementById('showExpired');
  const cards = Array.from(document.querySelectorAll('.salvadanaio-card'));
  function filter() {
    const q = search.value.trim().toLowerCase();
    const showAll = showExpired.checked;
    cards.forEach(card => {
      const text = card.dataset.search || '';
      const expired = card.dataset.scaduto === '1';
      const match = text.includes(q) && (showAll || !expired);
      if (match) {
        card.style.removeProperty('display');
      } else {
        card.style.setProperty('display', 'none', 'important');
      }
    });
  }
  search.addEventListener('input', filter);
  showExpired.addEventListener('change', filter);
  filter();
});
