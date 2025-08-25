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

function openEventoModal(data){
  const form = document.getElementById('eventoForm');
  if(!form) return;
  form.reset();
  form.id_evento.value = data && data.id ? data.id : '';
  form.id_tipo_evento.value = data && data.id_tipo_evento ? data.id_tipo_evento : form.id_tipo_evento.value;
  form.data_evento.value = data && data.data_evento ? data.data_evento : '';
  form.km_evento.value = data && data.km_evento ? data.km_evento : '';
  form.note.value = data && data.note ? data.note : '';
  new bootstrap.Modal(document.getElementById('eventoModal')).show();
}

document.getElementById('eventoForm')?.addEventListener('submit', function(e){
  e.preventDefault();
  const fd = new FormData(this);
  fd.append('id_mezzo', mezzoData.id);
  fetch('ajax/update_mezzo_evento.php', {method:'POST', body:fd})
    .then(r=>r.json())
    .then(res=>{ if(res.success) location.reload(); });
});

function editChilometro(el){
  openChilometroModal({id:el.dataset.id, data:el.dataset.data, km:el.dataset.km});
}

document.querySelectorAll('#chilometriList li').forEach(li=>{
  li.addEventListener('click', ()=>editChilometro(li));
});

function editEvento(el){
  openEventoModal({
    id: el.dataset.id,
    id_tipo_evento: el.dataset.tipo,
    data_evento: el.dataset.data,
    km_evento: el.dataset.km,
    note: el.dataset.note
  });
}

document.querySelectorAll('#eventiList .mezzo-card').forEach(div=>{
  div.addEventListener('click', ()=>editEvento(div));
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
