document.addEventListener('DOMContentLoaded', () => {
  const search = document.getElementById('search');
  const onlyActive = document.getElementById('onlyActive');
  const cards = Array.from(document.querySelectorAll('.rule-card'));
  function filter() {
    const q = search.value.trim().toLowerCase();
    const activeOnly = onlyActive.checked;
    cards.forEach(card => {
      const text = card.dataset.search || '';
      const isActive = card.dataset.active === '1';
      const match = text.includes(q) && (!activeOnly || isActive);
      card.style.display = match ? '' : 'none';
    });
  }
  search.addEventListener('input', filter);
  onlyActive.addEventListener('change', filter);
  filter();
});
