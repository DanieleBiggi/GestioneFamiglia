document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.checklist-checkbox').forEach(chk => {
    chk.addEventListener('change', e => {
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
    sel.addEventListener('change', e => {
      const id = sel.dataset.id;
      const fd = new FormData();
      fd.append('id', id);
      fd.append('id_utente', sel.value);
      fetch('ajax/update_viaggi_checklist.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(res => { if(!res.success){ alert(res.error || 'Errore'); } });
    });
  });

  document.querySelectorAll('.collapse').forEach(col => {
    col.addEventListener('shown.bs.collapse', () => {
      const msgBox = col.querySelector('.checklist-chat-messages');
      const id = msgBox.dataset.id;
      loadMessages(id);
    });
  });

  document.querySelectorAll('.checklist-chat-send').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      const input = document.querySelector('.checklist-chat-input[data-id="'+id+'"]');
      const text = input.value.trim();
      if(!text) return;
      const fd = new FormData();
      fd.append('id_checklist', id);
      fd.append('messaggio', text);
      fetch('ajax/add_viaggi_checklist_message.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(res => {
          if(res.success){
            input.value='';
            loadMessages(id);
          } else {
            alert(res.error || 'Errore');
          }
        });
    });
  });

  function loadMessages(id){
    fetch('ajax/get_viaggi_checklist_messages.php?id_checklist='+id)
      .then(r => r.json())
      .then(res => {
        if(res.success){
          const box = document.querySelector('.checklist-chat-messages[data-id="'+id+'"]');
          if(!box) return;
          box.innerHTML = '';
          res.messages.forEach(m => {
            const div = document.createElement('div');
            div.className = 'small';
            div.textContent = m.username + ': ' + m.messaggio;
            box.appendChild(div);
          });
        }
      });
  }
});
