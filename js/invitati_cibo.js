document.addEventListener('DOMContentLoaded', () => {
  const search = document.getElementById('search');
  const cards = Array.from(document.querySelectorAll('.cibo-card'));
  function filter(){
    const q = search.value.trim().toLowerCase();
    cards.forEach(card=>{
      const text = card.dataset.search || '';
      if(text.includes(q)) card.style.removeProperty('display');
      else card.style.setProperty('display','none','important');
    });
  }
  search.addEventListener('input', filter);
  filter();
  const form = document.getElementById('ciboForm');
  form?.addEventListener('submit', e=>{
    e.preventDefault();
    const fd = new FormData(form);
    const id = fd.get('id');
    fd.append('action', id ? 'update' : 'add');
    fetch('ajax/invitati_cibo.php',{method:'POST',body:fd})
      .then(r=>r.json()).then(res=>{ if(res.success){ location.reload(); } else { alert(res.error||'Errore'); }});
  });
  document.getElementById('deleteCibo')?.addEventListener('click', ()=>{
    const id = document.getElementById('cibo_id').value;
    if(!id) return;
    const fd = new FormData();
    fd.append('action','delete');
    fd.append('id',id);
    fetch('ajax/invitati_cibo.php',{method:'POST',body:fd})
      .then(r=>r.json()).then(res=>{ if(res.success){ location.reload(); } else { alert(res.error||'Errore'); }});
  });
});
function openCiboModal(){
  const form = document.getElementById('ciboForm');
  if(form){ form.reset(); document.getElementById('cibo_id').value=''; document.getElementById('deleteCibo').classList.add('d-none'); new bootstrap.Modal(document.getElementById('ciboModal')).show(); }
}
function openCiboEdit(el){
  const form = document.getElementById('ciboForm');
  if(!form) return;
  form.reset();
  form.piatto.value = el.dataset.piatto;
  form.dolce.checked = el.dataset.dolce === '1';
  form.bere.checked = el.dataset.bere === '1';
  form.um.value = el.dataset.um;
  document.getElementById('cibo_id').value = el.dataset.id;
  document.getElementById('deleteCibo').classList.remove('d-none');
  new bootstrap.Modal(document.getElementById('ciboModal')).show();
}
