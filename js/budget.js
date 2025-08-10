document.addEventListener('DOMContentLoaded', () => {
  const search = document.getElementById('search');
  const items = Array.from(document.querySelectorAll('.budget-item'));

  function filter() {
    const q = search.value.trim().toLowerCase();
    items.forEach(item => {
      const text = (item.textContent || '').toLowerCase();
      item.style.display = text.includes(q) ? '' : 'none';
    });
  }

  if (search) {
    search.addEventListener('input', filter);
  }
});
