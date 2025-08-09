document.addEventListener('DOMContentLoaded', () => {
  const userlevelFilter = document.getElementById('filterUserlevel');
  const resourceSearch = document.getElementById('searchResource');
  const cards = Array.from(document.querySelectorAll('.permission-card'));

  function filter() {
    const ul = userlevelFilter.value;
    const query = resourceSearch.value.toLowerCase();
    cards.forEach(card => {
      const matchUl = !ul || card.dataset.userlevel === ul;
      const matchRes = !query || card.dataset.resourceName.includes(query);
      const visible = matchUl && matchRes;
      if (visible) {
        card.style.removeProperty('display');
      } else {
        card.style.setProperty('display', 'none', 'important');
      }
    });
  }

  userlevelFilter.addEventListener('change', filter);
  resourceSearch.addEventListener('input', filter);
  filter();

  // Save permission changes via AJAX when a checkbox is toggled
  document
    .querySelectorAll('.permission-card input[type="checkbox"]')
    .forEach(cb => {
      cb.addEventListener('change', () => {
        const form = cb.form;
        const formData = new FormData(form);
        fetch('userlevel_permissions.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: formData
        }).catch(err => console.error('Errore salvataggio permesso', err));
      });
    });
});
