document.addEventListener('DOMContentLoaded', () => {
  const search = document.getElementById('search');
  const showInactive = document.getElementById('showInactive');
  const cards = Array.from(document.querySelectorAll('.label-card'));
  const filterAnnoInput = document.getElementById('filterAnno');
  const filterMeseInput = document.getElementById('filterMese');
  let filterAnno = '';
  let filterMese = '';

  function filter() {
    const q = search.value.trim().toLowerCase();
    cards.forEach(card => {
      const text = card.dataset.search || '';
      const isInactive = card.classList.contains('inactive');
      const matchText = text.includes(q);
      const matchAnno = !filterAnno || card.dataset.year === filterAnno;
      const matchMese = !filterMese || card.dataset.month === filterMese;
      const visible = matchText && matchAnno && matchMese && (!isInactive || showInactive.checked || q !== '' || filterAnno || filterMese);
      if (visible) {
        card.style.removeProperty('display');
      } else {
        card.style.setProperty('display', 'none', 'important');
      }
    });
  }

  document.getElementById('applyFilter').addEventListener('click', () => {
    filterAnno = filterAnnoInput.value.trim();
    filterMese = filterMeseInput.value.trim();
    filter();
    bootstrap.Modal.getInstance(document.getElementById('filterModal')).hide();
  });

  document.getElementById('clearFilter').addEventListener('click', () => {
    filterAnno = '';
    filterMese = '';
    filterAnnoInput.value = '';
    filterMeseInput.value = '';
    filter();
    bootstrap.Modal.getInstance(document.getElementById('filterModal')).hide();
  });

  search.addEventListener('input', filter);
  showInactive.addEventListener('input', filter);
  filter();
});

function openEtichettaModal() {
  document.getElementById('descrizione').value = '';
  document.getElementById('attivo').checked = true;
  document.getElementById('da_dividere').checked = false;
  document.getElementById('anno').value = '';
  document.getElementById('mese').value = '';
  document.getElementById('utenti_tra_cui_dividere').value = '';
  new bootstrap.Modal(document.getElementById('editEtichettaModal')).show();
}

function saveEtichetta(event) {
  event.preventDefault();
  const payload = {
    descrizione: document.getElementById('descrizione').value.trim(),
    attivo: document.getElementById('attivo').checked ? 1 : 0,
    da_dividere: document.getElementById('da_dividere').checked ? 1 : 0,
    anno: document.getElementById('anno').value ? parseInt(document.getElementById('anno').value, 10) : null,
    mese: document.getElementById('mese').value ? parseInt(document.getElementById('mese').value, 10) : null,
    utenti_tra_cui_dividere: document.getElementById('utenti_tra_cui_dividere').value.trim()
  };
  fetch('ajax/add_etichetta.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(payload)
  }).then(r => r.json()).then(resp => {
    if (resp.success) {
      window.location.reload();
    } else {
      alert(resp.error || 'Errore nel salvataggio');
    }
  });
}
