document.addEventListener('DOMContentLoaded', () => {
  const search = document.getElementById('search');
  const showInactive = document.getElementById('showInactive');
  const cards = Array.from(document.querySelectorAll('.label-card'));

  function filter() {
    const q = search.value.trim().toLowerCase();
    cards.forEach(card => {
      const text = card.dataset.search || '';
      const isInactive = card.classList.contains('inactive');
      const match = text.includes(q);
      const visible = match && (!isInactive || showInactive.checked || q !== '');
      //card.style.display = visible ? '' : 'none';
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

function openEtichettaModal() {
  document.getElementById('descrizione').value = '';
  document.getElementById('attivo').checked = true;
  document.getElementById('da_dividere').checked = false;
  document.getElementById('utenti_tra_cui_dividere').value = '';
  new bootstrap.Modal(document.getElementById('editEtichettaModal')).show();
}

function saveEtichetta(event) {
  event.preventDefault();
  const payload = {
    descrizione: document.getElementById('descrizione').value.trim(),
    attivo: document.getElementById('attivo').checked ? 1 : 0,
    da_dividere: document.getElementById('da_dividere').checked ? 1 : 0,
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
