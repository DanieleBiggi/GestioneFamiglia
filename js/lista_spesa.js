document.addEventListener('DOMContentLoaded', () => {
  const list = document.getElementById('listaSpesaList');
  const form = document.getElementById('listaForm');

  function render(items) {
    list.innerHTML = '';
    items.forEach(item => {
      const div = document.createElement('div');
      div.className = 'd-flex align-items-center py-2 border-bottom';
      div.dataset.id = item.id;

      const edit = document.createElement('button');
      edit.type = 'button';
      edit.className = 'btn btn-sm btn-outline-light me-2 edit-btn';
      edit.dataset.id = item.id;
      edit.dataset.nome = item.nome;
      edit.dataset.quantita = item.quantita || '';
      edit.dataset.note = item.note || '';
      edit.innerHTML = '<i class="bi bi-pencil"></i>';

      const del = document.createElement('button');
      del.type = 'button';
      del.className = 'btn btn-sm btn-outline-light me-2 delete-btn';
      del.dataset.id = item.id;
      del.innerHTML = '<i class="bi bi-trash"></i>';

      const info = document.createElement('div');
      info.className = 'flex-grow-1';
      if (item.checked == 1) { info.classList.add('text-decoration-line-through'); }
      let html = item.nome;
      if (item.quantita) {
        html += ' <span class="badge bg-secondary ms-2">' + item.quantita + '</span>';
      }
      if (item.note) {
        html += '<br><small class="text-muted">' + item.note + '</small>';
      }
      info.innerHTML = html;

      const chk = document.createElement('input');
      chk.type = 'checkbox';
      chk.className = 'form-check-input ms-2';
      chk.dataset.id = item.id;
      chk.checked = item.checked == 1;

      div.appendChild(edit);
      div.appendChild(del);
      div.appendChild(info);
      div.appendChild(chk);
      list.appendChild(div);
    });
  }

  function refresh() {
    fetch('ajax/get_lista_spesa.php')
      .then(r => r.json())
      .then(res => { if (res.success) { render(res.items); } });
  }

  form?.addEventListener('submit', e => {
    e.preventDefault();
    const fd = new FormData(form);
    const id = fd.get('id');
    const url = id ? 'ajax/update_lista_spesa.php' : 'ajax/add_lista_spesa.php';
    fetch(url, { method: 'POST', body: fd })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          form.reset();
          const modalEl = document.getElementById('listaModal');
          if (modalEl) {
            bootstrap.Modal.getInstance(modalEl)?.hide();
          }
          refresh();
        } else {
          alert(res.error || 'Errore');
        }
      });
  });

  list.addEventListener('change', e => {
    if (e.target.matches('input[type="checkbox"][data-id]')) {
      const id = e.target.dataset.id;
      const checked = e.target.checked ? 1 : 0;
      const fd = new FormData();
      fd.append('id', id);
      fd.append('checked', checked);
      fetch('ajax/update_lista_spesa.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => { if (!res.success) { alert(res.error || 'Errore'); } else { refresh(); } });
    }
  });

  list.addEventListener('click', e => {
    const editBtn = e.target.closest('.edit-btn');
    if (editBtn) {
      openListaModal({
        id: editBtn.dataset.id,
        nome: editBtn.dataset.nome,
        quantita: editBtn.dataset.quantita,
        note: editBtn.dataset.note
      });
      return;
    }
    const delBtn = e.target.closest('.delete-btn');
    if (delBtn && confirm('Eliminare questo elemento?')) {
      const fd = new FormData();
      fd.append('id', delBtn.dataset.id);
      fetch('ajax/delete_lista_spesa.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => { if (res.success) { refresh(); } else { alert(res.error || 'Errore'); } });
    }
  });

  document.getElementById('clearListaBtn')?.addEventListener('click', () => {
    if (confirm('Svuotare tutta la lista?')) {
      fetch('ajax/clear_lista_spesa.php', { method: 'POST' })
        .then(r => r.json())
        .then(res => { if (res.success) { refresh(); } else { alert(res.error || 'Errore'); } });
    }
  });

  const importForm = document.getElementById('importForm');
  importForm?.addEventListener('submit', e => {
    e.preventDefault();
    const fd = new FormData(importForm);
    fetch('ajax/import_lista_spesa.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          importForm.reset();
          const modalEl = document.getElementById('importModal');
          if (modalEl) {
            bootstrap.Modal.getInstance(modalEl)?.hide();
          }
          refresh();
        } else {
          alert(res.error || 'Errore');
        }
      });
  });

  refresh();
  setInterval(refresh, 5000);
});

function openListaModal(item){
  const form = document.getElementById('listaForm');
  const modalEl = document.getElementById('listaModal');
  if(form && modalEl){
    form.reset();
    if(item){
      form.querySelector('[name="id"]').value = item.id || '';
      form.querySelector('[name="nome"]').value = item.nome || '';
      form.querySelector('[name="quantita"]').value = item.quantita || '';
      form.querySelector('[name="note"]').value = item.note || '';
      modalEl.querySelector('.modal-title').textContent = 'Modifica elemento';
    } else {
      modalEl.querySelector('.modal-title').textContent = 'Nuovo elemento';
    }
    new bootstrap.Modal(modalEl).show();
  }
}

function openImportModal(){
  const form = document.getElementById('importForm');
  const modalEl = document.getElementById('importModal');
  if(form && modalEl){
    form.reset();
    new bootstrap.Modal(modalEl).show();
  }
}
