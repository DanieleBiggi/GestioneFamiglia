function openMezzoModal() {
  const form = document.getElementById('mezzoForm');
  form.nome_mezzo.value = mezzoData.nome_mezzo || '';
  form.data_immatricolazione.value = mezzoData.data_immatricolazione || '';
  form.attivo.checked = mezzoData.attivo == 1;
  new bootstrap.Modal(document.getElementById('editMezzoModal')).show();
}

document.getElementById('mezzoForm')?.addEventListener('submit', function(e){
  e.preventDefault();
  const formData = new FormData(this);
  formData.append('id_mezzo', mezzoData.id);
  fetch('ajax/update_mezzo.php', {method:'POST', body:formData})
    .then(r=>r.json())
    .then(res=>{ if(res.success) location.reload(); });
});

function openChilometroModal(data){
  const form = document.getElementById('chilometroForm');
  form.id_chilometro.value = data && data.id ? data.id : '';
  form.data_chilometro.value = data && data.data ? data.data : '';
  form.chilometri.value = data && data.km ? data.km : '';
  new bootstrap.Modal(document.getElementById('chilometroModal')).show();
}

document.getElementById('chilometroForm')?.addEventListener('submit', function(e){
  e.preventDefault();
  const formData = new FormData(this);
  formData.append('id_mezzo', mezzoData.id);
  fetch('ajax/update_chilometro.php', {method:'POST', body:formData})
    .then(r=>r.json())
    .then(res=>{ if(res.success) location.reload(); });
});

function openEventoModal(){
  const form = document.getElementById('eventoForm');
  form?.reset();
  new bootstrap.Modal(document.getElementById('eventoModal')).show();
}

document.getElementById('eventoForm')?.addEventListener('submit', function(e){
  e.preventDefault();
  const fd = new FormData(this);
  fd.append('id_mezzo', mezzoData.id);
  fetch('ajax/add_mezzo_evento.php', {method:'POST', body:fd})
    .then(r=>r.json())
    .then(res=>{ if(res.success) location.reload(); });
});

function editChilometro(el){
  openChilometroModal({id:el.dataset.id, data:el.dataset.data, km:el.dataset.km});
}

document.querySelectorAll('#chilometriList li').forEach(li=>{
  li.addEventListener('click', ()=>editChilometro(li));
});

function deleteChilometro(ev, id){
  ev.stopPropagation();
  if(!confirm('Eliminare questo record?')) return;
  fetch('ajax/delete_chilometro.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'id_chilometro='+encodeURIComponent(id)})
    .then(r=>r.json())
    .then(res=>{ if(res.success) location.reload(); });
}

document.getElementById('toggleChilometri')?.addEventListener('click', function(){
  document.querySelectorAll('.extra-row').forEach(el=>el.classList.toggle('d-none'));
  this.textContent = this.textContent === 'Mostra tutti' ? 'Mostra meno' : 'Mostra tutti';
});

function deleteEvento(ev, id){
  ev.stopPropagation();
  if(!confirm('Eliminare questo evento?')) return;
  fetch('ajax/delete_mezzo_evento.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'id_evento='+encodeURIComponent(id)})
    .then(r=>r.json())
    .then(res=>{ if(res.success) location.reload(); });
}
