function openTagliandoModal() {
  const form = document.getElementById('tagliandoForm');
  form.nome_tagliando.value = tagliandoData.nome_tagliando || '';
  form.data_scadenza.value = tagliandoData.data_scadenza || '';
  form.attivo.checked = tagliandoData.attivo == 1;
  new bootstrap.Modal(document.getElementById('tagliandoModal')).show();
}

document.getElementById('tagliandoForm')?.addEventListener('submit', function(e){
  e.preventDefault();
  const fd = new FormData(this);
  fd.append('id_tagliando', tagliandoData.id);
  fd.append('id_mezzo', tagliandoData.id_mezzo);
  fetch('ajax/update_tagliando.php', {method:'POST', body:fd})
    .then(r=>r.json())
    .then(res=>{ if(res.success) location.reload(); });
});

function openRecordModal(data){
  const form = document.getElementById('recordForm');
  form.id_m2t.value = data && data.id ? data.id : '';
  form.data_tagliando.value = data && data.data ? data.data : '';
  form.km_tagliando.value = data && data.km ? data.km : '';
  new bootstrap.Modal(document.getElementById('recordModal')).show();
}

document.getElementById('recordForm')?.addEventListener('submit', function(e){
  e.preventDefault();
  const fd = new FormData(this);
  fd.append('id_tagliando', tagliandoData.id);
  // map fields to backend expected names
  fd.append('id_record', fd.get('id_m2t') || '');
  fd.append('chilometri', fd.get('km_tagliando') || '');
  fetch('ajax/update_mezzo2tagliando.php', {method:'POST', body:fd})
    .then(r=>r.json())
    .then(res=>{ if(res.success) location.reload(); });
});

document.querySelectorAll('#recordsList li').forEach(li=>{
  li.addEventListener('click', ()=>openRecordModal({id:li.dataset.id, data:li.dataset.data, km:li.dataset.km}));
});

function deleteRecord(ev, id){
  ev.stopPropagation();
  if(!confirm('Eliminare questo record?')) return;
  fetch('ajax/delete_mezzo2tagliando.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'id_record='+encodeURIComponent(id)})
    .then(r=>r.json())
    .then(res=>{ if(res.success) location.reload(); });
}

document.getElementById('toggleRecords')?.addEventListener('click', function(){
  document.querySelectorAll('.extra-row').forEach(el=>el.classList.toggle('d-none'));
  this.textContent = this.textContent === 'Mostra tutti' ? 'Mostra meno' : 'Mostra tutti';
});

