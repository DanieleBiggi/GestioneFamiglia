document.addEventListener('DOMContentLoaded', () => {
  const search = document.getElementById('search');
  const showInactive = document.getElementById('showInactive');
  const cards = Array.from(document.querySelectorAll('.evento-tipo-card'));

  function filter() {
    const q = search.value.trim().toLowerCase();
    cards.forEach(card => {
      const text = card.dataset.search || '';
      const isInactive = card.classList.contains('inactive');
      const match = text.includes(q);
      const visible = match && (!isInactive || showInactive.checked || q !== '');
      if (visible) {
        card.style.removeProperty('display');
      } else {
        card.style.setProperty('display', 'none', 'important');
      }
    });
  }

  search.addEventListener('input', filter);
  showInactive.addEventListener('input', filter);
  filter();
});
