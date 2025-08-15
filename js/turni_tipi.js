document.addEventListener('DOMContentLoaded', () => {
  const search = document.getElementById('search');
  const showInactive = document.getElementById('showInactive');
  const rows = Array.from(document.querySelectorAll('.type-row'));
  const modalEl = document.getElementById('typeModal');
  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById('typeForm');

  function filter() {
    const q = search.value.trim().toLowerCase();
    rows.forEach(row => {
      const text = row.dataset.search || '';
      const isInactive = row.classList.contains('inactive');
      const match = text.includes(q);
      const visible = match && (!isInactive || showInactive.checked || q !== '');
      row.style.display = visible ? '' : 'none';
    });
  }

  search.addEventListener('input', filter);
  showInactive.addEventListener('input', filter);
  filter();

  function openModal(row) {
    if (row) {
      document.getElementById('typeId').value = row.dataset.id;
      document.getElementById('descrizione').value = row.dataset.descrizione;
      document.getElementById('ora_inizio').value = row.dataset.ora_inizio;
      document.getElementById('ora_fine').value = row.dataset.ora_fine;
      document.getElementById('colore_bg').value = row.dataset.colore_bg;
      document.getElementById('colore_testo').value = row.dataset.colore_testo;
      document.getElementById('attivo').checked = row.dataset.attivo === '1';
    } else {
      form.reset();
      document.getElementById('typeId').value = '';
      document.getElementById('ora_inizio').value = '';
      document.getElementById('ora_fine').value = '';
      document.getElementById('colore_bg').value = '#ffffff';
      document.getElementById('colore_testo').value = '#000000';
      document.getElementById('attivo').checked = true;
    }
    modal.show();
  }

  document.getElementById('addType').addEventListener('click', () => openModal(null));
  rows.forEach(row => row.addEventListener('click', () => openModal(row)));

  document.getElementById('saveType').addEventListener('click', () => {
    const formData = new FormData(form);
    fetch('ajax/turni_tipi_save.php', { method: 'POST', body: formData })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          location.reload();
        }
      });
  });
});
