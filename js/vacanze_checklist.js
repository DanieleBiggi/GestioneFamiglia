document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.checklist-checkbox').forEach(chk => {
    chk.addEventListener('change', () => {
      const id = chk.dataset.id;
      const fd = new FormData();
      fd.append('id', id);
      fd.append('completata', chk.checked ? 1 : 0);
      fetch('ajax/update_viaggi_checklist.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(res => { if(!res.success){ alert(res.error || 'Errore'); } });
    });
  });

  document.querySelectorAll('.checklist-user').forEach(sel => {
    sel.addEventListener('change', () => {
      const id = sel.dataset.id;
      const fd = new FormData();
      fd.append('id', id);
      fd.append('id_utente', sel.value);
      fetch('ajax/update_viaggi_checklist.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(res => { if(!res.success){ alert(res.error || 'Errore'); } });
    });
  });

  const addBtn = document.getElementById('addChecklistBtn');
  const addModalEl = document.getElementById('addChecklistModal');
  const addForm = document.getElementById('addChecklistForm');
  const addModal = addModalEl ? new bootstrap.Modal(addModalEl) : null;
  if(addBtn && addModal){
    addBtn.addEventListener('click', () => addModal.show());
  }
  if(addForm){
    addForm.addEventListener('submit', e => {
      e.preventDefault();
      const fd = new FormData(addForm);
      fd.append('id_viaggio', viaggioId);
      fetch('ajax/add_viaggi_checklist.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(res => {
          if(res.success){
            window.location.reload();
          } else {
            alert(res.error || 'Errore');
          }
        });
    });
  }

  let currentChecklist = null;
  const chatModalEl = document.getElementById('chatModal');
  const chatModal = chatModalEl ? new bootstrap.Modal(chatModalEl) : null;
  const chatMessages = document.getElementById('chatMessages');
  const chatInput = document.getElementById('chatInput');
  const chatSend = document.getElementById('chatSend');

  document.querySelectorAll('.checklist-chat-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      currentChecklist = btn.dataset.id;
      chatInput.value = '';
      loadMessages(currentChecklist);
      chatModal.show();
    });
  });

  if(chatSend){
    chatSend.addEventListener('click', () => {
      const text = chatInput.value.trim();
      if(!text || !currentChecklist) return;
      const fd = new FormData();
      fd.append('id_checklist', currentChecklist);
      fd.append('messaggio', text);
      fetch('ajax/add_viaggi_checklist_message.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(res => {
          if(res.success){
            chatInput.value='';
            loadMessages(currentChecklist);
          } else {
            alert(res.error || 'Errore');
          }
        });
    });
  }

  function loadMessages(id){
    fetch('ajax/get_viaggi_checklist_messages.php?id_checklist='+id)
      .then(r => r.json())
      .then(res => {
        if(res.success && chatMessages){
          chatMessages.innerHTML = '';
          res.messages.forEach(m => {
            const div = document.createElement('div');
            div.className = 'small';
            div.textContent = m.username + ': ' + m.messaggio;
            chatMessages.appendChild(div);
          });
        }
      });
  }
});
