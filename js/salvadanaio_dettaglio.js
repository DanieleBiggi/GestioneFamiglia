document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('editSalvadanaioBtn')?.addEventListener('click', () => {
    const form = document.getElementById('salvadanaioForm');
    form.nome_salvadanaio.value = salvadanaioData.nome_salvadanaio || '';
    form.importo_attuale.value = salvadanaioData.importo_attuale || '';
    new bootstrap.Modal(document.getElementById('salvadanaioModal')).show();
  });

  document.getElementById('salvadanaioForm')?.addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this);
    fetch('ajax/update_salvadanaio.php', {method:'POST', body:fd})
      .then(r=>r.json())
      .then(res=>{ if(res.success) location.reload(); else alert(res.error||'Errore'); });
  });

  document.getElementById('addSeBtn')?.addEventListener('click', () => {
    const form = document.getElementById('addSeForm');
    form.reset();
    new bootstrap.Modal(document.getElementById('addSeModal')).show();
  });

  document.getElementById('addSeForm')?.addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this);
    fetch('ajax/add_e2se.php', {method:'POST', body:fd})
      .then(r=>r.json())
      .then(res=>{ if(res.success) location.reload(); else alert(res.error||'Errore'); });
  });

  document.querySelectorAll('#finanzeList .se-row').forEach(li => {
    li.addEventListener('click', () => {
      const form = document.getElementById('seForm');
      form.id_e2se.value = li.dataset.id;
      form.id_evento.value = li.dataset.idEvento;
      form.id_etichetta.value = li.dataset.idEtichetta;
      new bootstrap.Modal(document.getElementById('seModal')).show();
    });
  });

  document.getElementById('seForm')?.addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this);
    fetch('ajax/update_e2se.php', {method:'POST', body:fd})
      .then(r=>r.json())
      .then(res=>{ if(res.success) location.reload(); else alert(res.error||'Errore'); });
  });

  document.getElementById('deleteSeBtn')?.addEventListener('click', function(){
    const id = document.getElementById('seForm')?.id_e2se.value;
    if(!id || !confirm('Eliminare questo collegamento?')) return;
    const fd = new FormData();
    fd.append('id_e2se', id);
    fetch('ajax/delete_e2se.php', {method:'POST', body:fd})
      .then(r=>r.json())
      .then(res=>{ if(res.success) location.reload(); else alert(res.error||'Errore'); });
  });

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
  const budgetItems = Array.from(document.querySelectorAll('.budget-item'));
  const budgetModal = (typeof bootstrap !== 'undefined' && budgetModalEl) ? new bootstrap.Modal(budgetModalEl) : null;

  addBudgetBtn?.addEventListener('click', () => {
    budgetForm?.reset();
    if (budgetId) budgetId.value = '';
    deleteBudgetBtn?.classList.add('d-none');
    budgetModal?.show();
  });

  budgetItems.forEach(item => {
    item.addEventListener('click', () => {
      budgetForm?.reset();
      if (budgetId) budgetId.value = item.dataset.id || '';
      if (budgetDescrizione) budgetDescrizione.value = item.dataset.descrizione || '';
      if (budgetTipologia) budgetTipologia.value = item.dataset.tipologia || '';
      if (budgetTipologiaSpesa) budgetTipologiaSpesa.value = item.dataset.tipologiaSpesa || '';
      if (budgetImporto) budgetImporto.value = item.dataset.importo || '';
      if (budgetDataInizio) budgetDataInizio.value = item.dataset.inizio || '';
      if (budgetDataFine) budgetDataFine.value = item.dataset.fine || '';
      deleteBudgetBtn?.classList.remove('d-none');
      budgetModal?.show();
    });
  });

  budgetForm?.addEventListener('submit', e => {
    e.preventDefault();
    const fd = new FormData(budgetForm);
    fd.append('action', 'save');
    fetch('ajax/budget_manage.php', {method:'POST', body:fd})
      .then(r=>r.json())
      .then(res=>{ if(res.success) location.reload(); else alert(res.error||'Errore'); });
  });

  deleteBudgetBtn?.addEventListener('click', () => {
    const id = budgetId?.value;
    if(!id || !confirm('Eliminare questo budget?')) return;
    const fd = new FormData();
    fd.append('id', id);
    fd.append('action', 'delete');
    fetch('ajax/budget_manage.php', {method:'POST', body:fd})
      .then(r=>r.json())
      .then(res=>{ if(res.success) location.reload(); else alert(res.error||'Errore'); });
  });
});
