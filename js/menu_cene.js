document.addEventListener('DOMContentLoaded', () => {
  const grid = document.getElementById('menuGrid');
  const editForm = document.getElementById('editMenuForm');
  const importForm = document.getElementById('importMenuForm');

  function render(items) {
    if (!grid) return;
    grid.innerHTML = '';
    items.forEach(item => {
      const card = document.createElement('div');
      card.className = 'menu-card d-flex flex-column';
      card.dataset.id = item.id;
      card.dataset.giorno = item.giorno;
      card.dataset.piatto = item.piatto || '';

      const header = document.createElement('div');
      header.className = 'd-flex justify-content-between align-items-start mb-2';
      const title = document.createElement('div');
      title.className = 'fw-semibold text-uppercase small';
      title.textContent = item.giorno;
      header.appendChild(title);

      if (MENU_CENE_CAN_EDIT) {
        const editBtn = document.createElement('button');
        editBtn.type = 'button';
        editBtn.className = 'btn btn-sm btn-outline-light p-1 edit-day-btn';
        editBtn.dataset.id = item.id;
        editBtn.dataset.giorno = item.giorno;
        editBtn.dataset.piatto = item.piatto || '';
        editBtn.innerHTML = '<i class="bi bi-pencil"></i>';
        header.appendChild(editBtn);
      }

      const body = document.createElement('div');
      body.className = 'flex-grow-1 text-break small';
      body.innerHTML = item.piatto ? item.piatto.replace(/\n/g, '<br>') : '<span class="text-muted">Nessun piatto</span>';

      card.appendChild(header);
      card.appendChild(body);
      grid.appendChild(card);
    });
  }

  function refreshMenu() {
    fetch('ajax/get_menu_cene.php')
      .then(r => r.json())
      .then(res => { if (res.success) { render(res.items); } });
  }

  grid?.addEventListener('click', e => {
    const btn = e.target.closest('.edit-day-btn');
    if (btn) {
      openEditMenuModal({
        id: btn.dataset.id,
        giorno: btn.dataset.giorno,
        piatto: btn.dataset.piatto
      });
    }
  });

  editForm?.addEventListener('submit', e => {
    e.preventDefault();
    const fd = new FormData(editForm);
    fetch('ajax/update_menu_cena.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          bootstrap.Modal.getInstance(document.getElementById('editMenuModal'))?.hide();
          refreshMenu();
        } else {
          alert(res.error || 'Errore durante il salvataggio');
        }
      });
  });

  importForm?.addEventListener('submit', e => {
    e.preventDefault();
    const fd = new FormData(importForm);
    fetch('ajax/import_menu_cene.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          bootstrap.Modal.getInstance(document.getElementById('importMenuModal'))?.hide();
          importForm.reset();
          refreshMenu();
        } else {
          alert(res.error || 'Errore durante l\'import');
        }
      });
  });

  refreshMenu();
});

function openEditMenuModal(item) {
  const form = document.getElementById('editMenuForm');
  const modalEl = document.getElementById('editMenuModal');
  if (form && modalEl) {
    form.reset();
    form.querySelector('[name="id"]').value = item?.id || '';
    form.querySelector('[name="giorno"]').value = item?.giorno || '';
    form.querySelector('[name="piatto"]').value = item?.piatto || '';
    new bootstrap.Modal(modalEl).show();
  }
}

function openImportMenuModal() {
  const form = document.getElementById('importMenuForm');
  const modalEl = document.getElementById('importMenuModal');
  if (form && modalEl) {
    form.reset();
    new bootstrap.Modal(modalEl).show();
  }
}
