// JavaScript for eventi_dettaglio.php

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('toggleInvitati')?.addEventListener('click', function(){
    document.querySelectorAll('#invitatiList .extra-row').forEach(el=>el.classList.toggle('d-none'));
    this.textContent = this.textContent === 'Mostra tutti' ? 'Mostra meno' : 'Mostra tutti';
  });
  document.getElementById('toggleCibo')?.addEventListener('click', function(){
    document.querySelectorAll('#ciboList .extra-row').forEach(el=>el.classList.toggle('d-none'));
    this.textContent = this.textContent === 'Mostra tutti' ? 'Mostra meno' : 'Mostra tutti';
  });

  // apertura modal modifica invitato
  document.querySelectorAll('#invitatiList .inv-row').forEach(li => {
    li.addEventListener('click', () => {
      const form = document.getElementById('invitatoForm');
      form.id_e2i.value = li.dataset.id;
      form.note.value = li.dataset.note || '';
      const stato = li.dataset.stato;
      form.querySelectorAll('input[name="stato"]').forEach(r => r.checked = (r.value === stato));
      new bootstrap.Modal(document.getElementById('invitatoModal')).show();
    });
  });

  document.getElementById('invitatoForm')?.addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this);
    fetch('ajax/update_e2i.php', {method:'POST', body:fd})
      .then(r=>r.json())
      .then(res=>{ if(res.success) location.reload(); else alert(res.error||'Errore'); });
  });

  document.getElementById('addInvitatoBtn')?.addEventListener('click', () => {
    const form = document.getElementById('addInvitatoForm');
    form.reset();
    new bootstrap.Modal(document.getElementById('addInvitatoModal')).show();
  });

  document.getElementById('invSearch')?.addEventListener('input', function(){
    const q = this.value.toLowerCase();
    document.querySelectorAll('#invSelect option').forEach(opt => {
      opt.style.display = opt.text.toLowerCase().includes(q) ? '' : 'none';
    });
  });

  document.getElementById('addInvitatoForm')?.addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this);
    fetch('ajax/add_e2i.php', {method:'POST', body:fd})
      .then(r=>r.json())
      .then(res=>{ if(res.success) location.reload(); else alert(res.error||'Errore'); });
  });

  document.getElementById('addCiboBtn')?.addEventListener('click', () => {
    const form = document.getElementById('addCiboForm');
    form.reset();
    new bootstrap.Modal(document.getElementById('addCiboModal')).show();
  });

  document.getElementById('addCiboForm')?.addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this);
    fetch('ajax/add_e2c.php', {method:'POST', body:fd})
      .then(r=>r.json())
      .then(res=>{ if(res.success) location.reload(); else alert(res.error||'Errore'); });
  });
});
