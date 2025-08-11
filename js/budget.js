document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('search');
  const filterBtn = document.getElementById('filterBtn');
  const filterModalEl = document.getElementById('filterModal');
  const filterForm = document.getElementById('filterForm');
  const filterTipologia = document.getElementById('filterTipologia');
  const filterSalvadanaio = document.getElementById('filterSalvadanaio');
  const filterTipologiaSpesa = document.getElementById('filterTipologiaSpesa');
  const filterDataInizio = document.getElementById('filterDataInizio');
  const filterDataFine = document.getElementById('filterDataFine');

  const addBudgetBtn = document.getElementById('addBudgetBtn');
  const budgetModalEl = document.getElementById('budgetModal');
  const budgetForm = document.getElementById('budgetForm');
  const budgetId = document.getElementById('budgetId');
  const budgetDescrizione = document.getElementById('budgetDescrizione');
  const budgetTipologia = document.getElementById('budgetTipologia');
  const budgetTipologiaSpesa = document.getElementById('budgetTipologiaSpesa');
  const budgetImporto = document.getElementById('budgetImporto');
  const budgetDataInizio = document.getElementById('budgetDataInizio');
  const budgetDataFine = document.getElementById('budgetDataFine');
  const deleteBudgetBtn = document.getElementById('deleteBudget');
  const duplicateBudgetBtn = document.getElementById('duplicateBudget');
  const modalTitle = budgetModalEl?.querySelector('.modal-title');

  const items = Array.from(document.querySelectorAll('.budget-item'));

  const filterModal = (typeof bootstrap !== 'undefined' && filterModalEl)
    ? new bootstrap.Modal(filterModalEl)
    : null;
  const budgetModal = (typeof bootstrap !== 'undefined' && budgetModalEl)
    ? new bootstrap.Modal(budgetModalEl)
    : null;

  function populateSelect(select, values) {
    if (!select) return;
    const frag = document.createDocumentFragment();
    Array.from(values).sort().forEach(v => {
      const opt = document.createElement('option');
      opt.value = v;
      opt.textContent = v;
      frag.appendChild(opt);
    });
    select.appendChild(frag);
  }

  const tipologie = new Set();
  const salvadanai = new Set();
  const tipologieSpesa = new Set();
  items.forEach(it => {
    if (it.dataset.tipologia) tipologie.add(it.dataset.tipologia);
    if (it.dataset.salvadanaio) salvadanai.add(it.dataset.salvadanaio);
    if (it.dataset.tipologiaSpesa) tipologieSpesa.add(it.dataset.tipologiaSpesa);
  });
  populateSelect(filterTipologia, tipologie);
  populateSelect(filterSalvadanaio, salvadanai);
  populateSelect(filterTipologiaSpesa, tipologieSpesa);
  populateSelect(budgetTipologia, tipologie);
  populateSelect(budgetTipologiaSpesa, tipologieSpesa);

  function applyFilters() {
    const q = (searchInput?.value || '').trim().toLowerCase();
    const t = filterTipologia?.value || '';
    const s = filterSalvadanaio?.value || '';
    const ts = filterTipologiaSpesa?.value || '';
    const dIn = filterDataInizio?.value || '';
    const dFin = filterDataFine?.value || '';
    items.forEach(item => {
      const text = item.dataset.search || '';
      const matchSearch = text.includes(q);
      const matchTipologia = !t || item.dataset.tipologia === t;
      const matchSalvadanaio = !s || item.dataset.salvadanaio === s;
      const matchTipologiaSpesa = !ts || item.dataset.tipologiaSpesa === ts;
      const start = item.dataset.inizio || '';
      const end = item.dataset.fine || '';
      let matchDate = true;
      if (dIn) {
        matchDate = matchDate && start >= dIn;
      }
      if (dFin) {
        if (end) {
          matchDate = matchDate && end <= dFin;
        } else {
          matchDate = matchDate && start <= dFin;
        }
      }
      const visible = matchSearch && matchTipologia && matchSalvadanaio && matchTipologiaSpesa && matchDate;
      item.classList.toggle('d-none', !visible);
      item.classList.toggle('d-flex', visible);
    });
  }

  searchInput?.addEventListener('input', applyFilters);

  filterBtn?.addEventListener('click', () => {
    filterModal?.show();
  });

  filterForm?.addEventListener('submit', e => {
    e.preventDefault();
    applyFilters();
    filterModal?.hide();
  });

  addBudgetBtn?.addEventListener('click', () => {
    budgetForm?.reset();
    if (budgetId) budgetId.value = '';
    if (budgetTipologia) budgetTipologia.value = '';
    if (budgetTipologiaSpesa) budgetTipologiaSpesa.value = '';
    if (modalTitle) modalTitle.textContent = 'Nuovo budget';
    deleteBudgetBtn?.classList.add('d-none');
    budgetModal?.show();
  });

  items.forEach(item => {
    item.addEventListener('click', () => {
      budgetForm?.reset();
      if (budgetId) budgetId.value = item.dataset.id || '';
      if (budgetDescrizione) budgetDescrizione.value = item.dataset.descrizione || '';
      if (budgetTipologia) budgetTipologia.value = item.dataset.tipologia || '';
      if (budgetTipologiaSpesa) budgetTipologiaSpesa.value = item.dataset.tipologiaSpesa || '';
      if (budgetImporto) budgetImporto.value = item.dataset.importo || '';
      if (budgetDataInizio) budgetDataInizio.value = item.dataset.inizio || '';
      if (budgetDataFine) budgetDataFine.value = item.dataset.fine || '';
      if (modalTitle) modalTitle.textContent = 'Modifica budget';
      deleteBudgetBtn?.classList.remove('d-none');
      budgetModal?.show();
    });
  });

  deleteBudgetBtn?.addEventListener('click', () => {
    const id = budgetId?.value;
    if (!id) return;
    if (!confirm('Eliminare questo budget?')) return;
    const fd = new FormData();
    fd.append('id', id);
    fetch('ajax/delete_budget.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          const idx = items.findIndex(it => it.dataset.id === id);
          if (idx !== -1) {
            items[idx].remove();
            items.splice(idx, 1);
          }
          budgetModal?.hide();
          applyFilters();
        } else {
          alert(res.error || 'Errore');
        }
      });
  });

  duplicateBudgetBtn?.addEventListener('click', () => {
    if (budgetId) budgetId.value = '';
    if (modalTitle) modalTitle.textContent = 'Nuovo budget';
  });

  applyFilters();
});

