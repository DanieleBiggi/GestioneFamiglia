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

  document.getElementById('queryList').addEventListener('click', e => {
    const btn = e.target.closest('.run-query');
    if (btn) {
      const id = btn.dataset.id;
      btn.disabled = true;
      fetch('query_execute.php?id=' + encodeURIComponent(id))
        .then(r => r.json())
        .then(data => {
          alert(JSON.stringify(data, null, 2));
        })
        .catch(err => alert('Errore: ' + err))
        .finally(() => { btn.disabled = false; });
    }
  });
});
