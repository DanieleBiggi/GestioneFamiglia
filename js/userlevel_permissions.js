document.addEventListener('DOMContentLoaded', () => {
  const userlevelFilter = document.getElementById('filterUserlevel');
  const resourceFilter = document.getElementById('filterResource');
  const cards = Array.from(document.querySelectorAll('.permission-card'));

  function filter() {
    const ul = userlevelFilter.value;
    const res = resourceFilter.value;
    cards.forEach(card => {
      const matchUl = !ul || card.dataset.userlevel === ul;
      const matchRes = !res || card.dataset.resource === res;
      const visible = matchUl && matchRes;
      if (visible) {
        card.style.removeProperty('display');
      } else {
        card.style.setProperty('display', 'none', 'important');
      }
    });
  }

  userlevelFilter.addEventListener('change', filter);
  resourceFilter.addEventListener('change', filter);
  filter();

  // Auto-submit permission form when a checkbox is toggled
  document
    .querySelectorAll('.permission-card input[type="checkbox"]')
    .forEach(cb => {
      cb.addEventListener('change', () => {
        cb.form.submit();
      });
    });
});
