document.addEventListener('DOMContentLoaded', () => {
  const search = document.getElementById('search');
  const cards = Array.from(document.querySelectorAll('.tema-card'));

  function filter() {
    const q = search.value.trim().toLowerCase();
    cards.forEach(card => {
      const text = card.dataset.search || '';
      const visible = text.includes(q);
      if (visible) {
        card.style.removeProperty('display');
      } else {
        card.style.setProperty('display', 'none', 'important');
      }
    });
  }

  search.addEventListener('input', filter);
  filter();
});
