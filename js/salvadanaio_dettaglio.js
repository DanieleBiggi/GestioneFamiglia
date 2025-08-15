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
});
