function openE2oModal(){
  const form = document.getElementById('editE2oForm');
  form.descrizione_extra.value = e2oData.descrizione_extra || '';
  form.importo.value = e2oData.importo !== null ? e2oData.importo : '';
  new bootstrap.Modal(document.getElementById('editE2oModal')).show();
}

document.getElementById('editE2oForm')?.addEventListener('submit', function(e){
  e.preventDefault();
  const formData = new FormData(this);
  formData.append('id_e2o', e2oData.id);
  fetch('ajax/update_e2o.php', {method:'POST', body:formData})
    .then(r=>r.json())
    .then(res=>{ if(res.success) location.reload(); });
});

function openU2oModal(li){
  const form = document.getElementById('editU2oForm');
  if(li){
    form.id_u2o.value = li.dataset.u2oId;
    form.id_utente.value = li.dataset.utenteId;
    form.quote.value = li.dataset.quote || '';
    form.saldata.checked = li.dataset.saldata === '1';
    form.data_saldo.value = li.dataset.dataSaldo ? li.dataset.dataSaldo.substring(0,10) : '';
  } else {
    form.id_u2o.value = 0;
    form.id_utente.value = '';
    form.quote.value = '';
    form.saldata.checked = false;
    form.data_saldo.value = '';
  }
  new bootstrap.Modal(document.getElementById('editU2oModal')).show();
}

document.getElementById('editU2oForm')?.addEventListener('submit', function(e){
  e.preventDefault();
  const fd = new FormData(this);
  fd.append('id_e2o', e2oData.id);
  fetch('ajax/update_u2o.php', {method:'POST', body:fd})
    .then(r=>r.json())
    .then(res=>{ if(res.success) location.reload(); });
});

function deleteU2o(id){
  if(!confirm('Eliminare questa riga?')) return;
  fetch('ajax/delete_u2o.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'id_u2o='+id})
    .then(r=>r.json())
    .then(res=>{ if(res.success) location.reload(); });
}

function deleteE2o(){
  if(!confirm('Eliminare questo movimento?')) return;
  fetch('ajax/delete_e2o.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'id_e2o='+e2oData.id})
    .then(r=>r.json())
    .then(res=>{ if(res.success) window.location.href = 'etichetta.php?id_etichetta='+e2oData.id_etichetta; });
}
