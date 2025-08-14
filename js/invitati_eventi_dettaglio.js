document.addEventListener('DOMContentLoaded', () => {
  const invitatoForm = document.getElementById('invitatoEditForm');
  invitatoForm?.addEventListener('submit', e => {
    e.preventDefault();
    const formData = new FormData(invitatoForm);
    formData.append('action','update_invitato');
    fetch('ajax/invitati_eventi_dettaglio.php',{method:'POST',body:formData})
      .then(r=>r.json()).then(res=>{ if(res.success){ location.reload(); } else { alert(res.error||'Errore'); }});
  });
  const famigliaForm = document.getElementById('famigliaForm');
  famigliaForm?.addEventListener('submit', e => {
    e.preventDefault();
    const formData = new FormData(famigliaForm);
    const id = formData.get('id');
    formData.append('action', id ? 'update_famiglia' : 'add_famiglia');
    fetch('ajax/invitati_eventi_dettaglio.php',{method:'POST',body:formData})
      .then(r=>r.json()).then(res=>{ if(res.success){ location.reload(); } else { alert(res.error||'Errore'); }});
  });
  document.getElementById('deleteFam')?.addEventListener('click', () => {
    const id = document.getElementById('id_i2f').value;
    if(!id) return;
    const fd = new FormData();
    fd.append('action','delete_famiglia');
    fd.append('id',id);
    fetch('ajax/invitati_eventi_dettaglio.php',{method:'POST',body:fd})
      .then(r=>r.json()).then(res=>{ if(res.success){ location.reload(); } else { alert(res.error||'Errore'); }});
  });
  document.getElementById('deleteInvitato')?.addEventListener('click', e => {
    const id = e.currentTarget.dataset.id;
    if(!id) return;
    if(!confirm('Sei sicuro di voler eliminare questo invitato?')) return;
    const fd = new FormData();
    fd.append('action','delete_invitato');
    fd.append('id', id);
    fetch('ajax/invitati_eventi_dettaglio.php',{method:'POST',body:fd})
      .then(r=>r.json()).then(res=>{ if(res.success){ history.back(); } else { alert(res.error||'Errore'); }});
  });
});
function openInvitatoEditModal(){
  new bootstrap.Modal(document.getElementById('invitatoEditModal')).show();
}
function openFamigliaModal(){
  const form = document.getElementById('famigliaForm');
  if(form){
    form.reset();
    document.getElementById('id_i2f').value='';
    document.getElementById('deleteFam').classList.add('d-none');
    new bootstrap.Modal(document.getElementById('famigliaModal')).show();
  }
}
function openFamigliaEdit(el){
  const form = document.getElementById('famigliaForm');
  if(!form) return;
  form.reset();
  form.id_famiglia.value = el.dataset.famiglia;
  form.data_inizio.value = el.dataset.inizio;
  form.data_fine.value = el.dataset.fine;
  form.attivo.checked = el.dataset.attivo === '1';
  document.getElementById('id_i2f').value = el.dataset.id;
  document.getElementById('deleteFam').classList.remove('d-none');
  new bootstrap.Modal(document.getElementById('famigliaModal')).show();
}
