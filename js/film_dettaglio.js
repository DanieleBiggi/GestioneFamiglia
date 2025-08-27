document.addEventListener('DOMContentLoaded', () => {
  const list = document.getElementById('commentiList');
  const form = document.getElementById('commentoForm');
  const modalEl = document.getElementById('commentoModal');
  const deleteBtn = document.getElementById('deleteCommentoBtn');
  const addBtn = document.getElementById('addCommentoBtn');
  const updateBtn = document.getElementById('updateFromApiBtn');

  addBtn?.addEventListener('click', () => {
    form.reset();
    form.id.value = '';
    deleteBtn.classList.add('d-none');
    modalEl.querySelector('.modal-title').textContent = 'Nuovo commento';
    new bootstrap.Modal(modalEl).show();
  });

  list?.addEventListener('click', e => {
    const row = e.target.closest('.commento-row');
    if (row && row.dataset.utente == UTENTE_ID) {
      form.commento.value = row.dataset.commento;
      form.id.value = row.dataset.id;
      deleteBtn.classList.remove('d-none');
      modalEl.querySelector('.modal-title').textContent = 'Modifica commento';
      new bootstrap.Modal(modalEl).show();
    }
  });

  form?.addEventListener('submit', e => {
    e.preventDefault();
    const fd = new FormData(form);
    fd.append('id_film', FILM_ID);
    const url = fd.get('id') ? 'ajax/update_film_commento.php' : 'ajax/add_film_commento.php';
    fetch(url, {method:'POST', body:fd})
      .then(r=>r.json())
      .then(res=>{ if(res.success) location.reload(); else alert(res.error||'Errore'); });
  });

  deleteBtn?.addEventListener('click', () => {
    const id = form.id.value;
    if(!id || !confirm('Eliminare questo commento?')) return;
    const fd = new FormData();
    fd.append('id', id);
    fetch('ajax/delete_film_commento.php', {method:'POST', body:fd})
      .then(r=>r.json())
      .then(res=>{ if(res.success) location.reload(); else alert(res.error||'Errore'); });
  });

  updateBtn?.addEventListener('click', () => {
    if(!confirm('Aggiornare i dati dal servizio TMDB?')) return;
    fetch('ajax/film_update.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ id_film: FILM_ID })
    })
      .then(r => r.json())
      .then(res => {
        if(res.success) location.reload();
        else alert(res.error || 'Errore');
      });
  });
});
