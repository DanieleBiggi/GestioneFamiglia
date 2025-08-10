document.addEventListener('DOMContentLoaded', () => {
  const list = document.getElementById('listaSpesaList');
  const form = document.getElementById('listaForm');

  function render(items) {
    list.innerHTML = '';
    items.forEach(item => {
      const div = document.createElement('div');
      div.className = 'd-flex justify-content-between align-items-center py-2 border-bottom';
      div.dataset.id = item.id;
      const span = document.createElement('span');
      span.className = 'flex-grow-1';
      if (item.checked == 1) { span.classList.add('text-decoration-line-through'); }
      span.textContent = item.nome;
      const chk = document.createElement('input');
      chk.type = 'checkbox';
      chk.className = 'form-check-input ms-2';
      chk.dataset.id = item.id;
      chk.checked = item.checked == 1;
      div.appendChild(span);
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
    fetch('ajax/add_lista_spesa.php', { method: 'POST', body: fd })
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

  refresh();
  setInterval(refresh, 5000);
});

function openListaModal(){
  const form = document.getElementById('listaForm');
  if(form){
    form.reset();
    new bootstrap.Modal(document.getElementById('listaModal')).show();
  }
}
