// JavaScript for eventi_dettaglio.php

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.select-search').forEach(wrapper => {
    const input = wrapper.querySelector('input');
    const select = wrapper.querySelector('select');
    if (!input || !select) return;
    input.addEventListener('input', () => {
      const val = input.value.toLowerCase();
      Array.from(select.options).forEach(opt => {
        opt.hidden = !opt.text.toLowerCase().includes(val);
      });
    });
  });
  document.getElementById('toggleLuoghi')?.addEventListener('click', function(){
    document.querySelectorAll('#luoghiList .extra-row').forEach(el=>el.classList.toggle('d-none'));
    this.textContent = this.textContent === 'Mostra tutti' ? 'Mostra meno' : 'Mostra tutti';
  });
  document.getElementById('toggleInvitati')?.addEventListener('click', function(){
    document.querySelectorAll('#invitatiList .extra-row').forEach(el=>el.classList.toggle('d-none'));
    this.textContent = this.textContent === 'Mostra tutti' ? 'Mostra meno' : 'Mostra tutti';
  });
  document.getElementById('toggleCibo')?.addEventListener('click', function(){
    document.querySelectorAll('#ciboList .extra-row').forEach(el=>el.classList.toggle('d-none'));
    this.textContent = this.textContent === 'Mostra tutti' ? 'Mostra meno' : 'Mostra tutti';
  });
  document.getElementById('toggleSalv')?.addEventListener('click', function(){
    document.querySelectorAll('#salvList .extra-row').forEach(el=>el.classList.toggle('d-none'));
    this.textContent = this.textContent === 'Mostra tutti' ? 'Mostra meno' : 'Mostra tutti';
  });
  document.getElementById('toggleEt')?.addEventListener('click', function(){
    document.querySelectorAll('#etList .extra-row').forEach(el=>el.classList.toggle('d-none'));
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

  document.getElementById('deleteInvitatoBtn')?.addEventListener('click', function(){
    const id = document.getElementById('invitatoForm')?.id_e2i.value;
    if(!id || !confirm('Eliminare questo invitato dall\'evento?')) return;
    const fd = new FormData();
    fd.append('id_e2i', id);
    fetch('ajax/delete_e2i.php', {method:'POST', body:fd})
      .then(r=>r.json())
      .then(res=>{ if(res.success) location.reload(); else alert(res.error||'Errore'); });
  });

  document.getElementById('addInvitatoBtn')?.addEventListener('click', () => {
    const form = document.getElementById('addInvitatoForm');
    form.reset();
    new bootstrap.Modal(document.getElementById('addInvitatoModal')).show();
  });

  document.getElementById('addInvitatoForm')?.addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this);
    fetch('ajax/add_e2i.php', {method:'POST', body:fd})
      .then(r=>r.json())
      .then(res=>{ if(res.success) location.reload(); else alert(res.error||'Errore'); });
  });

  document.getElementById('addSeBtn')?.addEventListener('click', () => {
    const form = document.getElementById('addSeForm');
    form.reset();
    new bootstrap.Modal(document.getElementById('addSeModal')).show();
  });

  document.getElementById('toggleHiddenFinBtn')?.addEventListener('click', function(){
    document.querySelectorAll('.fin-hidden').forEach(el=>el.classList.toggle('d-none'));
    this.textContent = this.textContent === 'Mostra nascoste' ? 'Nascondi nascoste' : 'Mostra nascoste';
  });

  document.getElementById('addSeForm')?.addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this);
    fetch('ajax/add_e2se.php', {method:'POST', body:fd})
      .then(r=>r.json())
      .then(res=>{ if(res.success) location.reload(); else alert(res.error||'Errore'); });
  });

  document.getElementById('addLuogoBtn')?.addEventListener('click', () => {
    const form = document.getElementById('addLuogoForm');
    form.reset();
    new bootstrap.Modal(document.getElementById('addLuogoModal')).show();
  });

  document.getElementById('addLuogoForm')?.addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this);
    fetch('ajax/add_e2l.php', {method:'POST', body:fd})
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

  document.getElementById('ciboToListaBtn')?.addEventListener('click', function(){
    if(!confirm('Sostituire la lista della spesa con il cibo di questo evento?')) return;
    const fd = new FormData();
    fd.append('id_evento', this.dataset.id);
    fetch('ajax/cibo_to_lista_spesa.php', {method:'POST', body:fd})
      .then(r=>r.json())
      .then(res=>{ if(res.success) window.location.href = 'lista_spesa.php'; else alert(res.error||'Errore'); });
  });

  // apertura modal modifica salvadanaio/etichetta
  document.querySelectorAll('#salvList .se-row, #etList .se-row').forEach(li => {
    li.addEventListener('click', () => {
      const form = document.getElementById('seForm');
      form.id_e2se.value = li.dataset.id;
      form.id_salvadanaio.value = li.dataset.idSalvadanaio;
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

  // apertura modal modifica cibo
  document.querySelectorAll('#ciboList .cibo-row').forEach(li => {
    li.addEventListener('click', () => {
      const form = document.getElementById('ciboForm');
      form.id_e2c.value = li.dataset.id;
      form.cibo.value = li.dataset.piatto;
      form.quantita.value = li.dataset.quantita || '';
      new bootstrap.Modal(document.getElementById('ciboModal')).show();
    });
  });

  document.getElementById('ciboForm')?.addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this);
    fetch('ajax/update_e2c.php', {method:'POST', body:fd})
      .then(r=>r.json())
      .then(res=>{ if(res.success) location.reload(); else alert(res.error||'Errore'); });
  });

  document.getElementById('deleteCiboBtn')?.addEventListener('click', function(){
    const id = document.getElementById('ciboForm')?.id_e2c.value;
    if(!id || !confirm('Eliminare questo cibo dall\'evento?')) return;
    const fd = new FormData();
    fd.append('id_e2c', id);
    fetch('ajax/delete_e2c.php', {method:'POST', body:fd})
      .then(r=>r.json())
      .then(res=>{ if(res.success) location.reload(); else alert(res.error||'Errore'); });
  });

  // apertura modal modifica luogo
  document.querySelectorAll('#luoghiList .luogo-row').forEach(li => {
    li.addEventListener('click', () => {
      const form = document.getElementById('luogoForm');
      form.id_e2l.value = li.dataset.id;
      form.luogo.value = li.dataset.luogo;
      new bootstrap.Modal(document.getElementById('luogoModal')).show();
    });
  });

  document.getElementById('luogoForm')?.addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this);
    fetch('ajax/update_e2l.php', {method:'POST', body:fd})
      .then(r=>r.json())
      .then(res=>{ if(res.success) location.reload(); else alert(res.error||'Errore'); });
  });

  document.getElementById('deleteLuogoBtn')?.addEventListener('click', function(){
    const id = document.getElementById('luogoForm')?.id_e2l.value;
    if(!id || !confirm('Eliminare questo luogo dall\'evento?')) return;
    const fd = new FormData();
    fd.append('id_e2l', id);
    fetch('ajax/delete_e2l.php', {method:'POST', body:fd})
      .then(r=>r.json())
      .then(res=>{ if(res.success) location.reload(); else alert(res.error||'Errore'); });
  });

  document.getElementById('editEventoBtn')?.addEventListener('click', () => {
    new bootstrap.Modal(document.getElementById('eventoModal')).show();
  });

    document.getElementById('eventoForm')?.addEventListener('submit', function(e){
      e.preventDefault();
      const fd = new FormData(this);
      fetch('ajax/update_evento.php', {method:'POST', body:fd})
        .then(r=>r.json())
        .then(res=>{ if(res.success) location.reload(); else alert(res.error||'Errore'); });
    });

    const deleteEventoBtn = document.getElementById('deleteEventoBtn');
    const deleteEventoModalEl = document.getElementById('deleteEventoModal');
    const confirmDeleteEventoBtn = document.getElementById('confirmDeleteEventoBtn');
    let deleteEventoModal;
    deleteEventoBtn?.addEventListener('click', () => {
      if(!deleteEventoModal) deleteEventoModal = new bootstrap.Modal(deleteEventoModalEl);
      deleteEventoModal.show();
    });
    confirmDeleteEventoBtn?.addEventListener('click', () => {
      const fd = new FormData();
      fd.append('id', deleteEventoBtn.dataset.id);
      fetch('ajax/delete_evento.php', {method:'POST', body:fd})
        .then(r=>r.json())
        .then(res=>{ if(res.success) window.location.href = 'eventi.php'; else alert(res.error||'Errore'); });
    });

  document.getElementById('addRuleBtn')?.addEventListener('click', function(){
      if(!confirm('Salvare regola per questo evento?')) return;
      const fd = new FormData();
      fd.append('id_evento', this.dataset.id);
    fetch('ajax/add_evento_google_rule.php', {method:'POST', body:fd})
      .then(r=>r.json())
      .then(res=>{
        if(res.success){
          alert('Regola salvata');
          this.classList.replace('bi-star','bi-star-fill');
        } else {
          alert(res.error||'Errore');
        }
      });
  });

  document.querySelectorAll('.toggle-finanze').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      const escludi = btn.dataset.escludi === '1' ? 0 : 1;
      const fd = new FormData();
      fd.append('id_e2o', id);
      fd.append('escludi', escludi);
      fetch('ajax/toggle_e2o_finanze.php', {method:'POST', body:fd})
        .then(r=>r.json())
        .then(res=>{ if(res.success) location.reload(); else alert(res.error||'Errore'); });
    });
  });
});
